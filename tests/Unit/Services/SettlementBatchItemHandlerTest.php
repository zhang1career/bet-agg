<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\mall\settlement\SettlementBatchItemHandler;
use Tests\TestCase;

final class SettlementBatchItemHandlerTest extends TestCase
{
    public function test_resolves_from_container(): void
    {
        $handler = app(SettlementBatchItemHandler::class);

        $this->assertInstanceOf(SettlementBatchItemHandler::class, $handler);
    }
}
