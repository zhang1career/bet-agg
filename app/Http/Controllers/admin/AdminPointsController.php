<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\mall\PointsService;
use App\Services\user\GatewayUserByIdClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPointsController extends Controller
{
    private const MAX_USER_IDS_PER_BATCH = 50;

    public function __construct(
        private readonly PointsService $points,
        private readonly GatewayUserByIdClient $gatewayUserById,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'flows' ? 'flows' : 'balances';
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        if ($tab === 'balances') {
            return view('admin.points.index', [
                'tab' => $tab,
                'balances' => $this->points->paginateBalances($perPage),
                'flows' => null,
            ]);
        }

        return view('admin.points.index', [
            'tab' => $tab,
            'balances' => null,
            'flows' => $this->points->paginateFlows($perPage),
        ]);
    }

    public function showUser(int $user_id): JsonResponse
    {
        $user = $this->gatewayUserById->fetch($user_id);

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json(['user' => $user]);
    }

    public function indexUsers(Request $request): JsonResponse
    {
        $raw = $request->query('user_ids');
        if ($raw === null || $raw === '') {
            return response()->json(['users' => []]);
        }

        if (! is_string($raw)) {
            return response()->json(['message' => 'Invalid user_ids.'], 422);
        }

        $parts = array_filter(array_map(trim(...), explode(',', $raw)), static fn (string $s): bool => $s !== '');
        $uniqueIds = [];
        foreach ($parts as $p) {
            if (preg_match('/^\d+$/', $p) !== 1) {
                return response()->json(['message' => 'Invalid user_ids.'], 422);
            }
            $id = (int) $p;
            if ($id < 1) {
                return response()->json(['message' => 'Invalid user_ids.'], 422);
            }
            $uniqueIds[$id] = true;
        }

        $ids = array_keys($uniqueIds);

        if (count($ids) > self::MAX_USER_IDS_PER_BATCH) {
            return response()->json(['message' => 'Too many user_ids.'], 422);
        }

        return response()->json(['users' => $this->gatewayUserById->fetchMany($ids)]);
    }

    public function showBalance(int $id): View
    {
        return view('admin.points.balances.show', [
            'balance' => $this->points->findBalanceForShow($id),
        ]);
    }

    public function showFlow(int $id): View
    {
        return view('admin.points.flows.show', [
            'flow' => $this->points->findFlowForShow($id),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'uid' => 'required|integer|min:1',
            'balance' => 'nullable|integer',
        ]);

        $errors = $this->points->createAccount(
            (int) $d['uid'],
            (int) ($d['balance'] ?? 0),
        );
        if ($errors !== null) {
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors($errors)
                ->withInput();
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Points account created.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'uid' => 'required|integer|min:1',
            'delta_points' => 'required|integer|not_in:0',
        ]);

        $errors = $this->points->adjustBalance((int) $d['uid'], (int) $d['delta_points']);
        if ($errors !== null) {
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors($errors)
                ->withInput();
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Points balance updated.');
    }

    public function destroyBalance(int $id): RedirectResponse
    {
        $this->points->deleteBalance($id);

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Profile deleted.');
    }
}
