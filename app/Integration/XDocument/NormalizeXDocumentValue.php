<?php

namespace App\Integration\XDocument;

final readonly class NormalizeXDocumentValue
{
    /** @return array<string, mixed> */
    public function handle(mixed $value): array
    {
        if ($value === null) {
            return ['type' => 'null', 'value' => null];
        }
        if (is_string($value)) {
            return ['type' => 'string', 'value' => $value];
        }
        if (is_int($value)) {
            return ['type' => 'integer', 'value' => $value];
        }
        if (is_bool($value)) {
            return ['type' => 'boolean', 'value' => $value];
        }
        if (is_float($value)) {
            throw new InvalidXDocumentCompilationRequest('Floating-point values cannot cross the x-document contract. Use an explicit decimal string.');
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return ['type' => 'list', 'value' => array_map(fn (mixed $item): array => $this->handle($item), $value)];
            }
            foreach (array_keys($value) as $key) {
                if (! is_string($key)) {
                    throw new InvalidXDocumentCompilationRequest('Structured x-document values require string map keys.');
                }
            }

            return ['type' => 'map', 'value' => array_map(fn (mixed $item): array => $this->handle($item), $value)];
        }

        throw new InvalidXDocumentCompilationRequest('Unsupported x-document value type: '.get_debug_type($value).'.');
    }
}
