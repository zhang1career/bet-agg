@php
    /** @var string $kind */
    /** @var int $value */
    $kind = $kind ?? 'event';
    $value = (int) ($value ?? 0);
    $code = in_array($kind, ['market', 'selection'], true) ? 'market_status' : 'game_status';
@endphp
<span data-mall-dict-code="{{ $code }}" data-mall-dict-value="{{ $value }}">{{ $value }}</span>
