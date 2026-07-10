<x-filament-panels::page>
    <div class="ff-dashboard">
    {{-- ── Stats Bar ──────────────────────────────────────────────── --}}
    <div class="ff-dashboard__stats grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        @foreach([
            ['label' => 'Total Entities',  'value' => $stats['entities'], 'icon' => 'heroicon-o-cube',         'color' => 'text-primary-600'],
            ['label' => 'Active Entities', 'value' => $stats['active'],   'icon' => 'heroicon-o-check-circle',  'color' => 'text-success-600'],
            ['label' => 'Custom Fields',   'value' => $stats['fields'],   'icon' => 'heroicon-o-variable',      'color' => 'text-info-600'],
            ['label' => 'Total Records',   'value' => $stats['records'],  'icon' => 'heroicon-o-table-cells',   'color' => 'text-warning-600'],
        ] as $stat)
        <div class="ff-stat-card fi-card rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-4 flex items-center gap-4">
            <div class="ff-stat-card__icon rounded-lg bg-gray-100 dark:bg-gray-800 p-3">
                <x-filament::icon :icon="$stat['icon']" class="w-6 h-6 {{ $stat['color'] }}" />
            </div>
            <div>
                <p class="ff-stat-card__label text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p class="ff-stat-card__value text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Entities Grid ───────────────────────────────────────────── --}}
    <div class="ff-dashboard__section fi-section">
        <div class="ff-dashboard__section-header fi-section-header-wrapper px-6 py-4 border-b border-gray-200 dark:border-white/10">
            <h2 class="fi-section-heading text-base font-semibold text-gray-950 dark:text-white">
                Entities
            </h2>
            <p class="fi-section-description text-sm text-gray-500 dark:text-gray-400">
                Manage your custom entity types and their data.
            </p>
        </div>

        @if(empty($entities))
            <div class="ff-empty-state p-10 text-center text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-cube" class="ff-empty-state__icon w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="font-medium">No entities yet.</p>
                <p class="text-sm mt-1">Create your first entity to get started.</p>
                <x-filament::button tag="a" :href="$this->getCreateEntityUrl()" class="mt-4" icon="heroicon-o-plus">
                    Create Entity
                </x-filament::button>
            </div>
        @else
        <div class="ff-entities-grid p-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($entities as $entity)
              @include('flex-fields::filament.partials._entity-card', ['entity' => $entity])
            @endforeach
        </div>
        @endif
    </div>
    </div>

</x-filament-panels::page>
