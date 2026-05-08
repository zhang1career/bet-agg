@php
    use App\Enums\BetLineResult;
    use App\Enums\BetOrderStatus;
@endphp

<div class="row g-4 mb-0">
    <div class="col-md-6">
        <h3 class="h6 text-muted">{{ __('console.settlement_overview.orders_by_status') }}</h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 bg-white">
                <thead class="table-light">
                <tr>
                    <th>{{ __('console.table.status') }}</th>
                    <th class="text-end">{{ __('console.settlement_overview.count_orders') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach(BetOrderStatus::cases() as $st)
                    @php $n = (int) ($orderCounts[$st->value] ?? 0); @endphp
                    <tr @class(['table-light' => $n === 0])>
                        <td>
                            <span data-mall-dict-code="bet_order_status" data-mall-dict-value="{{ $st->value }}">{{ $st->label() }}</span>
                        </td>
                        <td class="text-end font-monospace">{{ $n }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <h3 class="h6 text-muted">{{ __('console.settlement_overview.lines_by_result') }}</h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 bg-white">
                <thead class="table-light">
                <tr>
                    <th>{{ __('console.table.line_result') }}</th>
                    <th class="text-end">{{ __('console.settlement_overview.count_lines') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach(BetLineResult::cases() as $lr)
                    @php $n = (int) ($lineCounts[$lr->value] ?? 0); @endphp
                    <tr @class(['table-light' => $n === 0])>
                        <td>
                            <span data-mall-dict-code="bet_line_result" data-mall-dict-value="{{ $lr->value }}">{{ $lr->label() }}</span>
                        </td>
                        <td class="text-end font-monospace">{{ $n }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
