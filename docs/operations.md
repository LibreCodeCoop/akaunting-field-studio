<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Operação e reversão

## Antes de instalar

1. Identifique a versão do Akaunting, PHP, banco, o prefixo das tabelas e os módulos ativos. Verifique se \`modules/FieldStudio\` não contém código preexistente.
2. Faça backup consistente do banco e teste sua restauração em ambiente isolado. Faça snapshot dos arquivos existentes, especialmente \`.env\` e dos módulos.
3. Verifique regras de autenticação e escopo das empresas. Instale e habilite apenas para empresas autorizadas.
4. Execute o smoke de módulos e NFS-e no ambiente de homologação, pois uma migração pode depender de outra.
5. Use a revisão ou o release **assinado e aprovado**. Não utilize nomes de branches como substitutos de SHA verificado.

## Instalação

Copie o pacote verificado para \`modules/FieldStudio\`, sem sobrescrever a pasta \`modules/CustomFields\` caso exista. Execute os comandos nativos do Akaunting, substituindo o ID da empresa:

~~~sh
php artisan module:migrate field-studio --force
php artisan module:install field-studio 1 pt-BR
php artisan optimize:clear
php artisan module:list
php artisan route:list --path=field-studio
~~~

Após a instalação, abra **Settings → Field Studio** como administrador e confirme que as páginas são exibidas corretamente. Faça chamadas GET na API autenticada. Em produção, não crie notas ou transações fictícias para smoke.

## Reversão sem perda de dados

Se houver incompatibilidade, **desabilite o módulo na empresa**:

~~~sh
php artisan module:disable field-studio 1 pt-BR
php artisan optimize:clear
~~~

Os registros dos campos permanecem nas tabelas \`lcc_fieldstudio_definitions\`, \`lcc_fieldstudio_values\` e \`lcc_fieldstudio_audits\` com o prefixo do Akaunting. A migração \`down\` foi deliberadamente não destrutiva. **Não execute \`migrate:fresh\`, \`DROP TABLE\` nem \`db:wipe\`**. Restaure o pacote de arquivos anterior somente após confirmar o escopo da reversão. A exclusão definitiva de metadados requer inventário, exportação e aprovação específica.

## Restrições

- A API segue as permissões e o limite de requisições da aplicação hospedeira.
- A instalação em uma empresa não atribui automaticamente acesso a outra.
- O Field Studio não modifica o imposto, o total financeiro ou a transmissão de NFS-e.
- O suporte a outras versões do Akaunting depende de testes separados.