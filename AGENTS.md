# Frotika — instruções do projeto

Sistema de gestão para micro transportadoras. O produto é o **DRE Veicular**: o dono descobre se cada caminhão dá lucro. Todo o resto existe para alimentar esse relatório.

Especificação completa: [docs/frotika-blueprint.md](docs/frotika-blueprint.md). Leia a seção relevante antes de implementar um módulo.

## Stack

Laravel 13.8 · PHP 8.3 · MySQL 8 · Livewire · Tailwind CSS v4 · PHPUnit 12.
Sem Inertia, sem React, sem Vue. Sem Pest — o projeto usa PHPUnit.

## Comandos

```bash
composer setup                  # instalação inicial
composer dev                    # servidor + queue + logs + vite
composer test                   # config:clear + artisan test
vendor/bin/pint                 # formatação (rodar antes de terminar)
vendor/bin/phpstan analyse      # Larastan nível 6
php artisan frotika:demo --fresh    # banco de demonstração com dado realista
```

Rode `composer test` e `vendor/bin/pint` antes de considerar qualquer tarefa concluída.

## Regras invioláveis

Cada regra abaixo tem um motivo. Violar qualquer uma delas produz número errado na tela de um cliente.

1. **Dinheiro é `bigInteger` em centavos, nunca `float` nem `decimal`.** Colunas terminam em `_cents`. Preço unitário com fração (R$/litro) usa `decimal(10,3)`. Motivo: o produto final é um demonstrativo financeiro; ponto flutuante acumula erro de arredondamento.

2. **Rateio usa distribuição por maior resto.** A soma das partes tem que ser exatamente igual ao todo. Use `App\Support\Money\Apportionment`. Motivo: R$ 1.000,00 ÷ 3 com arredondamento ingênuo perde 1 centavo, e o DRE consolidado deixa de bater com o fluxo de caixa.

3. **Todo model com dado de empresa usa a trait `BelongsToCompany`.** Sem exceção. Motivo: é o único mecanismo de isolamento entre clientes; um model sem a trait vaza dado de uma transportadora para outra.

4. **`withoutGlobalScope(CompanyScope::class)` só é permitido dentro de `app/Platform/**`.** Motivo: fora do painel da plataforma, remover o escopo é sempre bug, nunca intenção.

5. **Jobs recebem `company_id` explícito no construtor e abrem o contexto com `TenantContext::runFor()`.** Motivo: o `TenantContext` não é serializado na fila; um job sem isso roda sem tenant e grava no lugar errado.

6. **Todo lançamento financeiro tem duas datas: `competence_date` (alimenta o DRE) e `paid_at` (alimenta o fluxo de caixa).** Nunca derive uma da outra. Motivo: são perguntas diferentes — "estou lucrando?" e "tenho dinheiro?".

7. **Relatório nunca soma direto de `fuelings`, `maintenances` ou `cte_documents`.** Essas tabelas geram `financial_entries` via `EntrySynchronizer`, e o relatório agrega só `financial_entries`. Motivo: uma única superfície de agregação, um único ponto de teste.

8. **Consumo (km/l) só é calculado entre dois abastecimentos com `full_tank = true`.** Arla 32 e óleo nunca entram no cálculo. Sem `full_tank` anterior → `null`, não zero. Motivo: tanque parcial não fecha o intervalo, e misturar Arla distorce o km/l em ~5%.

9. **Nunca invente o layout do CT-e.** Antes de tocar em `app/Domain/Trips/Cte/**`, leia a seção 7 do blueprint e valide contra os XMLs reais em `tests/Fixtures/Cte/`. Motivo: `tpMed` é texto livre, o grupo de ICMS varia, e `vRec ≠ vTPrest`.

10. **Nenhuma Action de domínio entra em `main` sem teste.** `DreBuilder` e `CteParser` exigem cobertura próxima de 100%. Motivo: são as duas peças onde um bug vira dinheiro errado.

## Convenções

