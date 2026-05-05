<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Enums\SportGameStatus;
use App\Http\Controllers\Controller;
use App\Models\SportGame;
use App\Services\mall\serv_fd\CmsGameClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Throwable;

class AdminGameController extends Controller
{
    public function __construct(
        private readonly CmsGameClient $cmsGameClient,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $games = SportGame::query()
            ->withCount('markets')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $cmsByRawId = [];
        try {
            $rawIds = $games->getCollection()
                ->map(static fn (SportGame $g): int => (int) $g->raw_id)
                ->unique()
                ->values()
                ->all();
            if ($rawIds !== []) {
                $cmsByRawId = $this->cmsGameClient->findManyById($rawIds);
            }
        } catch (Throwable) {
        }

        return view('admin.games.index', [
            'games' => $games,
            'cmsByRawId' => $cmsByRawId,
        ]);
    }

    public function create(): View
    {
        return view('admin.games.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:500',
            'starts_at' => 'nullable|integer|min:0',
            'banner_path' => 'nullable|string|max:2000',
            'main_image_path' => 'nullable|string|max:2000',
            'status' => ['required', 'integer', Rule::enum(SportGameStatus::class)],
        ]);

        $cmsPayload = $this->cmsPayloadFromValidatedGameForm($v);

        try {
            $created = $this->cmsGameClient->create($cmsPayload);
        } catch (DownstreamServiceException $e) {
            return back()->withInput()->withErrors(['cms' => $e->getMessage()]);
        }

        $rawId = (int) ($created['id'] ?? 0);
        if ($rawId < 1) {
            return back()->withInput()->withErrors(['cms' => 'Invalid create response.']);
        }

        if (SportGame::query()->where('raw_id', $rawId)->exists()) {
            return back()->withInput()->withErrors([
                'cms' => 'Local game already exists for CMS id '.$rawId.'.',
            ]);
        }

        $game = new SportGame([
            'raw_id' => $rawId,
            'status' => (int) $v['status'],
        ]);
        $game->save();

        return redirect()->route('admin.games.show', $game)->with('status', 'Game created.');
    }

    public function show(SportGame $game): View
    {
        $game->load([
            'markets' => static fn ($q) => $q->withCount('selections')->orderByDesc('id'),
        ]);

        $cmsGame = null;
        try {
            $cmsGame = $this->cmsGameClient->find((int) $game->raw_id);
        } catch (Throwable) {
            // Detail page still renders; CMS block shows unavailable.
        }

        return view('admin.games.show', [
            'game' => $game,
            'cms_game' => $cmsGame,
        ]);
    }

    public function edit(SportGame $game): View
    {
        $cmsGame = null;
        try {
            $cmsGame = $this->cmsGameClient->find((int) $game->raw_id);
        } catch (Throwable) {
            // Same as show: allow editing local row when CMS is down or raw_id has no CMS row yet.
        }

        return view('admin.games.edit', [
            'game' => $game,
            'cms_game' => $cmsGame,
        ]);
    }

    public function update(Request $request, SportGame $game): RedirectResponse
    {
        $cmsReachable = $this->cmsGameExistsForRemoteEdit((int) $game->raw_id);

        $rules = [
            'banner_path' => 'nullable|string|max:2000',
            'main_image_path' => 'nullable|string|max:2000',
            'status' => ['required', 'integer', Rule::enum(SportGameStatus::class)],
        ];
        if ($cmsReachable) {
            $rules['name'] = 'required|string|max:500';
            $rules['starts_at'] = 'nullable|integer|min:0';
        } else {
            $rules['name'] = 'nullable|string|max:500';
            $rules['starts_at'] = 'nullable|integer|min:0';
        }

        $v = $request->validate($rules);

        $localFields = [
            'status' => (int) $v['status'],
        ];

        if ($cmsReachable) {
            $cmsPayload = $this->cmsPayloadFromValidatedGameForm($v);

            try {
                $this->cmsGameClient->update((int) $game->raw_id, $cmsPayload);
            } catch (DownstreamServiceException $e) {
                return back()->withInput()->withErrors(['cms' => $e->getMessage()]);
            }

        }

        $game->fill($localFields);
        $game->save();

        $status = $cmsReachable ? 'Game updated.' : 'Local row saved (CMS not updated: no record or gateway unavailable for raw_id '.$game->raw_id.').';

        return redirect()->route('admin.games.show', $game)->with('status', $status);
    }

    public function destroy(SportGame $game): RedirectResponse
    {
        if ($game->markets()->exists()) {
            return redirect()
                ->route('admin.games.show', $game)
                ->withErrors(['delete' => 'Delete or reassign all markets for this game first.']);
        }

        try {
            $this->cmsGameClient->delete((int) $game->raw_id);
        } catch (DownstreamServiceException $e) {
            return redirect()
                ->route('admin.games.show', $game)
                ->withErrors(['delete' => $e->getMessage()]);
        }

        $game->delete();

        return redirect()->route('admin.games.index')->with('status', 'Game deleted.');
    }

    /**
     * @param  array<string, mixed>  $v  Validated request including form keys {@code banner_path} / {@code main_image_path}.
     * @return array<string, mixed> Payload for {@see CmsGameClient} ({@code title} / {@code banner} / {@code main_media}).
     */
    private function cmsPayloadFromValidatedGameForm(array $v): array
    {
        return [
            'title' => (string) $v['name'],
            'starts_at' => (int) ($v['starts_at'] ?? 0),
            'banner' => (string) ($v['banner_path'] ?? ''),
            'main_media' => (string) ($v['main_image_path'] ?? ''),
        ];
    }

    private function cmsGameExistsForRemoteEdit(int $rawId): bool
    {
        try {
            $this->cmsGameClient->find($rawId);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
