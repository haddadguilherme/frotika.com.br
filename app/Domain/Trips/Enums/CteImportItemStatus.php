<?php

declare(strict_types=1);

namespace App\Domain\Trips\Enums;

enum CteImportItemStatus: string
{
    case Imported = 'imported';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Imported => 'Importado',
            self::Failed => 'Falhou',
        };
    }
}
