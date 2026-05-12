@php
    use App\Support\MillisTimestampDisplay;
@endphp

<h3 class="h6 text-muted mt-4">{{ __('console.settlement_overview.batch_jobs') }}</h3>
<p class="small text-muted mb-2">{{ __('console.settlement_overview.batch_jobs_hint') }}</p>

@if($jobs->isEmpty())
    <p class="text-muted small mb-0">{{ __('console.settlement_overview.no_jobs') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>{{ __('console.table.id') }}</th>
                <th>{{ __('console.settlement_overview.biz_key') }}</th>
                <th>{{ __('console.table.status') }}</th>
                <th class="text-end">{{ __('console.settlement_overview.job_total') }}</th>
                <th class="text-end">{{ __('console.settlement_overview.job_ok') }}</th>
                <th class="text-end">{{ __('console.settlement_overview.job_fail') }}</th>
                <th class="text-end">{{ __('console.settlement_overview.job_cursor') }}</th>
                <th>{{ __('console.games.timestamps') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($jobs as $job)
                <tr>
                    <td class="font-monospace">{{ $job->id }}</td>
                    <td class="small font-monospace text-break">{{ $job->biz_key }}</td>
                    <td>
                        <span data-mall-dict-code="settle_job_status" data-mall-dict-value="{{ $job->status->value }}">{{ $job->status->label() }}</span>
                        <span class="text-muted">({{ $job->status->value }})</span>
                    </td>
                    <td class="text-end font-monospace">{{ $job->total }}</td>
                    <td class="text-end font-monospace">{{ $job->success_count }}</td>
                    <td class="text-end font-monospace">{{ $job->failure_count }}</td>
                    <td class="text-end font-monospace">{{ $job->cursor_offset }}</td>
                    <td class="text-muted small">{{ MillisTimestampDisplay::format($job->ut) }}</td>
                </tr>
                @if(filled($job->last_error))
                    <tr class="table-warning">
                        <td colspan="8" class="small font-monospace text-break">{{ $job->last_error }}</td>
                    </tr>
                @endif
                @php $pl = $job->payload; @endphp
                @if(is_array($pl) && ($pl !== []))
                    <tr>
                        <td colspan="8" class="small font-monospace bg-light">
                            {{ json_encode($pl, JSON_UNESCAPED_UNICODE) }}
                        </td>
                    </tr>
                @endif
            @endforeach
            </tbody>
        </table>
    </div>
@endif
