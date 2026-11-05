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
                <x-table id="field-studio-definitions">
                    <x-table.thead>
                        <x-table.tr>
                            <x-table.th class="w-5/12 sm:w-4/12">
                                <x-sortablelink column="label" title="{{ trans('field-studio::general.index.column_field') }}" />
                            </x-table.th>
                            <x-table.th class="w-3/12 sm:w-2/12">
                                <x-sortablelink column="entity_type" title="{{ trans('field-studio::general.index.column_record') }}" />
                            </x-table.th>
                            <x-table.th class="w-2/12" hidden-mobile>
                                <x-sortablelink column="field_type" title="{{ trans('field-studio::general.index.column_type') }}" />
                            </x-table.th>
                            <x-table.th class="w-2/12" hidden-mobile>
                                <x-sortablelink column="is_enabled" title="{{ trans('field-studio::general.index.column_status') }}" />
                            </x-table.th>
                            <x-table.th class="w-4/12 sm:w-2/12">
                                {{ trans('field-studio::general.index.column_actions') }}
                            </x-table.th>
                        </x-table.tr>
                    </x-table.thead>

                    <x-table.tbody>
                        @foreach ($definitions as $field)
                            <x-table.tr>
                                <x-table.td class="w-5/12 sm:w-4/12">
                                    <x-slot name="first">
                                        {{ $field['label'] }}
                                    </x-slot>
                                    <x-slot name="second">
                                        <span class="font-mono text-xs text-gray-500">{{ $field['field_key'] }}</span>
                                    </x-slot>
                                </x-table.td>
                                <x-table.td class="w-3/12 sm:w-2/12">
                                    {{ $entityLabels[$field['entity_type']] ?? $field['entity_type'] }}
                                </x-table.td>
                                <x-table.td class="w-2/12" hidden-mobile>
                                    {{ $typeLabels[$field['field_type']] ?? $field['field_type'] }}
                                </x-table.td>
                                <x-table.td class="w-2/12" hidden-mobile>
                                    <span class="{{ $field['is_enabled'] ? 'text-green-700' : 'text-gray-500' }}">
                                        {{ $field['is_enabled'] ? trans('field-studio::general.index.enabled') : trans('field-studio::general.index.disabled') }}
                                    </span>
                                </x-table.td>
                                <x-table.td class="w-4/12 sm:w-2/12">
                                    <a class="text-purple hover:underline mr-3"
                                       href="{{ route('field-studio.definitions.edit', $field['id']) }}"
                                       aria-label="{{ trans('field-studio::general.index.edit_field', ['label' => $field['label']]) }}">
                                        {{ trans('field-studio::general.index.edit') }}
                                    </a>
                                    <form class="inline" method="post"
                                          action="{{ route('field-studio.definitions.destroy', $field['id']) }}"
                                          onsubmit="return confirm(this.dataset.confirm)"
                                          data-confirm="{{ trans('field-studio::general.index.archive_confirm') }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline"
                                                aria-label="{{ trans('field-studio::general.index.archive_field', ['label' => $field['label']]) }}">
                                            {{ trans('field-studio::general.index.archive') }}
                                        </button>
                                    </form>
                                </x-table.td>
                            </x-table.tr>
                        @endforeach
                    </x-table.tbody>
                </x-table>
                <p class="mt-3 text-xs text-gray-500">{{ trans('field-studio::general.index.count', ['count' => count($definitions)]) }}</p>
            @endif
        </x-index.container>
    </x-slot>
</x-layouts.admin>