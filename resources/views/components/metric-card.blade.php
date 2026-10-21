@props(['label' => null, 'value', 'icon' => 'dashboard', 'hint' => null])
<article {{ $attributes->class(['stat', 'metric-card']) }}>
    <div class="metric-label"><x-icon :name="$icon"/><span>{{ $label ?? $slot }}</span></div>
    <strong>{{ $value }}</strong>
    @if($hint)<p class="muted">{{ $hint }}</p>@endif
</article>
