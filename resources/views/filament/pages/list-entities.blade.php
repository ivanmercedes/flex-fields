<x-filament-panels::page>

    @if(empty($entities))
        <div class="ff-empty-state p-10 text-center text-gray-500 dark:text-gray-400 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900">
            <x-filament::icon icon="heroicon-o-cube" class="ff-empty-state__icon w-12 h-12 mx-auto mb-3 opacity-30" />
            <p class="font-medium">No entities yet.</p>
            <p class="text-sm mt-1">Create your first entity to get started.</p>
        </div>
    @else
        <div class="ff-entities-grid grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($entities as $entity)
            @include('flex-fields::filament.partials._entity-card', ['entity' => $entity])
            @endforeach
        </div>
    @endif

</x-filament-panels::page>
