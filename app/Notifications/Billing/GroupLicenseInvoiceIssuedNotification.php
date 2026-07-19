<?php

declare(strict_types=1);

namespace App\Notifications\Billing;

use App\Domain\Billing\Models\GroupLicenseInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

final class GroupLicenseInvoiceIssuedNotification extends Notification
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

        $mail = (new MailMessage)
            ->subject(sprintf('Novo boleto da licença do grupo %s — Frotika', $groupName))
            ->view('emails.billing.group-license-invoice-issued', [
                'invoice' => $this->invoice,
                'user' => $notifiable,
            ]);

        $boletoPath = $this->invoice->getAttribute('boleto_file_path');

        if (is_string($boletoPath) && $boletoPath !== '') {
            $disk = Storage::disk((string) config('billing.group_license_invoice_boleto_disk', 'local'));

            if ($disk->exists($boletoPath)) {
                $attachmentName = $this->resolveAttachmentName($boletoPath);

                $mail->attach($disk->path($boletoPath), [
                    'as' => $attachmentName,
                    'mime' => $this->resolveAttachmentMime($attachmentName),
                ]);
            }
        }

        return $mail;
    }

    private function resolveAttachmentName(string $path): string
    {
        $originalName = $this->invoice->getAttribute('boleto_file_original_name');

        if (is_string($originalName) && trim($originalName) !== '') {
            return trim($originalName);
        }

        return basename($path);
    }

    private function resolveAttachmentMime(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
