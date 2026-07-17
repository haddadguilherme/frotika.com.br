# ADR-001 — Banco de dados: MySQL 8 (substitui PostgreSQL 16)

- **Status:** aceito
- **Data:** 2026-07-17
- **Substitui:** ADR-003 original do blueprint (PostgreSQL 16)

## Contexto

O blueprint fixava PostgreSQL 16 (ADR-003) por precisão decimal, `jsonb`, CTEs recursivas e window functions. A infraestrutura de hospedagem alvo e a familiaridade da equipe apontam para MySQL como banco padrão. O ambiente de execução (Docker) também foi montado com MySQL 8.

## Decisão

Adotar **MySQL 8** como banco de dados oficial do Frotika.

Os requisitos que motivavam o Postgres continuam atendidos:

- **Precisão monetária** já não depende do banco: valores são `bigInteger` em centavos (ADR-004), preços fracionários usam `decimal(10,3)`.
- **JSON**: tipo `JSON` nativo do MySQL 8 (formato binário), indexável via colunas geradas + índice funcional.
- **CTEs recursivas** e **window functions**: suportadas a partir do MySQL 8.

## Consequências

- **Índice único parcial não existe no MySQL.** Regras como "uma única conta `is_default = true` por empresa" passam a usar coluna gerada (`NULL` quando não se aplica) + índice único, reforçadas na Action dentro de transação. Ver seção 5.6 do blueprint.
- Colunas antes descritas como `jsonb` são `json`.
- Charset/collation padrão: `utf8mb4` / `utf8mb4_0900_ai_ci`.
- Driver: `pdo_mysql`. Conexão `DB_CONNECTION=mysql` no `.env`.
- Testes de tenancy/domínio rodam contra MySQL 8 quando exigirem schema real.

Reverter para PostgreSQL exige um novo ADR.
