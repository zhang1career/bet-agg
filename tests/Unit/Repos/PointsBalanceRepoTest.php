<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Repos\mall\PointsBalanceRepo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Tests\TestCase;

final class PointsBalanceRepoTest extends TestCase
{
    private PointsBalanceRepo $balances;

    protected function setUp(): void
    {
        parent::setUp();
        $this->balances = app(PointsBalanceRepo::class);
    }

    public function test_createAccount_and_findByUid(): void
    {
        $row = $this->balances->createAccount(42, 100);

        $found = $this->balances->findByUid(42);
        $this->assertNotNull($found);
        $this->assertSame((int) $row->id, (int) $found->id);
        $this->assertSame(100, $found->balance);
    }

    public function test_createAccount_throws_when_duplicate(): void
    {
        $this->balances->createAccount(42, 0);

        $this->expectException(RuntimeException::class);
        $this->balances->createAccount(42, 0);
    }

    public function test_adjustBalanceByUid_applies_delta(): void
    {
        $this->balances->createAccount(42, 50);

        $updated = $this->balances->adjustBalanceByUid(42, -10);

        $this->assertSame(40, $updated->balance);
    }

    public function test_ensureLockedProfile_creates_row_when_missing(): void
    {
        $row = $this->balances->ensureLockedProfile(99);

        $this->assertSame(99, $row->uid);
        $this->assertSame(0, $row->balance);
        $this->assertNotNull($this->balances->findByUid(99));
    }

    public function test_findOrFail_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->balances->findOrFail(999_999);
    }
}
