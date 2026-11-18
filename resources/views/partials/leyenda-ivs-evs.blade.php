{{-- Leyenda de stats e IV/EV del rival: solo presenta datos ya calculados por el backend. --}}
@php $columnas = (int) ($columnas ?? 1); $colapsable = (bool) ($colapsable ?? false); @endphp
<section role="region" aria-labelledby="leyenda-ivs-evs-titulo" class="mb-6">
    @if ($colapsable)
        <details class="group rounded-control border border-line bg-surface-sunken">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2.5 focus-ring [&::-webkit-details-marker]:hidden">
                <span id="leyenda-ivs-evs-titulo" class="shrink-0 text-sm font-bold text-ink">IV/EV del rival</span>
                <span class="flex min-w-0 items-center gap-2">
                    <span class="truncate text-xs text-ink-muted"
                          aria-live="polite"
                          aria-atomic="true"
                          x-text="pcCargando ? 'Cargando…' : (pcError ? 'No disponible' : (pcComparacion?.perfil_ivs_evs?.actual?.etiqueta || 'No disponible'))"></span>
                    <svg class="h-4 w-4 shrink-0 text-ink-muted transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </span>
            </summary>
            <div class="border-t border-line px-3 py-3">
                <p class="mb-3 text-xs text-ink-muted">
                    Cada Pokémon del rival usa uno de estos perfiles. Los stats se calculan al nivel del combate.
                </p>
    @else
        <h4 id="leyenda-ivs-evs-titulo" class="mb-1 text-sm font-bold text-ink">IV/EV del rival</h4>
        <p class="mb-3 text-xs text-ink-muted">
            Cada Pokémon del rival usa uno de estos perfiles. Los stats se calculan al nivel del combate.
        </p>
    @endif

    <div class="space-y-3 text-sm text-ink {{ $colapsable ? '' : 'rounded-control border border-line bg-surface-sunken px-3 py-3' }}"
         aria-live="polite"
         aria-atomic="true"
         :aria-busy="pcCargando ? 'true' : 'false'">

        {{-- Cargando perfiles --}}
        <div x-show="pcCargando" x-cloak role="status" class="flex items-center gap-2 text-ink-muted">
            <svg class="h-4 w-4 animate-spin motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span>Cargando perfiles de IV/EV…</span>
        </div>

        {{-- Error del indicador: nunca bloquea el combate --}}
        <p x-show="!pcCargando && pcError" x-cloak role="alert" class="text-danger-ink">
            No se pudieron cargar los perfiles del rival. Puedes combatir igualmente.
        </p>

        <template x-if="!pcCargando && !pcError && pcComparacion && pcComparacion.perfil_ivs_evs">
            <div class="space-y-3">
                {{-- Perfil aplicado --}}
                <p class="text-sm text-ink">
                    <template x-if="pcComparacion.perfil_ivs_evs.actual">
                        <span>Perfil aplicado: <strong x-text="pcComparacion.perfil_ivs_evs.actual.etiqueta"></strong></span>
                    </template>
                    <template x-if="!pcComparacion.perfil_ivs_evs.actual">
                        <span class="text-ink-muted">No disponible para este combate</span>
                    </template>
                </p>

                {{-- Perfiles disponibles --}}
                <div role="region" aria-label="Perfiles de IV/EV aplicables al rival" tabindex="0"
                     class="overflow-x-auto rounded-control border border-line bg-surface p-2 focus-ring">
                    <table class="w-full min-w-[22rem] border-collapse text-left text-xs">
                        <caption class="sr-only">Perfiles de IV/EV aplicables al rival y sus stats al máximo</caption>
                        <thead>
                            <tr class="border-b border-line text-ink-muted">
                                <th scope="col" class="py-1.5 pr-2 font-semibold">Perfil</th>
                                <th scope="col" class="py-1.5 pr-2 text-right font-semibold">Stats al máximo</th>
                                <th scope="col" class="py-1.5 pr-2 text-right font-semibold">IV (máx./resto)</th>
                                <th scope="col" class="py-1.5 text-right font-semibold">EV (máx./resto)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="perfil in pcComparacion.perfil_ivs_evs.perfiles" :key="perfil.clave">
                                <tr class="border-b border-line align-top last:border-b-0"
                                    :aria-current="perfil.actual ? 'true' : null"
                                    :class="perfil.actual ? 'bg-primary-soft' : ''">
                                    <th scope="row" class="py-1.5 pr-2 font-medium text-ink">
                                        <span class="flex flex-wrap items-center gap-1.5">
                                            <span x-text="perfil.etiqueta"></span>
                                            <template x-if="perfil.actual">
                                                <span class="rounded-full border border-primary bg-primary px-1.5 py-0.5 text-2xs font-bold text-on-primary">Este combate</span>
                                            </template>
                                        </span>
                                        <span class="mt-0.5 block text-2xs font-normal text-ink-muted" x-text="perfil.descripcion"></span>
                                    </th>
                                    <td class="py-1.5 pr-2 text-right tabular-nums" x-text="perfil.stats_al_maximo"></td>
                                    <td class="py-1.5 pr-2 text-right tabular-nums">
                                        <span x-text="perfil.iv_maximo"></span> / <span x-text="perfil.iv_resto"></span>
                                    </td>
                                    <td class="py-1.5 text-right tabular-nums">
                                        <span x-text="perfil.ev_maximo"></span> / <span x-text="perfil.ev_resto"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Desglose anónimo por Pokémon del rival --}}
                <div class="grid grid-cols-1 gap-3 {{ $columnas >= 3 ? 'xl:grid-cols-3' : '' }}">
                    <template x-for="pokemon in pcComparacion.perfil_ivs_evs.pokemons" :key="pokemon.etiqueta">
                        <div class="min-w-0 space-y-1.5">
                            <p class="text-xs font-semibold text-ink" x-text="pokemon.etiqueta"></p>
                            <div role="region" :aria-label="'Desglose de stats de ' + pokemon.etiqueta" tabindex="0"
                                 class="overflow-x-auto rounded-control border border-line bg-surface p-2 focus-ring">
                                <table class="w-full min-w-[20rem] border-collapse text-left text-xs">
                                    <caption class="sr-only" x-text="'Stats de ' + pokemon.etiqueta + ': base, IV, EV y calculado'"></caption>
                                    <thead>
                                        <tr class="border-b border-line text-ink-muted">
                                            <th scope="col" class="py-1.5 pr-2 font-semibold">Stat</th>
                                            <th scope="col" class="py-1.5 pr-2 text-right font-semibold">Base</th>
                                            <th scope="col" class="py-1.5 pr-2 text-right font-semibold">IV</th>
                                            <th scope="col" class="py-1.5 pr-2 text-right font-semibold">EV</th>
                                            <th scope="col" class="py-1.5 text-right font-semibold">Calculado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="stat in pokemon.stats" :key="stat.clave">
                                            <tr class="border-b border-line last:border-b-0">
                                                <th scope="row" class="py-1 pr-2 font-medium text-ink" x-text="stat.etiqueta"></th>
                                                <td class="py-1 pr-2 text-right tabular-nums" x-text="stat.base"></td>
                                                <td class="py-1 pr-2 text-right tabular-nums" x-text="stat.iv"></td>
                                                <td class="py-1 pr-2 text-right tabular-nums" x-text="stat.ev"></td>
                                                <td class="py-1 text-right tabular-nums" x-text="stat.calculado"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Sin rival disponible --}}
                <template x-if="!pcComparacion.perfil_ivs_evs.pokemons || pcComparacion.perfil_ivs_evs.pokemons.length === 0">
                    <p class="text-xs text-ink-muted">
                        No se muestra el desglose porque el rival no está disponible.
                    </p>
                </template>

                {{-- Nota final --}}
                <p class="text-2xs text-ink-muted">
                    Base = stats base; IV/EV = perfil aplicado; Calculado = stat al nivel del combate. El orden de los rivales no indica su posición en combate.
                </p>
            </div>
        </template>
    </div>

    @if ($colapsable)
            </div>
        </details>
    @endif
</section>
