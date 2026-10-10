<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Models\RecordRevision;
use IvanMercedes\FlexFields\Support\FlexSchema;

/**
 * Record History / Audit Log: stores a snapshot and a diff of a record each
 * time it changes, and can restore a record to any previous revision.
 */
class RecordHistoryService
{
    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_DELETED = 'deleted';

    public const EVENT_RESTORED = 'restored';

    public const EVENT_REVERTED = 'reverted';

    public function enabled(): bool
    {
        return (bool) config('flex-fields.history.enabled', true) && FlexSchema::hasRevisions();
    }

    /**
     * Store a revision for the record's current state.
     * Updates that did not change anything are skipped.
     */
    public function record(EntityRecord $record, string $event, ?Authenticatable $user = null): ?RecordRevision
    {
        if (! $this->enabled() || ! $record->exists) {
            return null;
        }

        $snapshot = $this->snapshot($record);

        $previous = RecordRevision::query()
            ->where('entity_record_id', $record->getKey())
            ->orderByDesc('id')
            ->first();

        $changes = $this->diff($previous?->snapshot ?? [], $snapshot);

        if ($event === self::EVENT_UPDATED && $changes === []) {
            return null;
        }

        $user ??= Auth::user();

        $revision = RecordRevision::create([
            'tenant_id' => $record->tenant_id,
            'entity_record_id' => $record->getKey(),
            'event' => $event,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user ? (string) ($user->name ?? $user->email ?? $user->getAuthIdentifier()) : null,
            'snapshot' => $snapshot,
            'diff' => $changes ?: null,
        ]);

        $this->prune($record);

        return $revision;
    }

    /**
     * Full state of a record: attributes, categories and field values.
     *
     * @return array<string, mixed>
     */
    public function snapshot(EntityRecord $record): array
    {
        $record->load(['categories', 'fieldValues.customField']);

        return [
            'title' => $record->title,
            'slug' => $record->slug,
            'status' => $record->status,
            'order' => $record->order,
            'meta' => $record->meta,
            'categories' => $record->categories->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'fields' => $record->data,
        ];
    }

    /**
     * Diff two snapshots. Field values are keyed as "fields.{key}".
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(array $old, array $new): array
    {
        $flatten = function (array $snapshot): array {
            $flat = $snapshot;
            unset($flat['fields']);

            foreach ((array) ($snapshot['fields'] ?? []) as $key => $value) {
                $flat["fields.{$key}"] = $value;
            }

            return $flat;
        };

        $oldFlat = $flatten($old);
        $newFlat = $flatten($new);
        $changes = [];

        foreach (array_unique([...array_keys($oldFlat), ...array_keys($newFlat)]) as $key) {
            $before = $oldFlat[$key] ?? null;
            $after = $newFlat[$key] ?? null;

            if (json_encode($before) !== json_encode($after)) {
                $changes[$key] = ['old' => $before, 'new' => $after];
            }
        }

        return $changes;
    }

    /**
     * @return Collection<int, RecordRevision>
     */
    public function revisions(EntityRecord $record, int $limit = 50): Collection
    {
        return RecordRevision::query()
            ->where('entity_record_id', $record->getKey())
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Restore a record to the state captured in a revision.
     */
    public function revert(RecordRevision $revision, ?Authenticatable $user = null): EntityRecord
    {
        /** @var EntityRecord $record */
        $record = $revision->record()->firstOrFail();
        $snapshot = (array) $revision->snapshot;

        return DB::transaction(function () use ($record, $snapshot, $user) {
            if ($record->trashed()) {
                $record->restore();
            }

            $entity = $record->entity;
            $attributes = array_intersect_key($snapshot, array_flip(['title', 'status', 'order', 'meta']));

            $slug = $snapshot['slug'] ?? null;
            if ($slug && ! $entity->records()->where('slug', $slug)->where('id', '!=', $record->id)->exists()) {
                $attributes['slug'] = $slug;
            }

            $record->update($attributes);

            $categoryIds = $entity->categories()->whereIn('id', (array) ($snapshot['categories'] ?? []))->pluck('id')->all();
            $record->categories()->sync($categoryIds);

            $values = [];
            foreach ($entity->customFields as $field) {
                $values[$field->key] = $snapshot['fields'][$field->key] ?? null;
            }

            app(RecordDomainService::class)->writeValues($record, $values);

            $this->record($record, self::EVENT_REVERTED, $user);

            return $record->fresh(['categories', 'fieldValues.customField']);
        });
    }

    /**
     * Format a revision for API / AI consumption.
     *
     * @return array<string, mixed>
     */
    public function format(RecordRevision $revision, bool $withSnapshot = false): array
    {
        $data = [
            'id' => $revision->id,
            'record_id' => $revision->entity_record_id,
            'event' => $revision->event,
            'user_id' => $revision->user_id,
            'user_name' => $revision->user_name,
            'changed' => $revision->changedKeys(),
            'changes' => $revision->diff ?? [],
            'created_at' => $revision->created_at?->toIso8601String(),
        ];

        if ($withSnapshot) {
            $data['snapshot'] = $revision->snapshot;
        }

        return $data;
    }

    protected function prune(EntityRecord $record): void
    {
        $max = (int) config('flex-fields.history.max_revisions', 50);

        if ($max <= 0) {
            return;
        }

        $keepIds = RecordRevision::query()
            ->where('entity_record_id', $record->getKey())
            ->orderByDesc('id')
            ->limit($max)
            ->pluck('id');

        RecordRevision::query()
            ->where('entity_record_id', $record->getKey())
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
