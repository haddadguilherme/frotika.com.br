<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Domain\Billing\Actions\IssueGroupLicenseInvoice;
use App\Domain\Billing\Data\IssueGroupLicenseInvoiceData;
use App\Domain\Billing\Models\GroupLicense;
use App\Models\User;
use App\Platform\Http\Requests\IssueGroupLicenseInvoiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class IssueGroupLicenseInvoiceController
{
    public function __invoke(
        IssueGroupLicenseInvoiceRequest $request,
        GroupLicense $license,
        IssueGroupLicenseInvoice $action,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $request->validated();
        $boletoDisk = (string) config('billing.group_license_invoice_boleto_disk', 'local');

        $dueDate = CarbonImmutable::parse($validated['due_date']);
        $referenceMonth = isset($validated['reference_month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['reference_month'])->startOfMonth()
            : $dueDate->startOfMonth();

        $storedBoletoPath = null;
        $storedBoletoOriginalName = null;
        $uploadedBoleto = $request->file('boleto_file');

        if ($uploadedBoleto instanceof UploadedFile) {
            $groupUuid = (string) ($license->group()->value('uuid') ?? 'sem-grupo');
            $directory = sprintf(
                'grupos/%s/licencas/boletos/%s/%s',
                $groupUuid,
                $referenceMonth->format('Y'),
                $referenceMonth->format('m'),
            );

            $extension = $uploadedBoleto->getClientOriginalExtension();
            $fileName = sprintf(
                'boleto-%s-%s%s',
                $dueDate->format('Ymd'),
                Str::uuid()->toString(),
                $extension !== '' ? '.'.$extension : '',
            );

            $storedBoletoPath = $uploadedBoleto->storeAs($directory, $fileName, $boletoDisk);
            $storedBoletoOriginalName = $uploadedBoleto->getClientOriginalName();
        }

        try {
            $action->execute(
                $user,
                $license,
                new IssueGroupLicenseInvoiceData(
                    amountCents: $request->amountCents(),
                    dueDate: $dueDate,
                    referenceMonth: $referenceMonth,
                    boletoNumber: $validated['boleto_number'] ?? null,
                    boletoUrl: $validated['boleto_url'] ?? null,
                    boletoPdfUrl: $validated['boleto_pdf_url'] ?? null,
                    boletoFilePath: $storedBoletoPath,
                    boletoFileOriginalName: $storedBoletoOriginalName,
                ),
            );
        } catch (Throwable $exception) {
            if ($storedBoletoPath !== null) {
                Storage::disk($boletoDisk)->delete($storedBoletoPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('platform.groups.show', ['group' => $license->group_id])
            ->with('status', 'Boleto lançado com sucesso para o grupo.');
    }
}
