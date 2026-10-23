<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Schema;

use Closure;
use Illuminate\Support\Str;

class Blueprint
{
    protected array $entityAttributes = [
        'is_active' => true,
        'show_in_menu' => true,
        'menu_order' => 0,
        'settings' => [],
    ];

    protected array $fields = [];

    protected array $dropFields = [];

    protected array $fieldGroups = [];

    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->entityAttributes['name'] = $name;
        $this->entityAttributes['slug'] = Str::slug($name);
    }

    public function slug(string $slug): self
    {
        $this->entityAttributes['slug'] = $slug;

        return $this;
    }

    public function description(string $description): self
    {
        $this->entityAttributes['description'] = $description;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->entityAttributes['icon'] = $icon;

        return $this;
    }

    public function entityColor(string $color): self
    {
        $this->entityAttributes['color'] = $color;

        return $this;
    }

    public function isActive(bool $active = true): self
    {
        $this->entityAttributes['is_active'] = $active;

        return $this;
    }

    public function showInMenu(bool $show = true): self
    {
        $this->entityAttributes['show_in_menu'] = $show;

        return $this;
    }

    public function menuOrder(int $order): self
    {
        $this->entityAttributes['menu_order'] = $order;

        return $this;
    }

    public function settings(array $settings): self
    {
        $this->entityAttributes['settings'] = array_merge($this->entityAttributes['settings'] ?? [], $settings);

        return $this;
    }

    public function string(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('string', $key, $label);
    }

    public function text(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('text', $key, $label);
    }

    public function textarea(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('textarea', $key, $label);
    }

    public function rich(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('richtext', $key, $label);
    }

    public function number(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('number', $key, $label);
    }

    public function boolean(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('boolean', $key, $label);
    }

    public function date(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('date', $key, $label);
    }

    public function datetime(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('datetime', $key, $label);
    }

    public function select(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('select', $key, $label);
    }

    public function multiselect(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('multiselect', $key, $label);
    }

    public function tags(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('tags', $key, $label);
    }

    public function repeater(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('repeater', $key, $label);
    }

    public function json(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('json', $key, $label);
    }

    public function image(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('image', $key, $label);
    }

    public function color(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('color', $key, $label);
    }

    public function email(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('email', $key, $label);
    }

    public function url(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('url', $key, $label);
    }

    public function file(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('file', $key, $label);
    }

    public function phone(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('phone', $key, $label);
    }

    public function address(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('address', $key, $label);
    }

    /**
     * A link (button, call to action): URL, title and target.
     */
    public function link(string $key, ?string $label = null): FieldDefinition
    {
        return $this->addField('link', $key, $label);
    }

    /**
     * Link to records of another entity (by slug).
     */
    public function relationship(string $key, string $relatedEntity, ?string $label = null, bool $multiple = false): FieldDefinition
    {
        return $this->addField('relationship', $key, $label)->relatedTo($relatedEntity, $multiple);
    }

    /**
     * Define a labeled, collapsible section. Assign fields with ->group($key)
     * or pass a callback that declares the fields of the group.
     */
    public function fieldGroup(
        string $key,
        ?string $label = null,
        ?string $description = null,
        bool $collapsible = true,
        bool $collapsed = false,
        ?Closure $fields = null,
    ): self {
        $this->fieldGroups[$key] = [
            'key' => $key,
            'label' => $label ?? Str::title(str_replace('_', ' ', $key)),
            'description' => $description,
            'is_collapsible' => $collapsible,
            'is_collapsed' => $collapsed,
            'order' => count($this->fieldGroups) + 1,
        ];

        if ($fields !== null) {
            $before = count($this->fields);
            $fields($this);

            foreach (array_slice($this->fields, $before) as $field) {
                $field->group($key);
            }
        }

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFieldGroups(): array
    {
        return $this->fieldGroups;
    }

    public function dropField(string $key): self
    {
        $this->dropFields[] = $key;

        return $this;
    }

    public function getEntityAttributes(): array
    {
        return $this->entityAttributes;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return string[]
     */
    public function getDropFields(): array
    {
        return $this->dropFields;
    }

    protected function addField(string $type, string $key, ?string $label = null): FieldDefinition
    {
        $field = new FieldDefinition($type, $key, $label);
        $this->fields[] = $field;

        return $field;
    }
}
