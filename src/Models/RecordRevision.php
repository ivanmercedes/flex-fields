<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use IvanMercedes\FlexFields\Models\Traits\BelongsToFlexTenant;
use IvanMercedes\FlexFields\Support\FlexModels;

/**
 * RecordRevision — an audit log entry with a full snapshot of a record after a change.
 */
class RecordRevision extends Model
{
    use BelongsToFlexTenant;

    public const UPDATED_AT = null;

    protected $table = 'ff_record_revisions';

    protected $fillable = [
        'tenant_id',
        'entity_record_id',
        'event',
        'user_id',
        'user_name',
        'snapshot',
        'diff',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'diff' => 'array',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(FlexModels::record(), 'entity_record_id')->withTrashed();
    }

    /**
     * Keys (attributes and field keys) changed by this revision.
     *
     * @return array<int, string>
     */
    public function changedKeys(): array
    {
        return array_keys($this->diff ?? []);
    }
}
