<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * EntityRecord with Spatie Media Library support.
 *
 * Only usable when spatie/laravel-medialibrary is installed. Enable it with:
 *   'models' => ['record' => MediaEntityRecord::class],
 *   'media'  => ['driver' => 'spatie'],
 */
class MediaEntityRecord extends EntityRecord implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'ff_entity_records';
}
