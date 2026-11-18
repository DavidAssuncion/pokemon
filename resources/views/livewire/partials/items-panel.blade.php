{{-- Panel de objetos de combate. Solo presentación: stock, aplicabilidad, motivo y
     etiqueta de objetivos llegan resueltos desde el backend (F5). --}}
@php
    $categoriasItems = [
        'baya' => 'Bayas',
        'pocion' => 'Pociones',
        'mejora' => 'Mejoras',
        'reductor' => 'Reductores',
        'resistencia' => 'Resistencias',
    ];

    $gruposItems = [];
    foreach ($objetosCombate as $objeto) {
        $gruposItems[$objeto['categoria']][] = $objeto;
    }

    $objetoActual = null;
    foreach ($objetosCombate as $objeto) {
        if ($objeto['clave'] === $objetoSeleccionado) {
            $objetoActual = $objeto;
            break;
        }
    }

    $objetivoLabel = $objetoActual['objetivos_label'] ?? match ($objetivosItem) {
        'aliado_vivo' => 'un aliado vivo',
        'aliado_debilitado' => 'un aliado debilitado',
        'rival_vivo' => 'un rival vivo',
        default => 'un objetivo',
    };
@endphp

<div class="items-panel"
     role="group"
     aria-label="{{ $phase === 'player_item_target' ? 'Objeto seleccionado' : 'Objetos de combate' }}">

    @if(($aliadosSeleccionables ?? false) && in_array($phase, ['player_target', 'player_move'], true))
        <div class="alert alert-info py-2 px-3 small mb-3" role="status">
            Puedes seleccionar un aliado para movimientos de apoyo.
        </div>
    @endif

    @if($phase === 'player_item_target')
        {{-- Objeto elegido: se confirma el objetivo directamente en el campo. --}}
        @if($objetoActual !== null)
            <div class="card mb-3">
                <div class="card-body p-3 d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <img src="{{ $objetoActual['icono'] }}"
                             alt=""
                             width="40"
                             height="40"
                             class="flex-shrink-0"
                             loading="lazy"
                             decoding="async">
                        <span class="fw-bold">{{ $objetoActual['nombre'] }}</span>
                    </div>
                    <p class="small text-ink-muted mb-0" role="status">
                        Selecciona {{ $objetivoLabel }} en el campo.
                    </p>
                </div>
            </div>
        @else
            <div class="alert alert-warning py-2 px-3 small" role="alert">
                No se pudo identificar el objeto seleccionado. Pulsa Cancelar para volver a los ataques.
            </div>
        @endif

        <button type="button"
                class="btn btn-outline-secondary w-100 focus-ring"
                wire:click="cancelarObjeto">
            Cancelar
        </button>
    @elseif($objetosCombate === [])
        <div class="alert alert-secondary py-2 px-3 small mb-0" role="status">
            No hay objetos disponibles para este combate.
        </div>
    @else
        @foreach($gruposItems as $categoria => $items)
            @php
                $categoriaLabel = $categoriasItems[$categoria] ?? ucfirst($categoria);
                $categoriaId = 'items-cat-'.\Illuminate\Support\Str::slug($categoria);
            @endphp

            <section class="mb-3" aria-labelledby="{{ $categoriaId }}">
                <h4 id="{{ $categoriaId }}"
                    class="h6 text-uppercase text-ink-muted mb-2 d-flex align-items-center justify-content-between gap-2">
                    <span>{{ $categoriaLabel }}</span>
                    <span class="badge bg-surface-sunken border text-ink-muted">{{ count($items) }}</span>
                </h4>

                <div class="d-grid gap-2">
                    @foreach($items as $objeto)
                        <button type="button"
                                class="card w-100 text-start p-2 d-flex flex-row align-items-center gap-2 focus-ring {{ $objeto['aplicable'] ? '' : 'opacity-50' }}"
                                @disabled(! $objeto['aplicable'])
                                title="{{ $objeto['aplicable'] ? 'Usar '.$objeto['nombre'] : $objeto['motivo'] }}"
                                wire:click="seleccionarObjeto('{{ $objeto['clave'] }}')">
                            <img src="{{ $objeto['icono'] }}"
                                 alt=""
                                 width="36"
                                 height="36"
                                 class="flex-shrink-0"
                                 loading="lazy"
                                 decoding="async">

                            <span class="flex-grow-1 min-w-0">
                                <span class="d-flex align-items-center gap-1 min-w-0">
                                    <span class="fw-semibold text-truncate" title="{{ $objeto['nombre'] }}">{{ $objeto['nombre'] }}</span>
                                    <span class="badge bg-surface-sunken border text-ink-muted flex-shrink-0">×{{ $objeto['cantidad'] }}</span>
                                </span>
                                <span class="d-block small text-ink-muted text-truncate" title="{{ $objeto['descripcion'] }}">
                                    {{ $objeto['descripcion'] }}
                                </span>
                                @if(! $objeto['aplicable'] && $objeto['motivo'] !== '')
                                    <span class="d-block small text-danger-ink">{{ $objeto['motivo'] }}</span>
                                @endif
                            </span>

                            <span class="badge {{ $objeto['aplicable'] ? 'bg-primary text-on-primary' : 'bg-surface-sunken border text-ink-muted' }} flex-shrink-0">
                                {{ $objeto['aplicable'] ? 'Usar' : '—' }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
