@props(['config', 'height' => 'h-64'])
{{-- La clé change avec les données : Livewire remplace l'élément et le graphique se redessine. --}}
<div wire:key="chart-{{ md5(json_encode($config)) }}" x-data="chart(@js($config))" {{ $attributes->class([$height, 'relative']) }}>
    <canvas x-ref="canvas"></canvas>
</div>
