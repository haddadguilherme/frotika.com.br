# Diario de desenvolvimento

## 2026-07-15 - Etapa 0.1 (base monetaria)

### Entregas da etapa 0.1

- Implementado rateio por maior resto em `App\Support\Money\Apportionment`.
- Cobertura de cenarios obrigatorios: divisao por 3, 7, 1 e 0, alem de distribuicao ponderada.

### Validacoes da etapa 0.1

- `vendor/bin/phpunit tests/Unit/Support/Money/ApportionmentTest.php`
- `vendor/bin/pint --dirty`
- `composer test`

## 2026-07-15 - Etapa 0.2 (money + cast + tenancy foundation)

### Entregas da etapa 0.2

- Criado value object `App\Support\Money\Money` em centavos, com soma, subtracao e integracao direta ao rateio.
- Criado cast `App\Casts\Money` para mapear colunas `_cents` para o value object.
- Criada fundacao de tenancy com `TenantContext`, `CompanyScope`, `BelongsToCompany`, `MissingTenantContextException`, `Group` e `Company`.
- `TenantContext` registrado como singleton no `AppServiceProvider`.
- Criado teste de isolamento para garantir que empresa A nao enxerga dados da empresa B.

### Validacoes da etapa 0.2

- `vendor/bin/phpunit tests/Unit/Support/Money/MoneyTest.php tests/Unit/Casts/MoneyCastTest.php tests/Feature/Tenancy/BelongsToCompanyIsolationTest.php`
- `vendor/bin/pint --dirty`
- `composer test`

### Proximos incrementos

- Conectar `TenantContext` ao middleware de request (`SetTenantContext`).
- Introduzir migrations oficiais de tenancy seguindo as convencoes do blueprint.
- Passar os testes de tenancy para schema real (PostgreSQL) quando a base de dominio estiver pronta.

## 2026-07-15 - Etapa 0.3 (middleware + schema inicial de tenancy)

### Entregas da etapa 0.3

- Criado middleware `App\Http\Middleware\SetTenantContext` para resolver tenant por sessao e fallback por usuario.
- Middleware registrado no grupo `web` no bootstrap da aplicacao.
- Criadas migrations iniciais de tenancy: `groups`, `companies`, `group_user`, `company_user`, `invitations` e colunas de tenant em `users`.
- Modelos `User`, `Group` e `Company` receberam relacoes de tenancy para suportar validacao de acesso por empresa.
- Teste de isolamento de `BelongsToCompany` atualizado para usar schema real de tenancy.
- Adicionado teste de integracao do middleware cobrindo sessao, fallback, 403 e request anonimo.

### Validacoes da etapa 0.3

- `vendor/bin/phpunit tests/Feature/Tenancy/SetTenantContextMiddlewareTest.php tests/Feature/Tenancy/BelongsToCompanyIsolationTest.php`
- `vendor/bin/pint --dirty`
- `composer test`

## 2026-07-15 - Etapa 0.4 (onboarding minimo transacional)

### Entregas da etapa 0.4

