<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Desenvolvimento e testes

Field Studio é um módulo PHP do Akaunting. Mantém persistência própria, rotas REST e formulários Blade integrados ao frontend nativo, sem recompilar o core.

## Requisitos

- Akaunting 3.2.4 com Laravel 10.x, PHP 8.3 e MySQL 8.x.
- Ambiente **descartável** isolado da produção, com empresa e documentos sintéticos.
- Node.js com Playwright e Chromium/Google Chrome para testes de navegador.
- Uma conta administradora e, opcionalmente, uma conta com permissão apenas de leitura de faturas.

Instalar o módulo no diretório \`modules/FieldStudio\` da aplicação e executar \`php artisan module:migrate field-studio --force\`, \`php artisan module:install field-studio 1 pt-BR\`, \`php artisan optimize:clear\`. Confirmar \`module:list\` e \`route:list --path=field-studio\`.

Atenção: alguns ambientes do Akaunting têm limites de plano para URLs terminadas em \`/create\`. O Field Studio utiliza \`/{company_id}/field-studio/new\` para evitar colisões. Esse desvio **não** altera licença nem limites do Akaunting. Os formulários de faturas usam a pilha \`category_start\` e o \`v-model\` do gerenciador de formulários Vue nativo.

## Executar testes reais

Os testes E2E exigem registros sintéticos previamente inseridos no banco descartável:

| Variável | Exemplo | Finalidade |
| --- | --- | --- |
| FS_BASE_URL | http://127.0.0.1:18086 | URL local, nunca produção |
| FS_ADMIN_EMAIL | admin@example.invalid | E-mail sintético do administrador |
| FS_ADMIN_PASSWORD_FILE | /tmp/test-admin-password | Caminho protegido, não versionar |
| FS_PLAYWRIGHT_PATH | /opt/test/node_modules/playwright | Dependência Playwright existente |
| FS_CHROME_PATH | /usr/bin/google-chrome | Executável Chromium/Chrome |
| FS_TEST_COMPANY_ID | 1 | Empresa sintética |
| FS_TEST_INVOICE_ID | 1 | Fatura sintética |
| FS_TEST_BILL_ID | 3 | Conta sintética |
| FS_TEST_CONTACT_ID | 1 | Cliente sintético |
| FS_VENDOR_ID | 3 | Fornecedor sintético |
| FS_TEST_ITEM_ID | 1 | Produto sintético |
| FS_TEST_LINE_ID | 1 | Linha de fatura sintética |
| FS_TEST_TRANSACTION_ID | 1 | Recebimento sintético |

~~~sh
export FS_BASE_URL=http://127.0.0.1:18086
export FS_PLAYWRIGHT_PATH=/opt/test/node_modules/playwright
export FS_ADMIN_PASSWORD_FILE=/tmp/test-admin-password
node tests/api-e2e.js
node tests/ui-e2e.js
~~~

Opcionalmente configure \`FS_VIEWER_EMAIL\` e \`FS_VIEWER_PASSWORD_FILE\` para testar negação de escrita e leitura segregada de fornecedores. Os scripts recusam executar mutações contra host que não seja localhost. O teste de API cria definições com prefixo sintético e as arquiva no fim.

Para a interface, \`FS_SCREENSHOT_DIR\` permite guardar capturas em **diretório não versionado**, com dados sintéticos. Não inclua prints pessoais ou de produção em commits.

## Verificações estáticas

~~~sh
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
find tests -type f -name '*.js' -print0 | xargs -0 -n1 node --check
php -r 'json_decode(file_get_contents("module.json"), true, 512, JSON_THROW_ON_ERROR); echo "module.json OK\n";'
php -r 'json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR); echo "composer.json OK\n";'
reuse lint
git diff --check
~~~

Os testes HTTP por si só não certificam todos os caminhos de um upgrade. Não declare suporte a novas versões do Akaunting sem regressão real na versão de destino.

## Arquitetura

- \`Providers/Main.php\`: bootstrap, rotas, views, traduções e menu nativo de Settings via \`SettingsCreated\`.
- \`Domain/Fields.php\`: definições, tipos, valores, transações, filtros e auditoria.
- \`Domain/Access.php\`: permissões por tipo e registro.
- \`Domain/NativeHooks.php\`: integração com formulário Vue/Blade nativo.
- \`Http/Controllers/ApiController.php\`: API autenticada com semântica REST.
- \`Http/Controllers/AdminController.php\`: interface administrativa; arquivos Blade.
- \`Database/Migrations\`: tabelas exclusivas e índices do módulo.

Nenhuma tabela comercial \`custom_fields\` é modificada. Não existe migração automática de dados de aplicações legadas. Questões fiscais ou cálculos contábeis permanecem fora do módulo.