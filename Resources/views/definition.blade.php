<x-layouts.admin>
    <x-slot name="title">{{ $field ? trans('field-studio::general.definition.edit_title') : trans('field-studio::general.definition.new_title') }} · Field Studio</x-slot>
    <x-slot name="content">
        @php
            $editing = $field !== null;
            $selectedType = old('field_type', $field['field_type'] ?? 'text');
            $defaultValue = old('default_value', $field['default_value'] ?? '');
            if ($editing && $selectedType === 'multiselect' && is_string($defaultValue) && $defaultValue !== '') {
                $defaultValue = implode(', ', json_decode($defaultValue, true) ?: []);
            }
            $inputClass = 'w-full text-sm px-3 py-2.5 mt-1 rounded-lg border border-light-gray text-black placeholder-light-gray bg-white focus:outline-none focus:border-purple';
        @endphp

        <x-form.container>
            <div class="mb-5">
                <a class="inline-flex items-center text-sm text-purple" href="{{ route('field-studio.definitions.index') }}">
                    <span class="material-icons-outlined text-sm mr-1" aria-hidden="true">arrow_back</span>
                    {{ trans('field-studio::general.definition.back') }}
                </a>
                <p class="text-sm text-gray-500 mt-3">
                    {{ trans('field-studio::general.definition.intro') }}
                </p>
            </div>

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-5" role="alert">
                    <p class="font-medium mb-1">{{ trans('field-studio::general.definition.error_intro') }}</p>
                    <ul class="list-disc pl-5">
                        @foreach($errors->all() as $error)<li class="text-sm">{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="post" novalidate="novalidate"
                action="{{ $editing ? route('field-studio.definitions.update', $field['id']) : route('field-studio.definitions.store') }}">
                @csrf
                @if($editing)
                    @method('PUT')
                    <input type="hidden" name="expected_version" value="{{ $field['version'] }}">
                @endif

                <x-form.section>
                    <x-slot name="head">
                        <x-form.section.head title="{{ trans('field-studio::general.definition.identification') }}" description="{{ trans('field-studio::general.definition.identification_description') }}" />
                    </x-slot>
                    <x-slot name="body">
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-label">{{ trans('field-studio::general.definition.label') }} <span class="text-red-500">*</span></label>
                            <input id="field-label" name="label" required maxlength="180" class="{{ $inputClass }}"
                                value="{{ old('label', $field['label'] ?? '') }}" placeholder="{{ trans('field-studio::general.definition.label_placeholder') }}">
                            <p class="text-xs text-gray-500 mt-1">{{ trans('field-studio::general.definition.label_help') }}</p>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-key">{{ trans('field-studio::general.definition.key') }} <span class="text-red-500">*</span></label>
                            <input id="field-key" name="field_key" required pattern="[a-z][a-z0-9_]{1,70}"
                                maxlength="71" class="{{ $inputClass }} font-mono"
                                value="{{ old('field_key', $field['field_key'] ?? '') }}"
                                @if($editing) readonly @endif placeholder="{{ trans('field-studio::general.definition.key_placeholder') }}">
                            <p class="text-xs text-gray-500 mt-1">{{ trans('field-studio::general.definition.key_help') }}</p>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-entity">{{ trans('field-studio::general.definition.entity') }} <span class="text-red-500">*</span></label>
                            <select id="field-entity" name="entity_type" class="{{ $inputClass }}" @if($editing) disabled @endif>
                                @foreach($entityLabels as $entity => $name)
                                    <option value="{{ $entity }}" {{ old('entity_type', $field['entity_type'] ?? 'invoice') === $entity ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">{{ trans('field-studio::general.definition.entity_help') }}</p>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-type">{{ trans('field-studio::general.definition.type') }} <span class="text-red-500">*</span></label>
                            <select id="field-type" name="field_type" class="{{ $inputClass }}">
                                @foreach($typeLabels as $type => $name)
                                    <option value="{{ $type }}" {{ $selectedType === $type ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1" id="field-type-description">{{ trans('field-studio::general.definition.type_help') }}</p>
                        </div>
                    </x-slot>
                </x-form.section>

                <x-form.section>
                    <x-slot name="head">
                        <x-form.section.head title="{{ trans('field-studio::general.definition.filling') }}" description="{{ trans('field-studio::general.definition.filling_description') }}" />
                    </x-slot>
                    <x-slot name="body">
                        <div id="field-options" class="sm:col-span-6">
                            <label class="block text-sm font-medium text-black" for="field-options-input">{{ trans('field-studio::general.definition.options') }} <span class="text-red-500">*</span></label>
                            <textarea id="field-options-input" name="options_text" rows="5" class="{{ $inputClass }}"
                                placeholder="{{ trans('field-studio::general.definition.options_placeholder') }}">{{ old('options_text', implode("\n", $field['options'] ?? [])) }}</textarea>
                            <p class="text-xs text-gray-500 mt-1">{{ trans('field-studio::general.definition.options_help') }}</p>
                        </div>
                        <div class="sm:col-span-6">
                            <label class="block text-sm font-medium text-black" for="field-description">{{ trans('field-studio::general.definition.description') }}</label>
                            <textarea id="field-description" name="description" rows="2" maxlength="2000" class="{{ $inputClass }}"
                                placeholder="{{ trans('field-studio::general.definition.description_placeholder') }}">{{ old('description', $field['description'] ?? '') }}</textarea>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-placeholder">{{ trans('field-studio::general.definition.placeholder') }}</label>
                            <input id="field-placeholder" name="placeholder" maxlength="255" class="{{ $inputClass }}"
                                value="{{ old('placeholder', $field['placeholder'] ?? '') }}" placeholder="{{ trans('field-studio::general.definition.placeholder_example') }}">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-default">{{ trans('field-studio::general.definition.default') }}</label>
                            <input id="field-default" name="default_value" class="{{ $inputClass }}"
                                value="{{ is_array($defaultValue) ? implode(', ', $defaultValue) : $defaultValue }}">
                            <p class="text-xs text-gray-500 mt-1">{{ trans('field-studio::general.definition.default_help') }}</p>
                        </div>
                    </x-slot>
                </x-form.section>

                <x-form.section>
                    <x-slot name="head">
                        <x-form.section.head title="{{ trans('field-studio::general.definition.presentation') }}" description="{{ trans('field-studio::general.definition.presentation_description') }}" />
                    </x-slot>
                    <x-slot name="body">
                        <div class="sm:col-span-3">
                            <label for="field-span" class="block text-sm font-medium text-black">{{ trans('field-studio::general.definition.width') }}</label>
                            <select id="field-span" name="column_span" class="{{ $inputClass }}">
                                @foreach(trans('field-studio::general.definition.widths') as $span => $label)
                                    <option value="{{ $span }}" {{ (int) old('column_span', $field['column_span'] ?? 6) === $span ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-6 space-y-4 pt-2">
                            @foreach(trans('field-studio::general.definition.flags') as $key => $description)
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" name="{{ $key }}" value="1" class="mt-1 accent-purple"
                                        {{ old($key, $field[$key] ?? ($key !== 'is_required')) ? 'checked' : '' }}>
                                    <span>
                                        <span class="block text-sm font-medium text-black">{{ $description['label'] }}</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">{{ $description['help'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="sm:col-span-3">
                            <label for="field-order" class="block text-sm font-medium text-black">{{ trans('field-studio::general.definition.order') }}</label>
                            <input id="field-order" type="number" min="0" max="100000" name="sort_order" class="{{ $inputClass }}"
                                value="{{ old('sort_order', $field['sort_order'] ?? 0) }}">
                        </div>
                    </x-slot>
                </x-form.section>

                <x-form.section>
                    <x-slot name="foot">
                        <div class="flex flex-wrap gap-3 items-center pb-5">
                            <button type="submit" id="field-save" class="px-6 py-2.5 text-white bg-green hover:bg-green-700 rounded-lg">
                                {{ $editing ? trans('field-studio::general.definition.save') : trans('field-studio::general.definition.create') }}
                            </button>
                            <a class="text-sm text-purple px-3" href="{{ route('field-studio.definitions.index') }}">{{ trans('field-studio::general.definition.cancel') }}</a>
                        </div>
                    </x-slot>
                </x-form.section>
            </form>
        </x-form.container>
    </x-slot>
</x-layouts.admin>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nameInput = document.getElementById('field-label');
    const keyInput = document.getElementById('field-key');
    const typeInput = document.getElementById('field-type');
    const options = document.getElementById('field-options');
    const typeHint = document.getElementById('field-type-description');
    const hints = @json(trans('field-studio::general.type_hints'));

    function refresh() {
        const type = typeInput.value;
        options.hidden = type !== 'select' && type !== 'multiselect';
        typeHint.textContent = hints[type] || hints.default;
    }
    typeInput.addEventListener('change', refresh);
    refresh();

    if (!keyInput.readOnly) {
        let touched = Boolean(keyInput.value);
        keyInput.addEventListener('input', () => { touched = true; });
        nameInput.addEventListener('input', () => {
            if (touched) return;
            const key = nameInput.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            keyInput.value = /^[a-z]/.test(key) ? key.slice(0, 71) : '';
        });
    }
});
</script>