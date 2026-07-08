<div class="ff-entity-card relative rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 transition-shadow overflow-hidden">

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
        <div class="flex flex-wrap items-center gap-2 pt-2">
            <x-filament::button
                tag="a"
                href="{{ $this->getEntityDataUrl($entity['id']) }}"
                color="primary"
                size="xs"
                outlined
            >
                Records
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ $this->getManageFieldsUrl($entity['id']) }}"
                color="info"
                size="xs"
                outlined
            >
                Fields
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ $this->getManageCategoriesUrl($entity['id']) }}"
                color="warning"
                size="xs"
                outlined
            >
                Categories
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ $this->getEditEntityUrl($entity['id']) }}"
                color="gray"
                size="xs"
                outlined
            >
                Edit
            </x-filament::button>
        </div>
    </div>
</div>
