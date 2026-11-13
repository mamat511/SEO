@props(['label', 'value', 'previous' => null, 'format' => 'int', 'lowerIsBetter' => false])
@php
    $fmt = fn ($v) => match ($format) {
        'pct' => number_format($v * 100, 1, ',', ' ').' %',
        'dec' => number_format($v, 1, ',', ' '),
        default => number_format($v, 0, ',', ' '),
    };
    $delta = ($previous !== null && $value !== null && $previous != 0) ? ($value - $previous) / abs($previous) : null;
    $good = $delta !== null && ($lowerIsBetter ? $delta < 0 : $delta > 0);
@endphp
<div class="card">
    <p class="kpi-label">{{ $label }}</p>
    <p class="kpi-value">{{ $value === null ? '—' : $fmt($value) }}</p>
    @if ($delta !== null && abs($delta) >= 0.001)
        <p class="mt-1 text-xs {{ $good ? 'text-emerald-600' : 'text-red-600' }}">
            {{ $delta > 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 1, ',', ' ') }} %
            <span class="text-slate-400">vs 28 j. précédents</span>
        </p>
    @endif
</div>
