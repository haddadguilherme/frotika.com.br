<?php

declare(strict_types=1);

namespace App\Notifications\Billing;

use App\Domain\Billing\Models\GroupLicenseInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class GroupLicenseInvoiceDueTodayNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly GroupLicenseInvoice $invoice,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $groupName = (string) ($this->invoice->group?->name ?? 'seu grupo');

        return (new MailMessage)
            ->subject(sprintf('Boleto da licença vence hoje (%s) — Frotika', $groupName))
            ->view('emails.billing.group-license-invoice-due-today', [
                'invoice' => $this->invoice,
                'user' => $notifiable,
            ]);
    }
}
