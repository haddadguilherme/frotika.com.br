# ADR-006 — Sem composição de veículos (engate/desengate) nem vínculo motorista↔veículo

- **Status:** aceito
- **Data:** 2026-07-18
- **Relaciona-se com:** seções 5.2 (Cadastros da Frota), 9.2/9.5 (DRE — escopo e modos), 14.4 (banco de demonstração), 15 (roadmap — Fase 2), 17 (glossário) do blueprint.

## Contexto

O blueprint modelava três estruturas de vínculo entre cadastros da frota:

- **`vehicle_compositions`** e **`vehicle_composition_items`** — o "conjunto" cavalo + carreta(s) com vigência (`started_at`/`ended_at`), um sistema de engate/desengate. A justificativa era levar o custo de pneu/manutenção da carreta para o DRE do conjunto que gerou a receita.
- **`driver_vehicle`** — vínculo motorista↔veículo com vigência.

Na prática das transportadoras atendidas (1 a 15 veículos, muitas agregadas), manter conjuntos vigentes e histórico de qual carreta estava atrás de qual cavalo em cada período é trabalho de digitação recorrente que o operador não faz — e, quando não é mantido, vira dado errado no DRE. O mesmo vale para amarrar motorista a veículo: o CT-e já traz motorista (CPF) e veículos (placas) por documento, que é a granularidade real do dado.

## Decisão

1. **Não existe composição de veículos.** As tabelas `vehicle_compositions` e `vehicle_composition_items` **não são criadas**. Cavalo e carreta são veículos independentes no cadastro.

2. **Não existe vínculo motorista↔veículo.** A tabela `driver_vehicle` **não é criada**. Motorista e veículo são cadastros independentes; a ligação real acontece por documento (CT-e liga veículo por placa e motorista por CPF; abastecimento e manutenção apontam o veículo, e o abastecimento pode apontar o motorista).

3. **O DRE trabalha por `vehicle_id`, sem modo "conjunto".** Cada veículo (cavalo, carreta, truck…) é uma linha do DRE. O custo da carreta aparece na linha da própria carreta — não é somado ao cavalo por um conjunto vigente. Os modos de visualização passam a ser: individual, comparativo da frota e consolidado.

## Consequências

- **Custo da carreta fica na carreta.** Quem quiser o resultado "do conjunto" olha cavalo e carreta lado a lado no comparativo da frota; não há agregação automática. Aceitável para o porte atendido.
- **Menos telas e menos dado obrigatório.** Sem cadastro de conjuntos nem de vínculos com vigência, o operador não tem mais um lugar para o dado ficar "incompleto".
- **Reversível.** Se algum dia o DRE precisar somar carretas a um cavalo, a composição volta como introdução aditiva (tabela nova + pivot), sem perda de dado.
- Ajusta o roadmap (Fase 2): "Composições com vigência" e "Vínculo motorista ↔ veículo" saem do escopo. O banco de demonstração (14.4) não cria conjuntos vigentes.
