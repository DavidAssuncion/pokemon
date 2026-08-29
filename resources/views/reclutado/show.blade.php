@extends('layouts.app')

@section('title', $reclutado['nombre'] ?? $reclutado['pokemon_nombre'])

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <a href="{{ url('/equipos') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">&larr; Volver a equipos</a>

    <div class="mt-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 flex items-center gap-6">
        <img
            src="{{ $reclutado['imagen'] }}"
            alt="{{ $reclutado['pokemon_nombre'] }}"
            class="w-24 h-24 object-contain"
            onerror="this.src='/images/iconos/{{ $reclutado['pokemon_id'] }}.png'"
        >
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white capitalize">
                {{ $reclutado['nombre'] ?? $reclutado['pokemon_nombre'] }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nivel {{ $reclutado['nivel'] }} &middot; {{ number_format($reclutado['exp_total'] ?? 0) }} exp
            </p>
        </div>
    </div>

    @if ($siguiente)
        <div class="mt-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Próxima evolución</h2>
            <div class="mt-3 flex items-center gap-4">
                <img src="{{ $siguiente['imagen'] }}" alt="{{ $siguiente['nombre'] }}" class="w-16 h-16 object-contain">
                <p class="text-gray-700 dark:text-gray-300 capitalize">{{ $siguiente['nombre'] }}</p>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($requisitos as $requisito)
                    <div class="flex items-center justify-between gap-4 border-t border-gray-100 dark:border-gray-700 pt-3" data-tipo="{{ $requisito['tipo'] }}">
                        <div>
                            <p class="font-medium text-gray-800 dark:text-gray-200">{{ $requisito['tipo'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format($requisito['actual'] ?? 0) }} / {{ number_format($requisito['necesario'] ?? 0) }} exp
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Caramelos de tipo: {{ $requisito['caramelosDisponibles'] ?? 0 }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors dar-caramelo-btn"
                            data-url="{{ url('/reclutado/'.$reclutado['id'].'/dar-caramelo') }}"
                            data-tipo="{{ $requisito['tipo'] }}"
                            {{ ($requisito['caramelosDisponibles'] ?? 0) <= 0 ? 'disabled' : '' }}
                        >
                            Dar caramelo (+100)
                        </button>
                    </div>
                @endforeach
            </div>

            @if ($puedeEvolucionar)
                <form method="POST" action="{{ url('/reclutado/'.$reclutado['id'].'/evolucionar') }}" class="mt-5">
                    @csrf
                    <button type="submit" class="w-full px-4 py-3 bg-green-600 text-white rounded-xl text-sm font-bold hover:bg-green-700 transition-colors">
                        ¡Evolucionar a {{ $siguiente['nombre'] }}!
                    </button>
                </form>
            @endif
        </div>
    @else
        <div class="mt-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <p class="text-gray-500 dark:text-gray-400">Este Pokémon no tiene evolución.</p>
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
                    body: JSON.stringify({ tipo: button.dataset.tipo }),
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
