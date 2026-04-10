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
                <x-filament::button tag="a" :href="$this->getEditEntityUrl(0)" class="mt-4" icon="heroicon-o-plus">
                    Create Entity
                </x-filament::button>
            </div>
        @else
        <div class="ff-entities-grid p-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($entities as $entity)
            <div class="ff-entity-card group relative rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow overflow-hidden">

                {{-- Color bar --}}
                <div class="ff-entity-card__bar h-1.5 w-full" style="background-color: {{ $entity['color'] ?? '#6366f1' }}"></div>

                <div class="ff-entity-card__body p-5">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="ff-entity-card__icon rounded-lg p-2" style="background-color: {{ ($entity['color'] ?? '#6366f1') }}22">
                                <x-filament::icon
                                    :icon="$entity['icon'] ?? 'heroicon-o-cube'"
                                    class="w-5 h-5"
                                    style="color: {{ $entity['color'] ?? '#6366f1' }}"
                                />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-950 dark:text-white text-sm">
                                    {{ $entity['name'] }}
                                </h3>
                                <code class="text-xs text-gray-400 font-mono">{{ $entity['slug'] }}</code>
                            </div>
                        </div>
                        @if(! $entity['is_active'])
                        <span class="ff-entity-card__badge text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500">
                            Inactive
                        </span>
                        @endif
                    </div>

                    @if($entity['description'])
                    <p class="ff-entity-card__description text-xs text-gray-500 dark:text-gray-400 mb-4 line-clamp-2">
                        {{ $entity['description'] }}
                    </p>
                    @endif

                    {{-- Counters --}}
                    <div class="ff-entity-card__counters flex gap-4 mb-4">
                        <div class="ff-entity-card__counter text-center">
                            <p class="text-lg font-bold text-gray-950 dark:text-white">
                                {{ $entity['custom_fields_count'] }}
                            </p>
                            <p class="text-xs text-gray-400">Fields</p>
                        </div>
                        <div class="ff-entity-card__divider w-px bg-gray-200 dark:bg-white/10"></div>
                        <div class="ff-entity-card__counter text-center">
                            <p class="text-lg font-bold text-gray-950 dark:text-white">
                                {{ $entity['records_count'] }}
                            </p>
                            <p class="text-xs text-gray-400">Records</p>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="ff-entity-card__actions flex gap-2">
                        <a href="{{ $this->getEntityDataUrl($entity['id']) }}"
                           class="ff-entity-card__action ff-entity-card__action--primary flex-1 text-center text-xs font-medium px-3 py-2 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 hover:bg-primary-100 transition-colors">
                            View Records
                        </a>
                        <a href="{{ $this->getManageFieldsUrl($entity['id']) }}"
                           class="ff-entity-card__action ff-entity-card__action--info flex-1 text-center text-xs font-medium px-3 py-2 rounded-lg bg-info-50 dark:bg-info-500/10 text-info-600 dark:text-info-400 hover:bg-info-100 transition-colors">
                            Manage Fields
                        </a>
                        <a href="{{ $this->getEditEntityUrl($entity['id']) }}"
                           class="ff-entity-card__action ff-entity-card__action--neutral text-xs font-medium px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 transition-colors">
                            Edit
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    </div>

</x-filament-panels::page>
