<div class="battle-log">
    <h4>Registro de batalla</h4>
    <div class="log-entries">
        @foreach(array_slice($log, -10) as $entry)
            <div class="log-entry">{{ $entry }}</div>
        @endforeach
    </div>
</div>
