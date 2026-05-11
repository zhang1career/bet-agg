<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Services\user\GatewayUserByIdClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class AdminPointsController extends Controller
{
    private const MAX_USER_IDS_PER_BATCH = 50;

    public function __construct(
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

        $users = $this->gatewayUserById->fetchMany($ids);

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
            'balance' => 'nullable|integer',
        ]);

        $uid = (int) $d['uid'];
        $initial = (int) ($d['balance'] ?? 0);

        try {
            DB::transaction(function () use ($uid, $initial): void {
                $p = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
                if ($p !== null) {
                    throw new RuntimeException('Reputation profile already exists for this user.');
                }
                $row = new PointsBalance(['uid' => $uid, 'balance' => $initial]);
                $row->save();
            });
        } catch (RuntimeException $e) {
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors(['account' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Reputation profile created.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'uid' => 'required|integer|min:1',
            'delta_points' => 'required|integer|not_in:0',
        ]);

        $uid = (int) $d['uid'];
        $delta = (int) $d['delta_points'];

        try {
            DB::transaction(function () use ($uid, $delta): void {
                $p = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
                if ($p === null) {
                    throw new RuntimeException('Reputation profile not found for this user.');
                }
                $p->balance = $p->balance + $delta;
                $p->save();
            });
        } catch (RuntimeException $e) {
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors(['adjust' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Reputation score updated.');
    }

    public function destroyBalance(int $id): RedirectResponse
    {
        $row = PointsBalance::query()->findOrFail($id);
        $row->delete();

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Profile deleted.');
    }
}
