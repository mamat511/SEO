@props(['values' => [], 'reverse' => false, 'color' => '#6366f1'])
@php
    $values = array_values(array_filter($values, fn ($v) => $v !== null));
    $n = count($values);
    $points = '';
    if ($n > 1) {
        $min = min($values); $max = max($values); $range = ($max - $min) ?: 1;
        foreach ($values as $i => $v) {
            $y = ($v - $min) / $range;
            $points .= round($i / ($n - 1) * 100, 2).','.round(($reverse ? $y : 1 - $y) * 28 + 2, 2).' ';
        }
    }
@endphp
<svg viewBox="0 0 100 32" preserveAspectRatio="none" {{ $attributes->class('h-8 w-24') }}>
    @if ($points)
        <polyline points="{{ trim($points) }}" fill="none" stroke="{{ $color }}" stroke-width="1.5" vector-effect="non-scaling-stroke" />
    @endif
</svg>
