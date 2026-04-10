<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

class Label
{
    public static function trans(string $key, array $replace = [], ?string $default = null): string
    {
        $translation = __($key, $replace);

        if ($translation !== $key) {
            return $translation;
        }

        return $default ?? $key;
    }

    public static function config(string $key, ?string $default = null): ?string
    {
        $value = config($key);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return self::value($value, $default);
    }

    public static function configOrTrans(string $configKey, string $translationKey, ?string $default = null): string
    {
        $configValue = config($configKey);

        if (is_string($configValue) && $configValue !== '') {
            return self::value($configValue, $default);
        }

        return self::trans($translationKey, default: $default);
    }

    public static function value(string $value, ?string $default = null): string
    {
        $translation = __($value);

        if ($translation !== $value) {
            return $translation;
        }

        return $value !== '' ? $value : ($default ?? '');
    }

    public static function options(array $options): array
    {
        return array_map(
            fn (mixed $label) => is_string($label) ? self::value($label) : $label,
            $options,
        );
    }
}
