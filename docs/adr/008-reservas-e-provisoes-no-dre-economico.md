# ADR-008 — Reservas e provisões no DRE (resultado econômico)

- **Status:** aceito
- **Data:** 2026-07-19
- **Relaciona-se com:** seções 8 (Financeiro — plano de contas e `affects_cashflow`), 9.1/9.2 (DRE — estrutura e modos), 14.4 (banco de demonstração) do blueprint. Depende de ADR-006 (DRE por `vehicle_id`).

## Contexto

O dono de micro transportadora precisa saber o **custo econômico real** de rodar cada caminhão, não só o desembolso de caixa do período. A planilha de custos do setor (NTC&Logística / ANTT) trata isso como **reservas/provisões**: separa um valor por km (ou por mês) para cobrir gastos futuros lumpy (troca de pneu, troca de óleo, imprevistos) e imputa uma remuneração justa de mão de obra (motorista e pró-labore do dono) mesmo quando o dono dirige sem folha.

O DRE atual (ADR-006) agrega **apenas** `financial_entries` com `affects_cashflow = true` — ou seja, ignora qualquer valor não-caixa (há teste garantindo isso). Reserva é, por definição, não-caixa. Além disso, pneu (3.5), lubrificantes (3.6), salário (4.1) e pró-labore (5.1) já existem como categorias lançáveis; somar a reserva **e** a compra real no mesmo resultado dobra o custo.

## Decisão

1. **Dois resultados no DRE.** Mantém-se o **resultado de caixa** (agregação atual de `financial_entries`, intacta) e acrescenta-se um **resultado econômico** = resultado de caixa − reservas. As reservas **não se misturam** com as compras reais; são uma camada à parte.

2. **Reserva é camada calculada, fora de `financial_entries`.** As reservas **não** viram lançamento e **não** entram no fluxo de caixa. São calculadas a cada montagem do DRE a partir de parâmetros do veículo. Assim a regra 7 (uma única superfície de agregação de caixa) e o DRE de caixa continuam idênticos.

3. **Parâmetros por veículo, com padrão da empresa.** Tabela `vehicle_cost_parameters`: uma linha com `vehicle_id` nulo é o **padrão da empresa**; uma linha por veículo **sobrescreve** campo a campo (campo nulo herda o padrão; ausência dos dois = zero). Reservas em `decimal(10,4)` R$/km (fração de centavo por km, mesma lógica de preço/litro da regra 1); salário em `_cents`; pró-labore em `decimal(5,2)` percentual.

4. **Bases de cálculo** (confirmadas com o cliente — revisão de 2026-07-19):
   - **Reserva de óleo** = R$/km × km rodados no período.
   - **Reserva de pneus** = R$/km × km rodados.
   - **Reserva prudencial** = R$/km × km rodados.
   - **Salário do motorista (provisão)** = R$/mês × meses do período.
   - **Retirada / pró-labore do dono** = % da receita líquida (só quando positiva — não se retira sobre prejuízo).

   As três reservas por km passaram de "preço ÷ vida útil" e "% da receita" para **R$/km direto** — é como a planilha do setor (NTC) publica o custo de reposição, e casa com o print de referência do cliente (0,0700 / 0,2513 / 0,2000). O pró-labore passou de R$/mês para **% da receita líquida** (retirada proporcional ao faturamento).

5. **Km do período vem do hodômetro (híbrido), não do tanque cheio.** A distância que alimenta reservas, R$/km, breakeven e o rateio `by_km` é calculada de snapshots de odômetro reunidos de abastecimentos, manutenções e leituras manuais (`vehicle_odometer_readings`): `km = (última leitura ≤ fim) − (última leitura < início)`. Sem leitura anterior ao período, usa a menor do período (subestima e sinaliza); menos de duas leituras → km desconhecido (não zero). O **consumo (km/l)** continua saindo só de intervalos de tanque cheio fechados (regra 8) — distância e consumo são conceitos separados. Meses são fração de dias por mês do calendário (julho inteiro = 1,0; 1–15/jul = 15/31), sem "mês médio".

6. **Provisão de salário/pró-labore é sempre imputada.** Independe de haver lançamento manual de 4.1/5.1. Consequência assumida: se o veículo tiver salário lançado **e** provisão configurada, o resultado econômico conta os dois. A tela avisa. (Alternativa "imputar só o que faltar" fica registrada como evolução possível.)

## Consequências

- **`DreBuilder`** ganha, por veículo e no consolidado, o bloco `reserves` (óleo, pneus, prudencial, salário, pró-labore, total — todos em centavos negativos), o subtotal `result_before_reserves_cents` (caixa − salário − pró-labore) e o `economic_result_cents`. A distância vem do novo `loadDistanceByVehicle` (hodômetro). O cálculo puro vive em `App\Domain\Reports\Reserves\VehicleReservesCalculator` (coberto por teste, regra 10).
- **UI:** o DRE individual segue um fluxo top-down (receita → deduções → custos → resultado de caixa → salário/pró-labore → resultado antes das reservas → reservas por km → **= RESULTADO ECONÔMICO** com selo "Viável"/"Inviável"), com drill-down explicando cada fórmula; o comparativo tem a coluna "Econômico". Tela "Parâmetros de custo" (Análise) edita o padrão da empresa e os overrides por veículo (R$/km + pró-labore %). A tela do veículo registra leituras de hodômetro.
- **Não-destrutivo e reversível.** Sem parâmetros, `reserves.total_cents = 0` e o resultado econômico é igual ao de caixa — nada muda para quem não usar. A troca de "imputar sempre" por "imputar o que faltar" é aditiva.
- O banco de demonstração (14.4) deve semear parâmetros realistas, incluindo um veículo com override.
