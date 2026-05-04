<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConfigurationMissingException;
use App\Http\Controllers\Controller;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\PointsAdminService;
use App\Services\user\GatewayUserByIdClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use RuntimeException;

class AdminPointsController extends Controller
{
    private const MAX_USER_IDS_PER_BATCH = 50;

    public function __construct(
        private readonly PointsAdminService $adminPoints,
        private readonly GatewayUserByIdClient $gatewayUserById,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'flows' ? 'flows' : 'balances';
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        if ($tab === 'balances') {
            $balances = PointsBalance::query()->orderByDesc('id')->paginate($perPage)->withQueryString();

            return view('admin.points.index', [
                'tab' => $tab,
                'balances' => $balances,
                'flows' => null,
            ]);
        }

        $flows = PointsFlow::query()->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.points.index', [
            'tab' => $tab,
            'balances' => null,
            'flows' => $flows,
        ]);
    }

    public function showUser(int $user_id): JsonResponse
    {
        try {
            $user = $this->gatewayUserById->fetch($user_id);
        } catch (ConfigurationMissingException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (DownstreamServiceException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

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

        try {
            $users = $this->gatewayUserById->fetchMany($ids);
        } catch (ConfigurationMissingException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (DownstreamServiceException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['users' => $users]);
    }

    public function showBalance(int $id): View
    {
        $row = PointsBalance::query()->findOrFail($id);

        return view('admin.points.balances.show', ['balance' => $row]);
    }

    public function showFlow(int $id): View
    {
        $row = PointsFlow::query()->findOrFail($id);

        return view('admin.points.flows.show', ['flow' => $row]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'uid' => 'required|integer|min:1',
            'balance' => 'nullable|integer|min:0',
        ]);

        try {
            $this->adminPoints->openAccount((int) $d['uid'], (int) ($d['balance'] ?? 0));
        } catch (RuntimeException $e) {
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors(['account' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Points account created.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'uid' => 'required|integer|min:1',
            'delta_points' => 'required|integer|not_in:0',
            'oid' => 'nullable|integer|min:0',
        ]);

        try {
            $this->adminPoints->adjustBalance(
                (int) $d['uid'],
                (int) $d['delta_points'],
                (int) ($d['oid'] ?? 0),
            );
        } catch (RuntimeException $e) {
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors(['adjust' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Points balance updated.');
    }

    public function destroyBalance(int $id): RedirectResponse
    {
        try {
            $this->adminPoints->deleteBalanceById($id);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Account deleted.');
    }
}
