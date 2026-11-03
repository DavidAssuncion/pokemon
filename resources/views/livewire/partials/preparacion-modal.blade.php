<div class="modal fade"
     id="preparacion-modal"
     tabindex="-1"
     aria-labelledby="preparacion-modal-title"
     aria-hidden="true"
     wire:ignore.self>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="preparacion-modal-title">Preparación de la ronda {{ $round }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                @if($preparacionPendiente)
                    @include('livewire.partials.preparacion-panel')
                @else
                    <p class="text-sm text-ink-muted mb-0">La preparación solo está disponible al inicio de la ronda.</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                @if($preparacionPendiente)
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="saltarPreparacion" data-bs-dismiss="modal" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                        Saltar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="confirmarPreparacion" data-bs-dismiss="modal" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                        Listo
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
