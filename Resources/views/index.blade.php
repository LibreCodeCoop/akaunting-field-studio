<x-layouts.admin>
    <x-slot name="title">{{ trans('field-studio::general.name') }}</x-slot>
    <x-slot name="favorite"
        title="{{ trans('field-studio::general.name') }}"
        icon="tune"
        route="field-studio.definitions.index"
    ></x-slot>
    <x-slot name="buttons">
        <x-link href="{{ route('field-studio.definitions.create') }}" kind="primary" id="field-studio-new">
            <span class="material-icons-outlined text-base mr-1" aria-hidden="true">add</span>
            {{ trans('field-studio::general.create') }}
        </x-link>
    </x-slot>
    <x-slot name="content">
        <x-index.container>
            <div class="mb-6">
                <h2 class="text-lg font-medium text-black">{{ trans('field-studio::general.definition_title') }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ trans('field-studio::general.index.intro') }}</p>
            </div>

            @if (session('success'))
                <div class="mb-5 px-4 py-3 rounded-lg bg-green-50 text-green-800" role="status">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-5 bg-white rounded-xl border border-gray-200 px-4 py-4">
                <form method="get" action="{{ route('field-studio.definitions.index') }}"
                    class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
                    <label class="block flex-1">
                        <span class="block mb-2 text-sm font-medium text-black">{{ trans('field-studio::general.index.filter_label') }}</span>
                        <select name="entity_type" class="w-full text-sm px-3 py-2.5 rounded-lg border border-light-gray bg-white text-black" aria-label="{{ trans('field-studio::general.index.filter_aria') }}">
                            <option value="">{{ trans('field-studio::general.index.all_records') }}</option>
                            @foreach($entityLabels as $value => $label)
                                <option value="{{ $value }}" {{ request('entity_type') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-purple text-white hover:opacity-90">
                        {{ trans('field-studio::general.index.apply_filter') }}
                    </button>
                    @if(request()->filled('entity_type'))
                        <a class="text-sm text-purple px-3 py-2.5" href="{{ route('field-studio.definitions.index') }}">{{ trans('field-studio::general.index.clear') }}</a>
                    @endif
                </form>
            </div>

            @if (count($definitions) === 0)
                <div class="rounded-xl bg-white border border-gray-200 px-8 py-12 text-center">
                    <span class="material-icons-outlined text-5xl text-gray-300" aria-hidden="true">post_add</span>
                    <h3 class="text-lg font-medium text-black mt-4">{{ trans('field-studio::general.index.empty_title') }}</h3>
                    <p class="text-sm text-gray-500 mt-2 mb-5">
                        {{ trans('field-studio::general.index.empty_description') }}
                    </p>
                    <x-link href="{{ route('field-studio.definitions.create') }}" kind="primary">
                        {{ trans('field-studio::general.index.create_first') }}
                    </x-link>
                </div>
            @else
                <div class="rounded-xl bg-white border border-gray-200 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-gray-500 bg-gray-50 border-b border-gray-200">
                                <th class="font-medium py-4 px-5">{{ trans('field-studio::general.index.column_field') }}</th>
                                <th class="font-medium py-4 px-5">{{ trans('field-studio::general.index.column_record') }}</th>
                                <th class="font-medium py-4 px-5">{{ trans('field-studio::general.index.column_type') }}</th>
                                <th class="font-medium py-4 px-5">{{ trans('field-studio::general.index.column_status') }}</th>
                                <th class="font-medium py-4 px-5 text-right">{{ trans('field-studio::general.index.column_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($definitions as $field)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
                                <td class="py-4 px-5">
                                    <div class="font-medium text-black">{{ $field['label'] }}</div>
                                    <div class="mt-1 font-mono text-xs text-gray-500">{{ $field['field_key'] }}</div>
                                    @if($field['description'])
                                        <div class="text-xs text-gray-500 mt-1">{{ Str::limit($field['description'], 90) }}</div>
                                    @endif
                                </td>
                                <td class="py-4 px-5">{{ $entityLabels[$field['entity_type']] ?? $field['entity_type'] }}</td>
                                <td class="py-4 px-5">{{ $typeLabels[$field['field_type']] ?? $field['field_type'] }}</td>
                                <td class="py-4 px-5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs {{ $field['is_enabled'] ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $field['is_enabled'] ? trans('field-studio::general.index.enabled') : trans('field-studio::general.index.disabled') }}
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-right whitespace-nowrap">
                                    <a class="text-purple hover:underline mr-4" href="{{ route('field-studio.definitions.edit', $field['id']) }}" aria-label="{{ trans('field-studio::general.index.edit_field', ['label' => $field['label']]) }}">
                                        {{ trans('field-studio::general.index.edit') }}
                                    </a>
                                    <form class="inline" method="post" action="{{ route('field-studio.definitions.destroy', $field['id']) }}"
                                        onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ trans('field-studio::general.index.archive_confirm') }}">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline" type="submit" aria-label="{{ trans('field-studio::general.index.archive_field', ['label' => $field['label']]) }}">{{ trans('field-studio::general.index.archive') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ trans('field-studio::general.index.count', ['count' => count($definitions)]) }}</p>
            @endif
        </x-index.container>
    </x-slot>
</x-layouts.admin>