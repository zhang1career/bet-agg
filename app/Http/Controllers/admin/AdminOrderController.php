<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\mall\OrderAdminService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderAdminService $orders,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        return view('admin.orders.index', [
            'orders' => $this->orders->paginateIndex($perPage),
        ]);
    }

    public function show(int $id): View
    {
        return view('admin.orders.show', $this->orders->showViewData($id));
    }
}
