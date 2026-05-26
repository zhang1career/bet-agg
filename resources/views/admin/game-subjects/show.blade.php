@extends('layouts.app')

@section('title', __('console.pages.subject_detail', ['id' => $subject->id]))

@section('content')
    @include('admin.includes.detail_back_link', [
        'backUrl' => route('admin.game-subjects.index'),
        'backLabel' => __('console.detail.back_subjects'),
    ])
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ $subject->name }} <span class="text-muted">#{{ $subject->id }}</span></h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.game-subjects.index', ['mall_edit' => $subject->id]) }}" class="btn btn-outline-primary btn-sm">{{ __('console.btn.edit') }}</a>
            <button type="button" class="btn btn-outline-danger btn-sm"
                    data-mall-delete-url="{{ route('admin.game-subjects.destroy', $subject) }}"
                    data-mall-delete-message="{{ __('console.game_subjects.delete_confirm', ['name' => $subject->name]) }}">
                {{ __('console.btn.delete') }}
            </button>
        </div>
    </div>
    @if($errors->has('delete'))
        <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
    @endif
    @php $cdnBase = rtrim((string) config('services.cloudfront.domain'), '/'); @endphp
    <div class="mall-console-card card shadow-sm mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('console.game_subjects.label_icon') }}</dt>
                <dd class="col-sm-9">
                    @php $subjectIcon = trim((string) $subject->icon); @endphp
                    @if($subjectIcon !== '')
                        <code class="small">{{ $subjectIcon }}</code>
                        @if($cdnBase !== '')
                            <div class="mt-2">
                                <img src="{{ $cdnBase.'/'.ltrim($subjectIcon, '/') }}" alt="{{ $subject->name }}"
                                     class="img-thumbnail" style="max-height: 4rem;">
                            </div>
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </dd>
                <dt class="col-sm-3">{{ __('console.game_subjects.label_info') }}</dt>
                <dd class="col-sm-9">
                    @php $subjectInfo = trim((string) $subject->info); @endphp
                    @if($subjectInfo !== '')
                        <div class="mall-rich-text-preview border rounded p-3 bg-body-secondary">{!! $subjectInfo !!}</div>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
    <div class="mall-console-card card shadow-sm">
        <div class="card-body">
            <h3 class="h6">{{ __('console.game_subjects.related_groups') }} <small class="text-muted">y</small></h3>
            <ul class="mb-0">
                @forelse($subject->groups as $g)
                    <li><a href="{{ route('admin.game-groups.show', $g) }}"><code>{{ $g->code }}</code></a></li>
                @empty
                    <li class="text-muted">{{ __('console.game_subjects.no_groups') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
