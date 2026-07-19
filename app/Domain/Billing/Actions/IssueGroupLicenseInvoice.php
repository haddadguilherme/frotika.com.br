<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Data\IssueGroupLicenseInvoiceData;
use App\Domain\Billing\Enums\GroupLicenseInvoiceStatus;
use App\Domain\Billing\Enums\GroupLicenseStatus;
use App\Domain\Billing\Models\GroupLicense;
use App\Domain\Billing\Models\GroupLicenseInvoice;
use App\Models\User;
use App\Notifications\Billing\GroupLicenseInvoiceIssuedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class IssueGroupLicenseInvoice
{
    public function execute(User $actor, GroupLicense $license, IssueGroupLicenseInvoiceData $data): GroupLicenseInvoice
    {
        Gate::forUser($actor)->authorize('access-platform');

        if ($license->trial_ends_at !== null && now()->lt($license->trial_ends_at)) {
            throw ValidationException::withMessages([
                'due_date' => 'A licença ainda está em trial. Emita o boleto após o fim do período de avaliação.',
            ]);
        }

        $hasOpenInvoice = $license->invoices()
            ->whereIn('status', [
                GroupLicenseInvoiceStatus::Pending->value,
                GroupLicenseInvoiceStatus::Overdue->value,
            ])
            ->exists();

        if ($hasOpenInvoice) {
            throw ValidationException::withMessages([
                'group_license_id' => 'Já existe boleto pendente para este grupo.',
            ]);
        }

        /** @var GroupLicenseInvoice $invoice */
        $invoice = DB::transaction(function () use ($actor, $license, $data): GroupLicenseInvoice {
            $invoice = GroupLicenseInvoice::query()->create([
                'group_license_id' => $license->getKey(),
                'group_id' => $license->group_id,
                'reference_month' => $data->referenceMonth->toDateString(),
                'amount_cents' => $data->amountCents,
                'due_date' => $data->dueDate->toDateString(),
                'status' => GroupLicenseInvoiceStatus::Pending,
                'boleto_number' => $data->boletoNumber,
                'boleto_url' => $data->boletoUrl,
                'boleto_pdf_url' => $data->boletoPdfUrl,
                'boleto_file_path' => $data->boletoFilePath,
                'boleto_file_original_name' => $data->boletoFileOriginalName,
                'created_by_user_id' => $actor->getKey(),
            ]);

            $license->forceFill([
                'status' => GroupLicenseStatus::PendingPayment,
                'suspended_at' => null,
            ])->save();

            return $invoice;
        });

        $invoice->loadMissing(['group.owner']);

        $owner = $invoice->group?->owner;

        if ($owner instanceof User && trim((string) $owner->email) !== '') {
            $owner->notify(new GroupLicenseInvoiceIssuedNotification($invoice));
        }

        return $invoice;
    }
}
