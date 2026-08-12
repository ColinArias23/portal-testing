<?php

namespace App\Enums;

enum SignatureType: string
{
    case Primary = 'primary';
    case Countersign = 'countersign';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary Signature',
            self::Countersign => 'Countersign Signature',
        };
    }
}
