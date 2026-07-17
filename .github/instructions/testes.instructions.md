---
name: 'Testes'
description: 'PHPUnit no Frotika — o que é obrigatório testar'
applyTo: 'tests/**'
---

# Testes no Frotika

PHPUnit 12. **Não use Pest** — o esqueleto veio com PHPUnit e trocar não agrega.

## Obrigatório

- Toda Action de domínio tem teste. Sem exceção.
- `DreBuilder` e `CteParser`: cobertura próxima de 100%. São as duas peças onde um bug vira dinheiro errado na tela de um cliente.
- Todo model com `BelongsToCompany` tem um teste de isolamento: empresa A não enxerga dado de B.

## Casos que não podem faltar

- **Rateio:** R$ 1.000,00 ÷ 3 → `[33333, 33333, 33334]`, soma exata. Também ÷ 7, ÷ 1, ÷ 0.
- **Consumo:** sequência com tanque parcial no meio · sem `full_tank` anterior → `null` · Arla não entra · odômetro regressivo bloqueia.
- **CT-e:** cada fixture de `tests/Fixtures/Cte/` asserta o `CteData` campo a campo · reimport é idempotente · anulação cancela o lançamento.
- **Saldo:** cache de `current_balance_cents` bate com o recálculo.
- **DRE:** período sem dado retorna zerado, não erro.

## Contexto de tenant

```php
$this->tenant->runFor($company, function () {
    Fueling::factory()->count(3)->create();
    // ...
});
```

Factory não define `company_id` — a trait resolve pelo contexto. Teste que cria model fora de `runFor()` deve falhar com `MissingTenantContextException`; isso é comportamento esperado, não bug.

## Estilo

- Nome do teste em português, descrevendo o comportamento: `public function test_consumo_ignora_abastecimento_de_arla(): void`.
- Um comportamento por teste.
- Sem mock de Eloquent. Use banco de teste real (SQLite em memória não serve: o projeto usa recursos do MySQL 8, como `json`, colunas geradas, CTE recursiva e window functions).
