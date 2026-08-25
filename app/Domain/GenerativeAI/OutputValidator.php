<?php

namespace App\Domain\GenerativeAI;

/**
 * Validates model output against a template's declared output schema.
 *
 * A deliberately small subset of JSON Schema — type, required, properties, items,
 * enum, minimum/maximum, minLength/maxLength. Enough to catch the failures that
 * actually happen (a missing field, a string where a number was promised, an array
 * of the wrong shape) without pulling in a schema library for it.
 *
 * Validation failing does not throw. The output is still stored, marked invalid,
 * with the errors attached — an unusable generation is a fact worth keeping, because
 * a template that keeps failing validation is a template that needs fixing.
 */
class OutputValidator
{
    /**
     * @return array{valid:bool, errors:array<int, string>, data:array|null}
     */
    public function validate(string $content, array $schema, string $format = 'text'): array
    {
        if ($schema === []) {
            return ['valid' => true, 'errors' => [], 'data' => null];
        }

        if ($format !== 'json') {
            // A schema on a non-JSON template still gets a length check if declared.
            return $this->validateScalar($content, $schema);
        }

        $decoded = $this->decodeJson($content);

        if ($decoded === null) {
            return [
                'valid' => false,
                'errors' => ['The model did not return valid JSON.'],
                'data' => null,
            ];
        }

        $errors = $this->check($decoded, $schema, '');

        return ['valid' => $errors === [], 'errors' => $errors, 'data' => $decoded];
    }

    /**
     * Models often wrap JSON in prose or a fenced block. Recover it rather than
     * failing a response that is substantively correct.
     */
    private function decodeJson(string $content): ?array
    {
        $trimmed = trim($content);

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(.+?)```/s', $trimmed, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strcspn($trimmed, '{[');

        if ($start < strlen($trimmed)) {
            $candidate = substr($trimmed, $start);
            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function check(mixed $value, array $schema, string $path): array
    {
        $errors = [];
        $label = $path === '' ? 'root' : $path;

        $type = $schema['type'] ?? null;

        if ($type !== null && ! $this->matchesType($value, $type)) {
            $errors[] = sprintf('%s should be %s.', $label, is_array($type) ? implode('|', $type) : $type);

            return $errors;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = sprintf('%s must be one of: %s.', $label, implode(', ', array_map('strval', $schema['enum'])));
        }

        if (is_array($value) && isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['required'] ?? [] as $required) {
                if (! array_key_exists($required, $value)) {
                    $errors[] = sprintf('%s is missing required property "%s".', $label, $required);
                }
            }

            foreach ($schema['properties'] as $property => $propertySchema) {
                if (array_key_exists($property, $value) && is_array($propertySchema)) {
                    $errors = array_merge(
                        $errors,
                        $this->check($value[$property], $propertySchema, $path === '' ? $property : $path . '.' . $property)
                    );
                }
            }
        }

        if (is_array($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $index => $item) {
                $errors = array_merge($errors, $this->check($item, $schema['items'], $path . '[' . $index . ']'));
            }

            if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
                $errors[] = sprintf('%s needs at least %d items.', $label, (int) $schema['minItems']);
            }
        }

        if (is_string($value)) {
            if (isset($schema['minLength']) && mb_strlen($value) < (int) $schema['minLength']) {
                $errors[] = sprintf('%s is shorter than %d characters.', $label, (int) $schema['minLength']);
            }

            if (isset($schema['maxLength']) && mb_strlen($value) > (int) $schema['maxLength']) {
                $errors[] = sprintf('%s is longer than %d characters.', $label, (int) $schema['maxLength']);
            }
        }

        if (is_numeric($value)) {
            if (isset($schema['minimum']) && (float) $value < (float) $schema['minimum']) {
                $errors[] = sprintf('%s is below the minimum of %s.', $label, $schema['minimum']);
            }

            if (isset($schema['maximum']) && (float) $value > (float) $schema['maximum']) {
                $errors[] = sprintf('%s is above the maximum of %s.', $label, $schema['maximum']);
            }
        }

        return $errors;
    }

    private function matchesType(mixed $value, string|array $type): bool
    {
        $types = is_array($type) ? $type : [$type];

        foreach ($types as $candidate) {
            $matches = match ($candidate) {
                'string' => is_string($value),
                'number' => is_numeric($value),
                'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
                'boolean' => is_bool($value),
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ! array_is_list($value),
                'null' => $value === null,
                default => true,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{valid:bool, errors:array<int, string>, data:array|null}
     */
    private function validateScalar(string $content, array $schema): array
    {
        $errors = [];

        if (isset($schema['minLength']) && mb_strlen($content) < (int) $schema['minLength']) {
            $errors[] = sprintf('The response is shorter than %d characters.', (int) $schema['minLength']);
        }

        if (isset($schema['maxLength']) && mb_strlen($content) > (int) $schema['maxLength']) {
            $errors[] = sprintf('The response is longer than %d characters.', (int) $schema['maxLength']);
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'data' => null];
    }
}
