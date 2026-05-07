@extends('layouts.app')

@section('title', 'Settlement')

@section('content')
    <div class="bg-white shadow-sm p-4 rounded mb-4" style="max-width: 640px;">
        <h2 class="h5 mb-3">录入赛果（入队结算）</h2>
        <p class="text-muted small">提交后写入待处理队列，由队列 Worker 执行兑奖；本表单不直接调用结算服务。</p>

        <form method="post" action="{{ route('admin.settlement.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="game_id">Open game</label>
                <select name="game_id" id="settlement_game_id" class="form-select" required>
                    @if($games->isEmpty())
                        <option value="" disabled>No open games</option>
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
                <label class="form-label" for="result_payload">赛果</label>
                <select name="result_payload" id="result_payload" class="form-select" required></select>
            </div>
            <button type="submit" class="btn btn-primary" @if($games->isEmpty()) disabled @endif>入队结算</button>
            <a href="{{ route('admin.games.index') }}" class="btn btn-outline-secondary">取消</a>
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
