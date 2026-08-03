<?php

namespace App\Domain\Rostering;

use DomainException;
use Symfony\Component\Yaml\Yaml;

final readonly class RosterLifecycleScenarioDefinition
{
    /** @param list<array{id: string, title: string, operation: string}> $steps */
    public function __construct(public string $identifier, public string $title, public array $steps) {}

    public static function fromFile(string $path): self
    {
        $data = Yaml::parseFile($path);
        if (! is_array($data) || array_diff(array_keys($data), ['identifier', 'slug', 'profile', 'title', 'description', 'lifecycle', 'steps']) !== []) {
            throw new DomainException('The roster lifecycle scenario contains unsupported top-level keys.');
        }
        if (($data['identifier'] ?? null) !== 'ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE' || ! is_array($data['steps'] ?? null)) {
            throw new DomainException('The roster lifecycle scenario grammar is malformed.');
        }
        $allowed = ['warning_readiness', 'error_readiness', 'assignment_audit', 'duplicate_rejection', 'audit_rollback'];
        $steps = [];
        foreach ($data['steps'] as $step) {
            if (! is_array($step) || array_diff(array_keys($step), ['id', 'title', 'operation']) !== [] || ! in_array($step['operation'] ?? null, $allowed, true)) {
                throw new DomainException('The roster lifecycle scenario contains an unknown or malformed step.');
            }
            $steps[] = ['id' => (string) $step['id'], 'title' => (string) $step['title'], 'operation' => (string) $step['operation']];
        }
        if (count(array_unique(array_column($steps, 'id'))) !== count($steps)) {
            throw new DomainException('The roster lifecycle scenario contains duplicate step identifiers.');
        }

        return new self($data['identifier'], (string) ($data['title'] ?? $data['identifier']), $steps);
    }
}