- Criada action `RegisterOwnerAndCompany` para criar usuario owner, grupo, empresa e vinculos em uma unica transacao.
- Criados DTOs `RegisterOwnerAndCompanyData` e `RegisterOwnerAndCompanyResult` para entrada/saida tipadas da action.
- Fluxo grava `current_group_id` e `current_company_id` no usuario para preparar bootstrap de sessao.
- Adicionado teste de sucesso do onboarding e teste de rollback completo em erro de CNPJ duplicado.
- Exposto endpoint HTTP `POST /registrar` para acionar o onboarding minimo via web.
- Adicionado teste de sucesso HTTP (201) e teste de validacao HTTP (422) para o endpoint.
- Extraida a logica do endpoint para controller dedicado `RegisterOwnerAndCompanyController`.
- Validacao do endpoint movida para Form Request dedicado `RegisterOwnerAndCompanyRequest`.
- Validacao fortalecida com unicidade de e-mail/CNPJ e normalizacao de entrada (email/cnpj/tax_regime) no Form Request.
- Cobertura de testes HTTP ampliada para conflitos de unicidade: e-mail existente e CNPJ existente (422).
- Mensagens de validacao padronizadas em pt-BR no Form Request de onboarding.
- Testes HTTP passam a validar explicitamente os textos de erro em pt-BR no JSON de resposta.
- Onboarding passa a criar assinatura inicial em status `trialing` (14 dias) na mesma transacao.
- Onboarding passa a criar conta bancaria padrao da empresa (`Caixa`, tipo `cash`, saldo inicial e atual zerados, `is_default = true`) na mesma transacao.
- Onboarding passa a semear o plano de contas padrao da empresa (`financial_categories`) com estrutura hierarquica e categorias de sistema obrigatorias.
- Categoria financeira passa a usar enums nativos (`type`, `dre_group`, `allocation`) com cast no model para tipagem forte.
- Adicionado teste dedicado da action `SeedDefaultFinancialCategories` cobrindo hierarquia, isolamento entre empresas e casts de enum.
- Criada migration inicial de `financial_entries` com os dois eixos de data (`competence_date` e `paid_at`), campos centrais e indices principais para fluxo de caixa e DRE.
- Criada action `CreateManualFinancialEntry` para inserir lancamentos manuais com validacoes de consistencia (`forecast` sem `paid_at`/conta, `settled` com `paid_at`/conta, categoria e conta da empresa ativa).
- Adicionado teste da action de lancamento manual cobrindo sucesso, rejeicao de inconsistencias e isolamento entre empresas.
- Criados enums de `FinancialEntry` (`type`, `status`, `payment_method`) e model `FinancialEntry` com `BelongsToCompany`, casts e relacoes com categoria/conta/autor.
- Action de lancamento manual passou a persistir via model `FinancialEntry` (Eloquent) mantendo isolamento por tenant e tipagem por enums.
- Criada action `UpdateManualFinancialEntry` para atualizar lancamentos manuais com as mesmas regras de consistencia de status/data/conta e validacao de categoria por tenant.
- Adicionado teste dedicado da action de update cobrindo sucesso no tenant ativo e bloqueio de atualizacao entre empresas.
- Criada action `RecalculateBankAccountCurrentBalance` para recalcular `current_balance_cents` pela formula de saldo em cima dos lancamentos `settled` (receitas menos despesas + saldo inicial).
- Integrado recálculo automatico de saldo nas actions `CreateManualFinancialEntry`, `UpdateManualFinancialEntry` e `CancelFinancialEntry` para manter conta sempre consistente apos mutacoes.
- Adicionado teste dedicado do recalculador e ampliadas assercoes de create/update/cancel para validar atualizacao de saldo da conta.
- Implementado comando `frotika:recalculate-balances` com opcoes `--company` e `--dry-run` para reconciliacao de saldos em lote.
- Adicionado teste de console para validar o modo aplicacao (persiste saldo) e o modo simulacao (`dry-run`, sem persistencia).
- Criada action `BuildCashFlowMatrix` para montar matriz dia x conta com saldo de abertura, entradas/saidas diarias, saldo acumulado e filtro de contas por periodo.
- Adicionado suporte ao toggle de previstos na matriz (inclui `forecast` com `due_date` e fallback em `competence_date`) e teste cobrindo o comportamento.
- Criada action `CreateTransferBetweenBankAccounts` para gerar transferencia entre contas em transacao unica, criando o par de lancamentos (`expense` origem e `revenue` destino) com vinculo cruzado em `transfer_pair_id`.
- Adicionado teste dedicado da transferencia cobrindo criacao do par, recálculo de saldo nas duas contas e validacoes de isolamento por tenant/contas invalidas.
- Action `UpdateManualFinancialEntry` passou a propagar edicoes para o par quando o lancamento possui `transfer_pair_id`, mantendo os dois lados consistentes e recalculando saldos das contas afetadas.
- Action `CancelFinancialEntry` passou a cancelar automaticamente o par de transferencia na mesma operacao, com recálculo de saldo das duas contas.
- Cobertura de testes ampliada para garantir que editar/cancelar um lado da transferencia afeta o par conforme a regra da secao 8.4 do blueprint.
- Action `BuildCashFlowMatrix` passou a aceitar filtros opcionais de categoria, veiculo e status, cobrindo o contrato de filtros definido para a tela de fluxo de caixa na secao 8.3.
- Adicionados testes para filtros combinados (categoria + veiculo + status) e validacao de status invalido na consulta da matriz.
- Action `BuildCashFlowMatrix` passou a retornar metadados de filtros aplicados e totais consolidados (global e por conta), facilitando o consumo pela futura tela de fluxo de caixa.
- Criada migration `recurrences` e novo model `Recurrence` com enum `RecurrenceFrequency` para suportar lancamentos recorrentes previstos.
- Criada action `CreateRecurrence` com validacoes de categoria/tipo/frequencia e isolamento por tenant.
- Criada action `GenerateForecastEntriesFromRecurrences` com geracao idempotente de lancamentos `forecast` por recorrencia ativa, respeitando janela mensal, fim de vigencia e limite de parcelas.
- Implementado comando `frotika:generate-recurrences` com opcoes `--company`, `--reference-date` e `--dry-run`.
- Agendadas rotinas financeiras no scheduler: `frotika:generate-recurrences` (mensal) e `frotika:recalculate-balances` (diario).
- Actions `CreateManualFinancialEntry` e `UpdateManualFinancialEntry` passaram a validar `recurrence_id` (tenant ativo, tipo e categoria compativeis) para impedir vinculos inconsistentes.
- Cobertura de testes ampliada para cadastro de recorrencia, geracao idempotente (incluindo dry-run), comando de geracao e isolamento de `recurrence_id` em create/update manual.

### Validacoes da etapa 0.4

- `vendor/bin/phpunit tests/Feature/Tenancy/RegisterOwnerAndCompanyActionTest.php`
- `vendor/bin/pint --dirty`
- `composer test`
