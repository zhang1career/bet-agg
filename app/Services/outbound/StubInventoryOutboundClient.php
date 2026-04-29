<?php

declare(strict_types=1);

namespace App\Services\outbound;

use App\Contracts\InventoryOutboundContract;
use App\Services\mall\SportSelectionBookService;
use Illuminate\Support\Str;

/**
 * Validates sport selections via {@see SportSelectionBookService}; no external inventory HTTP yet.
 */
final class StubInventoryOutboundClient implements InventoryOutboundContract
{
    public function __construct(
        private readonly SportSelectionBookService $book,
    ) {}

    public function reserve(int $uid, array $lines): array
    {
        $this->book->assertSelectionsAcceptingBets($uid, $lines);

        return ['reserve_id' => 'stub:'.Str::uuid()->toString()];
    }

    public function release(string $reserveId): void
    {
        // No external call until inventory outbound is integrated.
    }
}
