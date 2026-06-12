<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class ToolInputValidator
{
    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $input
     * @return array<int, string>
     */
    public function validate(array $schema, array $input): array
    {
        if ($schema === []) {
            return [];
        }

        $errors = [];
        $required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];
        foreach ($required as $field) {
            $name = (string) $field;
            if (!array_key_exists($name, $input) || $input[$name] === null || $input[$name] === '') {
                $errors[] = sprintf('Missing required field: %s', $name);
            }
        }

        $properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];
        foreach ($input as $key => $value) {
            if (!isset($properties[$key]) || !is_array($properties[$key])) {
                continue;
            }

            /** @var array<string, mixed> $rules */
            $rules = $properties[$key];
            $type = isset($rules['type']) ? (string) $rules['type'] : '';
            if ($type !== '' && !$this->matchesType($value, $type)) {
                $errors[] = sprintf('Field %s must be %s.', $key, $type);
                continue;
            }

            if (($type === 'number' || $type === 'integer') && is_numeric($value)) {
                $num = (float) $value;
                if (isset($rules['minimum']) && $num < (float) $rules['minimum']) {
                    $errors[] = sprintf('Field %s must be >= %s.', $key, (string) $rules['minimum']);
                }
                if (isset($rules['maximum']) && $num > (float) $rules['maximum']) {
                    $errors[] = sprintf('Field %s must be <= %s.', $key, (string) $rules['maximum']);
                }
            }

            if ($type === 'string' && is_string($value)) {
                if (isset($rules['minLength']) && mb_strlen($value) < (int) $rules['minLength']) {
                    $errors[] = sprintf('Field %s is shorter than %d characters.', $key, (int) $rules['minLength']);
                }
                if (isset($rules['maxLength']) && mb_strlen($value) > (int) $rules['maxLength']) {
                    $errors[] = sprintf('Field %s exceeds %d characters.', $key, (int) $rules['maxLength']);
                }
            }

            if (isset($rules['enum']) && is_array($rules['enum']) && !in_array($value, $rules['enum'], true)) {
                $errors[] = sprintf('Field %s must be one of: %s.', $key, implode(', ', array_map('strval', $rules['enum'])));
            }
        }

        return $errors;
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value),
            default => true,
        };
    }
}

