<?php

declare(strict_types=1);

namespace App\Enums;

enum BetLineResult: int
{
    case Win = 1;
    case Lose = 2;
    case Void = 3;
}
