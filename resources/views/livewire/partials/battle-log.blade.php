<div class="battle-log mt-3">
    <div class="card">
        <div class="card-header bg-body-tertiary fw-semibold">Registro de batalla</div>
        <ul class="list-group list-group-flush">
            @forelse(array_slice($log, -10) as $entry)
                <li class="list-group-item py-1 small log-entry">{{ $entry }}</li>
            @empty
                <li class="list-group-item py-1 small text-muted">Sin eventos en el registro</li>
            @endforelse
        </ul>
    </div>
</div>
