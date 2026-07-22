<?php

namespace App\Domain\Repository;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Throwable;

final readonly class ValidateArtifactPayloads
{
    public function __construct(private Validator $validator = new Validator(max_errors: 20, stop_at_first_error: false)) {}

    /** @return list<ValidationFinding> */
    public function handle(string $repositoryRoot, RepositoryManifest $manifest): array
    {
        $findings = [];
        $profiles = collect($manifest->profiles)->keyBy('identifier');
        $repository = new RepositoryAddress($repositoryRoot);

        foreach ($manifest->artifacts as $artifact) {
            if ($artifact['status'] !== 'accepted') {
                continue;
            }
            $profile = $profiles->get($artifact['profile']);
            $mapping = is_array($profile) ? ($profile['artifact_types'][$artifact['type']] ?? null) : null;
            $schemaRelative = is_array($mapping) ? ($mapping['schema'] ?? null) : null;
            if (! is_string($schemaRelative)) {
                $findings[] = $this->finding('ARTIFACT_SCHEMA_MISSING', "Accepted artifact {$artifact['identifier']}@{$artifact['revision']} has no declared schema mapping.", $artifact, null, 'Declare the artifact type and schema in profile.yaml.');

                continue;
            }
            $schemaRepositoryPath = dirname($profile['path']).'/'.$schemaRelative;
            $schemaPath = $repository->absolute($schemaRepositoryPath);
            try {
                $schema = json_decode(file_get_contents($schemaPath), false, flags: JSON_THROW_ON_ERROR);
                $payload = json_decode(json_encode($artifact['payload'], JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
                $result = $this->validator->validate($payload, $schema);
                if ($result->isValid()) {
                    continue;
                }
                foreach ((new ErrorFormatter)->format($result->error()) as $pointer => $messages) {
                    foreach ((array) $messages as $message) {
                        $findings[] = $this->finding('ARTIFACT_PAYLOAD_SCHEMA_VIOLATION', "{$artifact['identifier']}@{$artifact['revision']}: {$message}", $artifact, $pointer === '/' ? '' : $pointer, 'Change the payload or its canonical artifact schema.', ['schema_path' => $schemaRepositoryPath, 'schema_location' => '#']);
                    }
                }
            } catch (Throwable $exception) {
                $findings[] = $this->finding('ARTIFACT_SCHEMA_INVALID', "Schema {$schemaRepositoryPath} cannot validate {$artifact['identifier']}: {$exception->getMessage()}", $artifact, null, 'Correct the declared JSON Schema.', ['schema_path' => $schemaRepositoryPath]);
            }
        }

        return $findings;
    }

    /** @param array<string, mixed> $artifact @param array<string, mixed> $context */
    private function finding(string $code, string $message, array $artifact, ?string $payloadPointer, string $remediation, array $context = []): ValidationFinding
    {
        return new ValidationFinding(ValidationSeverity::Error, $code, $message, $artifact['path'], $payloadPointer === null ? null : '/payload'.$payloadPointer, $remediation, [...$context, 'artifact_identifier' => $artifact['identifier'], 'artifact_revision' => $artifact['revision'], 'artifact_type' => $artifact['type'], 'profile' => $artifact['profile']]);
    }
}
