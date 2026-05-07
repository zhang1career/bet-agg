@extends('layouts.app')

@section('title', 'Points · Balance')

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.points.index', ['tab' => 'balances']),
        'backLabel' => '返回积分账户列表',
    ])

    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Balance #{{ $balance->id }}</h2>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $balance->id }}</dd>
                <dt class="col-sm-3">UID</dt>
                <dd class="col-sm-9">{{ $balance->uid }}</dd>
                <dt class="col-sm-3">Balance (points)</dt>
                <dd class="col-sm-9 font-monospace">{{ number_format((int) $balance->balance) }}</dd>
                <dt class="col-sm-3">ct / ut</dt>
                <dd class="col-sm-9 text-muted small">{{ \App\Support\MillisTimestampDisplay::format($balance->ct) }}
                    / {{ \App\Support\MillisTimestampDisplay::format($balance->ut) }}</dd>
            </dl>
        </div>
    </div>
@endsection
