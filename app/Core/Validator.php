<?php
declare(strict_types=1);

namespace App\Core;

class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $output = [];

        foreach ($rules as $field => $ruleList) {
            $value = $data[$field] ?? null;
            $isRequired = in_array('required', $ruleList, true);

            if ($isRequired && ($value === null || $value === '')) {
                $errors[$field][] = 'is required';
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            foreach ($ruleList as $rule) {
                if ($rule === 'required') {
                    continue;
                }

                if ($rule === 'string' && !is_string($value)) {
                    $errors[$field][] = 'must be string';
                } elseif ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $errors[$field][] = 'must be integer';
                } elseif ($rule === 'numeric' && !is_numeric($value)) {
                    $errors[$field][] = 'must be numeric';
                } elseif ($rule === 'email' && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[$field][] = 'must be valid email';
                } elseif ($rule === 'boolean' && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                    $errors[$field][] = 'must be boolean';
                } elseif ($rule === 'date' && strtotime((string) $value) === false) {
                    $errors[$field][] = 'must be valid date';
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (float) substr($rule, 4);
                    if ((float) $value < $min) {
                        $errors[$field][] = 'must be >= ' . $min;
                    }
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = (float) substr($rule, 4);
                    if ((float) $value > $max) {
                        $errors[$field][] = 'must be <= ' . $max;
                    }
                } elseif (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if (!in_array((string) $value, $allowed, true)) {
                        $errors[$field][] = 'must be in ' . implode(', ', $allowed);
                    }
                }
            }

            $output[$field] = $value;
        }

        if ($errors !== []) {
            throw new HttpException(
                json_encode(['validation_errors' => $errors], JSON_UNESCAPED_UNICODE),
                422,
                'validation_error'
            );
        }

        return $output;
    }
}
