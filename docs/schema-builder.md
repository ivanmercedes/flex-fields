# FlexFields Schema Builder

The FlexFields Schema Builder allows you to programmatically define your Entities and Custom Fields using a fluent API, very similar to Laravel's database migrations.

Instead of manually creating Entities in the Filament admin panel every time you deploy to a new environment, you can define them in code, version control them, and run them using Artisan commands.

## Getting Started

To create a new schema, run the following Artisan command:

```bash
php artisan flex:make-schema Product
```

This will generate a new file in your application's `database/flex-schemas` directory, for example: `2024_01_01_100000_create_product_schema.php`.

## Defining Schemas

Open the generated file. You will see an `up` and `down` method. Use the `Flex` facade and the `Blueprint` object to define your entity and its fields.

```php
<?php

use IvanMercedes\FlexFields\Schema\Flex;
use IvanMercedes\FlexFields\Schema\Blueprint;

return new class
{
    public function up(): void
    {
        Flex::create('Product', function (Blueprint $entity) {
            // Entity Attributes
            $entity->icon('heroicon-o-shopping-cart');
            $entity->entityColor('#3b82f6');
            $entity->description('Product catalog');
            
            // Custom Fields
            $entity->text('sku', 'SKU')->required()->width('half');
            $entity->text('name', 'Product Name')->required()->width('half');
            
            $entity->rich('description', 'Description');
            
            $entity->number('price', 'Price')->required()->width('third');
            $entity->number('stock', 'Available Stock')->default(0)->width('third');
            
            $entity->select('status', 'Status')->options([
                'active' => 'Active',
                'draft' => 'Draft',
                'archived' => 'Archived',
            ])->required()->width('third');
            
            $entity->boolean('is_featured', 'Featured Product')->default(false);
            
            $entity->color('theme_color', 'Product Theme Color');
            $entity->image('cover_image', 'Cover Image');
            
            $entity->repeater('features', 'Product Features')->schema(function (Blueprint $table) {
                $table->string('feature_name', 'Feature')->required();
                $table->string('feature_value', 'Value')->required();
            });
        });
    }

    public function down(): void
    {
        Flex::drop('product'); // Pass the entity's slug
    }
};
```

## Running Schemas

Once you have defined your schema, you can execute it to sync it with the database.

```bash
php artisan flex:migrate
```

This command will read all pending files in the `database/flex-schemas` directory and run their `up` method. It keeps track of which schemas have already run in the `ff_schemas` database table, ensuring they are only executed once.

## Rolling Back Schemas

If you made a mistake or want to revert the last batch of schemas you ran, use the rollback command:

```bash
php artisan flex:rollback
```

This will call the `down` method of the schemas in the latest batch, deleting the entities and their associated fields, and remove their records from the `ff_schemas` table.

## Available Field Types

The following methods are available on the `$entity` (Blueprint) object to create Custom Fields:

- `$entity->string('key', 'Label')`
- `$entity->text('key', 'Label')`
- `$entity->textarea('key', 'Label')`
- `$entity->rich('key', 'Label')` (Rich Text Editor)
- `$entity->number('key', 'Label')`
- `$entity->boolean('key', 'Label')`
- `$entity->date('key', 'Label')`
- `$entity->datetime('key', 'Label')`
- `$entity->select('key', 'Label')`
- `$entity->multiselect('key', 'Label')`
- `$entity->tags('key', 'Label')`
- `$entity->json('key', 'Label')`
- `$entity->image('key', 'Label')`
- `$entity->color('key', 'Label')` (Color Picker)
- `$entity->repeater('key', 'Label')`

## Repeater Fields

Repeaters allow you to define a set of sub-fields that can be duplicated multiple times by the user. You can define the schema of a repeater using the `schema` method and a closure:

```php
$entity->repeater('links', 'Useful Links')
    ->schema(function (Blueprint $table) {
        $table->string('url', 'URL')->required();
        $table->string('label', 'Link Label');
    });
```

## Field Modifiers

You can chain modifiers to your field definitions to configure their behavior:

- `->required()`: Makes the field required.
- `->placeholder('...')`: Sets a placeholder.
- `->description('...')`: Adds helper text below the field.
- `->default($value)`: Sets a default value.
- `->options(['key' => 'Value'])`: Sets the options for `select` or `multiselect` fields.
- `->rules(['string', 'max:255'])`: Adds specific Laravel validation rules.
- `->width('half')`: Sets the field width. Accepts `full` (default), `half`, or `third`.
- `->searchable()`: Makes the field searchable in the table.
- `->showInList()`: Shows the field in the Entity records table.
- `->active(false)`: Disables the field.
