<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BetOrder;
use App\Services\mall\BetPlaceService;
use App\Services\mall\BetSettlementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only admin browse for bet orders. Order state is now driven exclusively
 * by {@see BetPlaceService} (placement) and
 * {@see BetSettlementService} (settlement); ad-hoc status
 * mutations from the admin UI are no longer supported.
 */
class AdminOrderController extends Controller
{
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
        $order = BetOrder::query()->with('lines')->find($id);
        if ($order === null) {
            abort(404);
        }

        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }
}
