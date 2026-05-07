@extends('layouts.app')

@section('title', __('console.pages.order_detail', ['id' => $order->id]))

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.orders.index'),
        'backLabel' => __('console.detail.back_orders'),
    ])

    <div class="bg-white shadow-sm p-4 rounded mb-4">
        <p><strong>{{ __('console.table.uid') }}:</strong> {{ $order->uid }}</p>
        <p><strong>{{ __('console.table.status') }}:</strong> <span data-mall-dict-code="bet_order_status" data-mall-dict-value="{{ $order->status->value }}">{{ $order->status->value }}</span></p>
        <p><strong>{{ __('console.detail.total_stake') }}:</strong> {{ $order->total_price }}</p>
        <p><strong>{{ __('console.detail.ct_ut') }}:</strong> {{ \App\Support\MillisTimestampDisplay::format($order->ct) }}
            / {{ \App\Support\MillisTimestampDisplay::format($order->ut) }}</p>
    </div>

    <h2 class="h5">{{ __('console.table.lines') }}</h2>
    <table class="table table-sm bg-white shadow-sm">
        <thead>
        <tr>
            <th>{{ __('console.table.outcome') }}</th>
            <th>{{ __('console.table.stake') }}</th>
            <th>{{ __('console.table.odds_millis_line') }}</th>
            <th>{{ __('console.table.potential_return') }}</th>
            <th>{{ __('console.table.line_result') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td class="small">
                    m{{ $item->market_id }}
                    · <code class="small">{{ json_encode($item->selection ?? [], JSON_UNESCAPED_UNICODE) }}</code>
                    @if(!empty($item->odds_snapshot['label']))
                        <br><span class="text-muted">{{ $item->odds_snapshot['label'] }}</span>
                    @endif
                </td>
                <td>{{ $item->stake_points }}</td>
                <td>{{ $item->decimal_odds_millis }}</td>
                <td>{{ $item->potential_return_points }}</td>
                <td>{{ $item->result?->value ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="text-muted mt-3">{{ __('console.orders.foot_note') }}</p>

    <a href="{{ route('admin.orders.index') }}" class="btn btn-link mt-3">{{ __('console.orders.back_to_orders') }}</a>
@endsection
