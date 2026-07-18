# Frotika

Sistema de gestão para micro transportadoras com foco em resultado por veículo.
O produto central é o DRE Veicular: a operação transforma eventos de viagem, abastecimento,
manutenção e financeiro em visão de lucro por caminhão, competência e caixa.

## Sumário

- [Visão do Produto](#visão-do-produto)
- [Fontes Canônicas](#fontes-canônicas)
- [Stack e Requisitos](#stack-e-requisitos)
- [Setup Rápido](#setup-rápido)
- [Comandos de Desenvolvimento](#comandos-de-desenvolvimento)
- [Arquitetura e Estrutura](#arquitetura-e-estrutura)
- [Regras Invioláveis](#regras-invioláveis)
- [Convenções de Código](#convenções-de-código)
- [Fluxo de Trabalho](#fluxo-de-trabalho)
- [Documentação Relacionada](#documentação-relacionada)
- [Licença](#licença)

## Visão do Produto

O Frotika existe para responder duas perguntas com precisão:

1. Estou lucrando por veículo? (DRE por competência)
2. Tenho dinheiro no caixa? (fluxo por pagamento)

Para isso, o sistema separa claramente competência e pagamento, usa centavos inteiros para
dinheiro e consolida números financeiros em uma única superfície de agregação.

### Capacidades principais

- Gestão multiempresa com isolamento por tenant
- Operação de frota (veículos, abastecimentos e manutenções)
- Importação de CT-e e geração de lançamentos financeiros
- DRE Veicular e fluxo de caixa com visão operacional
- Plataforma administrativa para gestão do produto SaaS

## Fontes Canônicas

Antes de implementar qualquer módulo, leia as fontes abaixo nesta ordem:

1. [AGENTS.md](AGENTS.md) (regra oficial de desenvolvimento e domínio)
2. [docs/frotika-blueprint.md](docs/frotika-blueprint.md) (especificação funcional e técnica)
3. [docs/adr](docs/adr) (decisões arquiteturais registradas)

Este README é um guia operacional. Em caso de conflito, prevalecem AGENTS e blueprint.

## Stack e Requisitos

### Stack principal

- PHP 8.3
- Laravel 13.8
- Livewire 4.3
- Tailwind CSS v4
- MySQL 8
- PHPUnit 12

### Requisitos locais

- PHP 8.3 com extensões exigidas pelo Laravel
- Composer 2
- Node.js 20+ e npm
- MySQL 8 disponível para a aplicação

## Setup Rápido

### 1) Instalação inicial

```bash
composer setup
```

Esse script executa:

- instalação de dependências PHP
- criação de arquivo .env (se ausente)
- geração da chave da aplicação
- execução de migrações
- instalação de dependências Node
- build de assets de frontend

### 2) Subir ambiente de desenvolvimento

```bash
composer dev
```

Esse comando sobe, em paralelo:

- servidor HTTP da aplicação
- worker de filas
- painel de logs (pail)
- websocket/realtime (reverb)
- vite em modo de desenvolvimento

## Comandos de Desenvolvimento

Comandos principais do dia a dia:

```bash
composer dev
composer test
vendor/bin/pint
vendor/bin/phpstan analyse
php artisan frotika:demo --fresh
```

Observações:

- Scripts e variações ficam em [composer.json](composer.json)
- Rode testes e formatação antes de concluir qualquer tarefa
- Evite reset destrutivo de schema sem alinhamento prévio

## Arquitetura e Estrutura

O projeto usa organização por domínio para preservar regras de negócio próximas da implementação.

```text
app/
	Domain/
		Billing/
		Finance/
		Fleet/
		Fuelings/
		Maintenances/
		Partners/
		Tenancy/
		Trips/
	Livewire/
	Platform/
	Support/
		Money/
		Tenancy/
database/
	migrations/
	factories/
	seeders/
docs/
	adr/
	development-log.md
	frotika-blueprint.md
resources/
	css/
	js/
	views/
tests/
	Feature/
	Unit/
```

### Princípios de arquitetura

- Mutações de domínio devem passar por Actions
- UI valida, chama Action e apresenta resultado
- Relatórios financeiros agregam a partir de financial_entries
- Contexto de tenant é obrigatório para escrita de dados multiempresa

## Regras Invioláveis

Resumo das regras que não podem ser quebradas:

1. Dinheiro em centavos (bigInteger), sem float/decimal para totais monetários
2. Rateio por maior resto para manter soma exata
3. Models de empresa com trait de tenant obrigatória
4. Remoção de scope de tenant apenas no módulo de plataforma
5. Jobs com company_id explícito e execução em TenantContext
6. Lançamento financeiro com competence_date e paid_at separados
7. DRE e relatórios não somam direto de tabelas operacionais
8. Consumo km/l apenas entre abastecimentos de tanque cheio
9. Parser/importação de CT-e sempre validado contra fixtures reais
10. Action de domínio sem teste não deve entrar em main

Detalhes completos em [AGENTS.md](AGENTS.md).

## Convenções de Código

- declare(strict_types=1); em arquivos PHP
- Código e enums em inglês
- Interface e rotas voltadas ao usuário em pt-BR
- Enums backed por string com método label() para exibição
- Formatação de apresentação via utilitários de suporte (não formatar ad hoc na view)
- Sem regra de negócio dentro de componentes de interface

## Fluxo de Trabalho

Processo recomendado para mudanças:

1. Ler a seção relevante do blueprint
2. Definir plano de implementação
3. Implementar seguindo convenções do domínio
4. Cobrir regras críticas com testes
5. Rodar validações locais
6. Registrar decisão arquitetural quando necessário

Checklist mínimo antes de concluir:

```bash
composer test
vendor/bin/pint
vendor/bin/phpstan analyse
```

## Documentação Relacionada

- Especificação completa: [docs/frotika-blueprint.md](docs/frotika-blueprint.md)
- Diário de evolução: [docs/development-log.md](docs/development-log.md)
- Decisões arquiteturais: [docs/adr](docs/adr)
- Guia de agentes: [docs/agentes.md](docs/agentes.md)

### ADRs disponíveis

- [docs/adr/001-mysql.md](docs/adr/001-mysql.md)
- [docs/adr/002-painel-plataforma-e-cobranca.md](docs/adr/002-painel-plataforma-e-cobranca.md)
- [docs/adr/003-licenca-por-grupo-e-cadastro-de-empresas.md](docs/adr/003-licenca-por-grupo-e-cadastro-de-empresas.md)
- [docs/adr/004-importacao-de-cte-e-parceiros.md](docs/adr/004-importacao-de-cte-e-parceiros.md)
- [docs/adr/005-cte-como-viagem-sem-entidade-trip.md](docs/adr/005-cte-como-viagem-sem-entidade-trip.md)
- [docs/adr/006-sem-composicao-de-veiculos-nem-vinculo-motorista-veiculo.md](docs/adr/006-sem-composicao-de-veiculos-nem-vinculo-motorista-veiculo.md)
- [docs/adr/007-observabilidade-filas-e-websocket.md](docs/adr/007-observabilidade-filas-e-websocket.md)

## Suporte Operacional

Quando uma tarefa envolver regra de negócio de dinheiro, tenancy, DRE ou CT-e, consulte primeiro:

1. [AGENTS.md](AGENTS.md)
2. [docs/frotika-blueprint.md](docs/frotika-blueprint.md)
3. ADR correspondente em [docs/adr](docs/adr)

Em caso de decisão ainda não formalizada, registre um novo ADR em docs/adr.

## Licença

Projeto licenciado sob MIT.
