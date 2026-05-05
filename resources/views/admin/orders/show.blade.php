@extends('layouts.app')

@section('title', 'Order '.$order->id)

@section('content')
    <div class="bg-white shadow-sm p-4 rounded mb-4">
        <p><strong>Uid:</strong> {{ $order->uid }}</p>
        <p><strong>Status:</strong> <span data-mall-dict-code="bet_order_status" data-mall-dict-value="{{ $order->status->value }}">{{ $order->status->value }}</span></p>
        <p><strong>Total stake (points):</strong> {{ $order->total_price }}</p>
        <p><strong>ct / ut:</strong> {{ \App\Support\MillisTimestampDisplay::format($order->ct) }}
            / {{ \App\Support\MillisTimestampDisplay::format($order->ut) }}</p>
    </div>

    <h2 class="h5">Lines</h2>
    <table class="table table-sm bg-white shadow-sm">
        <thead>
        <tr>
            <th>Selection</th>
            <th>Stake</th>
            <th>Odds millis</th>
            <th>Potential return</th>
            <th>Line result</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->kid }} — {{ $item->odds_snapshot['label'] ?? '' }}</td>
                <td>{{ $item->stake_points }}</td>
                <td>{{ $item->decimal_odds_millis }}</td>
                <td>{{ $item->potential_return_points }}</td>
                <td>{{ $item->line_result?->value ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="text-muted mt-3">Order status is driven by placement and settlement. Use admin/games to apply game results.</p>

    <a href="{{ route('admin.orders.index') }}" class="btn btn-link mt-3">Back to orders</a>
@endsection
