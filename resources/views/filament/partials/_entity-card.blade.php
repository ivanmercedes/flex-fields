<div class="ff-entity-card relative rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm hover:shadow transition-shadow overflow-hidden">

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
               class="ff-entity-card__action ff-entity-card__action--primary flex-1 text-center text-xs font-medium px-2 py-2 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 hover:bg-primary-100 transition-colors">
                Records
            </a>
            <a href="{{ $this->getManageFieldsUrl($entity['id']) }}"
               class="ff-entity-card__action ff-entity-card__action--info flex-1 text-center text-xs font-medium px-2 py-2 rounded-lg bg-info-50 dark:bg-info-500/10 text-info-600 dark:text-info-400 hover:bg-info-100 transition-colors">
                Fields
            </a>
            <a href="{{ $this->getManageCategoriesUrl($entity['id']) }}"
               class="ff-entity-card__action ff-entity-card__action--warning flex-1 text-center text-xs font-medium px-2 py-2 rounded-lg bg-warning-50 dark:bg-warning-500/10 text-warning-600 dark:text-warning-400 hover:bg-warning-100 transition-colors">
                Categories
            </a>
            <a href="{{ $this->getEditEntityUrl($entity['id']) }}"
               class="ff-entity-card__action ff-entity-card__action--neutral text-xs font-medium px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 transition-colors">
                Edit
            </a>
        </div>
    </div>
</div>
