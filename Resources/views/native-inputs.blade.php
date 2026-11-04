<div class="sm:col-span-6 border-t border-gray-200 mt-4 pt-5" id="field-studio-native">
    <h3 class="font-medium text-black mb-1">Informações adicionais</h3>
    <p class="text-sm text-gray-500 mb-4">Campos específicos desta empresa. As alterações também ficam disponíveis pela API.</p>
    <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
        @foreach($fields as $field)
            @if($field['is_visible'])
                @php
                    $record = $current->get($field['field_key']);
                    $value = $record['value'] ?? $field['default_value'] ?? '';
                    $key = $field['field_key'];
                    $type = $field['field_type'];
                    $cols = (int) ($field['column_span'] ?? 6);
                    if ($type === 'multiselect' && is_string($value) && $value !== '') {
                        $value = json_decode($value, true) ?: [];
                    }
                @endphp
                <div class="{{ $cols <= 2 ? 'sm:col-span-2' : ($cols <= 3 ? 'sm:col-span-3' : 'sm:col-span-6') }}">
                    <label class="block text-sm font-medium text-black" for="native-fs-{{ $key }}">
                        {{ $field['label'] }}
                        @if($field['is_required'])<span class="text-red-500">*</span>@endif
                    </label>
                    @if($type === 'select')
                        <select class="w-full px-3 py-2 mt-1 text-sm border border-light-gray rounded-lg"
                            id="native-fs-{{ $key }}"
                            name="custom_fields[{{ $key }}]"
                            v-model="form['custom_fields[{{ $key }}]']"
                            @if($field['is_required']) required @endif>
                            <option value="">Selecione</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option }}" {{ (string)$value === (string)$option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'multiselect')
                        <select class="w-full px-3 py-2 mt-1 text-sm border border-light-gray rounded-lg"
                            id="native-fs-{{ $key }}" name="custom_fields[{{ $key }}][]" multiple
                            v-model="form['custom_fields[{{ $key }}][]']"
                            size="{{ min(6, max(3, count($field['options']))) }}"
                            @if($field['is_required']) required @endif>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option }}" {{ in_array($option, is_array($value) ? $value : [], true) ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'boolean')
                        <label class="inline-flex items-center gap-2 mt-2">
                            <input type="checkbox" id="native-fs-{{ $key }}"
                                name="custom_fields[{{ $key }}]" value="1"
                                v-model="form['custom_fields[{{ $key }}]']"
                                {{ (string)$value === '1' ? 'checked' : '' }}>
                            <span class="text-sm text-gray-600">Sim</span>
                        </label>
                    @elseif($type === 'textarea')
                        <textarea id="native-fs-{{ $key }}" name="custom_fields[{{ $key }}]"
                            v-model="form['custom_fields[{{ $key }}]']"
                            rows="3" maxlength="10000"
                            class="w-full px-3 py-2 mt-1 text-sm border border-light-gray rounded-lg"
                            @if($field['is_required']) required @endif>{{ $value }}</textarea>
                    @else
                        <input id="native-fs-{{ $key }}"
                            name="custom_fields[{{ $key }}]"
                            v-model="form['custom_fields[{{ $key }}]']"
                            value="{{ $value }}"
                            class="w-full px-3 py-2 mt-1 text-sm border border-light-gray rounded-lg"
                            @if($type === 'date') type="date"
                            @elseif($type === 'time') type="time"
                            @elseif($type === 'datetime') type="datetime-local"
                            @elseif($type === 'email') type="email"
                            @elseif($type === 'url') type="url"
                            @else type="text" @endif
                            @if($field['is_required']) required @endif
                            placeholder="{{ $field['placeholder'] }}">
                    @endif
                    @if($field['description'])
                        <p class="text-xs text-gray-500 mt-1">{{ $field['description'] }}</p>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>