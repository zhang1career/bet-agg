@extends('layouts.app')

@section('title', '赛事主体')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">赛事主体 <small class="text-muted">biz_game_subject</small></h2>
        <a href="{{ route('admin.game-subjects.create') }}" class="btn btn-primary btn-sm">新建</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th class="text-end">分组数 (biz_y)</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($subjects as $s)
                        <tr>
                            <td><a href="{{ route('admin.game-subjects.show', $s) }}" class="font-monospace">{{ $s->id }}</a></td>
                            <td>{{ $s->name }}</td>
                            <td class="text-end font-monospace">{{ $s->groups_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.game-subjects.edit', $s) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none" title="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $subjects->links() }}
@endsection
