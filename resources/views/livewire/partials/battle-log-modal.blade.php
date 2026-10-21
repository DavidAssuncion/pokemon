<div class="modal fade"
     id="battle-log-modal"
     tabindex="-1"
     aria-labelledby="battle-log-modal-title"
     aria-hidden="true"
     wire:ignore.self>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="battle-log-modal-title">Registro de batalla</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                @include('livewire.partials.battle-log')
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
