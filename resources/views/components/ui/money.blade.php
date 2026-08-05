@props([
    'value' => 0,
    'currency' => 'PEN',
    'showCode' => false,
])

@php
    $numericValue = is_numeric($value) ? (float) $value : 0.0;
    $normalizedCurrency = strtoupper((string) $currency);
    $prefix = $showCode
        ? $normalizedCurrency
        : ($normalizedCurrency === 'USD' ? 'US$' : 'S/');
@endphp

<span {{ $attributes->class(['ui-money']) }}>
    <span class="ui-money__currency">{{ $prefix }}</span>
    <span class="ui-money__amount">{{ number_format($numericValue, 2, '.', ',') }}</span>
</span>
