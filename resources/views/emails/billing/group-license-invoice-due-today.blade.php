@php
    /** @var \App\Models\User $user */
    /** @var \App\Domain\Billing\Models\GroupLicenseInvoice $invoice */

    $name = trim((string) ($user->name ?? ''));
    $firstName = $name !== '' ? explode(' ', $name)[0] : null;
    $groupName = (string) ($invoice->group?->name ?? 'Seu grupo');
@endphp

<x-mail.layout heading="Boleto da licença vence hoje"
    preheader="A cobrança de licença do grupo {{ $groupName }} vence hoje e ainda está pendente.">

    <p
        style="margin:0 0 16px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:15px; line-height:1.6; color:#475569;">
        Olá{{ $firstName ? ', ' . $firstName : '' }}! Este é um lembrete: o boleto da licença do grupo
        <strong style="color:#1a2536;">{{ $groupName }}</strong> vence hoje e ainda não foi marcado como pago.
    </p>

    <p
        style="margin:0 0 14px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:14px; line-height:1.7; color:#475569;">
        <strong style="color:#1a2536;">Valor:</strong>
        {{ \App\Support\Format::money((int) $invoice->getAttribute('amount_cents')) }}<br />
        <strong style="color:#1a2536;">Vencimento:</strong>
        {{ \App\Support\Format::date($invoice->getAttribute('due_date')) }}
        @if ($invoice->getAttribute('boleto_number'))
            <br /><strong style="color:#1a2536;">Linha digitável:</strong> {{ $invoice->getAttribute('boleto_number') }}
        @endif
    </p>

    @if ($invoice->getAttribute('boleto_url'))
        <x-mail.button :url="$invoice->getAttribute('boleto_url')">Abrir boleto</x-mail.button>
    @endif

    <p
        style="margin:0 0 8px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:13px; line-height:1.6; color:#64748b;">
        Após a confirmação do pagamento, a equipe pode dar baixa manualmente no painel da plataforma.
    </p>
</x-mail.layout>
