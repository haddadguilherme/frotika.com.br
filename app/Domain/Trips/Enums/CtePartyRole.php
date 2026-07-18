<?php

declare(strict_types=1);

namespace App\Domain\Trips\Enums;

enum CtePartyRole: string
{
    case Issuer = 'issuer';
    case Sender = 'sender';
    case Dispatcher = 'dispatcher';
    case Receiver = 'receiver';
    case Recipient = 'recipient';

    public function label(): string
    {
        return match ($this) {
            self::Issuer => 'Emitente',
            self::Sender => 'Remetente',
            self::Dispatcher => 'Expedidor',
            self::Receiver => 'Recebedor',
            self::Recipient => 'Destinatário',
        };
    }
}
