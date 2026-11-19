{{--
    TASK-064 — Sección de caramelos del panel QA.

    Datos esperados (preparados por el backend):
    - $titulo (string)
    - $descripcion (string|null)
    - $filas (iterable<App\Support\Qa\FilaAjusteQa>)
    - $deltasCaramelos (list<int>)

    Cada fila es un único formulario con varios submit (name="delta").
    La vista no calcula reglas: solo muestra cantidades y dispara el POST.
--}}
@php
    $formatearDelta ??= static fn (int $delta): string => ($delta > 0 ? '+' : '-') . number_format(abs($delta), 0, ',', '.');
@endphp

<x-ui.card :title="$titulo" :subtitle="$descripcion ?? null" padding="sm" as="section" class="mb-6">
    @if (count($filas) === 0)
        <p class="text-sm text-ink-muted italic">No hay caramelos disponibles.</p>
    @else
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach ($filas as $fila)
                <li class="rounded-panel border border-line bg-surface-sunken p-3">
                    <div class="flex items-center gap-3">
                        <img
                            src="{{ $fila->imagen }}"
                            alt=""
                            width="40"
                            height="40"
                            loading="lazy"
                            decoding="async"
                            class="h-10 w-10 shrink-0 object-contain"
                            onerror="this.onerror=null; this.src='/images/candy_pokemon/0.webp';"
                        >
                        <p class="min-w-0 flex-1 truncate text-sm font-semibold text-ink" title="{{ $fila->nombre }}">
                            {{ $fila->nombre }}
                        </p>
                        <span class="shrink-0 rounded-full border border-warning bg-warning-soft px-2 py-0.5 text-2xs font-bold tabular-nums text-warning-ink">
                            <span class="sr-only">Cantidad actual: {{ $fila->cantidad }}.</span>
                            <span aria-hidden="true">&times;{{ number_format($fila->cantidad, 0, ',', '.') }}</span>
                        </span>
                    </div>

                    <form method="POST" action="{{ route('qa.debug.inventario') }}" class="mt-3 flex flex-wrap items-center gap-1.5">
                        @csrf
                        <input type="hidden" name="item_key" value="{{ $fila->clave }}">
                        @foreach ($deltasCaramelos as $delta)
                            <x-ui.button
                                type="submit"
                                name="delta"
                                value="{{ $delta }}"
                                variant="secondary"
                                size="sm"
                                class="!px-2.5"
                                aria-label="Ajustar {{ $fila->nombre }} en {{ $formatearDelta($delta) }} caramelos"
                                title="Ajustar {{ $fila->nombre }} en {{ $formatearDelta($delta) }} caramelos"
                            >
                                {{ $formatearDelta($delta) }}
                            </x-ui.button>
                        @endforeach
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
