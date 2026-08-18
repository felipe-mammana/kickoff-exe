<?php

declare(strict_types=1);

class ApiValidator
{
    public static function validate(array $payload, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $valueExists = array_key_exists($field, $payload);
            $value = $payload[$field] ?? null;

            foreach ($fieldRules as $rule => $option) {
                if (is_int($rule)) {
                    $rule = $option;
                    $option = true;
                }

                if ($rule === 'required' && (!$valueExists || self::blank($value))) {
                    $errors[$field][] = 'Campo obrigatorio.';
                    continue 2;
                }

                if (!$valueExists || $value === null || $value === '') {
                    continue;
                }

                if ($rule === 'string' && !is_string($value)) {
                    $errors[$field][] = 'Deve ser um texto.';
                }

                if ($rule === 'bool' && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    $errors[$field][] = 'Deve ser verdadeiro ou falso.';
                }

                if ($rule === 'max' && is_string($value) && strlen($value) > (int) $option) {
                    $errors[$field][] = 'Deve ter no maximo ' . (int) $option . ' caracteres.';
                }
            }
        }

        return $errors;
    }

    private static function blank($value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
