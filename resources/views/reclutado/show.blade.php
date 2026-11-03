@extends('layouts.app')

@section('title', $reclutado['nombre_formateado'])

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <a href="{{ url('/equipos') }}" class="text-sm text-primary-ink hover:underline">&larr; Volver a equipos</a>

    <div class="mt-4 bg-surface rounded-card shadow-sm border border-line p-6 flex items-center gap-6">
        <x-sprite :id="$reclutado['pokemon_id']" :shiny="$reclutado['es_shiny'] ?? false" :name="$reclutado['pokemon_nombre']" size="128" wrapper-class="mx-auto" />
        <div>
            <h1 class="text-2xl font-bold text-ink capitalize">
                {{ $reclutado['nombre_formateado'] }}
            </h1>
            <p class="text-sm text-ink-muted">
                Nivel {{ $reclutado['nivel'] }} &middot; {{ $reclutado['exp_total_formateado'] }} exp
                @if(!empty($reclutado['cp']))
                    &middot; <span class="font-bold text-info-ink">CP {{ number_format($reclutado['cp']) }}</span>
                @endif
            </p>
        </div>
    </div>

    @if ($siguiente)
        <div class="mt-4 bg-surface rounded-card shadow-sm border border-line p-6">
            <h2 class="text-lg font-semibold text-ink">Próxima evolución</h2>
            <div class="mt-3 flex items-center gap-4">
                <x-sprite :id="$siguiente['pokemon_id']" :name="$siguiente['nombre']" size="64" />
                <p class="text-ink">{{ $siguiente['nombre_formateado'] }}</p>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($requisitos as $requisito)
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-3" data-tipo="{{ $requisito['tipo'] }}">
                        <div>
                            <p class="font-medium text-ink">{{ $requisito['tipo'] }}</p>
                            <p class="text-sm text-ink-muted">
                                {{ $requisito['actual_formateado'] }} / {{ $requisito['necesario_formateado'] }} exp
                            </p>
                            <p class="text-xs text-ink-subtle">
                                Caramelos de tipo: {{ $requisito['caramelosDisponibles'] ?? 0 }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @foreach ([1, 10, 100] as $cantidad)
                                <button
                                    type="button"
                                    class="px-2.5 py-2 bg-primary text-on-primary rounded-control text-xs font-bold hover:bg-primary-hover focus-ring transition-colors dar-caramelo-btn disabled:opacity-40 disabled:cursor-not-allowed"
                                    data-url="{{ url('/reclutado/'.$reclutado['id'].'/dar-caramelo') }}"
                                    data-tipo="{{ $requisito['tipo'] }}"
                                    data-cantidad="{{ $cantidad }}"
                                    {{ ($requisito['caramelosDisponibles'] ?? 0) <= 0 ? 'disabled' : '' }}
                                >
                                    x{{ $cantidad }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($puedeEvolucionar)
                <form method="POST" action="{{ url('/reclutado/'.$reclutado['id'].'/evolucionar') }}" class="mt-5">
                    @csrf
                    <button type="submit" class="w-full px-4 py-3 bg-success text-on-primary rounded-card text-sm font-bold hover:bg-success/90 focus-ring transition-colors">
                        ¡Evolucionar a {{ $siguiente['nombre_formateado'] }}!
                    </button>
                </form>
            @endif
        </div>
    @else
        <div class="mt-4 bg-surface rounded-card shadow-sm border border-line p-6">
            <p class="text-ink-muted">Este Pokémon no tiene evolución.</p>
        </div>
    @endif
</div>

<script>
    document.querySelectorAll('.dar-caramelo-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                const response = await fetch(button.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        tipo: button.dataset.tipo,
                        cantidad: Number(button.dataset.cantidad || 1),
                    }),
                });
                if (response.ok) {
                    window.location.reload();
                } else {
                    const data = await response.json();
                    alert(data.error ?? 'Error');
                    button.disabled = false;
                }
            } catch {
                button.disabled = false;
            }
        });
    });
</script>
@endsection
