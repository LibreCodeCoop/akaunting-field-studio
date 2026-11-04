<x-layouts.admin>
    <x-slot name="title">Informações adicionais · Field Studio</x-slot>
    <x-slot name="content">
        @php
            $inputClass = 'w-full text-sm px-3 py-2.5 mt-1 rounded-lg border border-light-gray text-black placeholder-light-gray bg-white focus:outline-none focus:border-purple';
        @endphp
        <x-form.container>
            <div class="mb-5">
                <a href="{{ url()->previous() }}" class="inline-flex items-center text-sm text-purple">
                    <span class="material-icons-outlined text-sm mr-1" aria-hidden="true">arrow_back</span>
                    Voltar ao registro
                </a>
                <h2 class="text-lg font-medium text-black mt-3">Informações adicionais</h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $entityLabels[$entity_type] ?? $entity_type }}, identificador {{ $entity_id }}.
                    Valores armazenados separadamente dos lançamentos financeiros.
                </p>
            </div>

            @if (session('success'))
                <div role="status" class="px-4 py-3 bg-green-50 text-green-800 rounded-lg mb-5">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="px-4 py-3 bg-red-50 text-red-700 rounded-lg mb-5">
                    <p class="font-medium">Não foi possível salvar:</p>
                    <ul class="list-disc pl-5 text-sm">
                        @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('field-studio.records.update', ['entity_type' => $entity_type, 'entity_id' => $entity_id]) }}">
                @csrf @method('PUT')
                <x-form.section>
                    <x-slot name="head">
                        <x-form.section.head title="Dados do registro" description="Preencha somente os atributos relevantes para este registro." />
                    </x-slot>
                    <x-slot name="body">
                        @forelse($fields as $field)
                            @php
                                $key = $field['field_key'];
                                $value = old('values.'.$key, $field['value']);
                                $disabled = !$field['is_enabled'];
                                $cols = (int) ($field['column_span'] ?? 6);
                            @endphp
                            <div class="{{ $cols <= 2 ? 'sm:col-span-2' : ($cols <= 3 ? 'sm:col-span-3' : 'sm:col-span-6') }}">
                                <label for="fs-{{ $key }}" class="block text-sm font-medium text-black">
                                    {{ $field['label'] }}
                                    @if($field['is_required'] && !$disabled)<span class="text-red-500">*</span>@endif
                                    @if($disabled)<span class="text-xs text-gray-500">(desativado)</span>@endif
                                </label>
                                @if($field['description'])
                                    <p class="text-xs text-gray-500 mt-1">{{ $field['description'] }}</p>
                                @endif
                                <input type="hidden" name="versions[{{ $key }}]" value="{{ $field['value_version'] }}">

                                @if($field['field_type'] === 'select')
                                    <select id="fs-{{ $key }}" name="values[{{ $key }}]" class="{{ $inputClass }}"
                                        @if($disabled) disabled @endif @if($field['is_required'] && !$disabled) required @endif>
                                        <option value="">Selecione</option>
                                        @foreach($field['options'] as $option)
                                            <option value="{{ $option }}" {{ (string)$value === (string)$option ? 'selected' : '' }}>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif($field['field_type'] === 'multiselect')
                                    <select id="fs-{{ $key }}" name="values[{{ $key }}][]" multiple size="{{ min(7, max(3, count($field['options']))) }}"
                                        class="{{ $inputClass }}" @if($disabled) disabled @endif
                                        @if($field['is_required'] && !$disabled) required @endif>
                                        @foreach($field['options'] as $option)
                                            <option value="{{ $option }}" {{ in_array($option, is_array($value) ? $value : [], true) ? 'selected' : '' }}>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Selecione uma ou mais opções.</p>
                                @elseif($field['field_type'] === 'boolean')
                                    @if(!$disabled)<input type="hidden" name="values[{{ $key }}]" value="0">@endif
                                    <label class="inline-flex gap-2 items-center mt-2">
                                        <input type="checkbox" id="fs-{{ $key }}" value="1" name="values[{{ $key }}]"
                                            class="accent-purple"
                                            {{ (string)$value === '1' ? 'checked' : '' }}
                                            @if($disabled) disabled @endif>
                                        <span class="text-sm text-gray-600">Sim</span>
                                    </label>
                                @elseif($field['field_type'] === 'textarea')
                                    <textarea id="fs-{{ $key }}" name="values[{{ $key }}]" rows="4" maxlength="10000"
                                        class="{{ $inputClass }}" placeholder="{{ $field['placeholder'] }}"
                                        @if($disabled) disabled @endif @if($field['is_required'] && !$disabled) required @endif>{{ $value ?? '' }}</textarea>
                                @else
                                    <input id="fs-{{ $key }}" name="values[{{ $key }}]"
                                        class="{{ $inputClass }}"
                                        value="{{ $value ?? '' }}"
                                        @if($field['field_type'] === 'date') type="date"
                                        @elseif($field['field_type'] === 'time') type="time"
                                        @elseif($field['field_type'] === 'datetime') type="datetime-local"
                                        @elseif($field['field_type'] === 'email') type="email"
                                        @elseif($field['field_type'] === 'url') type="url"
                                        @elseif(in_array($field['field_type'], ['integer', 'contact'])) type="number" step="1"
                                        @else type="text" @endif
                                        @if(in_array($field['field_type'], ['percent', 'decimal'])) inputmode="decimal" @endif
                                        @if($disabled) disabled @endif
                                        @if($field['is_required'] && !$disabled) required @endif
                                        placeholder="{{ $field['placeholder'] }}">
                                @endif
                                @if($field['field_type'] === 'contact')
                                    <p class="text-xs text-gray-500 mt-1">Informe o identificador de um contato desta empresa.</p>
                                @endif
                            </div>
                        @empty
                            <div class="sm:col-span-6 rounded-xl bg-gray-50 px-5 py-8">
                                <p class="font-medium text-black">Nenhum campo foi configurado para este registro.</p>
                                <a href="{{ route('field-studio.definitions.create') }}" class="text-purple text-sm mt-2 inline-block">
                                    Criar um campo
                                </a>
                            </div>
                        @endforelse
                    </x-slot>
                    @if(count($fields))
                        <x-slot name="foot">
                            <div class="flex items-center gap-4">
                                <button type="submit" class="px-5 py-2.5 rounded-lg bg-green hover:bg-green-700 text-white">
                                    Salvar informações adicionais
                                </button>
                                <a href="{{ route('field-studio.definitions.index') }}" class="text-sm text-purple">
                                    Gerenciar campos
                                </a>
                            </div>
                        </x-slot>
                    @endif
                </x-form.section>
            </form>
            <p class="text-xs text-gray-500 mt-4">As alterações geram histórico auditável e não modificam valores contábeis.</p>
        </x-form.container>
    </x-slot>
</x-layouts.admin>