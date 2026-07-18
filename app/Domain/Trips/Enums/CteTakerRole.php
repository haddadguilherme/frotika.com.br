<?php

declare(strict_types=1);

namespace App\Domain\Trips\Enums;

enum CteTakerRole: string
{
    case Sender = 'sender';
    case Dispatcher = 'dispatcher';
    case Receiver = 'receiver';
    case Recipient = 'recipient';
    case Own = 'own';

    /**
     * Mapeia o `toma` do grupo ide/toma3 (0..3) ou ide/toma4 (4).
     */
    public static function fromCode(string $code): self
    {
        return match (trim($code)) {
            '0' => self::Sender,
            '1' => self::Dispatcher,
            '2' => self::Receiver,
            '3' => self::Recipient,
            '4' => self::Own,
            default => self::Own,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sender => 'Remetente',
            self::Dispatcher => 'Expedidor',
            self::Receiver => 'Recebedor',
            self::Recipient => 'Destinatário',
            self::Own => 'Tomador (outros)',
        };
    }
}
