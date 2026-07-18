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
