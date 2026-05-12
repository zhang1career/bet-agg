@extends('layouts.app')

@section('title', __('console.pages.points_flow'))

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.points.index', ['tab' => 'flows']),
        'backLabel' => __('console.detail.back_points_flows'),
    ])

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ __('console.pages.flow_detail', ['id' => $flow->id]) }}</h2>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('console.table.id') }}</dt>
                <dd class="col-sm-9 font-monospace">{{ $flow->id }}</dd>
                <dt class="col-sm-3">{{ __('console.table.uid') }}</dt>
                <dd class="col-sm-9">{{ $flow->uid }}</dd>
                <dt class="col-sm-3">OID</dt>
                <dd class="col-sm-9">{{ $flow->oid }}</dd>
                <dt class="col-sm-3">Amount</dt>
                <dd class="col-sm-9 font-monospace">{{ number_format((int) $flow->amount) }}</dd>
                <dt class="col-sm-3">State</dt>
                <dd class="col-sm-9">{{ $flow->state->value }}</dd>
                <dt class="col-sm-3">{{ __('console.detail.ct_ut') }}</dt>
                <dd class="col-sm-9 text-muted small">{{ \App\Support\MillisTimestampDisplay::format($flow->ct) }}
                    / {{ \App\Support\MillisTimestampDisplay::format($flow->ut) }}</dd>
            </dl>
        </div>
    </div>
@endsection
