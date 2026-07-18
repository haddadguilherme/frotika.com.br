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
- Passar os testes de tenancy para schema real (MySQL 8) quando a base de dominio estiver pronta.

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

## 2026-07-15 - Etapa 0.5 (LP + auth + layout base)

### Entregas da etapa 0.5

- Definido tema inicial no Tailwind v4 em `resources/css/app.css` com tokens de marca, semantica e fontes (`brand`, `accent`, `success`, `warning`, `danger`, `info`, `font-display`, `font-sans`, `font-mono`).
- Atualizado `vite.config.js` para carregar as familias `Archivo`, `Inter` e `JetBrains Mono`, alinhando com o blueprint visual.
- Criados componentes UI base em `resources/views/components/ui`: `button`, `link-button`, `input` e `card`.
- Criado layout publico reutilizavel `resources/views/layouts/guest.blade.php` com cabecalho, area de feedback e estrutura para paginas de entrada.
- Substituida a `welcome` padrao por LP inicial em `resources/views/welcome.blade.php`, com proposta de valor, CTA e blocos de beneficios.
- Criadas telas de autenticacao `resources/views/auth/login.blade.php` e `resources/views/auth/register.blade.php` usando os componentes UI.
- Criado layout autenticado base `resources/views/layouts/app.blade.php` com sidebar e topbar para evolucao das telas internas.
- Criada pagina inicial autenticada `resources/views/dashboard.blade.php` para servir de base das proximas telas.
- Rotas atualizadas em `routes/web.php` com URLs em pt-BR: `/entrar`, `/registrar`, `/painel` e `/sair`.
- Criados controllers de auth (`ShowLoginController`, `LoginController`, `LogoutController`) e request `LoginRequest`.
- Endpoint de onboarding `POST /registrar` mantido compativel com JSON e agora tambem com fluxo HTML (login automatico + redirect para o painel).
- Layouts `guest` e `app` passaram a ter fallback de assets para ambiente sem `manifest.json`, evitando erro em testes sem build de frontend.

### Validacoes da etapa 0.5

- `vendor/bin/pint --dirty`
- `composer test`

## 2026-07-16 - Etapa 0.6 (troca de empresa ativa)

### Entregas da etapa 0.6

- Criada action `SwitchCurrentCompany` para trocar a empresa ativa do usuario autenticado, atualizando `current_group_id` e `current_company_id`.
- Autorizacao da troca centralizada no backend via Gate `switch-company`, validando vinculo do usuario com empresa e grupo.
- Criados endpoint e rota autenticada `POST /empresa-atual` (`tenancy.switch-company`) para atualizar empresa ativa e sessao.
- Criado Form Request `SwitchCurrentCompanyRequest` com validacao de `company_id` e mensagens em pt-BR.
- Adicionada cobertura de testes em `SwitchCurrentCompanyTest` para sucesso, bloqueio por acesso indevido, empresa inexistente e atualizacao de sessao no endpoint.

### Validacoes da etapa 0.6

- `vendor/bin/pint --dirty`
- `vendor/bin/phpunit tests/Feature/Tenancy/SetTenantContextMiddlewareTest.php tests/Feature/Tenancy/SwitchCurrentCompanyTest.php`
- `composer test`

## 2026-07-16 - Etapa 0.7 (esqueci a senha + redefinicao)

### Entregas da etapa 0.7

- Adicionadas rotas publicas em pt-BR para recuperacao de senha: `GET/POST /esqueci-a-senha`, `GET /redefinir-senha/{token}` e `POST /redefinir-senha`.
- Criados os requests `ForgotPasswordRequest` e `ResetPasswordRequest` com validacoes e mensagens em pt-BR.
- Concluido fluxo de controllers de auth para recuperacao: exibicao do formulario, envio de link e redefinicao de senha.
- Criadas as views `auth/forgot-password` e `auth/reset-password`, integradas ao layout guest e aos componentes UI.
- Tela de login recebeu atalho direto para o fluxo "Esqueci minha senha".
- Adicionada cobertura feature de ponta a ponta em `PasswordResetFlowTest` para tela, envio do link, redefinicao com token valido e bloqueio com token invalido.

### Validacoes da etapa 0.7

- `vendor/bin/pint --dirty`
- `vendor/bin/phpunit tests/Feature/Auth/PasswordResetFlowTest.php`
- `composer test`

## 2026-07-16 - Etapa 0.8 (autenticacao base validada)

### Entregas da etapa 0.8

- Criada cobertura feature de autenticacao em `AuthenticationFlowTest` para os cenarios de acesso protegido, login com sucesso, falha de login e logout.
- Fluxo de acesso consolidado com validacao de redirecionamento de anonimo para `/entrar` ao tentar abrir `/painel`.
- Revalidado o conjunto de testes de auth com `AuthenticationFlowTest` + `PasswordResetFlowTest` para garantir consistencia entre login e recuperacao de senha.

### Validacoes da etapa 0.8

- `vendor/bin/pint --dirty`
- `vendor/bin/phpunit tests/Feature/Auth/AuthenticationFlowTest.php tests/Feature/Auth/PasswordResetFlowTest.php`
- `composer test`

## 2026-07-16 - Etapa 0.9 (confirmacao de e-mail no onboarding/login)

### Entregas da etapa 0.9

- `User` passou a implementar `MustVerifyEmail`, habilitando o contrato nativo de verificacao de e-mail do Laravel.
- Fluxo de onboarding web atualizado para enviar notificacao de confirmacao e redirecionar para a tela de confirmacao em vez de liberar acesso direto ao painel.
- Adicionadas rotas autenticadas de verificacao com URLs em pt-BR: tela de aviso (`/confirmar-email`), reenvio de notificacao (`POST /confirmar-email/notificacao`) e confirmacao assinada (`GET /confirmar-email/{id}/{hash}`).
- Painel (`/painel`) e troca de empresa (`/empresa-atual`) passaram a exigir middleware `verified`.
- Criados controllers de verificacao: `ShowVerifyEmailController`, `SendVerificationEmailController` e `VerifyEmailController`.
- Criada a view `auth/verify-email` integrada ao layout guest e componentes de UI.
- Adicionada cobertura feature em `EmailVerificationFlowTest` para:
	- redirecionamento de usuario nao verificado ao tentar acessar painel,
	- carregamento da tela de confirmacao,
	- reenvio de notificacao,
	- confirmacao por link assinado,
	- envio de notificacao no registro web.

