<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Schema;

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

    public function schema(\Closure $callback): self
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
