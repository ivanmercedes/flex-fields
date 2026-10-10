<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Services\RecordImporter;
use IvanMercedes\FlexFields\Support\Label;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * CSV / Excel import with a preview step: upload → preview & validate → confirm.
 */
class ImportEntityData extends Page
{
    public const PREVIEW_LIMIT = 200;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Locked]
    public ?int $entityId = null;

    #[Locked]
    public ?string $storedPath = null;

    #[Locked]
    public string $mode = RecordImporter::MODE_CREATE;

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    protected static string $resource = EntityDataResource::class;

    protected string $view = 'flex-fields::filament.pages.import-records';

    public function mount(): void
    {
        $entity = EntityDataResource::getCurrentEntity();

        abort_unless($entity !== null, 404);
        abort_unless(EntityDataResource::canCreate() && FlexPermissions::allows(null, FlexPermissions::IMPORT, $entity), 403);

        $this->entityId = $entity->id;
        $this->form->fill(['mode' => RecordImporter::MODE_CREATE]);
    }

    public function getTitle(): string
    {
        return Label::trans('flex-fields::flex-fields.import.title', ['entity' => $this->getEntity()->name]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label(Label::trans('flex-fields::flex-fields.import.fields.file'))
                    ->helperText(Label::trans('flex-fields::flex-fields.import.helpers.file'))
                    ->disk(config('flex-fields.import_export.disk', 'local'))
                    ->directory(config('flex-fields.import_export.directory', 'flex-fields-imports'))
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'text/x-csv',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->getUploadedFileNameForStorageUsing(fn ($file) => Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()))
                    ->required(),

                Select::make('mode')
                    ->label(Label::trans('flex-fields::flex-fields.import.fields.mode'))
                    ->options([
                        RecordImporter::MODE_CREATE => Label::trans('flex-fields::flex-fields.import.modes.create'),
                        RecordImporter::MODE_UPSERT => Label::trans('flex-fields::flex-fields.import.modes.upsert'),
                    ])
                    ->default(RecordImporter::MODE_CREATE)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function previewImport(): void
    {
        $state = $this->form->getState();
        $path = is_array($state['file']) ? (string) reset($state['file']) : (string) $state['file'];

        $this->discardStoredFile();
        $this->storedPath = $path;
        $this->mode = (string) $state['mode'];
        $this->result = null;

        try {
            $preview = app(RecordImporter::class)->preview($this->getEntity(), $this->readRows(), $this->mode);
        } catch (Throwable $e) {
            $this->discardStoredFile();

            Notification::make()->danger()->title(Label::trans('flex-fields::flex-fields.import.notifications.failed'))->body($e->getMessage())->send();

            return;
        }

        $preview['truncated'] = count($preview['rows']) > self::PREVIEW_LIMIT;
        $preview['rows'] = array_slice($preview['rows'], 0, self::PREVIEW_LIMIT);

        $this->preview = $preview;
    }

    public function confirmImport(): void
    {
        if (! $this->storedPath) {
            return;
        }

        try {
            $result = app(RecordImporter::class)->import($this->getEntity(), $this->readRows(), $this->mode);
        } catch (Throwable $e) {
            Notification::make()->danger()->title(Label::trans('flex-fields::flex-fields.import.notifications.failed'))->body($e->getMessage())->send();

            return;
        }

        $this->discardStoredFile();
        $this->preview = null;
        $this->result = $result;
        $this->form->fill(['mode' => $this->mode]);

        Notification::make()
            ->success()
            ->title(Label::trans('flex-fields::flex-fields.import.notifications.completed'))
            ->body(Label::trans('flex-fields::flex-fields.import.notifications.summary', [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
            ]))
            ->send();
    }

    public function cancelImport(): void
    {
        $this->discardStoredFile();
        $this->preview = null;
        $this->form->fill(['mode' => $this->mode]);
    }

    public function getEntity(): Entity
    {
        return Entity::currentTenant()->findOrFail($this->entityId);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label(Label::trans('flex-fields::flex-fields.import.actions.template'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $entity = $this->getEntity();
                    $csv = app(RecordImporter::class)->templateCsv($entity);

                    return response()->streamDownload(fn () => print ($csv), $entity->slug . '-import-template.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),

            Action::make('back')
                ->label(Label::trans('flex-fields::flex-fields.import.actions.back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => EntityDataResource::getUrl('index', ['entity' => $this->entityId])),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    protected function readRows(): array
    {
        $disk = Storage::disk(config('flex-fields.import_export.disk', 'local'));

        return app(RecordImporter::class)->read($disk->path((string) $this->storedPath));
    }

    protected function discardStoredFile(): void
    {
        if ($this->storedPath) {
            Storage::disk(config('flex-fields.import_export.disk', 'local'))->delete($this->storedPath);
            $this->storedPath = null;
        }
    }
}
