<?php

declare(strict_types=1);

namespace App\Enums;

enum ReputationFlowKind: int
{
    case WinCredit = 1;
    case LossDebit = 2;
}
