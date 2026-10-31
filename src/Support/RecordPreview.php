<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use IvanMercedes\FlexFields\Models\EntityRecord;

/**
 * Signed, expiring preview tokens for records that are not published yet.
 *
 * The editor opens the website with the token; the website exchanges it for
 * the record at GET {api prefix}/preview/{token}, so it never has to trust a
 * plain "?preview=1&id=5" parameter.
 */
class RecordPreview
{
    protected static ?Closure $urlResolver = null;

    public static function token(EntityRecord $record, ?DateTimeInterface $expiresAt = null): string
    {
        $expiresAt ??= now()->addMinutes((int) config('flex-fields.preview.ttl', 60));

        $encrypted = Crypt::encryptString((string) json_encode([
            'record' => (int) $record->getKey(),
            'entity' => (int) $record->entity_id,
            'tenant' => $record->tenant_id === null ? null : (int) $record->tenant_id,
            'expires' => $expiresAt->getTimestamp(),
        ]));

        // URL safe, so it can travel in paths and query strings unencoded.
        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    /**
     * The record of a valid, unexpired token (trashed records included), or null.
     */
    public static function resolve(string $token): ?EntityRecord
    {
        try {
            $payload = json_decode(Crypt::decryptString((string) base64_decode(strtr($token, '-_', '+/'), true)), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload) || ($payload['expires'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return FlexModels::record()::query()
            ->withTrashed()
            ->where('id', $payload['record'] ?? 0)
            ->where('entity_id', $payload['entity'] ?? 0)
            ->where('tenant_id', $payload['tenant'] ?? null)
            ->first();
    }

    /**
     * Build the website preview URL of records, e.g.:
     *   RecordPreview::urlUsing(fn (EntityRecord $record, string $token) => "https://site.test/preview?token={$token}");
     * Return null for records without a preview.
     *
     * @param  (Closure(EntityRecord, string): ?string)|null  $resolver
     */
    public static function urlUsing(?Closure $resolver): void
    {
        static::$urlResolver = $resolver;
    }

    /**
     * The website preview URL of a record, from urlUsing() or the
     * "preview.url" pattern ({token}, {entity}, {slug} and {id} placeholders).
     */
    public static function url(EntityRecord $record): ?string
    {
        if (static::$urlResolver !== null) {
            return (static::$urlResolver)($record, static::token($record));
        }

        $pattern = config('flex-fields.preview.url');

        if (! is_string($pattern) || $pattern === '') {
            return null;
        }

        return strtr($pattern, [
            '{token}' => static::token($record),
            '{entity}' => urlencode((string) $record->entity?->slug),
            '{slug}' => urlencode((string) $record->slug),
            '{id}' => (string) $record->getKey(),
        ]);
    }
}
