<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Contribuição

A LibreCode acolhe correções e melhorias para o Field Studio.

## Como contribuir

Antes de abrir uma alteração, verifique se existe uma issue correspondente. Defina o comportamento esperado, os riscos de segurança e pelo menos um teste positivo e um teste negativo. Não inclua credenciais, informações privadas, dumps financeiros ou screenshots de clientes.

Trabalhe em branch dedicada e abra pull request para revisão humana. As alterações devem usar commits [Conventional Commits](https://www.conventionalcommits.org/) e DCO com trailer \`Signed-off-by\` correspondente ao autor real, além de assinatura criptográfica. Evite squash automático. Nunca force push em branches protegidas.

Teste a sintaxe PHP, a integridade dos arquivos JSON, os testes E2E do ambiente descartável e os checks de licenciamento antes de pedir revisão. Consulte [desenvolvimento](docs/development.md) para comandos.

## Como relatar vulnerabilidades

Não publique PoCs de segurança que exponham dados. Use o canal privado indicado em [SECURITY.md](SECURITY.md).