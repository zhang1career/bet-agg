@php
    /** @var string $kind */
    /** @var int $value */
    $kind = $kind ?? 'event';
    $value = (int) ($value ?? 0);
    $code = in_array($kind, ['market', 'selection'], true) ? 'sport_market_status' : 'sport_game_status';
@endphp
<span data-mall-dict-code="{{ $code }}" data-mall-dict-value="{{ $value }}">{{ $value }}</span>
