<?php

use App\Domain\Billing\Enums\GroupLicenseInvoiceStatus;
use App\Domain\Billing\Models\GroupLicenseInvoice;
use App\Domain\Finance\Actions\GenerateForecastEntriesFromRecurrences;
use App\Domain\Finance\Actions\RecalculateBankAccountCurrentBalance;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\Billing\GroupLicenseInvoiceDueTodayNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('frotika:promote-platform-admin {email}', function (): int {
    $email = (string) $this->argument('email');

    $user = User::query()->where('email', $email)->first();

    if (! $user instanceof User) {
        $this->error(sprintf('Nenhum usuario encontrado com o e-mail %s.', $email));

        return Command::FAILURE;
    }

    $user->forceFill(['is_platform_admin' => true])->save();

    $this->info(sprintf('Usuario %s promovido a administrador da plataforma.', $email));

    return Command::SUCCESS;
})->purpose('Marca uma conta existente como administradora da plataforma (is_platform_admin)');

Artisan::command('frotika:recalculate-balances {--company=} {--dry-run}', function (
    TenantContext $tenant,
    RecalculateBankAccountCurrentBalance $recalculate,
): int {
    $companyOption = $this->option('company');
    $companyId = is_numeric($companyOption) ? (int) $companyOption : null;
    $dryRun = (bool) $this->option('dry-run');

    $companies = Company::query()
        ->when($companyId !== null, fn ($query) => $query->whereKey($companyId))
        ->get();

    if ($companies->isEmpty()) {
        $this->error('Nenhuma empresa encontrada para reconciliacao.');

        return Command::FAILURE;
    }

    $processedAccounts = 0;
    $updatedAccounts = 0;
    $divergentAccounts = 0;

    foreach ($companies as $company) {
        $bankAccounts = $tenant->runFor($company, fn () => BankAccount::query()->get());

        foreach ($bankAccounts as $bankAccount) {
            $processedAccounts++;

            $current = (int) $bankAccount->current_balance_cents;

            $expected = $tenant->runFor($company, function () use ($bankAccount): int {
                $initial = (int) $bankAccount->initial_balance_cents;

                $revenueTotal = (int) FinancialEntry::query()
                    ->where('bank_account_id', $bankAccount->getKey())
                    ->where('status', FinancialEntryStatus::Settled->value)
                    ->whereNotNull('paid_at')
                    ->where('type', FinancialEntryType::Revenue->value)
                    ->sum('amount_cents');

                $expenseTotal = (int) FinancialEntry::query()
                    ->where('bank_account_id', $bankAccount->getKey())
                    ->where('status', FinancialEntryStatus::Settled->value)
                    ->whereNotNull('paid_at')
                    ->where('type', FinancialEntryType::Expense->value)
                    ->sum('amount_cents');

                return $initial + $revenueTotal - $expenseTotal;
            });

            if ($current !== $expected) {
                $divergentAccounts++;
            }

            if (! $dryRun) {
                $newBalance = $recalculate->execute($company, (int) $bankAccount->getKey());

                if ($newBalance !== $current) {
                    $updatedAccounts++;
                }
            }
        }
    }

    $modeLabel = $dryRun ? 'dry-run' : 'apply';

    $this->info(sprintf(
        'Reconciliacao concluida (%s): empresas=%d, contas=%d, divergentes=%d, atualizadas=%d',
        $modeLabel,
        $companies->count(),
        $processedAccounts,
        $divergentAccounts,
        $updatedAccounts,
    ));

    return Command::SUCCESS;
})->purpose('Recalcula e reconcilia current_balance_cents das contas bancarias');

