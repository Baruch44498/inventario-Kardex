@props([
    'value' => 0,
    'decimals' => 3,
])

@php
    $numericValue = is_numeric($value) ? (float) $value : 0.0;
    $precision = max(0, (int) $decimals);
    $formattedValue = number_format($numericValue, $precision, '.', ',');

    if (str_contains($formattedValue, '.')) {
        $formattedValue = rtrim(rtrim($formattedValue, '0'), '.');
    }

    if ($formattedValue === '-0') {
        $formattedValue = '0';
    }
@endphp

{{ $formattedValue }}
