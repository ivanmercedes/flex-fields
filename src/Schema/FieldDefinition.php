<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Schema;

use Closure;
use Illuminate\Support\Str;

class FieldDefinition
{
    protected array $attributes = [
        'is_required' => false,
        'is_active' => true,
        'is_searchable' => false,
        'is_shown_in_list' => false,
        'width' => 'full',
        'options' => [],
        'validation_rules' => [],
        'settings' => [],
        'category_ids' => null,
    ];

    public function __construct(string $type, string $key, ?string $label = null)
    {
        $this->attributes['type'] = $type;
        $this->attributes['key'] = $key;
        $this->attributes['label'] = $label ?? Str::title(str_replace('_', ' ', $key));
    }

    public function label(string $label): self
    {
        $this->attributes['label'] = $label;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->attributes['is_required'] = $required;

        if ($required) {
            $this->rules(['required']);
        }

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->attributes['placeholder'] = $placeholder;

        return $this;
    }

    public function description(string $description): self
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->attributes['default_value'] = $value;

        return $this;
    }

    public function options(array $options): self
    {
        $this->attributes['options'] = $options;

        return $this;
    }

    public function rules(array | string $rules): self
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        $this->attributes['validation_rules'] = array_merge($this->attributes['validation_rules'], $rules);

        return $this;
    }

    public function settings(array $settings): self
    {
        $this->attributes['settings'] = array_merge($this->attributes['settings'], $settings);

        return $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->attributes['settings']['multiple'] = $multiple;

        return $this;
    }

    public function optimize(bool | string $optimize = true): self
    {
        if (is_string($optimize)) {
            $this->attributes['settings']['optimize'] = true;
            $this->attributes['settings']['image_format'] = $optimize;
        } else {
            $this->attributes['settings']['optimize'] = $optimize;
        }

        return $this;
    }

    public function imageDimensions(?int $maxWidth = null, ?int $maxHeight = null, ?int $quality = null): self
    {
        if ($maxWidth !== null) {
            $this->attributes['settings']['image_max_width'] = $maxWidth;
        }
        if ($maxHeight !== null) {
            $this->attributes['settings']['image_max_height'] = $maxHeight;
        }
        if ($quality !== null) {
            $this->attributes['settings']['image_quality'] = $quality;
        }

        return $this;
    }

    public function width(string $width): self
    {
        $this->attributes['width'] = $width;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->attributes['is_searchable'] = $searchable;

        return $this;
    }

    public function showInList(bool $show = true): self
    {
        $this->attributes['is_shown_in_list'] = $show;

        return $this;
    }

    public function active(bool $active = true): self
    {
        $this->attributes['is_active'] = $active;

        return $this;
    }

    public function categories(array $categoryIds): self
    {
        $this->attributes['category_ids'] = $categoryIds;

        return $this;
    }

    /**
     * Place the field in a field group declared with Blueprint::fieldGroup().
     */
    public function group(string $groupKey): self
    {
        $this->attributes['group'] = $groupKey;

        return $this;
    }

    /**
     * Only show the field when another field matches a condition.
     * Operators: equals, not_equals, in, not_in, contains, filled, blank, gt, gte, lt, lte, is_true, is_false.
     */
    public function showWhen(string $field, string $operator = 'equals', mixed $value = null): self
    {
        $showWhen = $this->attributes['settings']['show_when'] ?? ['match' => 'all', 'conditions' => []];
        $showWhen['conditions'][] = ['field' => $field, 'operator' => $operator, 'value' => $value];
        $this->attributes['settings']['show_when'] = $showWhen;

        return $this;
    }

    /**
     * Show the field when ANY (instead of all) of the showWhen() conditions match.
     */
    public function showWhenAny(): self
    {
        $this->attributes['settings']['show_when']['match'] = 'any';
        $this->attributes['settings']['show_when']['conditions'] ??= [];

        return $this;
    }

    public function translatable(bool $translatable = true): self
    {
        $this->attributes['settings']['translatable'] = $translatable;

        return $this;
    }

    public function relatedTo(string $entitySlug, bool $multiple = false): self
    {
        $this->attributes['settings']['related_entity'] = $entitySlug;
        $this->attributes['settings']['multiple'] = $multiple;

        return $this;
    }

    public function phoneMask(string $mask): self
    {
        $this->attributes['settings']['phone_mask'] = $mask;

        return $this;
    }

    /**
     * @param  array<int, string>  $parts  street, street_2, city, state, postal_code, country
     */
    public function addressParts(array $parts): self
    {
        $this->attributes['settings']['address_parts'] = array_values($parts);

        return $this;
    }

    public function schema(Closure $callback): self
    {
        $blueprint = new Blueprint($this->attributes['key'] . '_schema');
        $callback($blueprint);

        $fields = array_map(function (FieldDefinition $field) {
            return $field->getAttributes();
        }, $blueprint->getFields());

        $this->attributes['settings']['schema'] = $fields;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
