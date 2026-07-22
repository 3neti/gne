<?php

namespace App\Domain\Repository;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final readonly class ValidateDocumentDefinitions
{
    public function __construct(
        private RepositorySourceLoader $sourceLoader,
        private Validator $validator = new Validator(max_errors: 20, stop_at_first_error: false),
    ) {}

    /** @return list<ValidationFinding> */
    public function handle(string $repositoryRoot, RepositoryManifest $manifest): array
    {
        $findings = [];
        $identifiers = [];
        $repository = new RepositoryAddress($repositoryRoot);
        $languageSchemaPath = dirname(__DIR__, 3).'/resources/gne/schemas/document-definition.schema.json';
        $languageSchema = $this->sourceLoader->jsonObject($languageSchemaPath);

        foreach ($manifest->profiles as $profile) {
            $profileRoot = dirname($repository->absolute($profile['path']));
            foreach ($profile['declarations']['documents'] ?? [] as $declaredPath) {
                $absolutePath = $profileRoot.'/'.$declaredPath;
                $sourcePath = $repository->relative($absolutePath);
                try {
                    $definition = $this->sourceLoader->yamlMapping($absolutePath);
                } catch (RepositorySourceException $exception) {
                    $findings[] = new ValidationFinding(ValidationSeverity::Error, 'DOCUMENT_DEFINITION_INVALID', $exception->getMessage(), $sourcePath, remediation: 'Correct the authored YAML and document grammar.');

                    continue;
                }
                $documentObject = json_decode(json_encode($definition, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
                $result = $this->validator->validate($documentObject, $languageSchema);
                if (! $result->isValid()) {
                    foreach ((new ErrorFormatter)->format($result->error()) as $pointer => $messages) {
                        foreach ((array) $messages as $message) {
                            $findings[] = new ValidationFinding(ValidationSeverity::Error, 'DOCUMENT_DEFINITION_INVALID', $message, $sourcePath, $pointer, 'Conform the definition to resources/gne/schemas/document-definition.schema.json.', ['document_identifier' => $definition['identifier'] ?? null, 'grammar_schema' => 'resources/gne/schemas/document-definition.schema.json']);
                        }
                    }

                    continue;
                }
                $findings = [...$findings, ...$this->contextualFindings($repositoryRoot, $manifest, $profile, $definition, $sourcePath)];
                $identifier = $definition['identifier'] ?? null;
                if (is_string($identifier)) {
                    if (isset($identifiers[$identifier])) {
                        $findings[] = new ValidationFinding(ValidationSeverity::Error, 'DOCUMENT_IDENTIFIER_DUPLICATE', "Document identifier {$identifier} is declared more than once.", $sourcePath, '/identifier');
                    }
                    $identifiers[$identifier] = true;
                }
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $definition
     * @return list<ValidationFinding>
     */
    private function contextualFindings(string $repositoryRoot, RepositoryManifest $manifest, array $profile, array $definition, string $sourcePath): array
    {
        $findings = [];
        $identifier = is_string($definition['identifier'] ?? null) ? $definition['identifier'] : '(unknown document)';
        $artifactTypes = $profile['artifact_types'];
        $declaredArtifacts = is_array($definition['artifacts'] ?? null) ? $definition['artifacts'] : [];
        $primary = $definition['primary_artifact_type'] ?? null;
        if (is_string($primary) && ! in_array($primary, $declaredArtifacts, true)) {
            $findings[] = new ValidationFinding(ValidationSeverity::Error, 'DOCUMENT_PRIMARY_ARTIFACT_INVALID', "{$identifier} primary artifact {$primary} is not in its artifacts declaration.", $sourcePath, '/primary_artifact_type');
        }
        $scenario = collect($manifest->scenarios)->firstWhere('identifier', $definition['scenario'] ?? null);
        if (! is_array($scenario) || $scenario['profile'] !== $profile['identifier']) {
            $findings[] = new ValidationFinding(ValidationSeverity::Error, 'DOCUMENT_SCENARIO_INVALID', "{$identifier} references a scenario outside its declaring profile.", $sourcePath, '/scenario');
        }
        foreach ($declaredArtifacts as $offset => $artifactType) {
            if (! is_string($artifactType) || ! isset($artifactTypes[$artifactType])) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'DOCUMENT_ARTIFACT_TYPE_UNKNOWN', "{$identifier} references undeclared artifact type {$artifactType}.", $sourcePath, "/artifacts/{$offset}", 'Declare the artifact type and schema in profile.yaml.');
            }
        }

        $sections = is_array($definition['sections'] ?? null) ? array_values($definition['sections']) : [];
        $findings = [...$findings, ...$this->duplicates($sections, 'DOCUMENT_SECTION_IDENTIFIER_DUPLICATE', $sourcePath, '/sections')];
        $fields = collect($sections)->flatMap(fn (mixed $section): array => is_array($section) && is_array($section['fields'] ?? null) ? array_values($section['fields']) : [])->values()->all();
        $findings = [...$findings, ...$this->duplicates(array_values($fields), 'DOCUMENT_FIELD_IDENTIFIER_DUPLICATE', $sourcePath, '/sections/*/fields')];
        $actions = is_array($definition['actions'] ?? null) ? array_values($definition['actions']) : [];
        $findings = [...$findings, ...$this->duplicates($actions, 'DOCUMENT_ACTION_IDENTIFIER_DUPLICATE', $sourcePath, '/actions')];

        foreach ($fields as $field) {
            if (! is_array($field) || ! is_array($field['source'] ?? null)) {
                continue;
            }
            $artifactType = $field['source']['artifact'] ?? null;
            $path = $field['source']['path'] ?? null;
            if (! is_string($artifactType) || ! in_array($artifactType, $declaredArtifacts, true)) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'DOCUMENT_FIELD_ARTIFACT_UNDECLARED', "Field {$field['identifier']} uses an artifact not declared by {$identifier}.", $sourcePath, '/sections/*/fields/source/artifact');

                continue;
            }
            try {
                $pathExists = is_string($path) && $this->schemaHasPath($repositoryRoot, $profile, $artifactType, $path);
            } catch (RepositorySourceException $exception) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'ARTIFACT_SCHEMA_INVALID', $exception->getMessage(), $sourcePath, '/sections/*/fields/source/path', 'Correct the declared artifact schema.');

                continue;
            }
            if (! $pathExists) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'DOCUMENT_FIELD_PATH_UNKNOWN', "Field {$field['identifier']} references unknown schema path {$path} on {$artifactType}.", $sourcePath, '/sections/*/fields/source/path', 'Use a payload.* path declared by the artifact type schema.', ['document_identifier' => $identifier, 'field_identifier' => $field['identifier'], 'artifact_type' => $artifactType]);
            }
        }

        if (is_string($primary) && ! array_any($manifest->artifacts, fn (array $artifact): bool => $artifact['profile'] === $profile['identifier'] && $artifact['type'] === $primary && $artifact['status'] === 'accepted')) {
            $findings[] = new ValidationFinding(ValidationSeverity::Warning, 'DOCUMENT_EVIDENCE_ABSENT', "{$identifier} is valid but has no accepted {$primary} evidence yet.", $sourcePath, '/primary_artifact_type', 'Accept supporting evidence before resolving this document.');
        }

        return $findings;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<ValidationFinding>
     */
    private function duplicates(array $items, string $code, string $sourcePath, string $location): array
    {
        $seen = [];
        $findings = [];
        foreach ($items as $item) {
            $identifier = is_array($item) ? ($item['identifier'] ?? null) : null;
            if (is_string($identifier) && isset($seen[$identifier])) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, $code, "Duplicate identifier {$identifier}.", $sourcePath, $location);
            }
            if (is_string($identifier)) {
                $seen[$identifier] = true;
            }
        }

        return $findings;
    }

    /** @param array<string, mixed> $profile */
    private function schemaHasPath(string $repositoryRoot, array $profile, string $artifactType, string $path): bool
    {
        if (! preg_match('/^payload\.[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $path)) {
            return false;
        }
        $schemaRelative = $profile['artifact_types'][$artifactType]['schema'] ?? null;
        if (! is_string($schemaRelative)) {
            return false;
        }
        $schemaPath = dirname($profile['path']).'/'.$schemaRelative;
        $schemaObject = $this->sourceLoader->jsonObject((new RepositoryAddress($repositoryRoot))->absolute($schemaPath));
        $schema = json_decode(json_encode($schemaObject, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $node = $schema;
        foreach (array_slice(explode('.', $path), 1) as $segment) {
            if (($node['type'] ?? null) !== 'object' || ! is_array($node['properties'][$segment] ?? null)) {
                return false;
            }
            $node = $node['properties'][$segment];
        }

        return true;
    }
}
