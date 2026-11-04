<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Field Studio for Akaunting

**O jeito de trabalhar da sua organização não precisa caber em um formulário engessado.**

O **Field Studio** é uma extensão de software livre desenvolvida pela [LibreCode](https://github.com/LibreCodeCoop) para personalizar os dados do [Akaunting](https://akaunting.com) sem modificar seu código-fonte nem comprometer os lançamentos contábeis.

Centro de custo, número de contrato, projeto, referência externa, competência gerencial ou responsável pelo documento: cada organização pode registrar as informações de que realmente precisa.

*English: Field Studio is an independent, open-source Akaunting extension that adds customizable, company-scoped metadata fields to financial and administrative records, with an authenticated REST API.*

## Por que usar?

| Necessidade | Como o Field Studio ajuda |
| --- | --- |
| Cada área precisa registrar dados próprios | Criação de campos sem desenvolvimento de código |
| Informações preenchidas de maneira inconsistente | Campos obrigatórios, opções de escolha e validação por tipo |
| Empresas com regras operacionais distintas | Definições e valores isolados por empresa |
| Integração com CRM e sistemas externos | API REST completa para configurações e valores |
| Histórico confiável de alterações | Auditoria de modificações e arquivamento sem perda de registros |
| Independência de fornecedor | Código licenciado sob AGPL, sem dependência de um aplicativo proprietário |

O módulo atende cooperativas, prestadoras de serviços, associações, equipes financeiras e organizações que integram o Akaunting a outros sistemas.

**Importante:** campos personalizados não alteram automaticamente a emissão fiscal, os cálculos de impostos ou os valores contábeis.

## Funcionalidades

- **Autonomia:** cadastre, edite, ative, desative, ordene e arquive campos em **Configurações → Field Studio**.
- **14 tipos de campo:** texto curto e longo, e-mail, URL, inteiro, decimal exato, percentual, data, horário, data e horário, sim/não, seleção simples, seleção múltipla e referência a contatos.
- **Formulários mais úteis:** nomes claros, textos de ajuda, valores iniciais, preenchimento obrigatório, visibilidade e largura configurável.
- **Integração com Akaunting:** campos editáveis nos formulários nativos de faturas e contas a pagar; editores vinculados a transações, contatos, produtos e itens de documentos.
- **API-first:** operações de criação, leitura, atualização e arquivamento de definições; operações completas de gravação, consulta, substituição, filtragem e exclusão de valores.
- **Controle e segurança:** permissões do Akaunting, isolamento por empresa, validação de referências, controle de concorrência e trilha de auditoria.
- **Dados preservados:** tabelas independentes; o arquivamento de um campo não apaga seus valores históricos.

## Exemplo de aplicação

Uma organização pode criar o campo **Centro de custo** para faturas, com as opções **Operações**, **Comercial** e **Administração**, exigindo a seleção no preenchimento. Essa informação passa a acompanhar cada fatura e pode ser consultada pela API, sem alterar seu total financeiro.

## Compatibilidade

| Componente | Validação realizada |
| --- | --- |
| Akaunting | 3.2.4 |
| Laravel | 10.x |
| PHP | 8.3 |
| Banco de dados | MySQL 8.x |
| Interface | Blade, Tailwind e formulários Vue nativos do Akaunting |
| Licença | GNU AGPL-3.0-or-later |

Outras versões e bancos de dados exigem testes próprios. Field Studio é um projeto independente, não desenvolvido nem endossado pela Akaunting Inc.

## Como instalar

Faça backup da aplicação e do banco de dados do Akaunting antes de instalar módulos novos.

1. Copie este repositório para a pasta **modules/FieldStudio** da instalação do Akaunting.
2. No diretório da aplicação, execute:

~~~sh
php artisan module:migrate field-studio --force
php artisan module:install field-studio 1 pt-BR
php artisan optimize:clear
php artisan module:list
~~~

Substitua o número **1** pelo identificador da empresa escolhida. Instale separadamente nas empresas autorizadas que precisarão do módulo.

3. Entre no Akaunting, selecione a empresa e abra **Configurações → Field Studio**.

As tabelas são **lcc_fieldstudio_definitions**, **lcc_fieldstudio_values** e **lcc_fieldstudio_audits**, acrescidas do prefixo padrão configurado no Akaunting. O módulo não modifica tabelas do aplicativo comercial Custom Fields.

## Integração por API

O endpoint base é:

~~~text
/api/v1/field-studio
~~~

Uma consulta autenticada às definições pode ser feita com:

~~~sh
curl -u admin@example.invalid \
  -H 'Accept: application/json' \
  -H 'X-Company: 1' \
  https://your-akaunting.example/api/v1/field-studio/definitions
~~~

O exemplo utiliza credenciais sintéticas. Em instalações reais, use HTTPS e uma conta com as permissões adequadas.

Consulte a [referência da API](docs/api.md) para métodos HTTP, exemplos de payloads, validação e controle de concorrência.

## Qualidade e segurança

Existem testes automatizados de CRUD, tipos de dados, erros de validação, permissões, isolamento por empresa e integração com o formulário de faturas. Os testes de ponta a ponta recusam executar mutações em endereços não locais.

Os procedimentos estão em [desenvolvimento e testes](docs/development.md) e [operação e reversão](docs/operations.md). Vulnerabilidades devem ser relatadas pelos [avisos privados de segurança](https://github.com/LibreCodeCoop/akaunting-field-studio/security/advisories), não em issues públicas.

## Suporte e colaboração

O Field Studio é software livre. Organizações podem hospedá-lo, utilizá-lo e adaptá-lo, enquanto implantação, integração, treinamento e manutenção podem ser contratados separadamente com o fornecedor de sua escolha.

Contribuições são bem-vindas conforme o [guia de contribuição](CONTRIBUTING.md). Dúvidas e propostas podem ser registradas no [rastreador de issues](https://github.com/LibreCodeCoop/akaunting-field-studio/issues).

**Licença:** [GNU Affero General Public License v3 ou posterior](LICENSE). Copyright © 2026 LibreCode Coop e contribuidores.