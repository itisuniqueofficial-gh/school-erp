<?php

declare(strict_types=1);

namespace Core\Validation;

use Core\Database\Connection;

/**
 * Rule-based input validator supporting a pipe-delimited rule syntax, e.g.
 *   ['email' => 'required|email|unique:users,email', 'age' => 'nullable|integer|min:1']
 */
final class Validator
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, string> */
    private array $rules;

    /** @var array<string, string> */
    private array $messages;

    /** @var array<string, string[]> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $messages
     */
    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
        $this->run();
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            $isNullable = in_array('nullable', $rules, true);
            $isPresent = $value !== null && $value !== '';

            if ($isNullable && !$isPresent) {
                $this->validated[$field] = $value;
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $method = 'validate' . str_replace('_', '', ucwords($name, '_'));

                if (method_exists($this, $method)) {
                    $ok = $this->{$method}($field, $value, $param);
                    if ($ok === false) {
                        // Stop on first failure per field.
                        break;
                    }
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function addError(string $field, string $default): void
    {
        $key = $field;
        $message = $this->messages[$key] ?? $this->messages[$field] ?? $default;
        $this->errors[$field][] = str_replace(':field', $this->label($field), $message);
    }

    private function label(string $field): string
    {
        return ucwords(str_replace(['_', '.'], ' ', $field));
    }

    // --- Rules -------------------------------------------------------------

    private function validateRequired(string $field, mixed $value): bool
    {
        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            $this->addError($field, ':field is required.');
            return false;
        }
        return true;
    }

    private function validateEmail(string $field, mixed $value): bool
    {
        if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, ':field must be a valid email address.');
            return false;
        }
        return true;
    }

    private function validateInteger(string $field, mixed $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, ':field must be an integer.');
            return false;
        }
        return true;
    }

    private function validateNumeric(string $field, mixed $value): bool
    {
        if (!is_numeric($value)) {
            $this->addError($field, ':field must be a number.');
            return false;
        }
        return true;
    }

    private function validateString(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            $this->addError($field, ':field must be a string.');
            return false;
        }
        return true;
    }

    private function validateBoolean(string $field, mixed $value): bool
    {
        if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on', 'off'], true)) {
            $this->addError($field, ':field must be true or false.');
            return false;
        }
        return true;
    }

    /**
     * Determine the size of a value for min/max/size/between rules. When the
     * field is explicitly declared as a `string`, always measure character
     * length (so a numeric-looking string like a 12-digit ID is bounded by
     * length, not value). Otherwise numeric values are compared by value.
     */
    private function sizeOf(string $field, mixed $value): float
    {
        $rules = explode('|', $this->rules[$field] ?? '');
        if (in_array('string', $rules, true)) {
            return (float) mb_strlen((string) $value);
        }
        return is_numeric($value) ? (float) $value : (float) mb_strlen((string) $value);
    }

    private function validateMin(string $field, mixed $value, ?string $param): bool
    {
        $min = (float) $param;
        $size = $this->sizeOf($field, $value);
        if ($size < $min) {
            $this->addError($field, ":field must be at least {$param}.");
            return false;
        }
        return true;
    }

    private function validateMax(string $field, mixed $value, ?string $param): bool
    {
        $max = (float) $param;
        $size = $this->sizeOf($field, $value);
        if ($size > $max) {
            $this->addError($field, ":field must not exceed {$param}.");
            return false;
        }
        return true;
    }

    private function validateBetween(string $field, mixed $value, ?string $param): bool
    {
        [$min, $max] = array_pad(explode(',', (string) $param), 2, '0');
        $size = $this->sizeOf($field, $value);
        if ($size < (float) $min || $size > (float) $max) {
            $this->addError($field, ":field must be between {$min} and {$max}.");
            return false;
        }
        return true;
    }

    private function validateIn(string $field, mixed $value, ?string $param): bool
    {
        $options = explode(',', (string) $param);
        if (!in_array((string) $value, $options, true)) {
            $this->addError($field, ':field is invalid.');
            return false;
        }
        return true;
    }

    private function validateConfirmed(string $field, mixed $value): bool
    {
        $confirmation = $this->data[$field . '_confirmation'] ?? null;
        if ($value !== $confirmation) {
            $this->addError($field, ':field confirmation does not match.');
            return false;
        }
        return true;
    }

    private function validateDate(string $field, mixed $value): bool
    {
        if (strtotime((string) $value) === false) {
            $this->addError($field, ':field must be a valid date.');
            return false;
        }
        return true;
    }

    private function validateRegex(string $field, mixed $value, ?string $param): bool
    {
        if ($param === null || @preg_match($param, (string) $value) !== 1) {
            $this->addError($field, ':field format is invalid.');
            return false;
        }
        return true;
    }

    private function validateAlpha(string $field, mixed $value): bool
    {
        if (!ctype_alpha(str_replace(' ', '', (string) $value))) {
            $this->addError($field, ':field may only contain letters.');
            return false;
        }
        return true;
    }

    private function validateAlphaNum(string $field, mixed $value): bool
    {
        if (!ctype_alnum((string) $value)) {
            $this->addError($field, ':field may only contain letters and numbers.');
            return false;
        }
        return true;
    }

    private function validatePhone(string $field, mixed $value): bool
    {
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', (string) $value)) {
            $this->addError($field, ':field must be a valid phone number.');
            return false;
        }
        return true;
    }

    /**
     * Strong password: >= 8 chars with upper, lower and a digit.
     */
    private function validatePassword(string $field, mixed $value): bool
    {
        $value = (string) $value;
        if (mb_strlen($value) < 8
            || !preg_match('/[A-Z]/', $value)
            || !preg_match('/[a-z]/', $value)
            || !preg_match('/\d/', $value)
        ) {
            $this->addError($field, ':field must be at least 8 characters and include upper, lower case letters and a number.');
            return false;
        }
        return true;
    }

    /**
     * unique:table,column[,ignoreId[,idColumn]]
     */
    private function validateUnique(string $field, mixed $value, ?string $param): bool
    {
        [$table, $column, $ignore, $idColumn] = array_pad(explode(',', (string) $param), 4, null);
        $column ??= $field;
        $idColumn ??= 'id';

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $bindings = [$value];
        if ($ignore !== null && $ignore !== '' && $ignore !== 'null') {
            $sql .= " AND `{$idColumn}` <> ?";
            $bindings[] = $ignore;
        }

        try {
            $count = (int) Connection::instance()->scalar($sql, $bindings);
        } catch (\Throwable) {
            return true; // Fail open on DB error to avoid blocking; logged elsewhere.
        }

        if ($count > 0) {
            $this->addError($field, ':field has already been taken.');
            return false;
        }
        return true;
    }

    /**
     * exists:table,column
     */
    private function validateExists(string $field, mixed $value, ?string $param): bool
    {
        [$table, $column] = array_pad(explode(',', (string) $param), 2, null);
        $column ??= $field;
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        try {
            $count = (int) Connection::instance()->scalar($sql, [$value]);
        } catch (\Throwable) {
            return true;
        }
        if ($count === 0) {
            $this->addError($field, 'Selected :field is invalid.');
            return false;
        }
        return true;
    }
}
