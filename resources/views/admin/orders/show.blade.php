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
        <p><strong>{{ __('console.detail.ct_ut') }}:</strong> {{ \App\Support\MillisTimestampDisplay::format($order->ct) }}
            / {{ \App\Support\MillisTimestampDisplay::format($order->ut) }}</p>
    </div>

    @if(!empty($settlementGameIds))
        <div class="mall-console-card card shadow-sm mb-4">
            <div class="card-body">
                <h3 class="h6 mb-3">{{ __('console.settlement_overview.order_context') }}</h3>
                <p class="small text-muted">{{ __('console.settlement_overview.order_context_hint') }}</p>
                <ul class="mb-4">
                    @foreach($settlementGameIds as $gid)
                        <li>
                            <a href="{{ route('admin.games.show', $gid) }}">{{ __('console.pages.game_detail', ['id' => $gid]) }}</a>
                            — {{ __('console.settlement_overview.recent_batches_for_game') }}
                            @php $jb = $settlementJobsByGameId[$gid] ?? collect(); @endphp
                            @if($jb->isEmpty())
                                <span class="text-muted small">{{ __('console.settlement_overview.no_jobs') }}</span>
                            @else
                                <span class="font-monospace small">{{ $jb->first()->biz_key }}</span>
                                (<span data-mall-dict-code="settle_job_status" data-mall-dict-value="{{ $jb->first()->status->value }}">{{ $jb->first()->status->value }}</span>)
                            @endif
                        </li>
                    @endforeach
                </ul>
                @foreach($settlementGameIds as $gid)
                    @php $jb = $settlementJobsByGameId[$gid] ?? collect(); @endphp
                    @if($jb->isNotEmpty())
                        <h4 class="h6 text-muted">{{ __('console.pages.game_detail', ['id' => $gid]) }}</h4>
                        @include('admin.partials.settlement_jobs', ['jobs' => $jb])
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <h2 class="h5">{{ __('console.table.lines') }}</h2>
    <table class="table table-sm bg-white shadow-sm">
        <thead>
        <tr>
            <th>{{ __('console.table.market') }}</th>
            <th>{{ __('console.table.outcome') }}</th>
            <th>{{ __('console.table.line_result') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td class="small">
                    @if($item->relationLoaded('market') && $item->market)
                        <a href="{{ route('admin.markets.show', $item->market) }}" class="font-monospace">{{ __('console.table.market') }} {{ $item->mid }}</a>
                        <span class="text-muted">·</span>
                        <a href="{{ route('admin.games.show', $item->market->gid) }}">{{ __('console.pages.game_detail', ['id' => $item->market->gid]) }}</a>
                    @else
                        <span class="font-monospace">{{ __('console.table.market') }} {{ $item->mid }}</span>
                    @endif
                </td>
                <td class="small">
                    <code class="small">{{ json_encode($item->selection ?? [], JSON_UNESCAPED_UNICODE) }}</code>
                    @if($item->pick_label !== '')
                        <br><span class="text-muted">{{ $item->pick_label }}</span>
                    @endif
                </td>
                <td>
                    <span data-mall-dict-code="order_item_result" data-mall-dict-value="{{ $item->result->value }}">{{ $item->result->value }}</span>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="text-muted mt-3">{{ __('console.orders.foot_note') }}</p>

    <a href="{{ route('admin.orders.index') }}" class="btn btn-link mt-3">{{ __('console.orders.back_to_orders') }}</a>
@endsection
