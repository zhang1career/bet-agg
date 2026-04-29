<?php

namespace App\Services\user;

use Paganini\Aggregation\Policies\DefaultDegradePolicy;

class UserDegradePolicy extends DefaultDegradePolicy
{
    public function __construct()
    {
        parent::__construct(
            (string)config('bet_agg.degrade.strategy', self::STRATEGY_MASK_NULL),
            (string)config('bet_agg.degrade.mask_error_message', 'Service temporarily unavailable.')
        );
    }
}
