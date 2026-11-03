@php
    $candyFallback ??= "this.src='/images/candy_pokemon/0.webp'; this.onerror=null;";
@endphp
<div class="text-center" @if(!empty($caramelo['nombre'])) title="{{ $caramelo['nombre'] }}" @endif>
    <div class="relative inline-block">
        <img
            src="{{ $caramelo['src'] }}"
            loading="lazy"
            decoding="async"
            alt="{{ $caramelo['alt'] }}"
            title="{{ $caramelo['alt'] }}"
            class="w-12 h-12 object-contain"
            onerror="{{ $candyFallback }}"
        >
        <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-warning-soft text-warning-ink border border-warning text-2xs font-bold rounded-full">
            ×{{ $caramelo['cantidad'] }}
        </span>
    </div>
    @if(!empty($caramelo['nombre_js']))
        <p class="text-2xs text-ink-muted truncate w-12" x-text="{{ $caramelo['nombre_js'] }}"></p>
    @elseif(!empty($caramelo['nombre']))
        <p class="text-2xs text-ink-muted truncate w-12">{{ $caramelo['nombre'] }}</p>
    @else
        <p class="text-2xs text-ink-subtle truncate w-12">—</p>
    @endif
</div>
