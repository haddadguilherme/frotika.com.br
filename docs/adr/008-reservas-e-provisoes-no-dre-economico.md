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

3. **Parâmetros por veículo, com padrão da empresa.** Tabela `vehicle_cost_parameters`: uma linha com `vehicle_id` nulo é o **padrão da empresa**; uma linha por veículo **sobrescreve** campo a campo (campo nulo herda o padrão; ausência dos dois = zero). Dinheiro em `_cents` (regra 1).

4. **Bases de cálculo** (confirmadas com o cliente):
   - **Reserva de pneu** = preço do jogo ÷ vida útil (R$/km) × km rodados no período.
   - **Reserva de óleo** = custo da troca ÷ intervalo (R$/km) × km rodados.
   - **Reserva prudencial** = % da receita líquida (só quando positiva — não se reserva sobre prejuízo).
   - **Salário do motorista (provisão)** = R$/mês × meses do período.
   - **Pró-labore do dono (provisão)** = R$/mês × meses do período.

   O km usado é o mesmo do DRE (intervalos de tanque cheio fechados, regra 8); sem km, pneu e óleo ficam zerados. Meses são fração de dias por mês do calendário (julho inteiro = 1,0; 1–15/jul = 15/31), sem "mês médio".

5. **Provisão de salário/pró-labore é sempre imputada.** Independe de haver lançamento manual de 4.1/5.1. Consequência assumida: se o veículo tiver salário lançado **e** provisão configurada, o resultado econômico conta os dois. A tela avisa. (Alternativa "imputar só o que faltar" fica registrada como evolução possível.)

## Consequências

- **`DreBuilder`** ganha, por veículo e no consolidado, o bloco `reserves` (pneu, óleo, prudencial, salário, pró-labore, total — todos em centavos negativos) e `economic_result_cents`. O cálculo puro vive em `App\Domain\Reports\Reserves\VehicleReservesCalculator` (coberto por teste, regra 10).
- **UI:** o DRE individual mostra a seção "RESERVAS E PROVISÕES" e a linha "= RESULTADO ECONÔMICO" abaixo do resultado de caixa, com drill-down explicando cada fórmula; o comparativo ganha a coluna "Econômico". Tela "Parâmetros de custo" (Análise) edita o padrão da empresa e os overrides por veículo.
- **Não-destrutivo e reversível.** Sem parâmetros, `reserves.total_cents = 0` e o resultado econômico é igual ao de caixa — nada muda para quem não usar. A troca de "imputar sempre" por "imputar o que faltar" é aditiva.
- O banco de demonstração (14.4) deve semear parâmetros realistas, incluindo um veículo com override.
