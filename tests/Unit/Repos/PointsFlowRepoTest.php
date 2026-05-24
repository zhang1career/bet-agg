<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Enums\PointsFlowKind;
use App\Repos\mall\PointsFlowRepo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

final class PointsFlowRepoTest extends TestCase
{
    private PointsFlowRepo $flows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flows = app(PointsFlowRepo::class);
    }

    public function test_create_and_existsForOrderAndState(): void
    {
        $flow = $this->flows->create(42, 1001, 10, PointsFlowKind::WinCredit);

        $this->assertGreaterThan(0, $flow->id);
        $this->assertTrue($this->flows->existsForOrderAndState(1001, PointsFlowKind::WinCredit));
        $this->assertFalse($this->flows->existsForOrderAndState(1001, PointsFlowKind::LossDebit));
    }

    public function test_findOrFail_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->flows->findOrFail(999_999);
    }
}
