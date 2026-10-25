@php
    $evento = \Src\Exploraciones\Presentation\EventoExploracionVista::toView($evento);
    $candyFallback ??= "this.src='/images/candy_pokemon/0.webp'; this.onerror=null;";
    $tipo = $evento['tipo'];
@endphp
<li class="flex items-center gap-3 py-1.5">
    @if($tipo === 'pokemon')
        <img
            src="{{ $evento['icono_pokemon'] }}"
            loading="lazy"
            decoding="async"
            alt="{{ $evento['nombre'] ?? 'Pokémon' }}"
            class="w-10 h-10 object-contain"
            onerror="this.style.display='none'"
        >
        <span class="text-sm text-gray-700 dark:text-gray-300">
            Encontraste un <strong>{{ $evento['nombre'] ?? 'Pokémon' }}</strong>
        </span>
    @elseif($tipo === 'huida')
        <img
            src="{{ $evento['icono_pokemon'] }}"
            loading="lazy"
            decoding="async"
            alt="{{ $evento['nombre'] ?? 'Pokémon' }}"
            class="w-10 h-10 object-contain"
            onerror="this.style.display='none'"
        >
        <span class="text-sm text-gray-700 dark:text-gray-300">
            {{ $evento['texto'] }}
        </span>
    @elseif($tipo === 'emboscada')
        <svg class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div class="flex flex-wrap items-center gap-2">
            <div>
                <p class="text-sm font-semibold text-red-600 dark:text-red-400">{{ $evento['titulo'] }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-300">{{ $evento['subtitulo'] }}</p>
            </div>
            @if($evento['iconos_pokemon'] !== [])
                <div class="flex -space-x-2">
                    @foreach($evento['iconos_pokemon'] as $icono)
                        <img
                            src="{{ $icono }}"
                            loading="lazy"
                            decoding="async"
                            alt="Pokémon salvaje"
                            title="Pokémon salvaje"
                            class="w-8 h-8 object-contain rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600"
                            onerror="this.style.display='none'"
                        >
                    @endforeach
                </div>
            @endif
        </div>
    @elseif($tipo === 'contratiempo')
        <svg class="w-5 h-5 text-orange-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $evento['texto'] }}</span>
    @elseif($tipo === 'retirada')
        <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        <span class="text-sm text-gray-700 dark:text-gray-300">
            El equipo se retira: <strong>{{ $evento['reason'] }}</strong>
        </span>
    @elseif($tipo === 'grupo')
        <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $evento['texto'] }}</span>
            @if($evento['iconos_pokemon'] !== [])
                <div class="flex -space-x-2">
                    @foreach($evento['iconos_pokemon'] as $icono)
                        <img
                            src="{{ $icono }}"
                            loading="lazy"
                            decoding="async"
                            alt="Pokémon salvaje"
                            title="Pokémon salvaje"
                            class="w-8 h-8 object-contain rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600"
                            onerror="this.style.display='none'"
                        >
                    @endforeach
                </div>
            @endif
        </div>
    @elseif($tipo === 'hallazgo')
        @if($evento['subtype'] === 'caramelo_familia')
            @if($evento['candy_src'] !== null)
                <img src="{{ $evento['candy_src'] }}" loading="lazy" decoding="async"
                     alt="{{ $evento['candy_alt'] }}"
                     title="{{ $evento['candy_alt'] }}"
                     class="w-10 h-10 object-contain shrink-0"
                     onerror="{{ $candyFallback }}">
            @else
                <svg class="w-5 h-5 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
                </svg>
            @endif
            <span class="text-sm text-gray-700 dark:text-gray-300">
                Caramelos de <strong>{{ $evento['candy_nombre'] ?? 'Pokémon' }}</strong>
                <span class="font-semibold text-gray-900 dark:text-white">×{{ $evento['cantidad'] }}</span>
            </span>
        @elseif($evento['subtype'] === 'caramelo_ev')
            @if($evento['candy_src'] !== null)
                <img src="{{ $evento['candy_src'] }}" loading="lazy" decoding="async"
                     alt="{{ $evento['candy_alt'] }}"
                     title="{{ $evento['candy_alt'] }}"
                     class="w-10 h-10 object-contain shrink-0"
                     onerror="{{ $candyFallback }}">
            @else
                <svg class="w-5 h-5 text-cyan-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
                </svg>
            @endif
            <span class="text-sm text-gray-700 dark:text-gray-300">
                Caramelo EV
                @if($evento['candy_nombre_js'] !== null)
                    <strong x-text="{{ $evento['candy_nombre_js'] }}"></strong>
                @else
                    <strong>{{ $evento['candy_nombre'] }}</strong>
                @endif
                <span class="font-semibold text-gray-900 dark:text-white">×{{ $evento['cantidad'] }}</span>
            </span>
        @elseif($evento['subtype'] === 'caramelo_tipo')
            @if($evento['candy_src'] !== null)
                <img src="{{ $evento['candy_src'] }}" loading="lazy" decoding="async"
                     alt="{{ $evento['candy_alt'] }}"
                     title="{{ $evento['candy_alt'] }}"
                     class="w-10 h-10 object-contain shrink-0"
                     onerror="{{ $candyFallback }}">
            @else
                <svg class="w-5 h-5 text-pink-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
                </svg>
            @endif
            <span class="text-sm text-gray-700 dark:text-gray-300">
                Caramelo de tipo <strong>{{ $evento['candy_nombre'] }}</strong>
                <span class="font-semibold text-gray-900 dark:text-white">×{{ $evento['cantidad'] }}</span>
            </span>
        @else
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $evento['texto'] }}</span>
        @endif
    @elseif($tipo === 'caramelo_familia')
        @if($evento['candy_src'] !== null)
            <img src="{{ $evento['candy_src'] }}" loading="lazy" decoding="async"
                 alt="{{ $evento['candy_alt'] }}"
                 title="{{ $evento['candy_alt'] }}"
                 class="w-10 h-10 object-contain shrink-0"
                 onerror="{{ $candyFallback }}">
        @else
            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
            </svg>
        @endif
        <span class="text-sm text-gray-700 dark:text-gray-300">
            Caramelos de <strong>{{ $evento['candy_nombre'] ?? 'Pokémon' }}</strong>
            <span class="font-semibold text-gray-900 dark:text-white">×{{ $evento['cantidad'] }}</span>
        </span>
    @elseif($tipo === 'caramelo_ev')
        @if($evento['candy_src'] !== null)
            <img src="{{ $evento['candy_src'] }}" loading="lazy" decoding="async"
                 alt="{{ $evento['candy_alt'] }}"
                 title="{{ $evento['candy_alt'] }}"
                 class="w-10 h-10 object-contain shrink-0"
                 onerror="{{ $candyFallback }}">
        @else
            <svg class="w-5 h-5 text-cyan-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
            </svg>
        @endif
        <span class="text-sm text-gray-700 dark:text-gray-300">
            Caramelo EV
            @if($evento['candy_nombre_js'] !== null)
                <strong x-text="{{ $evento['candy_nombre_js'] }}"></strong>
            @else
                <strong>{{ $evento['candy_nombre'] }}</strong>
            @endif
            <span class="font-semibold text-gray-900 dark:text-white">×{{ $evento['cantidad'] }}</span>
        </span>
    @elseif($tipo === 'encuentro')
        <img
            src="{{ $evento['icono_pokemon'] }}"
            loading="lazy"
            decoding="async"
            alt="{{ $evento['nombre'] ?? 'Pokémon' }}"
            class="w-10 h-10 object-contain"
            onerror="this.style.display='none'"
        >
        <div class="flex flex-wrap items-center gap-2">
            <div>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    <strong>{{ $evento['titulo'] }}</strong>
                    @if($evento['resolucion_texto'] !== '')
                        <span class="text-gray-600 dark:text-gray-400"> · {{ $evento['resolucion_texto'] }}</span>
                    @endif
                    @if($evento['duracion'] > 0)
                        <span class="text-xs text-gray-500 dark:text-gray-400">(-{{ $evento['duracion'] }} min)</span>
                    @endif
                </p>
            </div>
        </div>
    @elseif($tipo === 'neutral')
        <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $evento['texto'] }}</span>
    @elseif($tipo === 'combate')
        <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-red-600 dark:text-red-400">⚔️ Combate</span>
            @if($evento['resolucion'] !== '')
                <span class="text-xs text-gray-600 dark:text-gray-300">· {{ $evento['resolucion'] }}</span>
            @endif
            @if($evento['hp_final'] > 0)
                <span class="text-xs text-gray-500 dark:text-gray-400">· HP final: {{ $evento['hp_final'] }}</span>
            @endif
            @if($evento['combate_log'] !== '')
                <p class="text-xs text-gray-500 dark:text-gray-400 w-full">{{ $evento['combate_log'] }}</p>
            @endif
        </div>
    @elseif($tipo === 'descanso')
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-sm text-gray-700 dark:text-gray-300">
            El equipo descansa
            @if($evento['duracion'] > 0)
                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $evento['duracion'] }} min)</span>
            @endif
        </span>
    @else
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ $evento['texto'] }}
        </span>
    @endif
    <span class="ml-auto text-xs text-gray-400 dark:text-gray-500 shrink-0"
          x-text="fmtTime('{{ $evento['timestamp'] ?? '' }}')"></span>
</li>
