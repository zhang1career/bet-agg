<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Repos\mall\PointsBalanceRepo;

final readonly class PointsAdminService
{
    public function __construct(
        private PointsBalanceRepo $balances,
    ) {}

    /**
     * Available points balance for {@code uid}; missing row yields 0.
     */
    public function availableBalance(int $uid): int
    {
        $row = $this->balances->findByUid($uid);
        if ($row === null) {
            return 0;
        }

        return (int) $row->balance;
    }
}
