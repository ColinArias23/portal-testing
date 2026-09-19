<?php

namespace App\Enums;

enum LeaveRequestStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case ReturnedForRevision = 'returned_for_revision';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Retracted = 'retracted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::ReturnedForRevision => 'Returned for Clarification/Revision',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected/Disapproved',
            self::Cancelled => 'Cancelled',
            self::Retracted => 'Retracted',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled, self::Retracted], true);
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }
}