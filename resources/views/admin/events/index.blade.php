@extends('layouts.app')

@section('title', 'Events')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Business events</h2>
        <a href="{{ route('admin.events.create') }}" class="btn btn-primary btn-sm">New event</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th class="text-nowrap">Starts (UTC)</th>
                        <th>Status</th>
                        <th class="text-end">Markets</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($events as $event)
                        <tr>
                            <td>
                                <a href="{{ route('admin.events.show', $event) }}" class="font-monospace">{{ $event->id }}</a>
                            </td>
                            <td>{{ $event->name }}</td>
                            <td class="text-muted small">{{ \App\Support\MillisTimestampDisplay::format($event->starts_at) }}</td>
                            <td>
                                @include('admin.partials.sport_status_label', ['kind' => 'event', 'value' => $event->status])
                                <span class="text-muted">({{ $event->status }})</span>
                            </td>
                            <td class="text-end font-monospace">{{ $event->markets_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.events.edit', $event) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none"
                                   title="Edit" aria-label="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="Delete" aria-label="Delete"
                                        data-mall-delete-url="{{ route('admin.events.destroy', $event) }}"
                                        data-mall-delete-message="Delete event #{{ $event->id }}?">
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

    {{ $events->links() }}
@endsection
