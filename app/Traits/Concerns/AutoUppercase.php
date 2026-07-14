<?php

namespace App\Traits\Concerns;

trait AutoUppercase
{
    protected function getUppercaseExcludeFields(): array
    {
        return $this->uppercaseExclude ?? [];
    }

    protected function getJsonCastColumns(): array
    {
        if (! method_exists($this, 'getCasts')) {
            return [];
        }

        return array_keys(array_filter(
            $this->getCasts(),
            fn($cast) => in_array($cast, ['array', 'json', 'collection', 'object', 'encrypted:array'], true)
        ));
    }

    public static function bootAutoUppercase(): void
    {
        static::saving(function ($model) {
            $exclude = array_unique(array_merge(
                $model->getUppercaseExcludeFields(),
                $model->getJsonCastColumns(),
                [
                    'password',
                    'remember_token',
                    'two_factor_secret',
                    'two_factor_recovery_codes',

                    'created_at',
                    'updated_at',
                    'deleted_at',
                    'email_verified_at',
                    'performed_at',

                    'avatar_url',
                    'photo_url',
                    'file_path',
                    'thumbnail_url',
                ]
            ));

            foreach ($model->getAttributes() as $key => $value) {
                if (in_array($key, $exclude, true)) {
                    continue;
                }

                if (is_string($value) && $value !== '') {
                    $model->attributes[$key] = strtoupper($value);
                }
            }
        });
    }
}