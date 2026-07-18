# ADR-005 — CT-e é a viagem: sem entidade `Trip` no MVP

- **Status:** aceito
- **Data:** 2026-07-18
- **Relaciona-se com:** seções 5.3 (Viagens e CT-e), 6.3 (EntrySynchronizer), 15 (roadmap — Fase 3) e o glossário (seção 17) do blueprint. Estende o item 6 das consequências do ADR-004.

## Contexto

O blueprint modela **Viagem** (`trips`) e **CT-e** (`cte_documents`) como entidades distintas ligadas por `trip_cte_document` (N CT-es por viagem; um CT-e em no máximo uma viagem). A viagem seria a unidade operacional (veículo, motorista, rota, `odometer_start/end`, `distance_km`, `empty_km`, peso), e a receita da viagem, a soma dos CT-es.

Na prática das transportadoras atendidas (1 a 15 veículos, agregadas), **um CT-e corresponde a um deslocamento** e o dado que alimenta o financeiro já vem inteiro do XML. A entidade `Trip` agregaria pouco no curto prazo e cobraria um custo real: mais uma tela para o operador preencher, mais um ponto onde o dado fica "incompleto" ("N CT-es sem viagem"). O ADR-004 já havia adiado o model `Trip` ("o CT-e é a viagem e o `vehicle_id` vai direto no lançamento").

## Decisão

1. **Não existe entidade `Trip` no MVP.** O CT-e vincula-se **direto ao veículo** (`cte_documents.vehicle_id`, provisionado por placa no import — ADR-004). O `EntrySynchronizer` usa esse `vehicle_id` no lançamento; não há camada de viagem entre o CT-e e o veículo.

2. **A navegação fala só em CT-e.** O item "Viagens" sai do menu lateral, do bottom-nav (substituído por "CT-e") e do botão "+ Viagem" da topbar. O produto usa um único vocabulário para o operador: importar CT-e.

3. **Tabelas `trips` e `trip_cte_document` não são criadas.** A relação N:N e os campos operacionais da viagem (km vazio, peso, `odometer_start/end`) ficam fora do escopo até que o DRE precise deles.

## Consequências

- **km rodado / km vazio / R$ por km** por enquanto derivam do que já existe: odômetro do veículo (atualizado por abastecimento) e municípios do CT-e. O CT-e **não traz distância** (seção 18.5), então indicadores de rota ficam limitados até haver origem de km — provavelmente o próprio abastecimento ou digitação, não uma entidade Viagem.
- **1 CT-e = 1 deslocamento** é a premissa. Se aparecer o caso de vários CT-es no mesmo deslocamento (mesma rota/dia/veículo), cada um gera sua receita normalmente; o que se perde é o agrupamento — aceitável para o porte atendido.
- **Reversível.** Se o DRE exigir a viagem como agrupador, introduzir `Trip` depois é aditivo: uma tabela nova + pivot, migrando `cte_documents.vehicle_id` para viagens de um CT-e cada. Nenhum dado se perde.
- O roadmap perde a "Fase 3 — CT-e e viagens" como estava; vira apenas "CT-e" (já entregue). A parte de viagens só volta se e quando o DRE pedir.
