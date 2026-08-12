<?php

namespace App\Enums;

enum LeaveBalanceTransactionType: string
{
    case Accrual = 'accrual';
    case Usage = 'usage';
    case Adjustment = 'adjustment';
    case CarryForward = 'carry_forward';
    case Monetization = 'monetization';
    case Restoration = 'restoration'; 
}
