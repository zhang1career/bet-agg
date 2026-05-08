<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameGroup;
use App\Models\GameSubject;
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
        $games = Game::query()
            ->withCount('markets')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $cmsByRawId = [];
        try {
            $rawIds = $games
                ->getCollection()
                ->map(static fn (Game $g): int => (int) $g->raw_id)
                ->unique()
                ->filter(static fn (int $r): bool => $r >= 1)
                ->values()
                ->all();
            if ($rawIds !== []) {
                $cmsByRawId = $this->cmsGameClient->findManyById($rawIds);
            }
        } catch (Throwable) {
        }

        $mallCreate = $request->boolean('mall_create');
        $mallEditId = (int) $request->query('mall_edit', 0);

        $modalEditGame = null;
        $modalEditCms = null;
        $modalEditSelectedGroups = [];
        $allGroups = collect();
        $allSubjects = collect();

        if ($mallCreate || ($mallEditId >= 1)) {
            $allGroups = GameGroup::query()->orderBy('code')->get();
            $allSubjects = GameSubject::query()->with('groups')->orderBy('name')->get();
        }

        if ($mallEditId >= 1) {
            $modalEditGame = Game::query()->with('groups')->find($mallEditId);
            if ($modalEditGame instanceof Game) {
                $modalEditCms = $this->fetchCmsGameOrNull((int) $modalEditGame->raw_id);
                $modalEditSelectedGroups = $modalEditGame->groups->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            } else {
                $modalEditGame = null;
            }
        }

        return view('admin.games.index', [
            'games' => $games,
            'cmsByRawId' => $cmsByRawId,
            'mallCreate' => $mallCreate,
            'mallEditId' => $mallEditId,
            'modalEditGame' => $modalEditGame,
            'modalEditCms' => $modalEditCms,
            'modalEditSelectedGroups' => $modalEditSelectedGroups,
            'allGroups' => $allGroups,
            'allSubjects' => $allSubjects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate($this->gameFormRules(forCreate: true));
        $groupIds = $this->normalizedGroupIds($v);
        $sideA = $this->normalizedOptionalSubjectId($v['side_a_subject_id'] ?? null);
        $sideB = $this->normalizedOptionalSubjectId($v['side_b_subject_id'] ?? null);
        $sideErrors = $this->sideSubjectValidationErrors($groupIds, $sideA, $sideB);
        if ($sideErrors !== []) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors($sideErrors);
        }

        $cmsFields = $this->cmsWriteFieldsFromValidated($v);

        try {
            $created = $this->cmsGameClient->create($cmsFields);
        } catch (DownstreamServiceException $e) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors(['cms' => $e->getMessage()]);
        } catch (Throwable $e) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors(['cms' => $e->getMessage()]);
        }

        $rawId = $this->cmsResponseId($created);
        if ($rawId < 1) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors(['cms' => 'CMS did not return a game id.']);
        }

        $game = new Game([
            'raw_id' => $rawId,
            'side_a_subject_id' => $sideA,
            'side_b_subject_id' => $sideB,
            'status' => (int) $v['status'],
        ]);
        $game->save();
        $game->groups()->sync($groupIds);

        return redirect()->route('admin.games.index')->with('status', 'Game created.');
    }

    public function show(Game $game): View
    {
        $game->load([
            'groups',
            'sideASubject',
            'sideBSubject',
            'markets' => static fn ($q) => $q->orderByDesc('id'),
        ]);

        $cmsGame = $this->fetchCmsGameOrNull((int) $game->raw_id);

        return view('admin.games.show', [
            'game' => $game,
            'cms_game' => $cmsGame,
        ]);
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $v = $request->validate($this->gameFormRules(forCreate: false));
        $groupIds = $this->normalizedGroupIds($v);
        $sideA = $this->normalizedOptionalSubjectId($v['side_a_subject_id'] ?? null);
        $sideB = $this->normalizedOptionalSubjectId($v['side_b_subject_id'] ?? null);
        $sideErrors = $this->sideSubjectValidationErrors($groupIds, $sideA, $sideB);
        if ($sideErrors !== []) {
            return redirect()->route('admin.games.index', ['mall_edit' => $game->id])
                ->withInput()
                ->withErrors($sideErrors);
        }

        $cmsGame = $this->fetchCmsGameOrNull((int) $game->raw_id);
        if (is_array($cmsGame)) {
            if (trim((string) ($v['name'] ?? '')) === '') {
                return redirect()->route('admin.games.index', ['mall_edit' => $game->id])
                    ->withInput()
                    ->withErrors(['name' => 'Title is required when the CMS record can be loaded.']);
            }
            $cmsFields = $this->cmsWriteFieldsFromValidated($v);
            try {
                $this->cmsGameClient->update((int) $game->raw_id, $cmsFields);
            } catch (DownstreamServiceException $e) {
                return redirect()->route('admin.games.index', ['mall_edit' => $game->id])
                    ->withInput()
                    ->withErrors(['cms' => $e->getMessage()]);
            } catch (Throwable $e) {
                return redirect()->route('admin.games.index', ['mall_edit' => $game->id])
                    ->withInput()
                    ->withErrors(['cms' => $e->getMessage()]);
            }
        }

        $game->side_a_subject_id = $sideA;
        $game->side_b_subject_id = $sideB;
        $game->status = (int) $v['status'];
        $game->save();
        $game->groups()->sync($groupIds);

        return redirect()->route('admin.games.index')->with('status', 'Game updated.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        if ($game->markets()->exists()) {
            return redirect()
                ->route('admin.games.show', $game)
                ->withErrors(['delete' => 'There are markets for this game; remove them first.']);
        }

        try {
            $this->cmsGameClient->delete((int) $game->raw_id);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.games.show', $game)
                ->withErrors(['delete' => 'CMS delete failed: '.$e->getMessage()]);
        }

        $game->groups()->detach();
        $game->delete();

        return redirect()->route('admin.games.index')->with('status', 'Game deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function gameFormRules(bool $forCreate): array
    {
        $rules = [
            'banner_path' => ['nullable', 'string', 'max:1024'],
            'main_image_path' => ['nullable', 'string', 'max:1024'],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', Rule::exists('biz_game_group', 'id')],
            'side_a_subject_id' => ['nullable', 'integer', Rule::exists('biz_game_subject', 'id')],
            'side_b_subject_id' => ['nullable', 'integer', Rule::exists('biz_game_subject', 'id')],
            'status' => ['required', 'integer', Rule::in([
                Game::STATUS_OPEN,
                Game::STATUS_CLOSED,
                Game::STATUS_SETTLED,
                Game::STATUS_PENDING_SETTLEMENT,
            ])],
        ];
        if ($forCreate) {
            $rules['name'] = ['required', 'string', 'max:500'];
            $rules['starts_at'] = ['required', 'integer', 'min:0'];
        } else {
            $rules['name'] = ['nullable', 'string', 'max:500'];
            $rules['starts_at'] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return list<int>
     */
    private function normalizedGroupIds(array $v): array
    {
        /** @var list<int|string> $raw */
        $raw = $v['group_ids'] ?? [];

        return array_values(array_unique(array_map(static fn ($x): int => (int) $x, $raw)));
    }

    private function normalizedOptionalSubjectId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $n = (int) $value;

        return $n >= 1 ? $n : null;
    }

    /**
     * @param  list<int>  $groupIds
     * @return array<string, list<string>>
     */
    private function sideSubjectValidationErrors(array $groupIds, ?int $sideA, ?int $sideB): array
    {
        $errors = [];
        foreach (['A' => $sideA, 'B' => $sideB] as $label => $sid) {
            if ($sid === null) {
                continue;
            }
            $ok = GameSubject::query()
                ->whereKey($sid)
                ->whereHas('groups', static fn ($q) => $q->whereIn('biz_game_group.id', $groupIds))
                ->exists();
            if (! $ok) {
                $errors['side_'.strtolower($label).'_subject_id'] = [
                    'The selected side '.$label.' subject is not in any of the chosen groups.',
                ];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $v  Validated request subset
     * @return array<string, mixed>
     */
    private function cmsWriteFieldsFromValidated(array $v): array
    {
        $title = trim((string) ($v['name'] ?? ''));
        $fields = [
            'title' => $title,
        ];
        if (array_key_exists('starts_at', $v) && $v['starts_at'] !== null) {
            $fields['starts_at'] = (int) $v['starts_at'];
        }
        $banner = trim((string) ($v['banner_path'] ?? ''));
        $main = trim((string) ($v['main_image_path'] ?? ''));
        if ($banner !== '') {
            $fields['banner'] = $banner;
        }
        if ($main !== '') {
            $fields['main_media'] = $main;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $cmsWriteResponse
     */
    private function cmsResponseId(array $cmsWriteResponse): int
    {
        if (isset($cmsWriteResponse['id']) && is_numeric($cmsWriteResponse['id'])) {
            return (int) $cmsWriteResponse['id'];
        }

        return 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCmsGameOrNull(int $rawId): ?array
    {
        if ($rawId < 1) {
            return null;
        }
        try {
            $row = $this->cmsGameClient->find($rawId);
        } catch (Throwable) {
            return null;
        }

        return $row;
    }
}
