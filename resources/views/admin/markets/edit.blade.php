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
        <div class="mb-3">
            <label class="form-label" for="game_id">Game</label>
            <select name="game_id" id="game_id" class="form-select" required>
                @foreach($games as $g)
                    <option value="{{ $g->id }}" @selected((int) old('game_id', $market->game_id) === $g->id)>
                        Local #{{ $g->id }} · raw {{ $g->raw_id }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" maxlength="256"
                   value="{{ old('name', $market->name) }}" placeholder="Display label for this market">
        </div>
        <div class="mb-3">
            <label class="form-label" for="status">Market status</label>
            <select name="status" id="status" class="form-select" required
                    data-mall-dict-options="sport_market_status"
                    data-mall-dict-selected="{{ (int) old('status', $market->status) }}"></select>
        </div>
        <button type="submit" class="btn btn-primary">Save market</button>
    </form>

    @include('admin.markets.partials.selections-panel', ['market' => $market])
@endsection
