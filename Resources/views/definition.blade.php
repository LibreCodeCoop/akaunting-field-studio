<x-layouts.admin>
    <x-slot name="title">{{ $field ? 'Editar campo' : 'Novo campo' }} · Field Studio</x-slot>
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
                    Voltar às configurações
                </a>
                <p class="text-sm text-gray-500 mt-3">
                    Personalize a informação capturada pela empresa sem criar colunas nos documentos financeiros.
                </p>
            </div>

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-5" role="alert">
                    <p class="font-medium mb-1">Verifique os campos destacados:</p>
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
                        <x-form.section.head title="Identificação" description="Defina um nome compreensível e o registro onde o campo será exibido." />
                    </x-slot>
                    <x-slot name="body">
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-label">Nome do campo <span class="text-red-500">*</span></label>
                            <input id="field-label" name="label" required maxlength="180" class="{{ $inputClass }}"
                                value="{{ old('label', $field['label'] ?? '') }}" placeholder="Ex.: Centro de custo">
                            <p class="text-xs text-gray-500 mt-1">Será exibido para quem preencher o documento.</p>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-key">Chave de integração <span class="text-red-500">*</span></label>
                            <input id="field-key" name="field_key" required pattern="[a-z][a-z0-9_]{1,70}"
                                maxlength="71" class="{{ $inputClass }} font-mono"
                                value="{{ old('field_key', $field['field_key'] ?? '') }}"
                                @if($editing) readonly @endif placeholder="Ex.: centro_custo">
                            <p class="text-xs text-gray-500 mt-1">Usada pela API. Não pode ser alterada após a criação.</p>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-entity">Onde utilizar <span class="text-red-500">*</span></label>
                            <select id="field-entity" name="entity_type" class="{{ $inputClass }}" @if($editing) disabled @endif>
                                @foreach($entityLabels as $entity => $name)
                                    <option value="{{ $entity }}" {{ old('entity_type', $field['entity_type'] ?? 'invoice') === $entity ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">O campo será exclusivo desta entidade e desta empresa.</p>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-type">Formato do dado <span class="text-red-500">*</span></label>
                            <select id="field-type" name="field_type" class="{{ $inputClass }}">
                                @foreach($typeLabels as $type => $name)
                                    <option value="{{ $type }}" {{ $selectedType === $type ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1" id="field-type-description">A validação acompanha o formato escolhido.</p>
                        </div>
                    </x-slot>
                </x-form.section>

                <x-form.section>
                    <x-slot name="head">
                        <x-form.section.head title="Preenchimento" description="Configure ajuda contextual, opções e valores sugeridos." />
                    </x-slot>
                    <x-slot name="body">
                        <div id="field-options" class="sm:col-span-6">
                            <label class="block text-sm font-medium text-black" for="field-options-input">Opções disponíveis <span class="text-red-500">*</span></label>
                            <textarea id="field-options-input" name="options_text" rows="5" class="{{ $inputClass }}"
                                placeholder="Operações&#10;Administração&#10;Comercial">{{ old('options_text', implode("\n", $field['options'] ?? [])) }}</textarea>
                            <p class="text-xs text-gray-500 mt-1">Uma opção por linha. Não remova opções já utilizadas nos registros.</p>
                        </div>
                        <div class="sm:col-span-6">
                            <label class="block text-sm font-medium text-black" for="field-description">Texto de ajuda</label>
                            <textarea id="field-description" name="description" rows="2" maxlength="2000" class="{{ $inputClass }}"
                                placeholder="Explique como preencher este campo">{{ old('description', $field['description'] ?? '') }}</textarea>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-placeholder">Exemplo no campo</label>
                            <input id="field-placeholder" name="placeholder" maxlength="255" class="{{ $inputClass }}"
                                value="{{ old('placeholder', $field['placeholder'] ?? '') }}" placeholder="Ex.: Projeto Alfa">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-black" for="field-default">Valor inicial (opcional)</label>
                            <input id="field-default" name="default_value" class="{{ $inputClass }}"
                                value="{{ is_array($defaultValue) ? implode(', ', $defaultValue) : $defaultValue }}">
                            <p class="text-xs text-gray-500 mt-1">Para seleção múltipla, separe os valores por vírgula.</p>
                        </div>
                    </x-slot>
                </x-form.section>

                <x-form.section>
                    <x-slot name="head">
                        <x-form.section.head title="Apresentação e regras" description="Controle visibilidade, preenchimento obrigatório e disposição do campo." />
                    </x-slot>
                    <x-slot name="body">
                        <div class="sm:col-span-3">
                            <label for="field-span" class="block text-sm font-medium text-black">Largura do campo</label>
                            <select id="field-span" name="column_span" class="{{ $inputClass }}">
                                @foreach([6 => 'Largura completa', 3 => 'Metade da linha', 2 => 'Um terço da linha'] as $span => $label)
                                    <option value="{{ $span }}" {{ (int) old('column_span', $field['column_span'] ?? 6) === $span ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-6 space-y-4 pt-2">
                            @foreach([
                                'is_required' => ['Preenchimento obrigatório', 'O formulário exigirá valor válido quando este campo for enviado.'],
                                'is_visible' => ['Visível nos formulários', 'Mostra o campo nas telas de lançamento onde existe integração.'],
                                'is_enabled' => ['Campo ativo', 'Permite novas gravações pela API ou pela interface.'],
                            ] as $key => $description)
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" name="{{ $key }}" value="1" class="mt-1 accent-purple"
                                        {{ old($key, $field[$key] ?? ($key !== 'is_required')) ? 'checked' : '' }}>
                                    <span>
                                        <span class="block text-sm font-medium text-black">{{ $description[0] }}</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">{{ $description[1] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="sm:col-span-3">
                            <label for="field-order" class="block text-sm font-medium text-black">Ordem</label>
                            <input id="field-order" type="number" min="0" max="100000" name="sort_order" class="{{ $inputClass }}"
                                value="{{ old('sort_order', $field['sort_order'] ?? 0) }}">
                        </div>
                    </x-slot>
                </x-form.section>

                <x-form.section>
                    <x-slot name="foot">
                        <div class="flex flex-wrap gap-3 items-center pb-5">
                            <button type="submit" id="field-save" class="px-6 py-2.5 text-white bg-green hover:bg-green-700 rounded-lg">
                                {{ $editing ? 'Salvar alterações' : 'Criar campo' }}
                            </button>
                            <a class="text-sm text-purple px-3" href="{{ route('field-studio.definitions.index') }}">Cancelar</a>
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
    const hints = {
        decimal: 'Informe números decimais com ponto e até seis casas. Ex.: 123.450000',
        percent: 'Percentual de 0 a 100. Ex.: 27.450000',
        date: 'Data no formato AAAA-MM-DD.',
        time: 'Horário no formato HH:MM.',
        datetime: 'Data e horário no formato AAAA-MM-DDTHH:MM.',
        boolean: 'Sim ou não.',
        select: 'O usuário escolherá uma das opções informadas.',
        multiselect: 'O usuário poderá selecionar várias opções.',
        contact: 'Referência a um contato cadastrado nesta empresa.',
        email: 'Validação de endereço de e-mail.',
        url: 'Endereço web HTTP ou HTTPS.',
        textarea: 'Campo de texto longo.'
    };

    function refresh() {
        const type = typeInput.value;
        options.hidden = type !== 'select' && type !== 'multiselect';
        typeHint.textContent = hints[type] || 'Texto livre, conforme as regras definidas.';
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