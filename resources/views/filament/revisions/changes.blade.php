@php
    /** @var \IvanMercedes\FlexFields\Models\RecordRevision $revision */
    $changes = $revision->diff ?? [];
    $format = fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : (is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '—'));
@endphp

<div style="display: flex; flex-direction: column; gap: 0.75rem;">
    <div style="font-size: 0.875rem; opacity: 0.75;">
        {{ __('flex-fields::flex-fields.history.events.' . $revision->event) }}
        · {{ $revision->user_name ?? '—' }}
        · {{ $revision->created_at?->toDateTimeString() }}
    </div>

    @if (empty($changes))
        <p style="font-size: 0.875rem;">{{ __('flex-fields::flex-fields.history.no_changes') }}</p>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid rgba(127,127,127,.3);">
                        <th style="padding: 0.5rem;">{{ __('flex-fields::flex-fields.history.fields.key') }}</th>
                        <th style="padding: 0.5rem;">{{ __('flex-fields::flex-fields.history.fields.old') }}</th>
                        <th style="padding: 0.5rem;">{{ __('flex-fields::flex-fields.history.fields.new') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($changes as $key => $change)
                        <tr style="border-bottom: 1px solid rgba(127,127,127,.15); vertical-align: top;">
                            <td style="padding: 0.5rem; font-weight: 600; white-space: nowrap;">{{ $key }}</td>
                            <td style="padding: 0.5rem; color: rgb(220 38 38);"><pre style="white-space: pre-wrap; margin: 0; font-family: inherit;">{{ $format($change['old'] ?? null) }}</pre></td>
                            <td style="padding: 0.5rem; color: rgb(22 163 74);"><pre style="white-space: pre-wrap; margin: 0; font-family: inherit;">{{ $format($change['new'] ?? null) }}</pre></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
