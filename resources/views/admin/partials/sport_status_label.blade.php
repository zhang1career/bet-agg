@php
    /** @var string $kind */
    /** @var int $value */
    $kind = $kind ?? 'event';
    $value = (int) ($value ?? 0);
    $label = match ($kind) {
        'event' => match ($value) {
            1 => 'Open',
            2 => 'Closed',
            3 => 'Settled',
            default => (string) $value,
        },
        'market', 'selection' => match ($value) {
            1 => 'Open',
            2 => 'Suspended',
            3 => 'Settled',
            default => (string) $value,
        },
        default => (string) $value,
    };
@endphp
{{ $label }}