- `declare(strict_types=1);` em todo arquivo PHP.
- Código, tabelas e valores de enum em **inglês**. Interface e URLs em **pt-BR**.
- Enum nativo backed por string para todo campo enum, com método `label(): string` retornando o rótulo pt-BR. Nunca `->value` direto na Blade.
- Toda mutação passa por uma **Action** de método único em `app/Domain/{Módulo}/Actions/`. Livewire valida, chama a Action, trata o resultado — não contém regra de negócio.
- `final` por padrão em Action, Service e DTO. `readonly` em DTO.
- `Gate::authorize` dentro da Action, não só na UI.
- Formatação pt-BR só via `App\Support\Format` (`Format::money`, `Format::plate`, `Format::km`...). Nunca `number_format` solto.
- Glossário pt-BR ↔ código: seção 17 do blueprint. `cavalo` = `VehicleType::Tractor`, `carreta` = `SemiTrailer`, `viagem` = `Trip`, `abastecimento` = `Fueling`, `lançamento` = `FinancialEntry`.

## Interface

Trabalho de UI **exige** carregar a skill `.github/skills/frotika-ui/`. Ela tem a direção visual, os tokens exatos, a anatomia dos componentes, a especificação mobile e o processo de crítica.

A tese: **painel de instrumentos, não dashboard.** A referência é a cabine do caminhão e a ficha da oficina — densidade alta, filete em vez de sombra, número grande, zero ornamento. Se a tela pudesse ser o admin de um e-commerce trocando as palavras, está errada.

**O uso principal é desktop** — é lá que se concilia, importa, audita e decide. Otimize para o milésimo clique, não para o primeiro. Mobile é o complemento: o motorista lançando no posto.

Não negociável: zero sombra fora de overlay · raio máximo 8px · **linha de tabela 36px no desktop** (44 é alvo de toque, não medida de tela; meta dura ≥16 linhas em 1440×900) · todo número `font-mono tabular text-right` · cor só quando codifica um fato · um acento por tela · nunca hex na Blade.

**A tabela é o produto.** 80% do tempo de tela é nela: cabeçalho sticky, ordenação por clique, seleção em lote, edição inline, teclado, totais sticky. Clicar na linha abre **master-detail** de 480px — a lista encolhe, não some. Auditar 20 lançamentos não pode custar 40 navegações.

**Mobile é IA diferente, não media query:** bottom nav, ação no polegar, tabela vira card, input de 16px. Toda tela é planejada nos dois tamanhos antes de existir.

## Estrutura

```
app/Domain/{Tenancy,Fleet,Trips,Fuelings,Maintenances,Finance,Reports,Billing}/
    Models/ Actions/ Data/ Enums/ Policies/ Events/
app/Platform/          painel do dono do sistema — único lugar sem global scope
app/Livewire/          componentes, sem regra de negócio
app/Support/Tenancy/   TenantContext, CompanyScope, BelongsToCompany
app/Support/Money/     Money, Apportionment
resources/views/components/ui/    design system
docs/frotika-blueprint.md         especificação
docs/adr/                         decisões de arquitetura
```

## Fluxo de trabalho

1. Leia a seção do blueprint referente à tarefa antes de escrever código.
2. Em mudança de mais de 2 arquivos, apresente o plano e espere aprovação.
3. Escreva o teste antes da implementação quando a tarefa envolver cálculo (dinheiro, consumo, rateio, DRE).
4. Rode `composer test` e `vendor/bin/pint` ao final.
5. Commit em Conventional Commits, em português: `feat(fleet): cadastro de composições`.

## Não faça

- Não instale pacote novo sem perguntar. As dependências estão fixadas na seção 2.1 do blueprint.
- Não crie migration alterando tabela existente antes de checar se a original ainda não foi para produção.
- Não use `localStorage` para estado de domínio — só preferência de UI, e o preferido é `users.preferences`.
- Não gere seeder com lorem ipsum. Dado de demonstração é realista (seção 14.4), inclusive um veículo com resultado negativo.
- Não altere `composer.json` para remover `laravel/pao` — ele reduz o ruído da saída do Artisan e do PHPUnit nesta sessão.

## Quando perguntar em vez de decidir

A seção 18 do blueprint lista o que está em aberto: layout do CT-e, gateway de pagamento, preços dos planos, tratamento de financiamento no DRE, consolidação multi-empresa. Se a tarefa esbarrar em um desses, **pergunte**. Palpite em regra financeira vira número errado na tela do cliente.

Ao decidir algo não previsto: registre um ADR em `docs/adr/NNN-titulo.md` e atualize o blueprint.
