<div class="battle-log">
    <ul class="list-group list-group-flush">
        @forelse($logVista as $entry)
            <li class="list-group-item py-1 small log-entry d-flex align-items-center gap-1 flex-wrap">
                {!! $entry['html'] !!}
            </li>
        @empty
            <li class="list-group-item py-1 small text-muted d-flex align-items-center gap-1 flex-wrap">Sin eventos en el registro</li>
        @endforelse
    </ul>
</div>