Artisan::command('frotika:generate-recurrences {--company=} {--reference-date=} {--dry-run}', function (
    TenantContext $tenant,
    GenerateForecastEntriesFromRecurrences $generate,
): int {
    $companyOption = $this->option('company');
    $companyId = is_numeric($companyOption) ? (int) $companyOption : null;
    $referenceDate = (string) ($this->option('reference-date') ?: now()->toDateString());
    $dryRun = (bool) $this->option('dry-run');

    $companies = Company::query()
        ->when($companyId !== null, fn ($query) => $query->whereKey($companyId))
        ->get();

    if ($companies->isEmpty()) {
        $this->error('Nenhuma empresa encontrada para gerar recorrencias.');

        return Command::FAILURE;
    }

    $totals = [
        'recurrences_processed' => 0,
        'recurrences_skipped' => 0,
        'occurrences_evaluated' => 0,
        'entries_created' => 0,
    ];

    foreach ($companies as $company) {
        $result = $tenant->runFor($company, fn () => $generate->execute($company, $referenceDate, $dryRun));

        $totals['recurrences_processed'] += $result['recurrences_processed'];
        $totals['recurrences_skipped'] += $result['recurrences_skipped'];
        $totals['occurrences_evaluated'] += $result['occurrences_evaluated'];
        $totals['entries_created'] += $result['entries_created'];
    }

    $modeLabel = $dryRun ? 'dry-run' : 'apply';

    $this->info(sprintf(
        'Geracao de recorrencias concluida (%s): empresas=%d, recorrencias=%d, ignoradas=%d, ocorrencias=%d, criadas=%d',
        $modeLabel,
        $companies->count(),
        $totals['recurrences_processed'],
        $totals['recurrences_skipped'],
        $totals['occurrences_evaluated'],
        $totals['entries_created'],
    ));

    return Command::SUCCESS;
})->purpose('Gera lancamentos previstos a partir de recorrencias ativas');

Artisan::command('frotika:mail-preview {--to=}', function (): int {
    $to = (string) ($this->option('to') ?: config('mail.from.address') ?: 'dev@frotika.test');

    // Notifiable transitorio: nao persiste nada, so precisa de chave + e-mail
    // para o framework montar as URLs assinadas/de reset.
    $user = new User(['name' => 'Guilherme (preview)', 'email' => $to]);
    $user->id = 999999;

    $emails = [
        'Confirmacao de e-mail' => fn () => $user->notify(new VerifyEmailNotification),
        'Redefinicao de senha' => fn () => $user->notify(new ResetPasswordNotification(Str::random(64))),
    ];

    foreach ($emails as $label => $send) {
        $send();
        $this->line(sprintf('  ✓ %s', $label));
    }

    $this->info(sprintf('%d e-mail(s) enviados para %s. Abra o MailHog para avaliar.', count($emails), $to));

    return Command::SUCCESS;
})->purpose('Envia todos os e-mails do sistema (branded) para avaliacao no MailHog');

Artisan::command('frotika:notify-group-license-due-today', function (): int {
    $today = now()->toDateString();

    $invoices = GroupLicenseInvoice::query()
        ->with(['group.owner'])
        ->whereDate('due_date', $today)
        ->whereIn('status', [
            GroupLicenseInvoiceStatus::Pending->value,
            GroupLicenseInvoiceStatus::Overdue->value,
        ])
        ->get();

    if ($invoices->isEmpty()) {
        $this->info('Nenhum boleto pendente vencendo hoje para notificar.');

        return Command::SUCCESS;
    }

    $sent = 0;
    $skipped = 0;

    foreach ($invoices as $invoice) {
        $owner = $invoice->group?->owner;

        if (! $owner instanceof User || trim((string) $owner->email) === '') {
            $skipped++;

            continue;
        }

        $owner->notify(new GroupLicenseInvoiceDueTodayNotification($invoice));
        $sent++;
    }

    $this->info(sprintf(
        'Notificacoes de vencimento enviadas: %d, sem destinatario: %d.',
        $sent,
        $skipped,
    ));

    return Command::SUCCESS;
})->purpose('Envia e-mail no dia do vencimento para boletos de licenca ainda nao pagos');

Schedule::command('frotika:generate-recurrences')
    ->monthlyOn(1, '01:30')
    ->withoutOverlapping();

Schedule::command('frotika:recalculate-balances')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('frotika:notify-group-license-due-today')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('backup:run --only-db')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::command('backup:run --only-db')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('backup:run --only-db')
    ->dailyAt('12:00')
    ->withoutOverlapping();

Schedule::command('backup:run --only-db')
    ->dailyAt('18:00')
    ->withoutOverlapping();

Schedule::command('backup:clean')
    ->dailyAt('01:30')
    ->withoutOverlapping();

Schedule::command('backup:monitor')
    ->dailyAt('07:00')
    ->withoutOverlapping();
