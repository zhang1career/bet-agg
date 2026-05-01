@extends('layouts.app')

@section('title', 'Edit market #'.$market->id)

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Edit market #{{ $market->id }}</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.markets.show', $market) }}" class="btn btn-outline-secondary btn-sm">View detail</a>
        </div>
    </div>

    <form method="post" action="{{ route('admin.markets.update', $market) }}" class="bg-white shadow-sm p-4 rounded mb-4">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="game_id">Game</label>
                <select name="game_id" id="game_id" class="form-select" required>
                    @foreach($games as $g)
                        <option value="{{ $g->id }}" @selected((int) old('game_id', $market->game_id) === $g->id)>
                            Local #{{ $g->id }} · raw {{ $g->raw_id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="market_type">Market type</label>
                <select name="market_type" id="market_type" class="form-select" required>
                    @foreach(\App\Enums\SportMarketType::cases() as $case)
                        @if($case === \App\Enums\SportMarketType::Unknown)
                            @continue
                        @endif
                        <option value="{{ $case->value }}" @selected((int) old('market_type', $market->market_type->value) === $case->value)>
                            {{ $case->label() }} ({{ $case->value }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="status">Market status</label>
            <select name="status" id="status" class="form-select" required>
                <option value="1" @selected((int) old('status', $market->status) === 1)>Open</option>
                <option value="2" @selected((int) old('status', $market->status) === 2)>Suspended</option>
                <option value="3" @selected((int) old('status', $market->status) === 3)>Settled</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Save market</button>
    </form>

    @include('admin.markets.partials.selections-panel', ['market' => $market])
@endsection
