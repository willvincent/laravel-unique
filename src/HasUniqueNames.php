<?php

namespace WillVincent\LaravelUnique;

use Exception;

/**
 * @method static saving(\Closure $param)
 * @method static where(array $constraintFields)
 * @method static query()
 */
trait HasUniqueNames
{
    private bool $_uniqueIncludesTrashed = false;

    /**
     * Boot the trait, hooking into the saving event.
     */
    public static function bootHasUniqueNames(): void
    {
        static::saving(function ($model) {
            $uniqueField = $model->uniqueField ?? config('unique_names.unique_field', 'name');
            $constraintFields = $model->constraintFields ?? config('unique_names.constraint_fields', []);

            if (method_exists($model, 'bootSoftDeletes') && config('unique_names.soft_delete', false)) {
                $model->_uniqueIncludesTrashed = true;
            }

            if ($model->exists && $model->isDirty($uniqueField)) {
                $model->{$uniqueField} = $model->getUniqueValue(
                    $uniqueField,
                    $constraintFields,
                    $model->{$uniqueField},
                    $model->getConstraintValues($constraintFields),
                    $model->id
                );
            } elseif (! $model->exists) {
                $model->{$uniqueField} = $model->getUniqueValue(
                    $uniqueField,
                    $constraintFields,
                    $model->{$uniqueField},
                    $model->getConstraintValues($constraintFields)
                );
            }
        });
    }

    /**
     * Get the values of the constraint fields.
     *
     * @return array<string>
     */
    public function getConstraintValues(array $constraintFields): array
    {
        $values = [];
        foreach ($constraintFields as $field) {
            $values[$field] = $this->{$field};
        }

        return $values;
    }

    /**
     * Generate a unique value for the specified field.
     */
    public function getUniqueValue(string $uniqueField, array $constraintFields, string $value, array $constraintValues, mixed $exclude_id = null): string
    {
        if (config('unique_names.trim', true)) {
            $value = trim($value);
        }

        // First, check if the original value is unique
        $query = self::query();
        $query->when($this->_uniqueIncludesTrashed, fn ($query) => $query->withTrashed());

        foreach ($constraintFields as $field) {
            $query->where($field, $constraintValues[$field]);
        }
        $query->where($uniqueField, $value);
        if ($exclude_id) {
            $query->where('id', '!=', $exclude_id);
        }
        if (! $query->exists()) {
            return $value; // Use original value if it doesn’t exist
        }

        // If the original value exists, proceed with custom generator or default logic
        if (isset($this->uniqueValueGenerator)) {
            $generator = $this->uniqueValueGenerator;

            if (is_string($generator)) {
                $newValue = $this->$generator($value, $constraintValues, 0);
            } elseif (is_callable($generator)) {
                $newValue = $generator($value, $constraintValues, 0);
            } else {
                throw new Exception('uniqueValueGenerator must be a method name or a callable');
            }

            $attempts = 1;
            $checkQuery = self::query();
            $checkQuery->when($this->_uniqueIncludesTrashed, fn ($query) => $query->withTrashed());
            foreach ($constraintFields as $field) {
                $checkQuery->where($field, $constraintValues[$field]);
            }
            while ($checkQuery->where($uniqueField, $newValue)->exists() && $attempts <= config('unique_names.max_attempts', 10)) {
                if (is_string($generator)) {
                    $newValue = $this->$generator($value, $constraintValues, $attempts);
                } else {
                    $newValue = $generator($value, $constraintValues, $attempts);
                }
                $attempts++;
            }

            if ($attempts > config('unique_names.max_attempts', 10)) {
                throw new Exception('Unable to generate a unique value after '.config('unique_names.max_attempts', 10).' attempts');
            }

            return $newValue;
        }

        // Default suffix-based logic
        $suffixFormat = $this->uniqueSuffixFormat ?? config('unique_names.suffix_format', ' ({n})');
        $suffixRegex = str_replace('\{n\}', '(\d+)', preg_quote($suffixFormat, '/'));
        $fullRegex = '/^(.*)'.$suffixRegex.'$/';
        $pos = strpos($suffixFormat, '{n}');
        if ($pos === false) {
            throw new Exception('uniqueSuffixFormat must contain {n}');
        }
        $separator = substr($suffixFormat, 0, $pos);

        $base = preg_match($fullRegex, $value, $matches) ? $matches[1] : $value;
        $likePattern = $base.$separator.'%';

        $existingQuery = self::query();
        $existingQuery->when($this->_uniqueIncludesTrashed, fn ($query) => $query->withTrashed());
        foreach ($constraintFields as $field) {
            $existingQuery->where($field, $constraintValues[$field]);
        }
        $existingQuery->where(function ($query) use ($uniqueField, $base, $likePattern) {
            $query->where($uniqueField, $base)
                ->orWhere($uniqueField, 'like', $likePattern);
        });
        if ($exclude_id) {
            $existingQuery->where('id', '!=', $exclude_id);
        }

        $existingValues = $existingQuery->pluck($uniqueField);
        $numbers = [];
        if ($existingValues->contains($base)) {
            $numbers[] = 0;
        }
        foreach ($existingValues as $existingValue) {
            if ($existingValue !== $base && preg_match($fullRegex, $existingValue, $matches)) {
                $numbers[] = (int) $matches[2];
            }
        }

        $maxN = $numbers ? max($numbers) : 0;
        $nextN = $maxN + 1;
        $newValue = $base.str_replace('{n}', $nextN, $suffixFormat);

        $query = self::query();
        foreach ($constraintFields as $field) {
            $query->where($field, $constraintValues[$field]);
        }
        while ($query->where($uniqueField, $newValue)->exists()) {
            $nextN++;
            $newValue = $base.str_replace('{n}', $nextN, $suffixFormat);
        }

        return $newValue;
    }
}
