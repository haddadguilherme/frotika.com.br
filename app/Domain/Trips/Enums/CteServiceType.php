<?php

declare(strict_types=1);

namespace App\Domain\Trips\Enums;

enum CteServiceType: string
{
    case Normal = 'normal';
    case Subcontracting = 'subcontracting';
    case Redispatch = 'redispatch';
    case IntermediateRedispatch = 'intermediate_redispatch';
    case MultimodalLinked = 'multimodal_linked';

    public static function fromCode(string $code): self
    {
        return match (trim($code)) {
            '0' => self::Normal,
            '1' => self::Subcontracting,
            '2' => self::Redispatch,
            '3' => self::IntermediateRedispatch,
            '4' => self::MultimodalLinked,
            default => self::Normal,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Subcontracting => 'Subcontratação',
            self::Redispatch => 'Redespacho',
            self::IntermediateRedispatch => 'Redespacho intermediário',
            self::MultimodalLinked => 'Serviço vinculado a multimodal',
        };
    }
}
