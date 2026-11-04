<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# API REST do Field Studio

O módulo publica uma API HTTP/JSON autenticada que reutiliza a seleção de empresa e as permissões do Akaunting.

Base: \`/api/v1/field-studio\`.

A autenticação é a mesma configurada pela instância do Akaunting. Para Basic Auth ou outro mecanismo aceito pelo servidor, envie um header \`X-Company: 1\` ou o parâmetro \`?company_id=1\`, desde que o usuário tenha acesso àquela empresa. Contas com token OAuth vinculado a uma empresa preservam essa restrição. **Não envie dados de empresas em produção para testes.**

## Definições

| Método | Recurso | Resultado |
| --- | --- | --- |
| GET | /definitions | Lista ativa de campos, opcionalmente por \`entity_type\` |
| POST | /definitions | Cria definição, HTTP 201 |
| GET | /definitions/{id} | Obtém definição |
| PUT / PATCH | /definitions/{id} | Altera campos informados; \`expected_version\` recomendado |
| DELETE | /definitions/{id} | Arquiva definição, HTTP 204; histórico preservado |

Exemplo de criação:

~~~json
{
  "entity_type": "invoice",
  "field_key": "centro_custo",
  "label": "Centro de custo",
  "field_type": "select",
  "options": ["Operações", "Comercial", "Administração"],
  "description": "Área que responde pela despesa",
  "is_visible": true,
  "is_required": true,
  "is_enabled": true,
  "default_value": "Operações",
  "sort_order": 10,
  "column_span": 3
}
~~~

\`entity_type\` e \`field_key\` são imutáveis depois da criação. A chave é exclusiva dentro da combinação empresa + entidade; arquivar não libera a chave. Altere o tipo de um campo somente enquanto ele não possuir valores gravados. Opções já utilizadas não podem ser removidas.

Os tipos suportados são \`text\`, \`textarea\`, \`email\`, \`url\`, \`integer\`, \`decimal\`, \`percent\`, \`date\`, \`time\`, \`datetime\`, \`boolean\`, \`select\`, \`multiselect\` e \`contact\`.

## Valores associados a registros

| Método | Recurso | Comportamento |
| --- | --- | --- |
| GET | /records/{entity_type}/{entity_id}/values | Obtém campos e valores existentes/defaults |
| POST / PATCH | /records/{entity_type}/{entity_id}/values | Criação ou atualização parcial de valores |
| PUT | /records/{entity_type}/{entity_id}/values | Substitui a representação: omissões opcionais apagam valores, omissões obrigatórias dão 422 |
| DELETE | /records/{entity_type}/{entity_id}/values/{field_key} | Apaga apenas o valor do campo |
| GET | /records?entity_type=invoice&field_key=centro_custo&value=Operações | Retorna IDs de registros cujo valor corresponde exatamente |
| GET | /audit?entity_type=invoice&entity_id=123 | Consulta histórico auditável |

Entidades: \`invoice\`, \`bill\`, \`transaction\`, \`contact\`, \`item\`, \`document_item\`.

Exemplo PATCH:

~~~json
{
  "values": {
    "centro_custo": "Comercial",
    "competencia": "2026-11",
    "taxa_administrativa": "27.450000"
  },
  "versions": {
    "centro_custo": 1
  }
}
~~~

Os números decimais são **strings**, sem operações de ponto flutuante, com até seis casas. Porcentagens vão de 0 a 100. \`date\` usa \`YYYY-MM-DD\`, \`time\` usa \`HH:MM\` ou \`HH:MM:SS\` e \`datetime\` usa \`YYYY-MM-DDTHH:MM\`. \`multiselect\` recebe um array sem duplicatas; \`contact\` recebe o ID de um contato da mesma empresa. A leitura devolve \`is_set\` e \`value_version\` para cada definição.

Se \`versions\` for enviado, uma versão divergente retorna HTTP 409, sem gravar. Uma alteração parcialmente inválida faz a operação inteira falhar na transação. Alterações repetidas com o mesmo valor não criam auditoria artificial.

## Autorização e erros

- **401**: não autenticado
- **403**: falta de permissão para o registro ou para definições
- **404**: definição ou registro ausente ou inacessível naquela empresa
- **409**: escrita concorrente com versão divergente
- **422**: campos inválidos, opção inválida, dado do tipo errado ou referência externa

Administrar definições requer acesso às configurações da empresa. Ler ou escrever valores exige permissão equivalente para a entidade original no Akaunting. Para contatos, o módulo diferencia clientes de fornecedores; para linhas, avalia o tipo do documento pai. Uma listagem global de auditoria exige permissão de leitura de configurações.

## Segurança de integração

Use sempre HTTPS, autenticação com menor privilégio e seleção explícita da empresa. Não armazene credenciais em código, variáveis públicas ou screenshots. Respeite o rate limit nativo do Akaunting; respostas 429 são esperadas sob excesso de chamadas.