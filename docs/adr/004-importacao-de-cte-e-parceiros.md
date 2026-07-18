# ADR-004 — Importação de CT-e, parceiros comerciais e receita do agregado

- **Status:** aceito
- **Data:** 2026-07-18
- **Relaciona-se com:** seções 5.2 (Veículo), 5.3 (CT-e), 6.3 (EntrySynchronizer), 7 (parser do CT-e) e 18 (pontos em aberto) do blueprint

## Contexto

O diferencial do produto é transformar o XML do CT-e em dado financeiro sem o dono digitar nada. As transportadoras atendidas costumam ser **agregadas**: prestam transporte subcontratadas por uma transportadora maior. No XML de exemplo (`tests/Fixtures/Cte/cte-hi-transportes.xml`), a **emitente** é a HI TRANSPORTES — a contratante — e não a empresa usuária do sistema. O que a agregada recebe é uma **fatia do frete** (`vTPrest`), negociada com a contratante.

O blueprint previa uma tabela `suppliers` restrita e um parser (seção 7) que assumia a empresa como emitente do CT-e. Nenhum dos dois cobre o cenário de subcontratação nem o cadastro amplo de parceiros (contratante, remetente, expedidor, recebedor, destinatário, além de postos e oficinas).

## Decisão

1. **`business_partners` substitui `suppliers`.** Cadastro único de parceiros por empresa, deduplicado por `(company_id, document)`. `kind` cobre `contractor/customer/carrier/gas_station/workshop/parts/other`. A importação enriquece (só preenche campo vazio, nunca rebaixa o `kind`) para não sobrescrever edição manual.

2. **Sem bloqueio por emitente.** Qualquer CT-e enviado é importável. O vínculo com a empresa se dá pelo **upload + placa** do veículo, não pelo CNPJ do `emit`. A emitente vira um parceiro `contractor`; as demais partes viram `customer`.

3. **Receita = percentual do `vTPrest`.** O crédito no fluxo é `amount_cents = round(vTPrest_cents * percent / 100)`. O `percent` vem da contratante (`business_partners.default_freight_share_percent`); se nulo, usa `config('cte.default_freight_share_percent', 100)`. Quando `emit == empresa` (transporte direto), `percent = 100`. O percentual aplicado fica gravado em `cte_documents.applied_share_percent` para auditoria.

4. **Lançamento como `forecast` (a receber)** — regra 6: `type=revenue`, categoria `1.1`, `competence_date = dhEmi`, `paid_at = null`, `due_date = dhEmi + config('cte.receivable_days', 30)`, `vehicle_id` do CT-e. Gerado pelo `EntrySynchronizer` via `CteDocumentObserver` (regra 7: o relatório agrega só `financial_entries`, nunca `cte_documents`). CT-e cancelado/denegado ou soft-deletado cancela o lançamento.

5. **Veículo mínimo por placa.** `ProvisionVehicleByPlate` cria um `Vehicle` stub (`provisioned = true`) a partir de `ObsCont/placa` (cavalo) e `placa2` (carreta). Motorista (`motorista`/`cpf_motorista`) fica no próprio `cte_document`; tabela `drivers` fica para depois.

6. **XML privado por grupo.** Disco `local` (`storage/app/private`), caminho `grupos/{group_uuid}/cte/{YYYY}/{MM}/{chave}.xml`, com `xml_hash` (sha256) e `raw` (json normalizado) para reprocessar no futuro.

7. **Idempotência.** `unique(company_id, access_key)` em `cte_documents`; `updateOrCreate` do documento e do lançamento (chaveado por `sourceable`). Reimportar atualiza, não duplica.

8. **Parser namespace-agnóstico (`CteReader`/`CteParser`).** Usa `local-name()` no XPath (o CT-e mistura o namespace do portal fiscal com o da assinatura), normaliza encoding, extrai a chave por `chCTe`/`Id`, converte dinheiro para centavos sem `float` (regra 1), tolera ICMS em grupos variados (`ICMS45` sem `vICMS` → 0) e lê o tomador por `toma3/toma4`.

## Consequências

- Editar o percentual da contratante **não** recalcula lançamentos já importados; é preciso reimportar o XML (que agora está guardado). Recalcular em massa fica como incremento futuro.
- `Vehicle` e `BusinessPartner` nascem como subconjuntos mínimos do blueprint (5.2); os cadastros completos de Frota e Motoristas continuam pendentes.
- Cenários `tpCTe` complementar/substituto e `toma4` (tomador "outros") são lidos, mas o produto ainda trata todos como receita simples; refino fica para a fase de Viagens.
- Sem model `Trip` por ora: o CT-e é a viagem e o `vehicle_id` vai direto no lançamento.
