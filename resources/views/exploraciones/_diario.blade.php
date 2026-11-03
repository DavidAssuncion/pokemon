@php
    $diario = $diario ?? [];
    $total = (int) ($diario['total'] ?? 0);
    $grupos = $diario['grupos'] ?? [];
    $hitos = $diario['hitos'] ?? [];
    $mostrarHitos = $mostrarHitos ?? true;
@endphp

<div class="space-y-3">
    @if($mostrarHitos && $hitos !== [])
        <ul class="flex flex-wrap items-center gap-2" aria-label="Hitos de la expedición">
            @foreach($hitos as $hito)
                <li>
                    <span
                        class="inline-block px-2 py-0.5 rounded-full bg-info-soft text-info-ink border border-info text-2xs font-bold uppercase tracking-wide"
                        @if(!empty($hito['descripcion'])) title="{{ $hito['descripcion'] }}" @endif
                    >
                        {{ $hito['etiqueta'] ?? '' }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    @if($total === 0)
        <p class="text-sm text-ink-subtle">Sin eventos en la bitácora</p>
    @else
        @foreach($grupos as $grupo)
            <section aria-label="Eventos del {{ $grupo['etiqueta'] ?? 'día' }}">
                <h4 class="text-2xs font-bold uppercase tracking-wide text-ink-subtle mb-1">
                    {{ $grupo['etiqueta'] ?? 'Sin fecha' }}
                </h4>
                <ul class="divide-y divide-line">
                    @foreach(($grupo['eventos'] ?? []) as $evento)
                        @include('exploraciones._evento', ['evento' => $evento])
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