### Validacoes da etapa 0.9

- `vendor/bin/pint --dirty`
- `vendor/bin/phpunit tests/Feature/Auth/EmailVerificationFlowTest.php tests/Feature/Auth/AuthenticationFlowTest.php tests/Feature/Auth/PasswordResetFlowTest.php`
- `composer test`

## 2026-07-17 - Etapa 0.10 (refino premium da interface)

### Entregas da etapa 0.10

- Criado o componente de assinatura `x-ui.km-gauge` (regua de R$/km com custo, receita, margem e ponto de equilibrio), a partir de `.github/skills/frotika-ui/reference/exemplo-regua.blade.php`.
- `dashboard` reescrito como painel de instrumentos: faixa de indicadores monocromatica (no lugar de 4 cards coloridos), comparativo da frota com o pior resultado primeiro e master-detail com a regua como unico acento da tela.
- `stat-card` alinhado a anatomia (valor em `font-display`, monocromatico por padrao) e `plate-chip` alinhado (borda `slate-800`, tarja do reboque com `opacity-40`).
- Shell (`layouts/app`) refinado: sidebar com secoes/filete e item ativo correto, busca da topbar com icone + `Ctrl+K` (corrigido o padding sobreposto) e bottom nav mobile com icones reais.
- Paginas publicas refinadas (`welcome`, `login`, `register`, `forgot-password`, `reset-password`, `verify-email`): removido o ornamento (quadrados flutuantes) e uso da regua real.
- Acentuacao pt-BR corrigida em toda a interface tocada (layouts, dashboard, welcome, auth/*).

### Validacoes da etapa 0.10

- `npm run build`
- `vendor/bin/pint --dirty`
- `composer test`

### Proximos incrementos da etapa 0.10

- Criar `App\Support\Format` (secao 14.3 do blueprint) e migrar a formatacao inline da `km-gauge` e do dashboard para ele.
- Sem Livewire/Alpine instalados: teclado (setas), ordenacao por URL, selecao em lote e edicao inline ficam para quando as telas virarem componentes Livewire (copiar `reference/exemplo-lista.blade.php`).
- Reconciliar a tipografia da secao 12.3 do blueprint (Inter/JetBrains Mono) com o que esta no codigo (IBM Plex Sans/Mono, alinhado a skill `frotika-ui`).

## 2026-07-17 - Etapa 0.11 (consulta de CNPJ no onboarding)

### Entregas da etapa 0.11

- Criado helper `App\Support\Cnpj\Cnpj` (digitos, validacao de checksum e formatacao) como fonte unica do checksum; `RegisterOwnerAndCompanyRequest` refatorado para reutiliza-lo (sem mudar comportamento nem mensagens).
- Criado servico `App\Support\Cnpj\CnpjLookup` com BrasilAPI (fonte primaria) e fallback ReceitaWS, normalizando as duas respostas para o DTO `CnpjData`.
- Resultado tipado com `CnpjLookupResult` e enum `CnpjLookupStatus` (`found` / `not_found` / `unavailable`): encontrou em qualquer fonte -> found; alguma fonte respondeu que nao existe -> not_found; as duas falharam por rede/indisponibilidade -> unavailable.
- Exposto endpoint `GET /registrar/cnpj/{cnpj}` (`register.cnpj`, grupo `guest`, `whereNumber`, `throttle:20,1`) via `LookupCnpjController`, que valida o CNPJ antes de consultar (invalido -> 422).
- Formulario de registro com mascara `00.000.000/0000-00`, limite real de 14 digitos, `inputmode="numeric"`, consulta automatica ao completar o CNPJ, preenchimento de razao social e nome fantasia e destravamento para preenchimento manual quando nao encontra ou a API esta indisponivel (com link "Preencher manualmente" como saida de resiliencia).
- Endereco, telefone e e-mail sao normalizados pelo servico (colunas ja existem em `companies`), mas ainda nao sao persistidos no onboarding.

### Validacoes da etapa 0.11

- `vendor/bin/pint --dirty`
- `vendor/bin/phpunit tests/Feature/Tenancy/LookupCnpjControllerTest.php`
- `composer test` (82 testes)

### Proximos incrementos da etapa 0.11

- Persistir endereco/telefone/e-mail retornados pela consulta (ampliar `RegisterOwnerAndCompanyData`, a action `RegisterOwnerAndCompany` e o `RegisterOwnerAndCompanyRequest`).
- Ajustar `RegisterOwnerAndCompanyRequest::failedValidation` para responder redirect com erros no fluxo web (hoje sempre JSON 422, o que impede `@error`/`old()` no navegador).
- Cachear o resultado por CNPJ (ex.: 24h) para reduzir chamadas repetidas as APIs externas.

## 2026-07-17 - Etapa 0.12 (helper de formatacao pt-BR)

### Entregas da etapa 0.12

- Criado `App\Support\Format` (secao 14.3 do blueprint) com `money`, `moneyDecimal`, `liters`, `km`, `consumption`, `percent`, `plate`, `cnpj`, `cpf`, `cteKey`, `date` e `dateTime`.
- Convencao de moeda: `money()` embute o `R$` e usa sinal de menos tipografico (−); em tabela/card o numero usa `moneyDecimal()` (sem simbolo) e o `R$` fica num `<span class="unit">` a parte, como a skill `frotika-ui` espera.
- `Format::cnpj` reutiliza `App\Support\Cnpj\Cnpj::format`, mantendo fonte unica de formatacao de CNPJ.
- Registrado alias global `Format` no `AppServiceProvider` (`AliasLoader`) para uso direto na Blade.
- `x-ui.km-gauge` e `dashboard` migrados: removida a formatacao inline (`number_format`/closure), tudo passa por `Format`.
- Adicionado teste unitario `FormatTest` (moeda, decimal, litros, km, consumo/null, percentual, CNPJ, CPF, chave de CT-e, datas) e teste de render `KmGaugeComponentTest` (valida a regua e a resolucao do alias `Format` dentro da Blade).

### Validacoes da etapa 0.12

- `vendor/bin/pint --dirty`
- `vendor/bin/phpunit tests/Unit/Support/FormatTest.php tests/Feature/Ui/KmGaugeComponentTest.php`
- `composer test` (96 testes)
- `npm run build`

### Proximos incrementos da etapa 0.12

- Consumir `Format` nas proximas telas (fluxo de caixa, DRE) e no seeder de demonstracao.
- Reconciliar a tipografia da secao 12.3 do blueprint (Inter/JetBrains Mono) com o codigo (IBM Plex Sans/Mono).

## 2026-07-17 - Etapa 0.13 (Livewire, Larastan e CI)

### Entregas da etapa 0.13

- Instalado `livewire/livewire` v4. O Livewire 4 embute o Alpine e auto-injeta seus assets na resposta HTML, entao o Alpine fica disponivel em todas as paginas sem editar layout.
- Instalado `larastan/larastan` v3 + `phpstan.neon.dist` no nivel 6, analisando `app/`, `database/` e `routes/`.
- Zerados os 58 erros iniciais do nivel 6 (corrigindo a causa, sem baseline nem ignore):
  - Genericos de relations/casts/scope anotados (`@return BelongsTo<...>`/`HasMany<...>`/`BelongsToMany<...>`, `@implements CastsAttributes<...>`, `CompanyScope` como `@template ... @implements Scope<TModel>`).
  - `@property` de enums nos models de Finance (`type`, `status`, `frequency`, `payment_method`) — o Larastan inferia `string` a partir do schema das migrations.
  - `FinancialCategory::$type` documentado como nullable (categorias sinteticas/agrupadoras nao tem tipo).
  - `@use HasFactory<\Database\Factories\UserFactory>` corrigido em `User`.
  - `rules()` do `RegisterOwnerAndCompanyRequest` com `@return array<string, list<Closure|string>>`.
  - `routes/console.php`: `self::SUCCESS/FAILURE` -> `Command::SUCCESS/FAILURE` (o `self` nao tinha escopo de classe dentro dos closures de comando).
  - `UpdateManualFinancialEntry`: removido nullsafe/comparacao redundante em ramo ja garantido nao-nulo pelo Larastan.
  - `BuildCashFlowMatrix`: `@param` dos normalizadores de input externo corrigidos para `array<int, mixed>` (o input cru nao e tipado).
- **Bug real corrigido:** `CnpjLookup` — o fallback da ReceitaWS nunca sinalizava `NotFound` (retornava so `?CnpjData`), tornando aquele ramo morto. Agora retorna `CnpjLookupStatus::NotFound` quando a Receita rejeita o CNPJ. Novo teste cobre BrasilAPI indisponivel + ReceitaWS rejeitando -> `not_found`.
- Workflow `.github/workflows/ci.yml`: PHP 8.3, `composer install`, `npm ci && npm run build`, `pint --test`, `phpstan` e `composer test`.

### Validacoes da etapa 0.13

- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` (0 erros, nivel 6)
- `composer test` (97 testes)
- `npm run build`

### Proximos incrementos da etapa 0.13

- Primeira listagem Livewire (Fase 2 - Frota) copiando `frotika-ui/reference/exemplo-lista.blade.php`, ja com a `km-gauge` no card do veiculo.
- Quando os testes passarem a usar recursos do MySQL 8 (a instrucao de testes ja pede), trocar o SQLite `:memory:` do `phpunit.xml` por um servico MySQL no workflow de CI.

## 2026-07-18 - Etapa 0.15 (licenca por grupo + cadastro de empresas)

Ver ADR-003.

### Entregas da etapa 0.15

- **Licenca migrada de empresa para grupo.** `company_licenses`/`company_license_invoices` -> `group_licenses`/`group_license_invoices` (uma licenca por grupo, sem `company_id`/`is_primary`). Migrations reescritas (nao estavam em producao). Models, enums (`GroupLicenseStatus`, `GroupLicenseInvoiceStatus`), Actions (`RegisterGroupLicense`, `IssueGroupLicenseInvoice`, `MarkGroupLicenseInvoiceAsPaid`), Data e chaves de config (`billing.group_license_*`) renomeados.
- **`Subscription` removido** (model + migration): a `GroupLicense` e o unico conceito de cobranca por grupo. Onboarding cria a licenca via `RegisterGroupLicense`; `CompanyObserver` deixou de criar licenca e so define `groups.primary_company_id`.
- **`EnsureGroupLicenseAllowsWrite`** (ex-`EnsureCompanyLicenseAllowsWrite`) resolve a licenca por `current_group_id`; status de bloqueio `group_license_blocked`. Composer do layout e painel `/admin` (lista com status/MRR da licenca do grupo, detalhe com 1 licenca + empresas) ajustados.
- **Modulo de empresas (`/empresas`)**: CRUD para `owner`/`admin` do grupo com `CompanyPolicy`. Actions `CreateCompany` (semeia plano de contas + conta Caixa, sem cobranca), `UpdateCompany`, `DeactivateCompany` (bloqueia desativar a principal e a empresa ativa). Requests `StoreCompanyRequest`/`UpdateCompanyRequest`, controllers single-action e views (index/create/edit/show) seguindo `frotika-ui`, com busca de CNPJ reaproveitando `LookupCnpjController`.

### Validacoes da etapa 0.15

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse`
- `composer test`

## 2026-07-18 - Etapa 0.16 (consulta de CEP no ViaCEP)

### Entregas da etapa 0.16

- **Modulo `app/Support/Cep`** espelhando o de CNPJ: `Cep` (digits/format/isValid de 8 digitos, sem digito verificador), `CepData` (DTO readonly com `isGeneric()` e `toFormPayload()`), `CepLookupStatus` (Found/NotFound/Unavailable com `label()`), `CepLookupResult` e `CepLookup` (GET `https://viacep.com.br/ws/{cep}/json/`, timeout 10s). `{"erro": true}` -> NotFound; falha de rede/HTTP -> Unavailable.
- **`LookupCepController`** invocavel: valida o formato (8 digitos) e retorna `422 invalid` antes de chamar a API; senao devolve `{status, generic, address}`. Rota `GET /empresas/cep/{cep}` (`companies.cep`, `whereNumber`, `throttle:30,1`) dentro do grupo autenticado.
- **Formulario de empresas (`companies/_form.blade.php`)**: CEP virou campo com botao "Buscar" + status; ao completar 8 digitos consulta o ViaCEP e preenche o endereco. Regra do CEP generico: quando o ViaCEP nao devolve logradouro/bairro (CEP da cidade toda), cidade/UF ficam preenchidas e bloqueadas e logradouro/bairro ficam **liberados** para digitacao; num CEP especifico, logradouro/bairro/cidade/UF ficam preenchidos e **bloqueados** (numero e complemento sempre editaveis). Nao encontrado/indisponivel/invalido libera todos os campos para preenchimento manual.
- **`ibge_code` passa a ser persistido** (coluna ja existia, antes vazia): campo hidden no form preenchido pelo ViaCEP, mais `CompanyData::$ibgeCode`, regra `size:7` no `StoreCompanyRequest` e repasse nos controllers Create/Update.

### Validacoes da etapa 0.16

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=512M` (0 erros, nivel 6)
- `composer test` (126 testes)

## 2026-07-18 - Etapa 0.17 (mascara de telefone/celular/WhatsApp)

### Entregas da etapa 0.17

- **Mascara global no `resources/js/app.js`**: qualquer input com `data-mask="phone"` recebe `(xx) x xxxx-xxxx` (celular, 11 digitos) ou `(xx) xxxx-xxxx` (fixo, 10 digitos), limitado a 11 digitos na digitacao. Formata tambem no load (edicao/valor antigo) e expoe `window.frotikaApplyPhoneMask`. Vale para todos os cadastros, atuais e futuros.
- **`Format::phone(?string)`** para exibicao/impressao a partir dos digitos gravados. Tamanho inesperado volta so com os digitos (nunca inventa separador). Usado no `companies/show.blade.php`.
- **Base guarda so digitos**: `StoreCompanyRequest` e `RegisterOwnerAndCompanyRequest` normalizam o telefone para digitos (`nullableDigits`) e validam `regex:/^\d{10,11}$/`. O input do formulario de empresas ganhou `data-mask="phone"`, `maxlength`, `inputmode="tel"`; o autofill do CNPJ dispara o `input` para a mascara formatar o telefone vindo cru da Receita.

### Validacoes da etapa 0.17

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=512M` (0 erros, nivel 6)
- `composer test` (129 testes)
- `npm run build`

## 2026-07-18 - Etapa 0.18 (importacao de CT-e + parceiros + receita do agregado)

Ver ADR-004. Baseado no XML real 4.00 (`tests/Fixtures/Cte/cte-hi-transportes.xml`), blueprint secoes 5.2/5.3/6.3/7.

### Entregas da etapa 0.18

- **Camada de dados**: migrations `business_partners` (dedupe por `(company_id, document)`, `kind`, `default_freight_share_percent`, endereco), `vehicles` (subconjunto minimo do 5.2, `unique(company_id, plate)`, flag `provisioned`), `cte_documents` (campos do 5.3 + `vehicle_id`/`trailer_vehicle_id`, `driver_name`/`driver_cpf`, `applied_share_percent`, `xml_path`/`xml_hash`/`raw`, `unique(company_id, access_key)`) e pivo `cte_document_business_partner` com `role`.
- **Parser namespace-agnostico** (`app/Domain/Trips/Cte`): `CteReader` (XPath por `local-name()`, normaliza encoding, `deepValue`/`xpathValue`/`obsCont`/`protocolValue`) e `CteParser` (chave por `chCTe`/`Id`, dinheiro em centavos sem float, ICMS variavel, tomador `toma3/toma4`, placas/motorista de `ObsCont`, status pelo `cStat`). Enums `CteType/CteServiceType/CteStatus/CteTakerRole/CtePartyRole` e DTOs `CteData`/`CtePartyData`.
- **Parceiros** (`app/Domain/Partners`): model `BusinessPartner`, enums `BusinessPartnerKind`/`BusinessPartnerDocumentType`, `UpsertBusinessPartner` (dedupe + enriquecimento sem sobrescrever), CRUD (`CreateBusinessPartner`/`UpdateBusinessPartner`/`DeactivateBusinessPartner`), `BusinessPartnerPolicy`, requests, controllers single-action e views (index/create/edit/show) em `frotika-ui`.
- **Frota** (`app/Domain/Fleet`): model `Vehicle`, enums `VehicleType`/`VehicleStatus` e `ProvisionVehicleByPlate` (find-or-create por placa, cavalo/carreta, stub `provisioned`).
- **ImportCte** (`app/Domain/Trips/Actions`): sem bloqueio por emitente; cadastra parceiros (emitente = `contractor`), resolve o percentual do frete (contratante -> config, 100% quando `emit == empresa`), provisiona veiculos, grava o XML privado por grupo (`grupos/{uuid}/cte/YYYY/MM/chave.xml` + sha256 + raw) e faz `updateOrCreate` do documento (idempotente).
- **Receita no fluxo** (`EntrySynchronizer` + `CteDocumentObserver`): lancamento `revenue`/`forecast` na categoria `1.1`, `amount = round(vTPrest * applied_share_percent / 100)`, `competence_date = dhEmi`, `due_date = dhEmi + cte.receivable_days`, `vehicle_id` do CT-e; cancelamento/soft delete cancela o lancamento. Superficie unica: o relatorio agrega so `financial_entries` (regra 7).
- **Rotas/nav**: `/ct-e` (lista), `/ct-e/importar` (upload + resultado), `/ct-e/{cte}` (detalhe com partes, veiculo, valores e lancamento); CRUD `/parceiros`. Nav e botao "Importar CT-e" do header ligados. `config/cte.php` (`receivable_days`, `default_freight_share_percent`, `storage_disk`).

### Validacoes da etapa 0.18

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=512M` (0 erros, nivel 6)
- `composer test` (138 testes)

## 2026-07-18 - Etapa 0.19 (telas de cadastro de parceiro com busca automatica)

### Entregas da etapa 0.19

- **Formulario de parceiro no mesmo nivel do de empresas**: `partners/_form.blade.php` reescrito com `x-ui.input`/`x-ui.select`, tag `<form>` e botoes movidos para `create`/`edit` (padrao do modulo de empresas). Campos de tipo, % do frete (com dica), documento, contato e endereco.
- **Busca por CNPJ na Receita** reaproveitando `GET /empresas/cnpj/{cnpj}` (`LookupCnpjController`, generico): mascara adaptativa CNPJ/CPF, autofill de `legal_name`/`trade_name`/endereco/telefone/email por `data-cnpj-field` e disparo do `input` para a mascara de telefone. CPF (11 digitos) nao dispara busca.
- **Busca por CEP no ViaCEP** reaproveitando `GET /empresas/cep/{cep}` (`LookupCepController`): mesma regra de CEP generico (cidade/UF bloqueadas, logradouro/bairro liberados) vs. especifico (endereco preenchido e bloqueado), com `ibge_code` em campo hidden.
- **Testes de CRUD** (`BusinessPartnerManagementTest`): cadastro com CNPJ (normaliza documento/telefone), documento duplicado rejeitado, CNPJ invalido rejeitado, membro sem papel de gestao bloqueado, edicao, desativacao (soft delete) e isolamento da listagem por empresa ativa.

### Validacoes da etapa 0.19

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=512M` (0 erros, nivel 6)
- `composer test` (145 testes)
- `npm run build`

## 2026-07-18 - Fix (auto-cura do plano de contas na importacao de CT-e)

### Problema

Importar CT-e numa empresa sem o plano de contas padrao (empresa legada criada antes do seed, ou por demo/seed antigo) estourava `RuntimeException` "Categoria de receita de fretes (1.1) nao encontrada", derrubando toda a importacao com 500 (a transacao inteira do `ImportCte` fazia rollback).

### Correcao

- `EntrySynchronizer` agora recebe `SeedDefaultFinancialCategories`. Quando a categoria `1.1` nao existe **e** a empresa nao tem categoria nenhuma, semeia o plano de contas padrao na hora (dentro da mesma transacao) e reobtem a categoria. So lanca a excecao se, mesmo apos semear, a `1.1` continuar ausente (evita duplicar plano em empresas que ja tem categorias). Empresas legadas se auto-curam na proxima importacao.
- Novo teste `ImportCteTest::test_importa_cte_semeando_plano_de_contas_para_empresa_legada` cobrindo o cenario (helper com `seedCategories: false`).

### Validacoes do fix

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse app/Domain/Finance/Actions/EntrySynchronizer.php --memory-limit=512M` (0 erros)
- `php artisan test --filter=ImportCteTest` (5 testes)

## 2026-07-18 - Etapa 0.20 (cadastro de veiculos)

### Entregas da etapa 0.20

- **Migration `vehicles` expandida** para o escopo completo da 5.2 (a original desta sessao ainda nao foi para producao): identificacao (placa, tipo, situacao, propriedade), especificacoes (marca/modelo, anos, RENAVAM, chassi, RNTRC, eixos, carroceria, combustivel, tanque, tara, capacidades kg/m3), hodometro inicial/atual, financeiro em centavos (aquisicao, residual) + `depreciation_months` e datas. Mantem `provisioned` e `unique(company_id, plate)`.
- **Enums** `VehicleOwnership` (proprio/arrendado/agregado/terceiro), `VehicleBodyType` (sider, bau, graneleiro, tanque, prancha, frigorifico, cacamba, porta-conteiner) e `VehicleFuelType` (diesel S10/S500, gasolina, etanol, GNV, eletrico), todos com `label()`. Model `Vehicle` com casts (dinheiro em centavos como inteiro, `capacity_m3` decimal, enums, data).
- **Dominio** (`app/Domain/Fleet`): `VehicleData` (DTO readonly), `CreateVehicle`/`UpdateVehicle`/`DeactivateVehicle` (dedupe por placa, `Gate::authorize` na Action, `TenantContext::runFor`; cadastro/edicao manual zera `provisioned`; `odometer_current` denormalizado nao e sobrescrito no update) e `VehiclePolicy` (papeis de gestao owner/admin).
- **HTTP**: `VehicleRequest` abstrato (normaliza placa maiuscula sem separador, valida padrao antigo/Mercosul, converte reais -> centavos para `acquisition_value`/`residual_value` sem float na base) + `Store`/`Update`; controllers single-action (list/create/store/show/edit/update/destroy) e rotas `/veiculos`. Policy registrada no `AppServiceProvider`.
- **Telas** `frotika-ui`: `index` (tabela desktop + card mobile, chip de situacao, selo "Provisionado", filtros por tipo/situacao), `_form` (secoes Identificacao/Especificacoes/Hodometro+depreciacao/Observacoes, mascara de placa no JS), `create`, `edit` (aviso quando provisionado) e `show`. Nav "Veiculos" ligada em Frota; placas de cavalo/carreta no detalhe do CT-e agora linkam para o veiculo.
- **Teste** `VehicleManagementTest` (cadastro com normalizacao de placa e conversao de valores, placa duplicada/invalida rejeitada, autorizacao, edicao que finaliza veiculo provisionado, desativacao, isolamento por empresa ativa).

### Validacoes da etapa 0.20

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=512M` (0 erros, nivel 6)
- `composer test` (153 testes)
- `npm run build`

## 2026-07-18 - Etapa 0.21 (telas de contas bancarias e lancamentos financeiros)

Exposicao em tela do backend financeiro que ja existia server-side (Fase 5, parte 1 de 2 — fluxo de caixa fica para a proxima rodada). Renderizacao em Blade + controllers single-action + filtros por query string, consistente com Empresas/Parceiros/Veiculos.

### Entregas da etapa 0.21

- **Helper `App\Support\Money\Brl`**: unica porta de entrada de valor digitado (reais pt-BR "1.500,50") -> centavos inteiros, sem float na base (regra 1). Enum `BankAccountType` (caixa/corrente/poupanca/digital/outra) com `label()`; cast adicionado ao model `BankAccount`.
- **Contas bancarias** (`/contas`): `BankAccountData` (DTO), actions `CreateBankAccount`/`UpdateBankAccount`/`DeactivateBankAccount` (garante uma unica conta padrao por empresa via limpeza previa do flag; recalculo de saldo pela action existente; bloqueio de remocao quando ha lancamento vinculado) e `BankAccountPolicy` (papeis owner/admin). Requests `Store`/`Update`; controllers list/create/store/edit/update/destroy; views `index`/`_form`/`create`/`edit`.
- **Lancamentos** (`/lancamentos`): reuso de `CreateManualFinancialEntry`/`UpdateManualFinancialEntry`/`CancelFinancialEntry`; nova action `SettleFinancialEntry` (dar baixa: previsto -> liquidado com conta, data e meio) e `FinancialEntryPolicy`. Tipo do lancamento derivado da categoria no controller (nunca diverge). Requests `Store`/`Update`/`Settle` (reais->centavos, `required_if`/`prohibited_if` por situacao). Controllers list (filtros por situacao/tipo/categoria/veiculo/conta/periodo/descricao + totais), create/store/show/edit/update/settle/destroy. Views `index` (tabela desktop com totais sticky + card mobile), `_form` (categoria agrupada por receita/despesa, radio de situacao com bloco de baixa condicional em JS), `create`/`edit`/`show` (detalhe + formulario de baixa + cancelamento). Edicao bloqueia lancamento sincronizado (CT-e) e transferencia — so a origem altera.
- **`EntrySynchronizer`**: preserva `status`/`paid_at`/`bank_account_id`/`payment_method` quando o lancamento ja esta liquidado, para que reimportar o CT-e nao desfaca a baixa conciliada. Habilita dar baixa com seguranca tambem na receita de CT-e.
- **Nav**: "Lancamentos" e "Contas bancarias" ligadas na secao Financeiro; botao central "+" do bottom-nav aponta para novo lancamento.

### Pendencias conhecidas

- **Fluxo de caixa** (tela consumindo `BuildCashFlowMatrix`) fica para a proxima rodada.
- Reimportar um CT-e ja liquidado com valor diferente atualiza `amount_cents` mas nao recalcula o saldo da conta (cenario raro; revisitar se aparecer).

### Validacoes da etapa 0.21

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=1G` (0 erros, nivel 6)
- `composer test` (165 testes)
- `npm run build`

## 2026-07-18 - Etapa 0.22 (tela de fluxo de caixa)

Fecha a parte visivel da Fase 5. Tela somente-leitura consumindo `BuildCashFlowMatrix` (dia x conta, com saldo acumulado).

### Entregas da etapa 0.22

- **`ShowCashFlowController`** (`/fluxo-de-caixa`): filtros por query string (periodo com padrao mes corrente, conta, toggle "Considerar previstos"); consolida os dias de todas as contas numa serie unica (soma por data, saldo acumulado = soma dos saldos por conta).
- **View `cash-flow/index`**: cards de indicadores (saldo inicial, entradas, saidas, resultado, saldo final), tabela "por conta" quando ha mais de uma, e tabela de movimento diario com saldo acumulado destacado em `danger` quando negativo (a projecao de saldo negativo e a razao da tela, secao 8.3). Nav "Fluxo de caixa" ligada.
- **Teste `CashFlowScreenTest`**: consolida realizado no periodo; previsto com conta-alvo so projeta com o toggle.

### Validacoes da etapa 0.22

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=1G` (0 erros, nivel 6)
- `composer test` (167 testes)
- `npm run build`

## 2026-07-18 - Etapa 0.23 (projecao de previstos no fluxo de caixa)

Resolve a tensao aberta na 0.22. Decisao do usuario: **conta-alvo opcional no previsto**; se informada, projeta naquela conta; se nao, projeta no saldo consolidado.

### Entregas da etapa 0.23

- **Actions** `CreateManualFinancialEntry`/`UpdateManualFinancialEntry`: removida a proibicao de conta bancaria em previsto (agora e conta-alvo opcional, onde o dinheiro deve cair). Mantida a regra de que previsto nao tem `paid_at` e liquidado exige conta + `paid_at`. Conta-alvo do previsto nao afeta o saldo realizado (`RecalculateBankAccountCurrentBalance` so soma liquidados).
- **`FinancialEntryRequest`**: `bank_account_id` deixa de ser `prohibited_if` no previsto (segue `required_if` no liquidado).
- **Formulario de lancamento**: conta bancaria movida para sempre visivel (rotulo unico, com ajuda explicando previsto vs. liquidado); bloco condicional agora so tem data de pagamento + meio.
- **`BuildCashFlowMatrix`**: novo balde `unassigned_forecast` — previstos **sem** conta-alvo, agregados por data de caixa (due_date com fallback para competence_date), projetados no consolidado e somados aos totais. So entra quando previstos estao incluidos e nao ha filtro de conta especifica. Contas existentes e seus testes nao mudam (baldes vazios quando nao ha previsto sem conta).
- **`ShowCashFlowController`**: serie diaria consolidada agora soma o balde `unassigned` (saldo acumulado projetado inclui a projecao dos previstos sem conta).
- **Testes**: `previsto aceita conta-alvo`, `previsto com paid_at e rejeitado`, `previsto sem conta projeta no consolidado com o toggle`.

### Validacoes da etapa 0.23

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=1G` (0 erros, nivel 6)
- `composer test` (169 testes)
- `npm run build`

## 2026-07-18 - Etapa 0.24 (abastecimentos)

Fase 4. Modulo de abastecimentos com calculo de consumo (regra 8) e despesa sincronizada no financeiro (regra 7). Escopo de campos completo (blueprint 5.4); motorista/viagem/posto-parceiro ficam para quando esses cadastros existirem (colunas nulaveis ja criadas).

### Entregas da etapa 0.24

- **Enums** `FuelProduct` (diesel_s10/s500, arla32, gasolina, etanol, GNV, oleo; `isFuel()` exclui arla32 e oleo), `FuelTank` (principal/auxiliar), `FuelingPaymentMethod` (dinheiro/pix/cartao de abastecimento/credito/debito/faturado; `isCashLike()` e `toFinancialEntryPaymentMethod()`).
- **Migration + model `Fueling`** (`BelongsToCompany`, soft delete, casts; `fueled_at` datetime, `liters`/`price_per_liter` decimal(10,3), `total_cents` bigint, `km_since_last`/`km_per_liter` calculados). Indice (`company_id`,`vehicle_id`,`fueled_at`).
- **`FuelConsumptionCalculator`** (regra 8 exatamente): so fecha entre dois `full_tank` do mesmo tanque; arla32 e oleo nunca entram (nem litros, nem abrem/fecham intervalo); litros do intervalo = soma dos combustiveis posteriores ao ultimo tanque cheio ate o atual; sem tanque cheio anterior -> `null` (nunca zero). Recalcula a serie inteira do veiculo por tanque, `saveQuietly` para nao reentrar no observer. **Teste escrito antes** (`FuelConsumptionCalculatorTest`).
- **Actions** `CreateFueling`/`UpdateFueling`/`DeleteFueling` (+ `FuelingData` DTO e `FuelingPolicy` owner/admin): validam odometro >= ultimo conhecido do veiculo (bloqueia regressivo salvo confirmacao explicita), derivam preco/litro do total quando ausente, atualizam `odometer_current` do veiculo e disparam o recalculo de consumo.
- **`EntrySynchronizer::syncFromFueling`** + `FuelingObserver`: abastecimento vira despesa (arla32->3.2, oleo->3.6, demais combustiveis->3.1). A vista (dinheiro/pix/debito/cartao de abastecimento) liquida na conta padrao com `paid_at = fueled_at`; a prazo (credito/faturado) vira conta a pagar (previsto). Preserva a baixa ja conciliada ao reeditar (como no CT-e) e recalcula o saldo da conta afetada. Se nao houver conta padrao, a vista cai como previsto.
- **HTTP + telas** (`/abastecimentos`): requests `Store`/`Update` (reais->centavos via `Brl`, decimais pt-BR normalizados), controllers single-action (list com filtros veiculo/produto/periodo + totais de litros e valor, create/store/show/edit/update/destroy), views `index`/`_form`/`create`/`edit`/`show` (consumo, custo, posto e link para o lancamento). Nav "Abastecimentos" ligada; botao "+ Abastecimento" da topbar aponta para o novo cadastro.
- **Teste `FuelingManagementTest`**: a vista liquida na conta padrao e reduz o saldo; faturado vira conta a pagar; arla cai na 3.2 e nao calcula consumo; consumo fecha entre dois tanques cheios; odometro regressivo bloqueia sem confirmacao; excluir cancela o lancamento e devolve o saldo; isolamento por grupo.

### Decisoes da etapa 0.24

- **Baixa a vista na conta padrao** (escolha do usuario): evita deixar toda compra de combustivel como "a pagar", mas mantem a conciliacao — sem conta padrao, cai como previsto.
- Editar de a vista para faturado (ou vice-versa) num lancamento **ja liquidado** respeita a baixa existente (caixa manda); o usuario ajusta manualmente se preciso.
- Mapeamento de `oil` -> categoria 3.6 (Lubrificantes), nao previsto explicitamente na 6.3 mas coerente com o plano de contas.

### Pendencias conhecidas

- Sinalizacao de km/l fora de +-40% da media dos ultimos 5 (aviso, nao bloqueio) ainda nao exibida na listagem.
- Vinculo de motorista, viagem e posto (parceiro) ao abastecimento fica para quando esses cadastros/telas existirem.

### Validacoes da etapa 0.24

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=1G` (0 erros, nivel 6)
- `composer test` (179 testes)
- `npm run build`

## 2026-07-18 - Etapa 0.25 (manutencoes)

Fase 4. Modulo de manutencoes fechando o lado de custos do DRE, com despesa sincronizada no financeiro (regra 7). Escopo de campos completo (blueprint 5.5). Detalhamento por item (`maintenance_items`) fica para depois — por ora so os totais mao de obra + pecas.

### Entregas da etapa 0.25

- **Enums** `MaintenanceType` (preventiva/corretiva/preditiva/pneus/revisao geral/sinistro; `isFixedCost()` isola a preventiva), `MaintenanceCategory` (motor, transmissao, freios, suspensao, eletrica, pneus, funilaria, carreta, documentacao, outros), `MaintenanceStatus` (aberta/em andamento/concluida/cancelada).
- **Migration + model `Maintenance`** (`BelongsToCompany`, soft delete, casts; `opened_at`/`closed_at`/`next_service_at` date, `labor_cents`/`parts_cents`/`total_cents` bigint, `downtime_hours` decimal(6,2)). Indices (`company_id`,`vehicle_id`,`opened_at`) e (`company_id`,`status`).
- **Actions** `CreateMaintenance`/`UpdateMaintenance`/`DeleteMaintenance` (+ `MaintenanceData` DTO e `MaintenancePolicy` owner/admin): `total_cents = labor_cents + parts_cents` no DTO, validam veiculo da empresa ativa, atualizam `odometer_current` quando maior.
- **`EntrySynchronizer::syncFromMaintenance`** + `MaintenanceObserver`: manutencao vira despesa **prevista a pagar** (blueprint 6.3: `paid_at` nulo, sem conta — o usuario da a baixa depois). Categoria 4.3 (preventiva, custo fixo) ou 3.4 (demais, corretiva). `competence_date = closed_at ?? opened_at`. Status `cancelada` ou soft delete cancela o lancamento; preserva baixa ja conciliada ao reeditar.
- **HTTP + telas** (`/manutencoes`): requests `Store`/`Update` (reais->centavos via `Brl`, `closed_at` obrigatoria quando concluida), controllers single-action (list com filtros veiculo/tipo/situacao/periodo + total exceto canceladas, create/store/show/edit/update/destroy), views `index`/`_form`/`create`/`edit`/`show` (custos, parada, proxima revisao e link para o lancamento; total calculado no form via JS). Nav "Manutencoes" ligada.
- **Teste `MaintenanceManagementTest`**: corretiva gera despesa prevista na 3.4 com competencia na abertura; preventiva cai na 4.3 com competencia na conclusao; conclusao obrigatoria quando concluida; cancelar status e excluir cancelam o lancamento; isolamento por grupo.

### Decisoes da etapa 0.25

- **Sempre prevista a pagar** (blueprint 6.3): manutencao nao tem "a vista" como abastecimento — a oficina normalmente fatura, e o usuario concilia a baixa depois no financeiro.
- **Preventiva = 4.3, demais = 3.4**: preventiva e custo fixo planejado; corretiva/pneus/sinistro sao custo variavel.
- **Itens detalhados adiados**: `maintenance_items` (peca/servico por linha com recalculo do total) fica para quando houver necessidade; por ora `labor` + `parts` -> `total`.

### Validacoes da etapa 0.25

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=1G` (0 erros, nivel 6)
- `composer test` (185 testes)
- `npm run build`

## 2026-07-18 - Etapa 0.26 (cadastro simples de motoristas)

Cadastro enxuto de motoristas (Fleet), conforme pedido: nome, CPF, CNH (numero, categoria e vencimento para alertas). Tabela `drivers` reduzida ao essencial, com colunas nulaveis prontas para crescer ate a especificacao completa do blueprint 5.2 sem migration destrutiva.

### Entregas da etapa 0.26

- **Suporte `App\Support\Cpf\Cpf`** (digits/format/isValid com os dois digitos verificadores) — espelha o utilitario de CNPJ; fonte unica do checksum. Teste unitario `CpfTest`.
- **Enums** `CnhCategory` (A..E, AB, AC, AD, AE com rotulo descritivo) e `DriverStatus` (ativo/inativo).
- **Migration + model `Driver`** (`BelongsToCompany`, soft delete): `name`, `cpf`, `cnh_number`, `cnh_category`, `cnh_expires_at`, `status`, `notes`, `user_id` nulavel (forward-compat). Metodos `cnhDaysToExpire()` e `cnhAlert()` (`expired`/`expiring`/null) com janela de 30 dias. Indices (`company_id`,`status`) e (`company_id`,`cnh_expires_at`).
- **Actions** `CreateDriver`/`UpdateDriver`/`DeactivateDriver` (+ `DriverData` DTO e `DriverPolicy` owner/admin): CPF unico por empresa checado em nivel de aplicacao (como a placa do veiculo).
- **HTTP + telas** (`/motoristas`): requests `Store`/`Update` (CPF normalizado para digitos e validado por checksum; `size:11`), controllers single-action (list com busca por nome/CPF, filtro de situacao e filtro "so CNH a vencer"; create/store/show/edit/update/destroy), views `index`/`_form`/`create`/`edit`/`show`. Mascara de CPF no input (JS) e badge de alerta de CNH na listagem e no detalhe. Nav "Motoristas" ligada em Frota.
- **Teste `DriverManagementTest`**: cadastro com CNH; CPF invalido bloqueado; CPF duplicado por empresa bloqueado; alerta de CNH vencida/a vencer; atualizacao; isolamento por grupo.

### Decisoes da etapa 0.26

- **Escopo enxuto** (pedido do usuario): so os campos essenciais + alerta de CNH. A tabela do blueprint 5.2 (MOPP, toxicologico, remuneracao, endereco, vinculo com veiculo) fica para quando o modulo de folha/DRE precisar — colunas adicionadas depois, sem quebrar o cadastro atual.
- **Alerta de CNH visual agora, job diario depois**: a listagem e o detalhe ja destacam CNH vencida/a vencer; a notificacao no sino + e-mail (blueprint 5.2) entra junto com a infra de notificacoes.
- **CPF unico em nivel de aplicacao** (nao indice DB unico) para conviver com soft delete e re-cadastro, seguindo o padrao da placa de veiculo.

### Validacoes da etapa 0.26

- `vendor/bin/pint`
- `vendor/bin/phpstan analyse --memory-limit=1G` (0 erros, nivel 6)
- `composer test` (194 testes)
- `npm run build`
