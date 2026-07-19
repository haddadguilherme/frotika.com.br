@php
    /** @var \App\Models\User $user */
    /** @var \App\Domain\Billing\Models\GroupLicenseInvoice $invoice */

    $name = trim((string) ($user->name ?? ''));
    $firstName = $name !== '' ? explode(' ', $name)[0] : null;
    $groupName = (string) ($invoice->group?->name ?? 'Seu grupo');
@endphp

<x-mail.layout heading="Novo boleto da licença"
    preheader="Uma nova cobrança de licença foi registrada para {{ $groupName }}.">

    <p
        style="margin:0 0 16px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:15px; line-height:1.6; color:#475569;">
        Olá{{ $firstName ? ', ' . $firstName : '' }}! Registramos um novo boleto da licença do grupo
        <strong style="color:#1a2536;">{{ $groupName }}</strong>.
    </p>

    <p
        style="margin:0 0 14px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:14px; line-height:1.7; color:#475569;">
        <strong style="color:#1a2536;">Valor:</strong>
        {{ \App\Support\Format::money((int) $invoice->getAttribute('amount_cents')) }}<br />
        <strong style="color:#1a2536;">Vencimento:</strong>
        {{ \App\Support\Format::date($invoice->getAttribute('due_date')) }}<br />
        <strong style="color:#1a2536;">Competência:</strong>
        {{ \App\Support\Format::date($invoice->getAttribute('reference_month')) }}
        @if ($invoice->getAttribute('boleto_number'))
            <br /><strong style="color:#1a2536;">Linha digitável:</strong> {{ $invoice->getAttribute('boleto_number') }}
        @endif
    </p>

    @if ($invoice->getAttribute('boleto_url'))
        <x-mail.button :url="$invoice->getAttribute('boleto_url')">Abrir boleto</x-mail.button>
    @endif

    <p
        style="margin:0 0 8px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:13px; line-height:1.6; color:#64748b;">
        O documento do boleto foi anexado neste e-mail para facilitar a conferência e o pagamento.
    </p>
</x-mail.layout>
