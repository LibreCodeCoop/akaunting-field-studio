<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Segurança

## Como relatar uma vulnerabilidade

Envie uma **denúncia privada** pelo recurso [Report a vulnerability](https://github.com/LibreCodeCoop/akaunting-field-studio/security/advisories/new) do repositório GitHub. Não divulgue detalhes exploráveis em uma issue pública.

Inclua a versão do Field Studio, do Akaunting, do PHP e do banco, os passos de reprodução com registros sintéticos e o impacto. Não compartilhe credenciais nem dumps de dados financeiros.

A manutenção e os prazos de resposta dependem da disponibilidade dos mantenedores e não representam garantia de SLA.

## Modelo de segurança

O módulo compartilha autenticação, sessões, permissões e isolamento multiempresa do Akaunting. A camada Field Studio precisa impedir referências entre empresas, aplicar a ACL do registro de origem e impedir gravação parcial após erros de validação. O ambiente de produção deve possuir backups recuperáveis, HTTPS e atualizações de segurança.