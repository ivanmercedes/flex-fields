<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="previewImport" style="display: flex; flex-direction: column; gap: 1rem;">
            {{ $this->form }}

            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                    {{ __('flex-fields::flex-fields.import.actions.preview') }}
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if ($preview)
        @php($summary = $preview['summary'])
        <x-filament::section :heading="__('flex-fields::flex-fields.import.preview.heading')">
            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.875rem; margin-bottom: 1rem;">
                <span><strong>{{ $summary['total'] }}</strong> {{ __('flex-fields::flex-fields.import.preview.total') }}</span>
                <span style="color: rgb(22 163 74);"><strong>{{ $summary['valid'] }}</strong> {{ __('flex-fields::flex-fields.import.preview.valid') }}</span>
                <span style="color: rgb(220 38 38);"><strong>{{ $summary['invalid'] }}</strong> {{ __('flex-fields::flex-fields.import.preview.invalid') }}</span>
                <span><strong>{{ $summary['create'] }}</strong> {{ __('flex-fields::flex-fields.import.preview.to_create') }}</span>
                <span><strong>{{ $summary['update'] }}</strong> {{ __('flex-fields::flex-fields.import.preview.to_update') }}</span>
            </div>

            @if (! empty($preview['columns']['unknown']))
                <p style="font-size: 0.875rem; color: rgb(217 119 6); margin-bottom: 1rem;">
                    {{ __('flex-fields::flex-fields.import.preview.unknown_columns') }}:
                    <strong>{{ implode(', ', $preview['columns']['unknown']) }}</strong>
                </p>
            @endif

            <div style="overflow-x: auto; max-height: 28rem; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid rgba(127,127,127,.3);">
                            <th style="padding: 0.5rem;">{{ __('flex-fields::flex-fields.import.preview.line') }}</th>
                            <th style="padding: 0.5rem;">{{ __('flex-fields::flex-fields.import.preview.action') }}</th>
                            <th style="padding: 0.5rem;">{{ __('flex-fields::flex-fields.record.fields.title_column') }}</th>
                            <th style="padding: 0.5rem;">{{ __('flex-fields::flex-fields.import.preview.values') }}</th>
                            <th style="padding: 0.5rem;">{{ __('flex-fields::flex-fields.import.preview.errors') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr style="border-bottom: 1px solid rgba(127,127,127,.15); vertical-align: top; {{ $row['valid'] ? '' : 'background: rgba(220,38,38,.06);' }}">
                                <td style="padding: 0.5rem;">{{ $row['line'] }}</td>
                                <td style="padding: 0.5rem;">
                                    {{ $row['valid'] ? __('flex-fields::flex-fields.import.preview.actions.' . $row['action']) : __('flex-fields::flex-fields.import.preview.actions.skip') }}
                                </td>
                                <td style="padding: 0.5rem;">{{ $row['attributes']['title'] ?? '—' }}</td>
                                <td style="padding: 0.5rem; max-width: 28rem; word-break: break-word;">
                                    {{ \Illuminate\Support\Str::limit(json_encode($row['values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 160) }}
                                </td>
                                <td style="padding: 0.5rem; color: rgb(220 38 38);">
                                    @foreach ($row['errors'] as $field => $messages)
                                        <div>{{ implode(' ', (array) $messages) }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($preview['truncated'] ?? false)
                <p style="font-size: 0.8125rem; opacity: .7; margin-top: .5rem;">{{ __('flex-fields::flex-fields.import.preview.truncated', ['count' => \IvanMercedes\FlexFields\Resources\EntityDataResource\Pages\ImportEntityData::PREVIEW_LIMIT]) }}</p>
            @endif

            <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                <x-filament::button wire:click="confirmImport" color="success" icon="heroicon-o-check" :disabled="$summary['valid'] === 0">
                    {{ __('flex-fields::flex-fields.import.actions.confirm', ['count' => $summary['valid']]) }}
                </x-filament::button>
                <x-filament::button wire:click="cancelImport" color="gray">
                    {{ __('flex-fields::flex-fields.import.actions.cancel') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($result)
        <x-filament::section :heading="__('flex-fields::flex-fields.import.result.heading')">
            <p style="font-size: 0.875rem;">
                {{ __('flex-fields::flex-fields.import.notifications.summary', ['created' => $result['created'], 'updated' => $result['updated'], 'skipped' => $result['skipped']]) }}
            </p>
            @foreach ($result['errors'] as $line => $errors)
                <p style="font-size: 0.8125rem; color: rgb(220 38 38);">
                    {{ __('flex-fields::flex-fields.import.preview.line') }} {{ $line }}:
                    {{ collect($errors)->flatten()->implode(' ') }}
                </p>
            @endforeach
        </x-filament::section>
    @endif
</x-filament-panels::page>
