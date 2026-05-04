<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BetOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\BetOrder;
use App\Services\mall\OrderCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use ValueError;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderCommandService $orders,
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
        $order = BetOrder::query()->with('lines')->find($id);
        if ($order === null) {
            abort(404);
        }

        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required',
        ]);

        $order = BetOrder::query()->with('lines')->find($id);
        if ($order === null) {
            abort(404);
        }

        try {
            $next = BetOrderStatus::fromClient($validated['status']);
        } catch (ValueError) {
            return back()->withErrors(['status' => 'Invalid status.']);
        }

        try {
            $this->orders->transitionStatus($order, $next);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        if ($request->input('redirect_to') === 'list') {
            return redirect()->route('admin.orders.index')->with('status', 'Order updated.');
        }

        return redirect()->route('admin.orders.show', $id)
            ->with('status', 'Order updated.');
    }
}
