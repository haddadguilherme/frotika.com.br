---
name: 'Migrations'
description: 'Convenções de schema do Frotika'
applyTo: 'database/migrations/**,database/factories/**,database/seeders/**'
---

# Schema no Frotika

- MySQL 8 (`utf8mb4` / `utf8mb4_0900_ai_ci`). Pode usar `json`, CTE recursiva e window functions. **Não** há índice único parcial.
- Toda tabela de cadastro ou lançamento tem `deleted_at` (soft delete).
- Toda tabela com dado de empresa tem `company_id` com FK e índice — **e o índice composto começa por `company_id`**. Motivo: toda query passa pelo global scope de tenant; índice que não começa por `company_id` não é usado.
- Valor monetário: `bigInteger` em centavos, sufixo `_cents`. Preço unitário: `decimal(10,3)`.
- Enum como `string` + cast para enum PHP. Não use o tipo enum nativo do banco. Motivo: adicionar um valor não exige migration.
- `timestamp` (use `->useCurrent()` / precisão quando necessário) para instante (`issued_at`, `fueled_at`), `date` para data de negócio (`competence_date`, `paid_at`).

Unicidade condicional: MySQL não tem índice único parcial. Use coluna gerada que só recebe valor quando a condição vale + índice único sobre ela, e reforce na Action dentro de transação:

```php
DB::statement("ALTER TABLE bank_accounts
    ADD COLUMN default_flag TINYINT
      GENERATED ALWAYS AS (CASE WHEN is_default = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END) VIRTUAL,
    ADD UNIQUE KEY uq_bank_accounts_default (company_id, default_flag)");
```

Antes de alterar tabela existente: verifique se a migration original já foi para produção. Se não foi, edite a original em vez de criar uma nova.

Factory de model com `BelongsToCompany` não define `company_id` — a trait resolve pelo `TenantContext`. Nos testes, abra o contexto com `TenantContext::runFor()`.

Ver seção 5 de [docs/frotika-blueprint.md](../../docs/frotika-blueprint.md) para o schema completo.
