@extends('layouts.app')

@section('title', __('console.pages.orders'))

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ __('console.list.orders') }}</h2>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('console.table.id') }}</th>
                        <th>{{ __('console.table.uid') }}</th>
                        <th>{{ __('console.table.status') }}</th>
                        <th>{{ __('console.table.created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="font-monospace">{{ $order->id }}</a>
                            </td>
                            <td>{{ $order->uid }}</td>
                            <td><span data-mall-dict-code="bet_order_status" data-mall-dict-value="{{ $order->status->value }}">{{ $order->status->value }}</span></td>
                            <td class="text-muted small">{{ \App\Support\MillisTimestampDisplay::format($order->ct) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{ $orders->withQueryString()->links() }}
@endsection
