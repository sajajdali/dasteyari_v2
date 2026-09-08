<?php

namespace App\Enums;

enum TxKind: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'واریز',
            self::Out => 'برداشت',
        };
    }
}
