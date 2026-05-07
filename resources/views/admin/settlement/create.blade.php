@extends('layouts.app')

@section('title', __('console.pages.settlement'))

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.games.index') }}" class="d-inline-flex align-items-center text-primary text-decoration-none">
            <svg class="flex-shrink-0 me-1" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            {{ __('console.settlement.back_games') }}
        </a>
    </div>

    <div class="bg-white shadow-sm p-4 rounded mb-4" style="max-width: 640px;">
        <h2 class="h5 mb-3">{{ __('console.settlement.heading') }}</h2>
        <p class="text-muted small">{{ __('console.settlement.hint') }}</p>

        <form method="post" action="{{ route('admin.settlement.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="settlement_game_id">{{ __('console.settlement.open_game') }}</label>
                <select name="game_id" id="settlement_game_id" class="form-select" required>
                    @if($games->isEmpty())
                        <option value="" disabled>{{ __('console.settlement.no_open_games') }}</option>
                    @else
                        @include('admin.partials.game_select_options', [
                            'games' => $games,
                            'gameSelectLabels' => $gameSelectLabels,
                            'selectedGameId' => (int) old('game_id', $games->first()->id),
                        ])
                    @endif
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="result_payload">{{ __('console.settlement.result') }}</label>
                <select name="result_payload" id="result_payload" class="form-select" required></select>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.games.index') }}" class="btn btn-outline-secondary">{{ __('console.btn.cancel') }}</a>
                <button type="submit" class="btn btn-primary" @if($games->isEmpty()) disabled @endif>{{ __('console.btn.submit_settlement') }}</button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var byGame = @json($outcomesByGame);
                var gameSel = document.getElementById('settlement_game_id');
                var payloadSel = document.getElementById('result_payload');
                if (!gameSel || !payloadSel) return;

                function refill() {
                    var gid = gameSel.value;
                    payloadSel.innerHTML = '';
                    var rows = byGame[gid] || [];
                    var oldVal = @json(old('result_payload', ''));
                    rows.forEach(function (o) {
                        var opt = document.createElement('option');
                        opt.value = o.value;
                        opt.textContent = o.label;
                        if (oldVal && oldVal === o.value) opt.selected = true;
                        payloadSel.appendChild(opt);
                    });
                }

                gameSel.addEventListener('change', refill);
                refill();
            });
        </script>
    @endpush
@endsection
