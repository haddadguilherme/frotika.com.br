# Frotika — Blueprint de Desenvolvimento

> **Documento mestre para o agente de desenvolvimento.**
> Sistema de gestão para micro transportadoras — `frotika.com.br`
> Stack: Laravel 13.8 · PHP 8.3 · Tailwind CSS v4 · Livewire · MySQL 8
> Versão do documento: 1.1 · Julho/2026

---

## Índice

1. [Contexto e escopo](#1-contexto-e-escopo)
2. [Decisões de arquitetura (ADRs)](#2-decisões-de-arquitetura-adrs)
3. [Modelo de tenancy](#3-modelo-de-tenancy)
4. [Papéis e permissões](#4-papéis-e-permissões)
5. [Modelo de dados](#5-modelo-de-dados)
6. [Módulos funcionais](#6-módulos-funcionais)
7. [Importação de CT-e](#7-importação-de-ct-e)
8. [Financeiro e fluxo de caixa](#8-financeiro-e-fluxo-de-caixa)
9. [DRE Veicular](#9-dre-veicular)
10. [Assinatura e cobrança (SaaS)](#10-assinatura-e-cobrança-saas)
11. [Painel da plataforma (dono do sistema)](#11-painel-da-plataforma-dono-do-sistema)
12. [Design system](#12-design-system)
13. [Layout da aplicação](#13-layout-da-aplicação)
14. [Convenções de código](#14-convenções-de-código)
15. [Testes](#15-testes)
16. [Roadmap por fases](#16-roadmap-por-fases)
17. [Glossário](#17-glossário)
18. [Pontos que exigem validação](#18-pontos-que-exigem-validação)

---

## 1. Contexto e escopo

### 1.1 Problema

Micro transportadoras (1 a 15 veículos) operam com controle financeiro em planilhas ou no papel. Não sabem se um veículo específico dá lucro. O CT-e — documento que já existe e já tem os dados de receita — não é aproveitado. O resultado é decisão por intuição: renovar frota, aceitar frete, trocar de rota.

### 1.2 Proposta

Um sistema onde o operador lança o que já acontece no dia a dia (viagem, abastecimento, manutenção) e recebe de volta a **saúde financeira de cada veículo**. A receita entra automaticamente pelo XML do CT-e. Os custos entram por lançamento simples. O resultado sai no **DRE Veicular**.

### 1.3 Escopo do MVP

| Incluído | Fora do MVP |
| --- | --- |
| Cadastro de grupo/empresas/usuários | Emissão de CT-e / MDF-e |
| Veículos (cavalo, carreta, conjunto) | Roteirização / rastreamento em tempo real |
| Motoristas | Folha de pagamento completa |
| Viagens via XML de CT-e | Integração contábil (SPED) |
| Abastecimentos | App mobile nativo |
| Manutenções | CT-e OS (modelo 67) e CT-e Globalizado |
| Contas, plano de contas e fluxo de caixa | Conciliação bancária automática (OFX/Open Finance) |
| DRE Veicular | Multi-idioma |
| Assinatura e cobrança | |
| Painel da plataforma | |

### 1.4 Persona e prioridade

O usuário típico é o **dono da transportadora**, que também dirige, ou uma pessoa no escritório fazendo tudo. Não é contador. Não é usuário avançado. Implicações de produto, obrigatórias:

- **Toda tela precisa funcionar em 3 cliques a partir da home.**
- **Nenhum jargão contábil sem tradução.** "Competência" vira "Data do serviço". "Regime de caixa" não aparece na tela.
- **Lançamento em massa importa mais que lançamento perfeito.** Importar 40 XMLs de uma vez é o fluxo real.
- **O celular é o dispositivo do motorista.** Abastecimento e manutenção precisam ser lançáveis do celular, com foto do cupom.

---

## 2. Decisões de arquitetura (ADRs)

Cada decisão abaixo está **tomada**. O agente deve seguir. Mudanças exigem registrar novo ADR em `docs/adr/`.

### ADR-001 — Monólito Laravel com Livewire

Livewire + Alpine + Blade, sem SPA. Motivo: equipe pequena, um único desenvolvedor principal com domínio de PHP/Laravel, telas majoritariamente CRUD + relatórios. Inertia/React adicionaria uma segunda stack sem ganho proporcional.

### ADR-002 — Multi-tenancy de banco único com coluna discriminadora

Um banco, `group_id` e `company_id` nas tabelas, global scopes no Eloquent. Motivo: dezenas/centenas de tenants pequenos; banco por tenant multiplicaria custo de migração e infra sem necessidade. Ver [seção 3](#3-modelo-de-tenancy).

### ADR-003 — MySQL 8

Motivo: infraestrutura padrão do ambiente de hospedagem e familiaridade da equipe. O MySQL 8 cobre o que o produto precisa: tipo `JSON` nativo, CTEs recursivas para o plano de contas hierárquico e window functions para o DRE. Requisitos monetários continuam atendidos por `bigInteger` em centavos (ADR-004) — sem dependência de tipo decimal do banco.

**Restrições em relação ao Postgres, tratadas explicitamente:**

- **Índice único parcial não existe no MySQL.** Regras como "uma única conta `is_default = true` por empresa" não usam `CREATE UNIQUE INDEX ... WHERE`. Alternativas, nesta ordem de preferência: (a) coluna gerada que vira `NULL` quando `is_default = false` + índice único sobre `(company_id, coluna_gerada)`; (b) garantir a unicidade na Action, dentro de transação.
- **`JSON` (não `jsonb`).** MySQL 8 armazena JSON em formato binário próprio e indexa via colunas geradas + índice funcional quando necessário.
- **Charset/collation:** `utf8mb4` / `utf8mb4_0900_ai_ci` como padrão do banco.

> Migração de banco (ex.: voltar ao Postgres) exige novo ADR. Este substitui a decisão original de PostgreSQL 16.

### ADR-004 — Valores monetários em centavos (`bigInteger`)

Todo valor total/monetário é armazenado como inteiro em centavos. Preços unitários com fração (R$/litro) usam `decimal(10,3)`. Motivo: zero erro de ponto flutuante em um sistema cujo produto final é um demonstrativo financeiro.

- Cast dedicado: `App\Casts\Money` → objeto `Money` (`brick/money` ou implementação própria).
- **Regra de rateio:** distribuição por maior resto. A soma das partes é sempre exatamente igual ao todo. Testar.

### ADR-005 — Duas datas em todo lançamento financeiro

- `competence_date` — quando o fato aconteceu (dirige o **DRE**).
- `paid_at` — quando o dinheiro saiu/entrou (dirige o **Fluxo de Caixa**).

Nunca derivar uma da outra. Ver [seção 8.1](#81-os-dois-regimes).

### ADR-006 — Lançamento financeiro é sempre a fonte da verdade

Abastecimento, manutenção e CT-e **geram** lançamentos financeiros. Nenhum relatório soma direto da tabela de abastecimentos. Motivo: uma única superfície de agregação, um único conjunto de regras de rateio, um único ponto de teste.

### ADR-007 — Import de CT-e é assíncrono e idempotente

Upload → fila → parse → persistência. A chave de acesso é única por empresa. Reimportar o mesmo XML atualiza, nunca duplica.

### ADR-008 — Código em inglês, interface em pt-BR

Tabelas, models, colunas e valores de enum em inglês. Rótulos em pt-BR via método `label()` nos enums e arquivos `lang/pt_BR`. Exceções mantidas em pt-BR/sigla: `cte`, `rntrc`, `cnh`, `cpf`, `cnpj`, `arla`, `pix`. Ver [glossário](#17-glossário).

### 2.1 Dependências

```bash
# Base (já no skeleton)
laravel/framework ^13.8 · laravel/tinker ^3.0 · PHP ^8.3

# Produção
composer require livewire/livewire
composer require spatie/laravel-permission      # papéis com teams = group_id
composer require spatie/laravel-activitylog     # auditoria
composer require spatie/laravel-pdf             # DRE em PDF
composer require spatie/simple-excel            # exportações
composer require laravel/horizon                # filas
composer require brick/money                    # aritmética monetária

# Desenvolvimento
composer require --dev laravel/boost            # ferramentas para o agente
composer require --dev larastan/larastan
composer require --dev laravel/telescope
composer require --dev rector/rector

# Front
npm i -D tailwindcss @tailwindcss/vite
```

> `laravel/pao` já vem no skeleton e reduz drasticamente o ruído de saída do Artisan/PHPUnit quando rodando dentro de agente. Não remover.

Após instalar Boost: `php artisan boost:install`.

---

## 3. Modelo de tenancy

### 3.1 Hierarquia

```
Group (tenant / assinatura / faturamento)
 ├── owner: User            ← recebe as notificações de cobrança
 ├── Company (1..N)         ← unidade fiscal, CNPJ, dona dos dados operacionais
 │    ├── Vehicle
 │    ├── Driver
 │    ├── Trip
 │    ├── BankAccount
 │    └── FinancialEntry
 └── User (1..N)            ← acesso ao grupo, com escopo por empresa
```

**Regras invioláveis:**

1. Todo dado operacional pertence a **uma** `Company`. Nunca a um `Group` diretamente.
2. Assinatura, plano e fatura pertencem ao **`Group`**, não à `Company`. Um grupo com 3 empresas tem 1 assinatura.
3. Um `User` pertence a **um** `Group` (papel no grupo) e tem acesso a **N** `Companies` daquele grupo.
4. `Group.type = 'platform'` identifica o grupo do dono do sistema (Frotika). Exatamente um registro. Ver [seção 11](#11-painel-da-plataforma-dono-do-sistema).

### 3.2 Cadastro inicial (onboarding)

```
1. Usuário preenche: nome, e-mail, senha, CNPJ da empresa, nome fantasia
2. Transação:
   a. cria User
   b. cria Group (type=customer, owner_user_id=User, trial_ends_at=now+14d)
   c. cria Company (group_id, CNPJ, dados da Receita se disponíveis)
   d. cria group_user (role=owner)
   e. cria company_user (User ↔ Company)
   f. cria Subscription (plano trial)
   g. semeia plano de contas padrão da Company
   h. cria BankAccount padrão ("Caixa", saldo inicial 0)
3. Dispara e-mail de boas-vindas
4. Redireciona para wizard: [saldo inicial] → [primeiro veículo] → [primeiro motorista]
```

O wizard é pulável, mas o dashboard mostra checklist de onboarding até completo.

> **Implementado (etapa 0.11).** A consulta da Receita do passo `2c` usa `App\Support\Cnpj\CnpjLookup` — BrasilAPI como fonte primária e ReceitaWS como fallback — exposta em `GET /registrar/cnpj/{cnpj}` (`LookupCnpjController`). No formulário de cadastro o CNPJ tem máscara e limite de 14 dígitos; ao completá-lo, razão social e nome fantasia são preenchidas automaticamente, com destravamento para preenchimento manual quando o CNPJ não é encontrado ou as APIs estão indisponíveis. Endereço, telefone e e-mail já são normalizados pelo serviço (as colunas existem em `companies`), mas **ainda não são persistidos** no onboarding — ver [seção 16.1](#161-estado-atual-e-sequência-de-continuação).

### 3.3 Implementação do isolamento

**`App\Support\Tenancy\TenantContext`** — singleton no container, resolvido por request:

```php
final class TenantContext
{
    private ?Group $group = null;
    private ?Company $company = null;

    public function group(): ?Group { ... }
    public function company(): ?Company { ... }
    public function companyId(): ?int { ... }
    public function setCompany(Company $company): void { ... }
    public function runFor(Company $company, Closure $callback): mixed { ... } // jobs
    public function runWithoutTenant(Closure $callback): mixed { ... }         // admin/plataforma
}
```

**Middleware `SetTenantContext`** (grupo `web`, após `auth`):

1. Lê `session('current_company_id')`, com fallback para `users.current_company_id`.
2. **Valida que o usuário tem acesso àquela empresa.** Falha → 403, não fallback silencioso.
3. Popula o `TenantContext`.

**Trait `BelongsToCompany`**:

```php
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (Model $model): void {
            $model->company_id ??= app(TenantContext::class)->companyId()
                ?? throw new MissingTenantContextException($model::class);
        });
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
```

> **`MissingTenantContextException` em vez de `company_id = null`.** Falhar alto é a única forma de garantir que um bug de contexto não vire vazamento de dados silencioso.

**Filas.** O `TenantContext` **não** é serializado automaticamente. Todo job com dado de tenant carrega `company_id` explícito no construtor e abre o contexto:

```php
final class ImportCteJob implements ShouldQueue
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $xmlPath,
    ) {}

    public function handle(TenantContext $tenant, CteImporter $importer): void
    {
        $tenant->runFor(Company::withoutGlobalScopes()->findOrFail($this->companyId),
            fn () => $importer->import($this->xmlPath),
        );
    }
}
```

**Proibido:** `withoutGlobalScope(CompanyScope::class)` fora de `app/Platform/**` e de repositórios explicitamente marcados. Adicionar regra no Larastan ou teste de arquitetura.

---

## 4. Papéis e permissões

`spatie/laravel-permission` com **teams habilitado**, `team_id = group_id`.

| Papel | Chave | O que faz |
| --- | --- | --- |
| Proprietário | `owner` | Tudo no grupo. **Único que recebe cobrança.** Não pode ser removido nem rebaixado enquanto for o único. |
| Administrador | `admin` | Tudo, exceto faturamento e exclusão do grupo. |
| Financeiro | `finance` | Lançamentos, contas, plano de contas, DRE, relatórios. Não altera veículos/motoristas. |
| Operacional | `operations` | Viagens, CT-e, abastecimentos, manutenções, veículos, motoristas. Vê custo, não vê fluxo de caixa consolidado. |
| Consulta | `viewer` | Somente leitura. |

**Fora dos papéis do grupo:** `users.is_platform_admin` (boolean). Não é papel Spatie — é flag de plataforma, verificada por `Gate::before` e pelo middleware `EnsurePlatformAdmin`.

### 4.1 Permissões

Nomear `{recurso}.{ação}`: `vehicle.view`, `vehicle.create`, `vehicle.update`, `vehicle.delete`, `trip.*`, `cte.import`, `fueling.*`, `maintenance.*`, `financial_entry.*`, `bank_account.*`, `report.dre`, `report.cashflow`, `user.manage`, `company.manage`, `billing.manage`.

### 4.2 Transferência de propriedade

Fluxo obrigatório: `owner` atual seleciona outro `admin` → confirmação por e-mail com token → troca `groups.owner_user_id` e os papéis → registra em activity log. Sem esse fluxo, um grupo pode ficar órfão de responsável financeiro.

---

## 5. Modelo de dados

> Convenções: toda tabela tem `id` (bigIncrements), `created_at`, `updated_at`. Tabelas de cadastro e lançamento têm `deleted_at` (soft delete). Valores monetários = `bigInteger` em centavos, sufixo `_cents`. Todo índice de tenant começa por `company_id`.

### 5.1 Tenancy

**`groups`**

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `uuid` | uuid, unique | exposto em URLs |
| `name` | string(120) | |
| `type` | enum | `customer` \| `platform` |
| `owner_user_id` | FK users | responsável pela cobrança |
| `status` | enum | `active` \| `suspended` \| `canceled` |
| `settings` | json | preferências do grupo |
| `deleted_at` | timestamp | |

**`companies`**

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `group_id` | FK groups | index |
| `uuid` | uuid, unique | |
| `cnpj` | char(14), unique | somente dígitos |
| `legal_name` | string(150) | razão social |
| `trade_name` | string(150) | nome fantasia |
| `state_registration` | string(20), null | IE |
| `rntrc` | string(12), null | |
| `tax_regime` | enum | `simples` \| `presumido` \| `real` |
| `zip_code`,`street`,`number`,`complement`,`district`,`city`,`state`,`ibge_code` | | endereço |
| `phone`, `email` | | |
| `logo_path` | string, null | |
| `settings` | json | ex.: critério de rateio, moeda |
| `deleted_at` | timestamp | |

**`users`** — além do padrão Laravel:

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `phone` | string(20), null | |
| `is_platform_admin` | boolean, default false | |
| `current_group_id` | FK groups, null | |
| `current_company_id` | FK companies, null | |
| `preferences` | json | sidebar colapsada, atalhos fixados, tema |
| `last_login_at` | timestamp, null | |

**`group_user`** — `group_id`, `user_id`, `role`, `invited_by`, `joined_at`. Unique(`group_id`,`user_id`).

**`company_user`** — `company_id`, `user_id`. Unique(`company_id`,`user_id`).

**`invitations`** — `group_id`, `email`, `role`, `company_ids` (json), `token`, `expires_at`, `accepted_at`, `invited_by`.

### 5.2 Cadastros

**`vehicles`**

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `company_id` | FK | |
| `type` | enum | `tractor` (cavalo), `semi_trailer` (carreta), `truck`, `toco`, `vuc`, `utility` |
| `plate` | string(8) | unique(`company_id`,`plate`); aceitar padrão antigo e Mercosul |
| `renavam` | string(11), null | |
| `chassis` | string(17), null | |
| `brand`, `model` | string | |
| `year_manufacture`, `year_model` | smallint | |
| `axles` | tinyint | eixos |
| `body_type` | enum, null | só para reboques: `sider`, `bau`, `graneleiro`, `tanque`, `prancha`, `frigorifico`, `cacamba`, `porta_container` |
| `tare_kg`, `capacity_kg`, `capacity_m3` | int/decimal, null | |
| `fuel_type` | enum, null | `diesel_s10`, `diesel_s500`, `gasoline`, `ethanol`, `cng`, `electric` |
| `tank_capacity_l` | int, null | |
| `ownership` | enum | `own`, `leased`, `aggregate` (agregado), `third_party` |
| `odometer_initial` | int | leitura no cadastro |
| `odometer_current` | int | denormalizado, atualizado por viagem/abastecimento |
| `acquisition_date` | date, null | |
| `acquisition_value_cents` | bigint, null | |
| `residual_value_cents` | bigint, null | valor de revenda estimado |
| `depreciation_months` | smallint, null | vida útil; null = sem depreciação |
| `status` | enum | `active`, `inactive`, `maintenance`, `sold` |
| `notes` | text, null | |

**`vehicle_compositions`** — o conjunto cavalo + carreta(s), com vigência.

`company_id`, `tractor_vehicle_id` (FK vehicles), `name`, `started_at` (date), `ended_at` (date, null = vigente).
Índice: (`company_id`, `tractor_vehicle_id`, `started_at`).

**`vehicle_composition_items`** — `vehicle_composition_id`, `trailer_vehicle_id`, `position` (1 = primeira carreta).

> **Por que existe:** o custo de pneu e manutenção da carreta precisa entrar no DRE do conjunto que gerou a receita. Sem vigência, não há como saber qual carreta estava atrás do cavalo em março.

**`drivers`**

`company_id`, `user_id` (null), `name`, `cpf` (unique por company), `birth_date`, `phone`, `email`, `cnh_number`, `cnh_category` (enum `A`..`E`, `AE`...), `cnh_expires_at`, `cnh_first_license_at`, `has_mopp` (bool), `mopp_expires_at`, `toxicological_exam_at`, `hired_at`, `terminated_at`, `pay_type` (enum `fixed`, `commission`, `per_km`, `mixed`), `pay_base_cents`, `commission_percent` (decimal 5,2), `per_km_cents`, `status` (enum `active`, `inactive`, `terminated`), endereço, `notes`.

> **Alertas obrigatórios:** CNH vencendo em 30 dias, MOPP vencendo, exame toxicológico vencendo. Job diário → notificação no sino + e-mail.

**`driver_vehicle`** — vínculo com vigência: `driver_id`, `vehicle_id`, `started_at`, `ended_at`.

**`business_partners`** (substitui `suppliers` — ver ADR-004) — `company_id`, `document` (null), `document_type` (enum `cnpj`, `cpf`, `none`), `legal_name`, `trade_name` (null), `kind` (enum `contractor`, `customer`, `carrier`, `gas_station`, `workshop`, `parts`, `other`), `default_freight_share_percent` (decimal 5,2, null — % do frete que a contratante repassa ao agregado), `state_registration`, `phone`, `email`, endereço (`zip_code`, `street`, `number`, `complement`, `district`, `city`, `state`, `ibge_code`), `notes`, `active`, soft deletes. `unique(company_id, document)`. Cadastro único deduplicado por documento; a importação de CT-e cadastra as partes automaticamente (emitente = `contractor`) e enriquece sem sobrescrever edição manual.

### 5.3 Viagens e CT-e

**`cte_documents`**

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `company_id` | FK | |
| `access_key` | char(44) | **unique(`company_id`,`access_key`)** |
| `layout_version` | string(6) | do atributo `versao` do XML |
| `number`, `series` | int | nCT, série |
| `model` | smallint | 57 |
| `cte_type` | enum | `normal`(0), `complement`(1), `annulment`(2), `substitute`(3) |
| `service_type` | enum | `normal`(0), `subcontract`(1), `redispatch`(2), `intermediary_redispatch`(3), `multimodal`(4) |
| `cfop`, `operation_nature` | string | |
| `modal` | string(2) | `01` rodoviário |
| `issued_at` | timestamptz | dhEmi |
| `issuer_cnpj`, `issuer_name` | | emit |
| `origin_city`, `origin_state`, `origin_ibge` | | |
| `destination_city`, `destination_state`, `destination_ibge` | | |
| `taker_role` | enum | `sender`, `recipient`, `dispatcher`, `receiver`, `other` |
| `taker_document`, `taker_name` | | |
| `sender_document`, `sender_name` | | |
| `recipient_document`, `recipient_name` | | |
| `total_value_cents` | bigint | vTPrest |
| `receivable_value_cents` | bigint | vRec |
| `icms_value_cents` | bigint | |
| `cargo_value_cents` | bigint, null | vCarga |
| `cargo_weight_kg` | decimal(12,3), null | infQ, tpMed peso |
| `cargo_description` | string, null | proPred |
| `rntrc` | string(12), null | |
| `referenced_key` | char(44), null | chave do CT-e referenciado (compl./anul./subst.) |
| `status` | enum | `authorized`, `canceled`, `denied`, `pending` |
| `protocol_number` | string(20), null | |
| `xml_path` | string | caminho no disco/objeto |
| `xml_hash` | char(64) | sha256 do conteúdo |
| `raw` | json | payload parseado completo (rede de segurança) |
| `imported_by` | FK users, null | |
| `imported_at` | timestamptz | |

**`trips`**

`company_id`, `code` (sequencial por empresa), `vehicle_id` (o cavalo/veículo trator), `vehicle_composition_id` (null), `driver_id`, `origin_city`, `origin_state`, `destination_city`, `destination_state`, `departed_at`, `arrived_at`, `odometer_start`, `odometer_end`, `distance_km` (int, calculado ou informado), `empty_km` (int, null — km vazio), `cargo_weight_kg`, `revenue_cents` (soma dos CT-es), `status` (enum `planned`, `in_progress`, `completed`, `canceled`), `notes`.

**`trip_cte_document`** — `trip_id`, `cte_document_id`, `revenue_share_cents`.
Uma viagem pode ter N CT-es. Um CT-e pertence a no máximo 1 viagem. Quando N CT-es numa viagem, cada um mantém sua receita própria — `revenue_share_cents` existe para o caso inverso: um CT-e cobrindo mais de um veículo (raro; ratear por km).

### 5.4 Abastecimentos

**`fuelings`**

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `company_id` | FK | |
| `vehicle_id` | FK | |
| `driver_id` | FK, null | |
| `trip_id` | FK, null | |
| `supplier_id` | FK, null | posto |
| `fueled_at` | timestamptz | |
| `odometer` | int | |
| `product` | enum | `diesel_s10`, `diesel_s500`, `arla32`, `gasoline`, `ethanol`, `cng`, `oil` |
| `liters` | decimal(10,3) | |
| `price_per_liter` | decimal(10,3) | em reais, 3 casas |
| `total_cents` | bigint | |
| `full_tank` | boolean | **crítico para o cálculo de consumo** |
| `tank` | enum | `main`, `auxiliary` |
| `station_name`, `station_city`, `station_state` | | |
| `invoice_number` | string, null | |
| `payment_method` | enum | `cash`, `pix`, `fuel_card`, `credit`, `debit`, `invoice` |
| `receipt_path` | string, null | foto do cupom |
| `km_since_last` | int, null | **calculado** |
| `km_per_liter` | decimal(6,3), null | **calculado** |
| `notes` | text, null | |

Índice: (`company_id`,`vehicle_id`,`fueled_at`).

**Regra de cálculo de consumo — implementar exatamente assim:**

```
Consumo (km/l) só é calculado entre dois abastecimentos com full_tank = true,
do mesmo veículo, mesmo tanque, produto combustível (arla32 e oil NUNCA entram).

km_since_last = odometer(atual) - odometer(último full_tank anterior)
litros_do_intervalo = SOMA dos litros de TODOS os abastecimentos de combustível
                      posteriores ao último full_tank e até o atual (inclusive)
km_per_liter = km_since_last / litros_do_intervalo

Se não houver full_tank anterior → km_per_liter = null (não é zero).
Se odometer atual <= odometer anterior → erro de validação, bloquear.
```

> **Por que:** somar tudo e dividir dá um número errado e o dono da transportadora vai tomar decisão com ele. Tanque parcial não fecha o intervalo. Arla é consumível de emissão, não combustível — misturá-lo distorce o km/l em ~5%.

**Validações:**
- `odometer` deve ser ≥ ao último odômetro conhecido do veículo (viagem ou abastecimento). Se menor, exigir confirmação explícita ("troca de painel / correção").
- `liters` ≤ `tank_capacity_l × 1.15` → aviso, não bloqueio.
- `km_per_liter` fora de ±40% da média dos últimos 5 → sinalizar na listagem, não bloquear.

### 5.5 Manutenções

**`maintenances`**

`company_id`, `vehicle_id`, `supplier_id` (null), `type` (enum `preventive`, `corrective`, `predictive`, `tire`, `overhaul`, `accident`), `category` (enum `engine`, `transmission`, `brakes`, `suspension`, `electrical`, `tires`, `bodywork`, `trailer`, `documentation`, `other`), `opened_at`, `closed_at` (null), `odometer`, `workshop_name`, `invoice_number`, `labor_cents`, `parts_cents`, `total_cents`, `description` (text), `status` (enum `open`, `in_progress`, `completed`, `canceled`), `downtime_hours` (decimal, null), `next_service_odometer` (int, null), `next_service_at` (date, null), `attachment_path`, `notes`.

**`maintenance_items`** — `maintenance_id`, `kind` (enum `part`, `service`), `description`, `part_number` (null), `quantity` (decimal 10,3), `unit_price_cents`, `total_cents`.

> `total_cents` da manutenção = `labor_cents` + `parts_cents`. Se houver itens, recalcular a partir deles. Um observer garante a consistência.

### 5.6 Financeiro

**`bank_accounts`**

`company_id`, `name`, `type` (enum `cash`, `checking`, `savings`, `investment`, `credit_card`), `bank_code` (null), `agency` (null), `number` (null), `initial_balance_cents`, `initial_balance_at` (date), `current_balance_cents` (denormalizado), `is_default` (bool), `active` (bool), `notes`.

> Somente **uma** conta `is_default = true` por empresa. MySQL não tem índice único parcial; use coluna gerada que só recebe valor quando a conta é padrão e ainda está ativa, com índice único sobre ela:
> ```sql
> ALTER TABLE bank_accounts
>   ADD COLUMN default_flag TINYINT
>     GENERATED ALWAYS AS (CASE WHEN is_default = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END) VIRTUAL,
>   ADD UNIQUE KEY uq_bank_accounts_default (company_id, default_flag);
> ```
> Reforce também a unicidade na Action, dentro de transação.

**`financial_categories`** — plano de contas hierárquico.

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `company_id` | FK, **null** | null = categoria padrão do sistema |
| `parent_id` | FK self, null | |
| `code` | string(20) | ex.: `3.1.02` |
| `name` | string(120) | |
| `type` | enum | `revenue`, `expense` |
| `dre_group` | enum | `gross_revenue`, `deductions`, `variable_cost`, `fixed_cost`, `admin_expense`, `financial_expense`, `non_operating`, `investment` |
| `allocation` | enum | `vehicle_direct` (custo direto do veículo), `apportioned` (rateado), `non_vehicle` (fora do DRE veicular) |
| `affects_cashflow` | boolean | **false para depreciação e provisões** |
| `is_system` | boolean | não editável/removível |
| `active` | boolean | |

**Plano de contas padrão (seeder — implementar este conteúdo):**

```
1. RECEITAS
  1.1 Receita de fretes .......... revenue / gross_revenue / vehicle_direct   [system]
  1.2 Receita de aluguel de veículo revenue / gross_revenue / vehicle_direct
  1.3 Outras receitas ............ revenue / non_operating / non_vehicle
2. DEDUÇÕES DA RECEITA
  2.1 ICMS sobre fretes .......... expense / deductions / vehicle_direct      [system]
  2.2 PIS/COFINS ................. expense / deductions / vehicle_direct
  2.3 Comissão sobre frete ....... expense / deductions / vehicle_direct
3. CUSTOS VARIÁVEIS
  3.1 Combustível ................ expense / variable_cost / vehicle_direct   [system]
  3.2 Arla 32 .................... expense / variable_cost / vehicle_direct   [system]
  3.3 Pedágio .................... expense / variable_cost / vehicle_direct
  3.4 Manutenção corretiva ....... expense / variable_cost / vehicle_direct   [system]
  3.5 Pneus ...................... expense / variable_cost / vehicle_direct
  3.6 Lubrificantes .............. expense / variable_cost / vehicle_direct
  3.7 Diárias e adiantamentos .... expense / variable_cost / vehicle_direct
  3.8 Chapa / carga e descarga ... expense / variable_cost / vehicle_direct
4. CUSTOS FIXOS DO VEÍCULO
  4.1 Salário do motorista ....... expense / fixed_cost / vehicle_direct
  4.2 Encargos do motorista ...... expense / fixed_cost / vehicle_direct
  4.3 Manutenção preventiva ...... expense / fixed_cost / vehicle_direct
  4.4 Seguro do veículo .......... expense / fixed_cost / vehicle_direct
  4.5 IPVA e licenciamento ....... expense / fixed_cost / vehicle_direct
  4.6 Rastreamento ............... expense / fixed_cost / vehicle_direct
  4.7 Parcela de financiamento ... expense / fixed_cost / vehicle_direct
  4.8 Depreciação ................ expense / fixed_cost / vehicle_direct  [affects_cashflow=false] [system]
5. DESPESAS ADMINISTRATIVAS
  5.1 Pró-labore ................. expense / admin_expense / apportioned
  5.2 Salários do escritório ..... expense / admin_expense / apportioned
  5.3 Contabilidade .............. expense / admin_expense / apportioned
  5.4 Aluguel e condomínio ....... expense / admin_expense / apportioned
  5.5 Energia, água, internet .... expense / admin_expense / apportioned
  5.6 Software e sistemas ........ expense / admin_expense / apportioned
  5.7 Outras despesas adm. ....... expense / admin_expense / apportioned
6. DESPESAS FINANCEIRAS
  6.1 Juros e multas ............. expense / financial_expense / apportioned
  6.2 Tarifas bancárias .......... expense / financial_expense / apportioned
  6.3 Antecipação de recebíveis .. expense / financial_expense / apportioned
7. INVESTIMENTOS
  7.1 Aquisição de veículo ....... expense / investment / non_vehicle
  7.2 Aquisição de equipamento ... expense / investment / non_vehicle
8. MOVIMENTAÇÕES NÃO OPERACIONAIS
  8.1 Aportes de sócios .......... revenue / non_operating / non_vehicle
  8.2 Retirada de sócios ......... expense / non_operating / non_vehicle
  8.3 Empréstimo captado ......... revenue / non_operating / non_vehicle
  8.4 Transferência entre contas . (tipo especial, ver 8.4)                   [system]
```

Cada empresa nasce com uma **cópia** dessas categorias (`company_id` preenchido), não com referência às globais. Motivo: o usuário precisa poder renomear "Chapa" para o que ele chama.

**`financial_entries`** — o registro central do sistema.

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `company_id` | FK | |
| `bank_account_id` | FK, null | null enquanto `status = forecast` |
| `financial_category_id` | FK | |
| `vehicle_id` | FK, null | preenchido = custo direto do veículo |
| `driver_id` | FK, null | |
| `trip_id` | FK, null | |
| `type` | enum | `revenue`, `expense`, `transfer` |
| `description` | string(200) | |
| `document_number` | string(50), null | |
| `competence_date` | date | **DRE** |
| `due_date` | date, null | vencimento |
| `paid_at` | date, null | **Fluxo de caixa**; null = não realizado |
| `amount_cents` | bigint | **sempre positivo**; o sinal vem de `type` |
| `status` | enum | `forecast`, `settled`, `canceled` |
| `payment_method` | enum, null | |
| `sourceable_type` / `sourceable_id` | morph, null | `Fueling`, `Maintenance`, `CteDocument`, `Trip`, `Invoice` |
| `transfer_pair_id` | FK self, null | |
| `recurrence_id` | FK, null | |
| `attachment_path` | string, null | |
| `reconciled_at` | timestamptz, null | |
| `created_by` | FK users | |

Índices:
```sql
(company_id, paid_at, status)                    -- fluxo de caixa
(company_id, competence_date, vehicle_id)        -- DRE veicular
(company_id, sourceable_type, sourceable_id)     -- idempotência da sincronização
(company_id, bank_account_id, paid_at)           -- extrato da conta
```

**`recurrences`** — `company_id`, template dos campos de `financial_entries`, `frequency` (enum `monthly`, `weekly`, `yearly`), `day_of_month`, `installments` (null = indefinido), `installments_generated`, `starts_at`, `ends_at`, `active`. Job mensal gera os `financial_entries` com `status = forecast`.

### 5.7 SaaS

**`plans`** — `name`, `slug`, `price_cents`, `billing_period` (enum `monthly`, `yearly`), `limits` (json: `max_vehicles`, `max_users`, `max_companies`), `features` (json), `trial_days`, `active`, `sort_order`.

**`subscriptions`** — `group_id`, `plan_id`, `status` (enum `trialing`, `active`, `past_due`, `canceled`, `expired`), `started_at`, `trial_ends_at`, `current_period_start`, `current_period_end`, `canceled_at`, `cancel_reason`, `gateway`, `gateway_customer_id`, `gateway_subscription_id`, `price_cents` (preço travado na contratação).

**`invoices`** — `group_id`, `subscription_id`, `number`, `amount_cents`, `due_date`, `paid_at`, `status` (enum `pending`, `paid`, `overdue`, `canceled`, `refunded`), `gateway_invoice_id`, `payment_method`, `pix_payload` (text, null), `boleto_url`, `pdf_url`, `attempts`, `last_attempt_at`.

**`gateway_webhook_events`** — `gateway`, `event_id` (unique), `event_type`, `payload` (json), `processed_at`, `error`. Idempotência de webhook.

### 5.8 Suporte

**`activity_log`** — do `spatie/laravel-activitylog`, com `group_id` e `company_id` adicionados nas properties.

**`attachments`** — `company_id`, `attachable_type`, `attachable_id`, `disk`, `path`, `original_name`, `mime`, `size_bytes`, `uploaded_by`.

**`notifications`** — tabela padrão do Laravel.

---

## 6. Módulos funcionais

### 6.1 Estrutura de pastas

```
app/
├── Domain/
│   ├── Tenancy/          Group, Company, Invitation, actions, policies
│   ├── Fleet/            Vehicle, VehicleComposition, Driver, Supplier
│   ├── Trips/            Trip, CteDocument
│   │   └── Cte/          CteReader, CteParser, CteImporter, DTOs, exceptions
│   ├── Fuelings/         Fueling, FuelConsumptionCalculator
│   ├── Maintenances/     Maintenance, MaintenanceItem
│   ├── Finance/          BankAccount, FinancialCategory, FinancialEntry,
│   │                     Recurrence, CashFlowService, EntrySynchronizer
│   ├── Reports/
│   │   ├── Dre/          DreBuilder, DreLine, DreResult, ApportionmentStrategy
│   │   └── CashFlow/
│   └── Billing/          Plan, Subscription, Invoice, Gateways/
├── Platform/             painel do dono do sistema (fora dos global scopes)
├── Http/
│   ├── Middleware/       SetTenantContext, EnsurePlatformAdmin, EnsureSubscribed
│   └── Controllers/
├── Livewire/
│   ├── Fleet/  Trips/  Fuelings/  Maintenances/  Finance/  Reports/
├── Support/
│   ├── Tenancy/          TenantContext, CompanyScope, BelongsToCompany
│   ├── Money/            Money cast, Rounding, Apportionment
│   └── Enums/
└── Casts/
```

Cada `Domain/X` tem: `Models/`, `Actions/`, `Data/` (DTOs), `Enums/`, `Policies/`, `Events/`, `Exceptions/`.

**Padrão de escrita:** toda mutação relevante passa por uma **Action** de método único (`execute()`/`handle()`), invocável de Livewire, de controller, de job e de teste. Livewire não contém regra de negócio.

### 6.2 Rotas

```
/                          → redireciona para /painel ou /entrar
/entrar  /registrar  /esqueci-a-senha
/onboarding
/painel                    → dashboard
/veiculos                  index · create · edit · show
/veiculos/{v}/conjuntos
/motoristas
/viagens
/viagens/importar-cte      → upload de XML/ZIP
/abastecimentos
/manutencoes
/financeiro/contas
/financeiro/plano-de-contas
/financeiro/lancamentos
/financeiro/fluxo-de-caixa
/relatorios/dre-veicular
/configuracoes/empresa  /usuarios  /assinatura
/admin/**                  → painel da plataforma
```

URLs em pt-BR (o usuário é brasileiro e URL é interface), código em inglês.

### 6.3 Sincronização lançamento ↔ origem

Implementar `App\Domain\Finance\EntrySynchronizer`, chamado por observers de `Fueling`, `Maintenance` e `CteDocument`:

```php
public function sync(Model $source): void
{
    // 1. Resolve categoria, veículo, datas e valor a partir da origem
    // 2. updateOrCreate em financial_entries por (sourceable_type, sourceable_id)
    // 3. Se a origem foi excluída (soft delete) → cancela o lançamento (status=canceled)
    // 4. Se a origem foi restaurada → reabre
}
```

**Mapeamento origem → lançamento:**

| Origem | type | Categoria | competence_date | paid_at | vehicle_id |
| --- | --- | --- | --- | --- | --- |
| `Fueling` (combustível) | expense | 3.1 Combustível | `fueled_at` | `fueled_at` se pagamento à vista; null se `invoice` | `vehicle_id` |
| `Fueling` (arla32) | expense | 3.2 Arla 32 | `fueled_at` | idem | `vehicle_id` |
| `Maintenance` | expense | 3.4 ou 4.3 conforme `type` | `closed_at ?? opened_at` | null (usuário informa) | `vehicle_id` |
| `CteDocument` (`normal`/`substitute`) | revenue | 1.1 Receita de fretes | `issued_at` | null | veículo da `trip` |
| `CteDocument` (`annulment`) | — | cancela o lançamento do CT-e referenciado | | | |
| `CteDocument` (`complement`) | revenue | 1.1 | `issued_at` | null | veículo da `trip` do referenciado |

> **Lançamento gerado é editável parcialmente.** O usuário pode ajustar `paid_at`, `bank_account_id`, `due_date` e categoria. Não pode alterar `amount_cents` nem `vehicle_id` — para isso, edita a origem. Marcar com `sourceable_type` preenchido e bloquear os campos na UI com explicação: *"Valor definido pelo abastecimento. Edite o abastecimento para alterar."*

---

## 7. Importação de CT-e

> ⚠️ **Antes de implementar, ler [seção 18](#18-pontos-que-exigem-validação).** As tags abaixo refletem o layout 3.00 do CT-e modelo 57 rodoviário. O agente deve validar contra XMLs reais fornecidos pelo usuário e contra o schema oficial da SEFAZ antes de fixar o parser.

### 7.1 Fluxo

```
Upload (múltiplos .xml ou .zip)
  → validação de extensão e tamanho (2 MB/arquivo, 50 MB/lote)
  → armazena bruto em xml/{company_uuid}/{YYYY}/{MM}/{chave}.xml
  → dispatch ImportCteJob por arquivo (batch)
  → CteReader: detecta envelope (cteProc | CTe | procEventoCTe)
  → CteParser: XML → CteData (DTO tipado)
  → CteImporter: valida empresa + persiste (updateOrCreate por access_key)
  → sugere/cria Trip
  → EntrySynchronizer gera o lançamento de receita
  → tela de resultado: importados / atualizados / ignorados / com erro
```

### 7.2 Envelopes a suportar

| Raiz | Situação |
| --- | --- |
| `<cteProc>` | CT-e + protocolo de autorização. **Caso padrão.** |
| `<CTe>` | CT-e sem protocolo. Importar com `status = pending`. |
| `<procEventoCTe>` | Evento. Se `tpEvento = 110111` (cancelamento) → marcar CT-e como `canceled` e cancelar o lançamento. |

Namespace: `http://www.portalfiscal.inf.br/cte`. **Registrar o namespace explicitamente** — `SimpleXML` sem `registerXPathNamespace` retorna vazio silenciosamente.

### 7.3 Mapeamento de campos (a validar)

| Campo | XPath (relativo a `infCte`) |
| --- | --- |
| `access_key` | `@Id` → remover prefixo `CTe` (44 dígitos) |
| `layout_version` | `@versao` |
| `number` | `ide/nCT` |
| `series` | `ide/serie` |
| `model` | `ide/mod` |
| `cte_type` | `ide/tpCTe` (0/1/2/3) |
| `service_type` | `ide/tpServ` |
| `cfop` | `ide/CFOP` |
| `operation_nature` | `ide/natOp` |
| `modal` | `ide/modal` |
| `issued_at` | `ide/dhEmi` |
| `origin_*` | `ide/xMunIni`, `ide/UFIni`, `ide/cMunIni` |
| `destination_*` | `ide/xMunFim`, `ide/UFFim`, `ide/cMunFim` |
| `issuer_*` | `emit/CNPJ`, `emit/xNome` |
| `sender_*` | `rem/CNPJ`\|`rem/CPF`, `rem/xNome` |
| `recipient_*` | `dest/CNPJ`\|`dest/CPF`, `dest/xNome` |
| `taker_role` | `ide/toma3/toma` (0=rem,1=exped,2=receb,3=dest) ou `ide/toma4` (outro, com CNPJ próprio) |
| `total_value_cents` | `vPrest/vTPrest` |
| `receivable_value_cents` | `vPrest/vRec` |
| `icms_value_cents` | `imp/ICMS/*/vICMS` (o filho varia: `ICMS00`, `ICMS20`, `ICMS45`, `ICMS60`, `ICMS90`, `ICMSOutraUF`, `ICMSSN`) |
| `cargo_value_cents` | `infCTeNorm/infCarga/vCarga` |
| `cargo_description` | `infCTeNorm/infCarga/proPred` |
| `cargo_weight_kg` | `infCTeNorm/infCarga/infQ[tpMed='PESO BRUTO']/qCarga` — **`tpMed` é texto livre**, ver 7.4 |
| `rntrc` | `infCTeNorm/infModal/rodo/RNTRC` |
| `referenced_key` | `infCteComp/chCTe` (complemento) ou `infCteAnu/chCte` (anulação) ou `ide/infCteSub/chCte` (substituto) |
| `protocol_number` | `../protCTe/infProt/nProt` (fora de `infCte`) |
| `status` | `../protCTe/infProt/cStat` — 100 = autorizado |

### 7.4 Armadilhas conhecidas — tratar explicitamente

1. **`tpMed` é texto livre.** Aparece como `PESO BRUTO`, `PESO BASE DE CALCULO`, `PESO DECLARADO`, `KG`, `M3`, `UNIDADE`, com e sem acento, em caixas variadas. Normalizar (upper + remover acentos) e casar por *contains* `PESO`, preferindo `BRUTO`. Se houver múltiplos, preferir bruto; se não, o primeiro com `PESO`. Nunca assumir posição fixa.
2. **`toma4` vs `toma3`.** Quando o tomador é "outro" (`toma = 4`), os dados vêm em `ide/toma4` com CNPJ/CPF e nome próprios. Quando é 0–3, é preciso *resolver* a referência para `rem`/`exped`/`receb`/`dest`. Implementar `resolveTaker()`.
3. **O grupo de ICMS varia.** Não existe caminho fixo. Iterar os filhos de `imp/ICMS` e pegar o primeiro `vICMS` encontrado. Em `ICMSSN` (Simples Nacional) não há `vICMS` → 0.
4. **`vRec` ≠ `vTPrest`.** `vTPrest` é o total da prestação; `vRec` é o valor a receber. **A receita da empresa é um percentual do `vTPrest`** (ADR-004): a empresa costuma ser o agregado subcontratado e recebe uma fatia negociada com a contratante — `amount = round(vTPrest * applied_share_percent / 100)`. O percentual vem da contratante (`business_partners.default_freight_share_percent`) ou de `config('cte.default_freight_share_percent', 100)`; é 100% quando `emit == empresa`. `vTPrest` e `vRec` ficam registrados para conferência.
5. **CT-e de anulação e complemento.** `tpCTe = 2` (anulação) não é receita — cancela o CT-e referenciado. `tpCTe = 1` (complemento) é receita adicional do mesmo transporte. `tpCTe = 3` (substituto) substitui o referenciado, que deve ser marcado como `canceled`. Se o referenciado ainda não foi importado, persistir e resolver depois (job de reconciliação).
6. **Chave de acesso ≠ único.** É única por emitente na SEFAZ, mas o mesmo XML pode ser importado por duas empresas do mesmo grupo (uma emitente, outra tomadora). Unique é `(company_id, access_key)`.
7. **O CNPJ do emitente pode não ser da empresa — e isso é o caso comum, não erro** (revisto no ADR-004). A empresa atendida costuma ser o **agregado subcontratado**: a emitente (`emit`) é a **contratante** que a contratou. **Não bloquear.** A emitente vira parceiro `contractor`, o vínculo com a empresa é pelo **upload + placa** do veículo, e a receita é o percentual do `vTPrest` (ver armadilha 4). O bloqueio anterior por emitente ≠ empresa foi removido.
8. **Encoding.** XMLs de SEFAZ vêm em UTF-8, mas alguns ERPs geram ISO-8859-1 sem declarar. Detectar e converter antes do parse.
9. **`dhEmi` traz offset** (`2026-03-14T08:22:31-03:00`). Persistir em `timestamptz`, exibir em `America/Sao_Paulo`.
10. **Valores com ponto decimal.** `<vTPrest>3450.00</vTPrest>` — nunca `str_replace(',', '.')`. Converter para centavos com `(int) round((float) $v * 100)` ou, melhor, `Money::of($v, 'BRL')`.

### 7.5 Vinculação com viagem

Após importar, sugerir a viagem:

```
1. Existe Trip aberta (status in [planned, in_progress]) com origem/destino compatíveis
   e período contendo issued_at? → sugerir
2. Não existe? → oferecer "Criar viagem a partir deste CT-e", pré-preenchendo
   origem, destino, data, peso e receita. Veículo e motorista ficam em branco — obrigatórios.
3. Importação em lote: agrupar CT-es por (origem, destino, dia) e sugerir uma viagem por grupo.
```

**Um CT-e sem viagem não entra no DRE Veicular** (não há veículo). Mostrar contador persistente no dashboard: *"7 CT-es sem viagem vinculada"* com link direto. Esse é o principal ponto de dado incompleto do sistema.

### 7.6 Testes obrigatórios do parser

Fixtures em `tests/Fixtures/Cte/`. Casos mínimos:

- `cteproc-normal-rodoviario.xml` — caso feliz
- `cte-sem-protocolo.xml`
- `cteproc-icms-sn.xml` — Simples Nacional, sem vICMS
- `cteproc-icms20.xml` — grupo de ICMS diferente
- `cteproc-toma4.xml` — tomador "outro"
- `cteproc-toma3-remetente.xml`
- `cteproc-complemento.xml`
- `cteproc-anulacao.xml`
- `cteproc-substituto.xml`
- `cteproc-multiplos-infq.xml` — vários `tpMed`
- `cteproc-tpmed-variantes.xml` — `PESO BRUTO`, `Peso Bruto`, `PESO_BRUTO`
- `cteproc-iso88591.xml` — encoding não-UTF-8
- `procevento-cancelamento.xml`
- `xml-malformado.xml`
- `nfe-em-vez-de-cte.xml` — arquivo do tipo errado

Cada um asserta o `CteData` resultante campo a campo. **Este é o conjunto de testes mais importante do sistema** — é onde o dinheiro entra.

---

## 8. Financeiro e fluxo de caixa

### 8.1 Os dois regimes

| | Fluxo de Caixa | DRE |
| --- | --- | --- |
| Pergunta | "Tenho dinheiro?" | "Estou lucrando?" |
| Data | `paid_at` | `competence_date` |
| Filtro | `status = settled` | `status in (settled, forecast)` conforme o modo |
| Depreciação | **não entra** (`affects_cashflow = false`) | entra |
| Compra de veículo | entra (saída de caixa) | não entra (é investimento) |
| Parcela de financiamento | entra integral | só os juros (MVP: entra integral — ver 18.4) |

Na interface, **nunca use as palavras "competência" e "caixa" sem apoio**:
- `competence_date` → rótulo **"Data do serviço"**, com ajuda: *"Quando o serviço aconteceu. É o que conta no resultado do veículo."*
- `paid_at` → rótulo **"Data do pagamento"**, com ajuda: *"Quando o dinheiro saiu ou entrou de fato."*

### 8.2 Saldo

```
saldo(conta, data) = initial_balance_cents
                   + Σ entries.amount WHERE type=revenue  AND status=settled AND paid_at <= data
                   - Σ entries.amount WHERE type=expense  AND status=settled AND paid_at <= data
                   (± transferências)
```

`bank_accounts.current_balance_cents` é cache, recalculado por observer em cada mutação de `financial_entry` liquidado. **Comando de reconciliação obrigatório:**

```bash
php artisan frotika:recalculate-balances {--company=} {--dry-run}
```

Roda diariamente e compara cache × cálculo. Divergência → log de erro + notificação ao admin da plataforma. Saldo errado destrói a confiança no produto inteiro.

### 8.3 Tela de fluxo de caixa

Layout: **matriz dia × conta**, com linha de saldo acumulado.

- Filtros: período (padrão: mês corrente), contas, veículo, categoria, status.
- Toggle **"Considerar previstos"** — inclui `status = forecast` e projeta o saldo. É a funcionalidade mais valiosa da tela: mostra se o dinheiro acaba dia 20.
- Linhas expansíveis: dia → lançamentos do dia.
- Coluna de saldo com destaque visual quando negativo (`danger`) — projeção de saldo negativo é a razão de o usuário abrir a tela.
- Exportar XLSX e PDF.

### 8.4 Transferências

Uma transferência gera **dois** `financial_entries` ligados por `transfer_pair_id`:
- saída na origem (`type = expense`, categoria 8.4)
- entrada no destino (`type = revenue`, categoria 8.4)

A categoria 8.4 tem `dre_group = non_operating` e `allocation = non_vehicle` — **transferência nunca aparece no DRE**. Editar/excluir um lado afeta o par, em transação.

---

## 9. DRE Veicular

**O relatório é o produto.** Todo o resto existe para alimentá-lo.

### 9.1 Estrutura

```
                                          Valor      %RL      R$/km
RECEITA BRUTA
  Receita de fretes                     45.200,00   103,4%     4,52
  Outras receitas do veículo                 0,00
(-) DEDUÇÕES
  ICMS sobre fretes                     -1.492,00    -3,4%    -0,15
  Comissão sobre frete                       0,00
= RECEITA LÍQUIDA                        43.708,00   100,0%     4,37
(-) CUSTOS VARIÁVEIS
  Combustível                          -14.850,00   -34,0%    -1,49
  Arla 32                                 -620,00    -1,4%    -0,06
  Pedágio                               -2.310,00    -5,3%    -0,23
  Manutenção corretiva                  -1.870,00    -4,3%    -0,19
  Pneus                                 -3.200,00    -7,3%    -0,32
  Diárias e adiantamentos               -2.400,00    -5,5%    -0,24
= MARGEM DE CONTRIBUIÇÃO                 18.458,00    42,2%     1,85
(-) CUSTOS FIXOS DO VEÍCULO
  Salário do motorista                  -3.500,00    -8,0%    -0,35
  Encargos                              -1.400,00    -3,2%    -0,14
  Manutenção preventiva                   -890,00    -2,0%    -0,09
  Seguro                                -1.200,00    -2,7%    -0,12
  IPVA e licenciamento                    -310,00    -0,7%    -0,03
  Rastreamento                             -89,00    -0,2%    -0,01
  Parcela de financiamento              -4.800,00   -11,0%    -0,48
  Depreciação                           -2.083,00    -4,8%    -0,21
= RESULTADO OPERACIONAL DO VEÍCULO        4.186,00     9,6%     0,42
(-) RATEIO DE DESPESAS ADMINISTRATIVAS
  (critério: km rodado — 24,3% da frota)  -1.944,00    -4,4%    -0,19
(-) RATEIO DE DESPESAS FINANCEIRAS          -122,00    -0,3%    -0,01
= RESULTADO LÍQUIDO DO VEÍCULO            2.120,00     4,9%     0,21
```

**Indicadores no topo (cards):**

| Indicador | Fórmula |
| --- | --- |
| Km rodados | Σ `trips.distance_km` no período |
| Receita por km | receita líquida ÷ km |
| Custo por km | (custos variáveis + fixos + rateios) ÷ km |
| Margem líquida | resultado líquido ÷ receita líquida |
| Consumo médio | Σ km dos intervalos fechados ÷ Σ litros |
| Custo de combustível por km | combustível ÷ km |
| Ponto de equilíbrio (km) | custos fixos ÷ margem de contribuição por km |
| Km vazio | Σ `trips.empty_km` ÷ km total |

### 9.2 Como cada linha é montada

```php
// App\Domain\Reports\Dre\DreBuilder

// 1. Escopo
//    período: [from, to] por competence_date
//    veículo: vehicle_id OU composição (cavalo + carretas vigentes no período)

// 2. Diretos — vehicle_id do lançamento bate com o escopo
//    SELECT category.dre_group, category.id, SUM(amount_cents)
//    FROM financial_entries
//    WHERE company_id = ?
//      AND competence_date BETWEEN ? AND ?
//      AND vehicle_id IN (?)
//      AND status <> 'canceled'
//      AND category.allocation = 'vehicle_direct'
//    GROUP BY 1, 2

// 3. Depreciação — calculada, não lançada manualmente (ver 9.4)

// 4. Rateio — categorias com allocation = 'apportioned'
//    total do período ÷ frota, pelo critério configurado

// 5. Composição — custos das carretas vigentes somam ao cavalo
//    quando o modo é "conjunto"
```

### 9.3 Rateio

`companies.settings.dre_apportionment_method` — enum:

| Critério | Base | Quando usar |
| --- | --- | --- |
| `by_km` (**padrão**) | km do veículo ÷ km total da frota | frota heterogênea de uso |
| `by_revenue` | receita do veículo ÷ receita total | frota com valores de frete muito diferentes |
| `equal` | 1 ÷ nº de veículos ativos | frota homogênea |
| `none` | sem rateio | quem só quer o resultado operacional |

**Regras:**
- Somente veículos `type = tractor`, `truck`, `toco`, `vuc` recebem rateio. Reboque não gera receita sozinho.
- Veículo com `status = inactive` ou `sold` fora do período não entra na base.
- Divisor zero (nenhum km na frota) → rateio = 0, com aviso na tela.
- **Distribuição por maior resto.** A soma dos rateios de todos os veículos é exatamente o total rateado. Testar com valores primos (ex.: R$ 1.000,00 entre 3 veículos = 33333 + 33333 + 33334 centavos).

O critério aparece na tela: *"Rateio por km rodado — este veículo rodou 24,3% dos km da frota."* Número sem explicação é número em que não se confia.

### 9.4 Depreciação

Linear, calculada em tempo de relatório, **não** lançada em `financial_entries`:

```
depreciação_mensal = (acquisition_value_cents - residual_value_cents) / depreciation_months

Só entra se: acquisition_value_cents, depreciation_months preenchidos
             E o mês está dentro de [acquisition_date, acquisition_date + depreciation_months]
```

`affects_cashflow = false` na categoria 4.8 garante que nunca contamine o fluxo de caixa.

> **Sugestão de default no cadastro do veículo:** cavalo mecânico ≈ 60 meses com 40% de residual; carreta ≈ 96 meses com 30%. São sugestões pré-preenchidas e editáveis, não regras — deixar claro na UI que é uma estimativa do usuário.

### 9.5 Modos de visualização

1. **Veículo individual** — um `vehicle_id`.
2. **Conjunto** — cavalo + carretas vigentes no período. Este é o modo que o dono quer olhar: a carreta não gera receita sozinha, mas consome pneu e manutenção.
3. **Comparativo da frota** — tabela, uma linha por veículo, colunas de indicadores, ordenável. **Esta é a tela que vende o produto:** ordenar por "Resultado líquido" e ver o veículo negativo.
4. **Consolidado** — soma da empresa, batendo com o DRE gerencial.

### 9.6 Elemento de assinatura — cascata de resultado

No topo do DRE individual, um gráfico de cascata (waterfall) em SVG: receita líquida → cada bloco de custo descendo em `brand` → resultado final em `success` ou `danger`. Sem biblioteca de gráficos; SVG gerado no servidor.

É a única peça visualmente ousada do sistema. Tudo ao redor é sóbrio. Cada barra é clicável e abre o drill-down dos lançamentos daquele bloco.

### 9.7 Drill-down

Toda linha do DRE é clicável e abre um painel lateral com os lançamentos que a compõem, cada um linkando para a origem (abastecimento, manutenção, CT-e). **Sem isso o relatório não é auditável e o usuário não confia.** Requisito de MVP, não de fase 2.

### 9.8 Performance

- Consulta agregada única com `GROUP BY`, nunca N+1 por categoria.
- Cache por (`company_id`, `vehicle_id`, `from`, `to`, `method`) com TTL de 10 min, invalidado por evento de `FinancialEntry` salvo.
- Comparativo da frota: uma única query com `GROUP BY vehicle_id`, não um loop de `DreBuilder`.
- Exportação (PDF/XLSX) via job + notificação quando pronta, se > 20 veículos.

---

## 10. Assinatura e cobrança (SaaS)

### 10.1 Planos (sugestão inicial — preços a definir)

| Plano | Veículos | Usuários | Empresas |
| --- | --- | --- | --- |
| Partida | até 3 | 2 | 1 |
| Estrada | até 10 | 5 | 2 |
| Frota | até 30 | ilimitado | 5 |
| Sob medida | negociado | | |

Trial de 14 dias, sem cartão. `limits` em `plans.limits` (json), verificados por `App\Domain\Billing\PlanLimiter`:

```php
$limiter->ensureCan('vehicle.create', $group);  // lança PlanLimitExceededException
```

Excedeu → modal com o limite atingido e link para upgrade. **Nunca bloquear leitura ou exportação por limite de plano.** O dado é do usuário.

### 10.2 Gateway

Interface `App\Domain\Billing\Gateways\BillingGateway`:

```php
interface BillingGateway
{
    public function createCustomer(Group $group): string;
    public function createSubscription(Subscription $subscription): GatewaySubscription;
    public function cancelSubscription(Subscription $subscription): void;
    public function fetchInvoice(string $gatewayInvoiceId): GatewayInvoice;
    public function handleWebhook(array $payload): ?WebhookEvent;
}
```

**Implementação inicial: Asaas.** Motivo: Pix e boleto são os meios de pagamento reais do público-alvo; cartão sozinho perde vendas. A interface permite trocar sem tocar no domínio.

**Webhooks:** endpoint fora do middleware de tenancy, com verificação de assinatura, gravação em `gateway_webhook_events` (unique em `event_id`) antes de processar. Processamento em job. Reprocessável.

### 10.3 Ciclo de vida da assinatura

```
trialing ──(trial_ends_at, com pagamento)──→ active
   └──(trial_ends_at, sem pagamento)──→ expired ──→ [somente leitura]

active ──(falha de pagamento)──→ past_due ──(7 dias)──→ canceled
   └──(cancelamento do usuário)──→ active até current_period_end ──→ canceled

canceled/expired → acesso somente leitura por 90 dias, depois exclusão programada
                   (avisar por e-mail em D-30, D-7, D-1)
```

**Middleware `EnsureSubscribed`:** grupos `expired`/`canceled` acessam apenas leitura e exportação. Nunca apagar dado por inadimplência sem aviso e sem janela de exportação — é dado fiscal do cliente.

### 10.4 Notificações de cobrança

**Destinatário: exclusivamente o `owner` do grupo** (`groups.owner_user_id`). Nenhum outro papel recebe.

| Evento | Canal | Quando |
| --- | --- | --- |
| Trial terminando | e-mail + sino | D-3, D-1 |
| Fatura gerada | e-mail (com Pix/boleto) + sino | D-5 do vencimento |
| Fatura vencendo | e-mail | D-1 |
| Fatura vencida | e-mail + sino | D+1, D+3, D+7 |
| Pagamento confirmado | e-mail | imediato |
| Assinatura cancelada | e-mail | imediato |
| Exclusão de dados agendada | e-mail | D-30, D-7, D-1 |

Implementar como Notifications do Laravel, canais `mail` e `database`. Banner persistente no topo quando `past_due` — visível para todos os usuários do grupo, com o texto *"Fale com {nome do owner}"* para quem não é owner.

---

## 11. Painel da plataforma (dono do sistema)

### 11.1 Conceito

O dono do Frotika tem um `Group` com `type = 'platform'` e uma `Company` própria — ele usa o sistema como qualquer cliente (é uma transportadora ou não, tanto faz). **Além disso**, usuários com `is_platform_admin = true` acessam `/admin`, que opera **fora dos global scopes**.

Separação rígida: `App\Platform\**`. Nenhum model de `App\Domain` é consultado sem escopo fora dessa pasta.

### 11.2 Funcionalidades

| Tela | Conteúdo |
| --- | --- |
| Dashboard | MRR, ARR, grupos ativos, trials em curso, conversão de trial, churn mensal, inadimplência, veículos totais |
| Grupos | lista com plano, status, nº de empresas/veículos/usuários, último acesso, MRR |
| Grupo (detalhe) | dados, empresas, usuários, assinatura, faturas, log, ações |
| Assinaturas | filtro por status, mudança manual de plano, concessão de cortesia, extensão de trial |
| Faturas | reemissão, baixa manual, estorno |
| Usuários | busca global, redefinição de senha, promoção a platform admin |
| Saúde | jobs falhados, erros de import de CT-e, divergências de saldo |
| Impersonação | entrar como usuário |

### 11.3 Impersonação — regras obrigatórias

1. Registrar em `activity_log` **antes** de iniciar: quem, quem foi personificado, quando, motivo (campo obrigatório).
2. Banner fixo, alto contraste, no topo de todas as telas: *"Você está vendo o sistema como {usuário} · {empresa}"* + botão "Sair da personificação".
3. **Somente leitura.** Nenhuma mutação durante impersonação — bloquear via `Gate::before`. Suporte precisa ver, não precisa alterar.
4. Expira em 30 minutos.
5. Impossível personificar outro `platform_admin`.

---

## 12. Design system

### 12.1 Direção

O sistema é usado às 6h da manhã, no escritório de um pátio, por alguém que também vai trocar um pneu hoje. A direção visual vem do universo da estrada: **sinalização rodoviária** — informação densa, hierarquia brutalmente clara, legível de longe, sem ornamento. Não é um dashboard de fintech. É uma placa de rodovia que sabe contar.

Toda a ousadia está concentrada em **dois lugares**: a cascata do DRE e o chip de placa. O resto é disciplina.

### 12.2 Paleta

Derivada de `#002573` (`hsl(220.7, 100%, 22.5%)`) mantendo matiz e saturação constantes, variando apenas a luminosidade. A cor da marca é a **900** — é uma cor escura, o que significa que ela é usada como âncora estrutural (sidebar, cabeçalhos, texto de destaque), não como cor de botão. Os botões usam a 700, que mantém o parentesco com contraste utilizável.

**Marca — `brand`**

| Token | Hex | Uso |
| --- | --- | --- |
| `brand-50` | `#E6EEFF` | fundos de destaque, linha selecionada |
| `brand-100` | `#CCDCFF` | badges suaves, hover de item de menu |
| `brand-200` | `#A3C1FF` | bordas de estado ativo |
| `brand-300` | `#709EFF` | ícones sobre fundo escuro |
| `brand-400` | `#3D7CFF` | links sobre fundo escuro |
| `brand-500` | `#0A59FF` | foco, anel de foco |
| `brand-600` | `#0048E0` | hover de botão primário |
| `brand-700` | `#0039B2` | **botão primário**, links |
| `brand-800` | `#002E8F` | pressed |
| **`brand-900`** | **`#002573`** | **cor da marca** — sidebar, cabeçalhos, logo |
| `brand-950` | `#001747` | topo do gradiente da sidebar, texto sobre `brand-50` |

**Destaque — `accent`** (complementar, âmbar de sinalização)

| Token | Hex | Uso |
| --- | --- | --- |
| `accent-50` | `#FFF8E6` | |
| `accent-100` | `#FFEDBF` | |
| `accent-300` | `#FFCF52` | |
| `accent-500` | `#FFAD00` | atalhos da topbar, chip de placa, badge de pendência |
| `accent-600` | `#DB8F00` | hover |
| `accent-700` | `#A86B00` | texto sobre `accent-50` |

> `accent` **não é cor de aviso.** É cor de "olhe aqui". Aviso é `warning`. Não misturar.

**Semânticos**

| Token | Hex | Uso |
| --- | --- | --- |
| `success-500` / `success-50` | `#059669` / `#ECFDF5` | resultado positivo, pago, autorizado |
| `warning-500` / `warning-50` | `#D97706` / `#FFFBEB` | vence em breve, dado inconsistente |
| `danger-500` / `danger-50` | `#DC2626` | resultado negativo, vencido, cancelado, saldo negativo |
| `info-500` / `info-50` | `#0284C7` | informativo, previsto |

**Neutros:** escala `slate` do Tailwind. Fundo da aplicação `slate-50`, superfícies `white`, bordas `slate-200`, texto `slate-900` / `slate-600` / `slate-400`.

### 12.3 Tipografia

| Papel | Fonte | Onde |
| --- | --- | --- |
| Display | **Archivo** (600/700) | títulos de página, valores dos cards, cabeçalhos do DRE |
| Corpo/UI | **Inter** (400/500/600) | tudo mais |
| Dados | **JetBrains Mono** (400/500) | colunas numéricas, placas, chaves de CT-e, CNPJ |

Archivo é uma grotesca com origem em sinalização — o parentesco com placa de rodovia é literal, não decorativo. Usar **com restrição**: só em título de página, valor de card e cabeçalho de seção do DRE. Nunca em rótulo de formulário.

**Regras não negociáveis:**

- Toda coluna numérica: `font-variant-numeric: tabular-nums` e alinhamento à direita. Sem isso, a coluna de valores não é lida — é decifrada.
- Valores monetários usam mono. R$ 1.245,00 e R$ 992,00 precisam alinhar na vírgula.
- Escala: `12 / 14 / 16 / 20 / 24 / 32 / 44`. Padrão da UI: 14px. Nada abaixo de 12px.

### 12.4 Tokens Tailwind v4

`resources/css/app.css`:

```css
@import "tailwindcss";

@theme {
  --color-brand-50:  #E6EEFF;
  --color-brand-100: #CCDCFF;
  --color-brand-200: #A3C1FF;
  --color-brand-300: #709EFF;
  --color-brand-400: #3D7CFF;
  --color-brand-500: #0A59FF;
  --color-brand-600: #0048E0;
  --color-brand-700: #0039B2;
  --color-brand-800: #002E8F;
  --color-brand-900: #002573;
  --color-brand-950: #001747;

  --color-accent-50:  #FFF8E6;
  --color-accent-100: #FFEDBF;
  --color-accent-300: #FFCF52;
  --color-accent-500: #FFAD00;
  --color-accent-600: #DB8F00;
  --color-accent-700: #A86B00;

  --color-success-50:  #ECFDF5;
  --color-success-500: #059669;
  --color-success-700: #047857;
  --color-warning-50:  #FFFBEB;
  --color-warning-500: #D97706;
  --color-warning-700: #B45309;
  --color-danger-50:   #FEF2F2;
  --color-danger-500:  #DC2626;
  --color-danger-700:  #B91C1C;
  --color-info-50:     #F0F9FF;
  --color-info-500:    #0284C7;
  --color-info-700:    #0369A1;

  --font-display: "Archivo", ui-sans-serif, system-ui, sans-serif;
  --font-sans:    "Inter", ui-sans-serif, system-ui, sans-serif;
  --font-mono:    "JetBrains Mono", ui-monospace, monospace;

  --radius-card: 0.625rem;

  --spacing-sidebar:           16.5rem;  /* 264px */
  --spacing-sidebar-collapsed:  4.5rem;  /*  72px */
  --spacing-topbar:             4rem;    /*  64px */
}

@layer base {
  .tabular { font-variant-numeric: tabular-nums; }
  :focus-visible { outline: 2px solid var(--color-brand-500); outline-offset: 2px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

### 12.5 Componentes Blade

Criar em `resources/views/components/ui/`. Todos com props tipadas e slot.

`button` (variants: `primary`, `secondary`, `ghost`, `danger`; sizes: `sm`, `md`) · `input` · `select` · `money-input` (máscara pt-BR, emite centavos) · `date-input` · `plate-input` · `card` · `page-header` · `data-table` (cabeçalho fixo, ordenação, zebra, ações por linha) · `empty-state` · `badge` · `modal` · `slide-over` · `toast` · `stat-card` · `plate-chip` · `dropdown` · `tabs` · `confirm-dialog`.

### 12.6 Chip de placa — elemento de identidade

O veículo é sempre exibido como um chip que evoca a placa Mercosul: tarja superior `brand-900` fina, corpo branco, borda `slate-800`, texto em mono maiúsculo com tracking.

```blade
<x-ui.plate-chip plate="RIO2A18" type="tractor" />
```

Aparece em toda referência a veículo — tabelas, selects, cards, DRE, breadcrumb. É o átomo de identidade visual do sistema e o que o usuário procura na tela. Custo: quase zero. Retorno: o sistema fala a língua de quem usa.

Variação por tipo: cavalo com tarja cheia, reboque com tarja tracejada. Diferença de 2px que informa.

### 12.7 Padrões obrigatórios

**Estados vazios** — nunca "Nenhum registro encontrado". Sempre: o que é a tela, por que está vazia, e o botão da ação.

> *"Nenhuma viagem ainda."*
> *"Importe os XMLs dos seus CT-es e as viagens aparecem aqui, com a receita já preenchida."*
> `[Importar CT-e]` `[Criar viagem manualmente]`

**Erros** — dizem o que aconteceu e o que fazer. Não pedem desculpa, não são vagos.

> ❌ "Erro ao processar arquivo."
> ✅ "Este XML é de uma NF-e, não de um CT-e. O Frotika importa CT-e modelo 57."
> ✅ "O CT-e 000012345 já foi importado em 14/03/2026. Os dados foram atualizados."
> ✅ "A quilometragem informada (412.300) é menor que a última registrada (418.900). Confirme se houve troca de painel."

**Ações** — mantêm o mesmo nome do começo ao fim. Botão "Importar CT-e" → toast "12 CT-es importados". Nunca "Enviar"/"Submeter".

**Confirmação destrutiva** — modal nomeando o registro e a consequência.

> *"Excluir o abastecimento de 14/03 (245 L · R$ 1.372,00)? O lançamento no fluxo de caixa também será cancelado."*

### 12.8 Piso de qualidade

Sem anunciar, sempre: responsivo até 360px · foco visível pelo teclado · `prefers-reduced-motion` respeitado · contraste AA · toda tabela navegável por teclado · toda ação com estado de carregamento (`wire:loading`) · nenhum clique duplo gerando lançamento duplicado.

---

## 13. Layout da aplicação

```
┌─────────────────────────────────────────────────────────────────────┐
│ ┌──────────────┬────────────────────────────────────────────────┐   │
│ │              │  TOPBAR (64px)                                 │   │
│ │              │  ‹ Empresa ▾ │ Buscar ⌘K │ + Atalhos │ 🔔 │ 👤 │   │
│ │              ├────────────────────────────────────────────────┤   │
│ │  SIDEBAR     │                                                │   │
│ │  264 / 72px  │  Frotika › Viagens › Viagem 000142             │   │
│ │  brand-900   │                                                │   │
│ │              │  ┌──────────────────────────────────────────┐  │   │
│ │  ▸ Painel    │  │  Cabeçalho da página + ações             │  │   │
│ │              │  ├──────────────────────────────────────────┤  │   │
│ │  OPERAÇÃO    │  │                                          │  │   │
│ │  ▸ Viagens   │  │  Conteúdo (max-w-[1400px])               │  │   │
│ │  ▸ Abastec.  │  │                                          │  │   │
│ │  ▸ Manut.    │  │                                          │  │   │
│ │              │  │                                          │  │   │
│ │  FROTA       │  └──────────────────────────────────────────┘  │   │
│ │  ▸ Veículos  │                                                │   │
│ │  ▸ Motorist. │                                                │   │
│ │              │                                                │   │
│ │  FINANCEIRO  │                                                │   │
│ │  ▸ Lançam.   │                                                │   │
│ │  ▸ Fluxo     │                                                │   │
│ │  ▸ Contas    │                                                │   │
│ │              │                                                │   │
│ │  ANÁLISE     │                                                │   │
│ │  ▸ DRE Veic. │                                                │   │
│ │              │                                                │   │
│ │  ───────     │                                                │   │
│ │  ▸ Config    │                                                │   │
│ │  ‹ Recolher  │                                                │   │
│ └──────────────┴────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### 13.1 Sidebar retrátil

- **Expandida 264px / recolhida 72px.** Transição de largura 200ms `ease-out`.
- Fundo: gradiente sutil `brand-950` → `brand-900` (vertical). Texto `brand-100`. Item ativo: fundo `brand-800`, barra de 3px `accent-500` à esquerda, texto branco. Hover: `brand-800/60`.
- Seções com rótulo (`OPERAÇÃO`, `FROTA`, `FINANCEIRO`, `ANÁLISE`) em 11px, `brand-400`, tracking largo, uppercase. **Somem quando recolhida** — a linha divisória permanece.
- Recolhida: só ícone, centralizado, com tooltip à direita após 400ms.
- **Submenu recolhido = flyout**, não expansão. Popover ancorado à direita do ícone.
- Estado persistido em `users.preferences.sidebar_collapsed`, aplicado no servidor no primeiro render. **Sem flash de layout** — a classe sai do Blade, não do JS.
- Atalho `[` alterna.
- **Mobile (< 1024px):** vira drawer sobreposto com backdrop, fechando ao navegar. O botão de recolher some; aparece hambúrguer na topbar.
- Rodapé fixo da sidebar: logo compacto + botão recolher.

### 13.2 Topbar

Altura 64px, fundo branco, borda inferior `slate-200`, sticky.

| Zona | Conteúdo |
| --- | --- |
| Esquerda | hambúrguer (mobile) · **seletor de empresa** — só renderiza se o grupo tem > 1 empresa; dropdown com busca se > 5 |
| Centro | busca global (`⌘K` / `Ctrl+K`) — placa, motorista, nº de CT-e, chave de acesso, nº de viagem |
| Direita | **atalhos** · notificações · avatar/menu |

**Atalhos** — a "sidebar superior" pedida. Barra de ações rápidas antes das notificações:

```
[ + Viagem ]  [ ⛽ Abastecimento ]  [ 🔧 Manutenção ]  [ ↑ Importar CT-e ]  [ ⋯ ]
```

- Abrem **slide-over**, não navegam. O usuário lança e volta pro que estava fazendo.
- Botões em `ghost` com ícone + rótulo; abaixo de 1280px, só ícone com tooltip.
- `[⋯]` abre o gerenciador: o usuário escolhe até 4 atalhos entre todas as ações de criação, ordenáveis por drag. Persistido em `users.preferences.shortcuts` (array de chaves).
- Padrão para novo usuário: os 4 acima.
- Cada atalho respeita a permissão do papel — `viewer` não vê nenhum e a zona colapsa.

**Notificações:** sino com contador. Popover com as 10 últimas, agrupadas por tipo, link para "ver todas". Origem: CNH vencendo, fatura, CT-e sem viagem, import concluído, saldo projetado negativo.

**Menu do usuário:** nome + empresa atual, perfil, preferências, trocar de grupo (raro), assinatura (só `owner`), sair. Se `is_platform_admin`: divisória + "Painel da plataforma".

### 13.3 Padrão de página

```blade
<x-app-layout>
    <x-ui.page-header title="Viagens" subtitle="142 viagens · março de 2026">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="upload">Importar CT-e</x-ui.button>
            <x-ui.button variant="primary" icon="plus">Nova viagem</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filters>...</x-ui.filters>

    <x-ui.card>
        <livewire:trips.table />
    </x-ui.card>
</x-app-layout>
```

Conteúdo: `max-w-[1400px] mx-auto px-6 py-6`. Grid de 12 colunas com `gap-6`.

### 13.4 Dashboard

Ordem das seções, de cima para baixo — reflete o que o dono pergunta ao abrir o sistema:

1. **Faixa de alertas** (só se houver): saldo projetado negativo em X dias · fatura vencida · CNH vencendo · N CT-es sem viagem.
2. **Quatro cards:** saldo consolidado · receita do mês (competência) · resultado do mês · km rodados.
3. **Cascata do resultado do mês** — a versão consolidada do gráfico do DRE.
4. **Ranking de veículos** — top 3 e bottom 3 por resultado, com chip de placa. Link para o comparativo.
5. **Últimas viagens** e **próximos vencimentos**, lado a lado.
6. **Checklist de onboarding**, enquanto incompleto.

---

## 14. Convenções de código

### 14.1 Regras

1. **`declare(strict_types=1)`** em todo arquivo PHP.
2. **Enums PHP nativos** para todo campo enum, sempre backed por string, sempre com `label(): string` e, quando visual, `color(): string`.
   ```php
   enum VehicleType: string
   {
       case Tractor = 'tractor';
       case SemiTrailer = 'semi_trailer';
       case Truck = 'truck';
       case Toco = 'toco';
       case Vuc = 'vuc';
       case Utility = 'utility';

       public function label(): string
       {
           return match ($this) {
               self::Tractor => 'Cavalo mecânico',
               self::SemiTrailer => 'Carreta',
               self::Truck => 'Truck',
               self::Toco => 'Toco',
               self::Vuc => 'VUC',
               self::Utility => 'Utilitário',
           };
       }

       public function pullsLoad(): bool
       {
           return $this !== self::SemiTrailer;
       }
   }
   ```
3. **Sem lógica em Livewire.** Componente valida, chama a Action, trata o resultado.
4. **Sem query em Blade.** Tudo resolvido no componente.
5. **`final` por padrão** em Actions, Services e DTOs.
6. **Métodos de escrita em transação** — `DB::transaction()` explícito quando toca mais de uma tabela.
7. **`readonly` em DTOs.**
8. **Nunca `$model->update($request->all())`.** Sempre FormRequest ou `rules()` do Livewire, sempre campo a campo.
9. **Policies para tudo.** `Gate::authorize` em toda Action de mutação, não só na UI.
10. **Pint** com preset `laravel`, rodando em pre-commit e CI.
11. **Larastan nível 6** mínimo, subindo ao longo do projeto.

### 14.2 Nomenclatura

| Item | Padrão | Exemplo |
| --- | --- | --- |
| Tabela | plural, snake, inglês | `financial_entries` |
| Model | singular, Pascal | `FinancialEntry` |
| Livewire | `Domain\Ação` | `Trips\TripForm` |
| Action | verbo + substantivo | `ImportCteFromXml`, `RegisterFueling` |
| Enum | singular | `FuelingProduct` |
| Migration | descritiva | `2026_07_15_000000_create_financial_entries_table` |
| Rota nomeada | `recurso.ação` | `trips.index`, `reports.dre` |
| Evento | passado | `CteImported`, `FuelingRegistered` |

### 14.3 Formatação pt-BR

Helper `App\Support\Format` centraliza. Nada de `number_format` espalhado.

```php
Format::money($cents);        // "R$ 1.372,00"
Format::money($cents, sign: true); // "-R$ 1.372,00"
Format::liters($decimal);     // "245,300 L"
Format::km($int);             // "418.900 km"
Format::consumption($d);      // "2,43 km/l"
Format::percent($d);          // "34,0%"
Format::plate($str);          // "RIO2A18"
Format::cnpj($str);           // "12.345.678/0001-90"
Format::cpf($str);            // "123.456.789-00"
Format::cteKey($str);         // "3126 0312 3456 7800 0190 5700 1000 0123 4510 0001 2345"
Format::date($date);          // "14/03/2026"
Format::dateTime($dt);        // "14/03/2026 08:22"
```

`config/app.php`: `timezone = 'America/Sao_Paulo'`, `locale = 'pt_BR'`, `faker_locale = 'pt_BR'`.

### 14.4 Seeders de desenvolvimento

`DemoSeeder` — dado realista, não lorem ipsum. Sem isso não dá para avaliar o DRE nem o layout de tabela.

```
1 grupo plataforma (Frotika) + 1 usuário platform admin
1 grupo cliente "Transportes Serra Azul" (Lavras/MG)
  └── 1 empresa com CNPJ válido
       ├── 4 veículos: 2 cavalos, 2 carretas (1 sider, 1 graneleiro)
       ├── 2 composições vigentes
       ├── 3 motoristas (1 com CNH vencendo em 12 dias)
       ├── 3 contas (Caixa, Banco, Cartão)
       ├── plano de contas padrão
       ├── 6 meses de histórico:
       │    ~90 viagens com CT-es
       │    ~140 abastecimentos (85% full_tank)
       │    ~25 manutenções
       │    ~200 lançamentos diversos
       └── cenário proposital: 1 cavalo com resultado NEGATIVO
            (consumo 18% pior + 2 manutenções corretivas grandes)
```

> O veículo negativo existe para que o DRE tenha algo a dizer no primeiro `php artisan migrate:fresh --seed`. Um relatório de demonstração onde tudo dá lucro não demonstra nada.

Comando: `php artisan frotika:demo --fresh`.

---

## 15. Testes

PHPUnit 12 (já no skeleton). Sem Pest — o skeleton veio com PHPUnit e trocar não agrega.

### 15.1 Cobertura obrigatória

| Área | O que testar |
| --- | --- |
| **Tenancy** | Empresa A não lê dado de B (um teste por model com `BelongsToCompany`) · troca de empresa valida vínculo · job restaura contexto · `creating` sem contexto lança exceção |
| **CT-e** | Todas as fixtures da [seção 7.6](#76-testes-obrigatórios-do-parser) · reimport é idempotente · anulação cancela lançamento · substituto cancela referenciado · chave duplicada não duplica |
| **Consumo** | Sequência com tanque parcial · sem full_tank anterior → null · arla não conta · odômetro regressivo bloqueia |
| **Financeiro** | Saldo com previstos e realizados · transferência gera par · cancelar origem cancela lançamento · depreciação fora do fluxo de caixa · recálculo de saldo bate com cache |
| **DRE** | Cada linha soma o esperado · rateio por km/receita/igual · **soma dos rateios == total** · veículo sem km não quebra · composição soma carretas · período sem dado retorna zerado, não erro |
| **Rateio** | Maior resto: R$ 1.000,00 ÷ 3 = 33333+33333+33334 · ÷ 7 · ÷ 1 · ÷ 0 |
| **Billing** | Trial expira → somente leitura · webhook duplicado processa uma vez · limite de plano bloqueia criação, não leitura · só o owner é notificado |
| **Permissões** | Uma asserção por papel × recurso · impersonação não muta |

### 15.2 Regra

Nenhuma Action de domínio entra em `main` sem teste. Livewire pode ter teste de smoke. **O `DreBuilder` e o `CteParser` exigem cobertura próxima de 100%** — são as duas peças onde um bug vira dinheiro errado na tela de um cliente.

---

## 16. Roadmap por fases

Cada fase é entregável e testável. Não iniciar a próxima com a anterior vermelha.

### Fase 0 — Fundação

- [ ] Projeto Laravel 13.8, MySQL 8, dependências da [seção 2.1](#21-dependências)
- [ ] `laravel/boost` instalado e configurado
- [ ] Tailwind v4 com os tokens da [seção 12.4](#124-tokens-tailwind-v4)
- [ ] Fontes (Archivo, Inter, JetBrains Mono) auto-hospedadas
- [ ] Componentes `ui/*` da [seção 12.5](#125-componentes-blade)
- [x] `Format`, `Money` cast, `Apportionment` (maior resto) + testes
- [ ] Layout: sidebar retrátil + topbar + `page-header`
- [x] Pint, Larastan 6, CI (testes + pint + phpstan)
- [ ] `docs/adr/` com os ADRs da seção 2

### Fase 1 — Tenancy, auth e onboarding

- [ ] Migrations: `groups`, `companies`, `users`, `group_user`, `company_user`, `invitations`
- [ ] `TenantContext`, `CompanyScope`, `BelongsToCompany`, `SetTenantContext`
- [ ] Spatie permission com teams; papéis e permissões da [seção 4](#4-papéis-e-permissões)
- [x] Registro → criação de grupo + empresa + assinatura trial + plano de contas + conta padrão
- [x] Consulta de CNPJ na Receita (BrasilAPI + fallback ReceitaWS) no cadastro, com máscara/limite, preenchimento automático e fallback manual — ver [seção 16.1](#161-estado-atual-e-sequência-de-continuação)
- [ ] Wizard de onboarding
- [ ] Convite de usuários, seletor de empresa
- [ ] Transferência de propriedade
- [ ] **Testes de isolamento** — bloqueante para a fase 2

### Fase 2 — Frota

- [ ] Veículos: CRUD, chip de placa, validação de placa (antiga + Mercosul)
- [ ] Composições com vigência
- [ ] Motoristas: CRUD, validação de CPF, alertas de CNH/MOPP/toxicológico
- [ ] Vínculo motorista ↔ veículo
- [ ] Fornecedores
- [ ] Job diário de alertas de vencimento

### Fase 3 — CT-e e viagens

- [ ] `CteReader` / `CteParser` / `CteImporter` + **todas as fixtures**
- [ ] Upload múltiplo + ZIP, batch de jobs, tela de progresso e resultado
- [ ] `cte_documents`, `trips`, `trip_cte_document`
- [ ] Sugestão e criação de viagem a partir do CT-e
- [ ] Indicador "CT-es sem viagem"
- [ ] Tratamento de complemento/anulação/substituto + evento de cancelamento
- [ ] CRUD manual de viagem

### Fase 4 — Abastecimentos e manutenções

- [ ] `fuelings` + `FuelConsumptionCalculator` + testes
- [ ] Validações de odômetro e outliers
- [ ] Upload de cupom, formulário mobile-first
- [ ] `maintenances` + `maintenance_items`
- [ ] Alerta de manutenção preventiva (`next_service_odometer` / `next_service_at`)
- [ ] Histórico por veículo

### Fase 5 — Financeiro

- [ ] `bank_accounts`, `financial_categories` + seeder do plano de contas
- [ ] `financial_entries` CRUD
- [ ] `EntrySynchronizer` + observers de Fueling/Maintenance/CteDocument
- [ ] Transferências
- [ ] Recorrências + job de geração
- [ ] Tela de fluxo de caixa com toggle de previstos
- [ ] `frotika:recalculate-balances` + agendamento
- [ ] Anexos

### Fase 6 — DRE Veicular ★

- [ ] `DreBuilder` + `ApportionmentStrategy` (4 critérios)
- [ ] Depreciação calculada
- [ ] Modos: individual, conjunto, comparativo, consolidado
- [ ] Cards de indicadores
- [ ] Cascata SVG
- [ ] Drill-down por linha
- [ ] Export PDF/XLSX
- [ ] Cache + invalidação
- [ ] **Cobertura ~100%**

### Fase 7 — Assinatura

- [ ] `plans`, `subscriptions`, `invoices`, `gateway_webhook_events`
- [ ] `BillingGateway` + implementação Asaas
- [ ] Webhooks idempotentes
- [ ] `PlanLimiter` + `EnsureSubscribed`
- [ ] Notificações ao owner
- [ ] Tela de assinatura e faturas
- [ ] Banner de inadimplência

### Fase 8 — Painel da plataforma

- [ ] `App\Platform`, `EnsurePlatformAdmin`
- [ ] Dashboard de métricas (MRR, churn, conversão)
- [ ] Gestão de grupos, assinaturas, faturas
- [ ] Tela de saúde (jobs, imports, saldos)
- [ ] Impersonação com as 5 regras da [seção 11.3](#113-impersonação--regras-obrigatórias)

### Fase 9 — Polimento

- [ ] Dashboard completo
- [ ] Busca global `⌘K`
- [ ] Gerenciador de atalhos
- [ ] Centro de notificações
- [ ] Estados vazios e mensagens de erro revisados um a um
- [ ] Auditoria de acessibilidade
- [ ] LGPD: exportação e exclusão de dados
- [ ] Backup automatizado + restore testado
- [ ] Sentry, Horizon em produção

### 16.1 Estado atual e sequência de continuação

> Diário completo em [development-log.md](development-log.md). Este resumo é o que já está em `main` e a ordem recomendada para seguir sem quebrar dependência.

**Concluído até a etapa 0.11**

- **Fundação e tenancy** (0.1–0.6): `Apportionment` (maior resto), `Money` + cast em centavos, `TenantContext`/`CompanyScope`/`BelongsToCompany`, `SetTenantContext`, onboarding transacional (`RegisterOwnerAndCompany`) e troca de empresa ativa.
- **Financeiro base** (0.4): `financial_entries`, actions de lançamento manual/atualização/cancelamento, transferências, recorrências, recálculo de saldo e matriz de fluxo de caixa (server-side; sem tela ainda).
- **Autenticação** (0.7–0.9): login, recuperação de senha e confirmação de e-mail.
- **Interface base + refino premium** (0.5, 0.10): tokens Tailwind v4, fontes auto-hospedadas (Archivo + IBM Plex Sans + IBM Plex Mono via Bunny), componentes `ui/*` (`button`, `link-button`, `input`, `select`, `card`, `stat-card`, `plate-chip`, `page-header`, `km-gauge`), shell (sidebar + topbar + bottom nav) e dashboard como painel de instrumentos.
- **Consulta de CNPJ no onboarding** (0.11): `App\Support\Cnpj\*` + `LookupCnpjController`.
- **Helper de formatação pt-BR** (0.12): `App\Support\Format` (seção 14.3) com alias global para a Blade; `km-gauge` e dashboard já consomem.
- **Livewire 4, Larastan nível 6 e CI** (0.13): `livewire/livewire` v4 (Alpine embutido, auto-injetado), `larastan/larastan` v3 com `phpstan.neon.dist` nível 6 zerado, e workflow `.github/workflows/ci.yml` (pint + phpstan + phpunit + build).

**Dívidas técnicas abertas** — resolver antes de escalar as telas de dados:

1. **Tipografia divergente da [seção 12.3](#123-tipografia).** O código usa IBM Plex Sans/Mono (alinhado à skill `frotika-ui`), não Inter/JetBrains Mono. Reconciliar: atualizar a 12.3 para IBM Plex (recomendado) ou trocar as fontes no `vite.config.js`.
2. **`RegisterOwnerAndCompanyRequest::failedValidation` sempre responde JSON 422**, quebrando `@error`/`old()` no fluxo web. Ajustar para redirect com erros quando a requisição não for `expectsJson`.
3. **CI roda os testes em SQLite `:memory:`** (o `phpunit.xml` fixa isso). A [instrução de testes](../.github/instructions/testes.instructions.md) pede MySQL 8 (json, colunas geradas, CTE recursiva, window functions). Enquanto nenhum teste usa esses recursos, o SQLite basta; ao usar o primeiro, provisionar um serviço MySQL no workflow e ajustar o `phpunit.xml`.

**Sequência recomendada**

1. Persistir endereço/telefone/e-mail do CNPJ no onboarding (ampliar `RegisterOwnerAndCompanyData`, a action e o request; colunas já existem em `companies`).
2. Concluir a Fase 1: wizard de onboarding, convite de usuários e seletor de empresa.
3. Fase 2 (Frota): primeira listagem Livewire copiando `frotika-ui/reference/exemplo-lista.blade.php`, já com a `km-gauge` no card do veículo.

---

## 17. Glossário

| pt-BR | Código |
| --- | --- |
| Grupo | `Group` |
| Empresa | `Company` |
| Cavalo mecânico | `VehicleType::Tractor` |
| Carreta / semirreboque | `VehicleType::SemiTrailer` |
| Conjunto | `VehicleComposition` |
| Motorista | `Driver` |
| Viagem | `Trip` |
| Abastecimento | `Fueling` |
| Manutenção | `Maintenance` |
| Fornecedor | `Supplier` |
| Conta bancária | `BankAccount` |
| Plano de contas | `FinancialCategory` |
| Lançamento | `FinancialEntry` |
| Fluxo de caixa | `CashFlow` |
| Data do serviço (competência) | `competence_date` |
| Data do pagamento | `paid_at` |
| Previsto / Realizado | `EntryStatus::Forecast` / `Settled` |
| Rateio | `Apportionment` |
| Tomador | `taker` |
| Remetente | `sender` |
| Destinatário | `recipient` |
| Chave de acesso | `access_key` |
| Assinatura | `Subscription` |
| Fatura | `Invoice` |

Mantidos em pt-BR/sigla: `cte`, `rntrc`, `cnh`, `mopp`, `cpf`, `cnpj`, `arla`, `pix`, `boleto`, `ipva`, `icms`, `toco`, `vuc`.

---

## 18. Pontos que exigem validação

**Antes de escrever código nessas áreas, o agente deve confirmar com o usuário ou com fonte oficial.**

### 18.1 Layout do CT-e — ⚠️ crítico

O mapeamento da [seção 7.3](#73-mapeamento-de-campos-a-validar) foi escrito a partir do layout 3.00 do modelo 57 rodoviário e **não foi validado contra o schema oficial vigente em julho/2026**. O layout 4.00 estava em implantação e pode alterar tags, obrigatoriedades e grupos.

**Ação obrigatória antes da Fase 3:**
1. Pedir ao usuário **5 a 10 XMLs reais** (podem ser anonimizados nos nomes, mas com a estrutura intacta), incluindo pelo menos um de Simples Nacional e um de tomador "outro".
2. Baixar o pacote de schemas (XSD) atual no Portal do CT-e.
3. Ler o campo `versao` de cada arquivo real e implementar **apenas** as versões encontradas.
4. Ajustar a seção 7.3 conforme o schema. Este documento é a hipótese; o XSD é a verdade.
5. Validar o XML contra o XSD antes do parse e falhar com mensagem clara.

### 18.2 Gateway de pagamento

Asaas é a recomendação, não uma decisão fechada. Confirmar com o usuário: taxas de Pix/boleto, se já existe conta, se prefere Stripe (melhor DX, sem boleto nativo) ou Pagar.me. A interface `BillingGateway` protege a decisão — mas a escolha do gateway define o cronograma da Fase 7.

### 18.3 Preços dos planos

Os limites da [seção 10.1](#101-planos-sugestão-inicial--preços-a-definir) são hipóteses baseadas no perfil "micro transportadora". Preço, nome dos planos e limites precisam vir do usuário antes da Fase 7.

### 18.4 Financiamento no DRE

O MVP lança a parcela inteira como custo fixo. Tecnicamente, só os juros são despesa — a amortização é redução de passivo. Para o público-alvo, "a parcela sai da minha conta" é a leitura correta e útil. **Decisão: manter a parcela integral no MVP**, documentar na tela (*"Considera a parcela cheia, como sai da conta"*) e revisitar se algum cliente com contador reclamar.

### 18.5 Multi-empresa e consolidação

O modelo suporta N empresas por grupo. **Não está definido** se o DRE consolidado deve cruzar empresas do mesmo grupo (a operação real pode ser uma só, dividida em dois CNPJs por razão fiscal). Perguntar ao usuário. Se sim, é uma mudança relevante no `DreBuilder` — melhor decidir antes da Fase 6 do que depois.

### 18.6 Cálculo de distância

`trips.distance_km` vem do odômetro ou é digitado. Não há integração de rotas no MVP. Se o usuário quiser distância automática (Google Directions, OSRM), é escopo novo — e o CT-e **não traz distância**, apenas municípios de origem e destino.

### 18.7 Custo por eixo / pneu

Controle de pneus por posição (vida útil, recapagem, rodízio) é uma dor real e comum em transportadoras, mas é um módulo inteiro. Fora do MVP. O custo de pneu entra como categoria de despesa (3.5), não como controle individual. Registrar como candidato à v2.

---

## Como usar este documento

1. Ler as seções 1–5 antes de escrever qualquer linha.
2. Seguir o roadmap em ordem. Cada fase fecha com testes verdes.
3. Ao encontrar ambiguidade: consultar a [seção 18](#18-pontos-que-exigem-validação). Se não estiver lá, **perguntar** — não adivinhar. Palpite em regra financeira vira número errado na tela do cliente.
4. Ao decidir algo não previsto aqui: registrar ADR em `docs/adr/NNN-titulo.md` e atualizar este documento.
5. Nunca contornar tenancy, nunca usar float para dinheiro, nunca somar direto das tabelas de origem, nunca pular o teste do parser.
