@extends('layouts.app')

@section('title', 'Game groups')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">赛事分组</h2>
        <a href="{{ route('admin.game-groups.create') }}" class="btn btn-primary btn-sm">新建</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th class="text-end">Games</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($groups as $group)
                        <tr>
                            <td>
                                <a href="{{ route('admin.game-groups.show', $group) }}" class="font-monospace">{{ $group->id }}</a>
                            </td>
                            <td class="font-monospace"><code>{{ $group->code }}</code></td>
                            <td class="text-end font-monospace">{{ $group->games_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.game-groups.edit', $group) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none"
                                   title="Edit" aria-label="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="Delete" aria-label="Delete"
                                        data-mall-delete-url="{{ route('admin.game-groups.destroy', $group) }}"
                                        data-mall-delete-message="删除分组 {{ $group->code }}？关联的赛事将仅解除映射，不会被删除。">
                                    @include('admin.partials.icon_trash')
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{ $groups->links() }}
@endsection
