<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BetOrder;
use App\Services\mall\SettlementConsoleOverviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only admin browse for bet orders. Order state is now driven exclusively
 * by {@see \App\Services\mall\BetPlaceService} (placement) and
 * {@see \App\Services\mall\BetSettlementService} (settlement); ad-hoc status
 * mutations from the admin UI are no longer supported.
 */
class AdminOrderController extends Controller
{
    public function __construct(
        private readonly SettlementConsoleOverviewService $settlementOverview,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $orders = BetOrder::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return view('admin.orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(int $id): View
    {
        $order = BetOrder::query()
            ->with(['lines.market.game'])
            ->find($id);
        if ($order === null) {
            abort(404);
        }

        $gameIds = [];
        foreach ($order->lines as $line) {
            $g = $line->market?->game_id;
            if ($g !== null && (int) $g >= 1) {
                $gameIds[(int) $g] = true;
            }
        }
        $gameIds = array_keys($gameIds);
        sort($gameIds);

        $jobsByGameId = [];
        foreach ($gameIds as $gid) {
            $jobsByGameId[$gid] = $this->settlementOverview->recentJobsForGame($gid, 5);
        }

        return view('admin.orders.show', [
            'order' => $order,
            'settlementGameIds' => $gameIds,
            'settlementJobsByGameId' => $jobsByGameId,
        ]);
    }
}
