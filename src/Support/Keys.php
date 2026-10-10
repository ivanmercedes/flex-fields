<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Illuminate\Support\Str;

/**
 * Builds machine keys (snake_case) for fields and field groups.
 *
 * "Canonical URL" → canonical_url, "coverImage" → cover_image, "SEO" → seo.
 */
class Keys
{
    public static function make(string $value): string
    {
        $value = trim($value);

        // Split camelCase words only when there are no explicit separators.
        if (! preg_match('/[\s\-_]/', $value) && preg_match('/[a-z][A-Z]/', $value)) {
            $value = Str::snake($value);
        }

        return Str::slug($value, '_');
    }
}
