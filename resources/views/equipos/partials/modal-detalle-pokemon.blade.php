    <!-- Pokemon Detail Modal (shared) -->
    <template x-if="showDetailModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeDetail()" role="dialog" aria-modal="true" aria-labelledby="detail-modal-title">
            <div class="absolute inset-0 bg-scrim" @click="closeDetail()"></div>
                <div class="relative bg-surface rounded-panel shadow-modal w-[90vw] max-w-[90vw] max-h-[94vh] overflow-hidden flex flex-col pokemon-modal-quality" :class="calidadClase(detailPokemon)">

                <div class="flex shrink-0 items-start gap-4 p-6 border-b border-line">
                    <div class="w-32 h-32 shrink-0 rounded-card pokemon-quality flex items-center justify-center overflow-hidden" :class="calidadClase(detailPokemon)">
                        <x-sprite
                            size="128"
                            x-bind:src="iconoPrincipalDe(detailPokemon)"
                            x-bind:alt="nombreDe(detailPokemon)"
                            x-on:error="onSpriteError($event, detailPokemon.pokemon_id, detailPokemon.es_shiny)"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 id="detail-modal-title" class="text-2xl font-bold text-ink capitalize truncate" x-text="nombreDe(detailPokemon)"></h3>
                            <template x-if="detailPokemon.es_shiny">
                                <span class="px-1.5 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded uppercase" title="Shiny">Shiny</span>
                            </template>
                        </div>
                        <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                            <template x-if="(detailPokemon.pokemon?.types || []).length">
                                <template x-for="t in detailPokemon.pokemon.types" :key="'header-type-'+(t.id ?? t.slot ?? t.type)">
                                    <span class="tipo-badge text-xs font-semibold inline-flex items-center gap-1" :style="'--type-color: ' + tipoBadge(tipoLabelDel(t)).color">
                                        <img :src="tipoIconoDeTipo(t)" :alt="tipoLabelDel(t)" loading="lazy" decoding="async" class="w-5 h-5 object-contain">
                                        <span x-text="tipoLabelDel(t)"></span>
                                    </span>
                                </template>
                            </template>
                        </div>
                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                            <span class="px-2 py-0.5 bg-primary-soft text-primary-ink text-xs font-bold rounded-full">Nv <span x-text="nivelDe(detailPokemon)"></span></span>
                            <template x-if="cpDe(detailPokemon) != null">
                                <span class="px-2 py-0.5 bg-info-soft text-info-ink text-xs font-bold rounded-full" title="Poder de combate">CP <span x-text="cpDe(detailPokemon)"></span></span>
                            </template>
                            <span class="text-3xs font-medium text-ink-subtle" x-text="getTeamName(pokemonTeamId(detailPokemon)) || 'Sin equipo'"></span>
                        </div>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <button
                            @click="releasePokemon()"
                            :disabled="detailLoading || (detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon)))"
                            :title="detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon)) ? 'El Pokémon está en exploración' : 'Liberar Pokémon'"
                            aria-label="Liberar Pokémon"
                            class="inline-flex items-center justify-center gap-1 px-2.5 py-2 bg-danger text-on-primary rounded-control text-xs font-bold hover:bg-danger/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            <span class="hidden sm:inline">Liberar</span>
                        </button>
                        <button @click="closeDetail()" class="w-8 h-8 flex items-center justify-center rounded-control text-ink-subtle hover:text-ink hover:bg-line transition-colors" aria-label="Cerrar">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-6 pokemon-modal-scroll">
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                        <!-- Columna 2/3: evolución, habilidades y movimientos -->
                        <div class="xl:col-span-2 space-y-4">
                            <!-- Evolución (solo si el Pokémon puede evolucionar) -->
                            <template x-if="detailEvoluciones.length > 0">
                                <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Evolución</h4>
                                    <template x-if="detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon))">
                                        <div class="mb-3 px-3 py-2 rounded-control bg-warning-soft text-warning-ink text-xs" role="status">Este Pokémon está en exploración activa: no puedes evolucionarlo hasta que vuelva.</div>
                                    </template>
                                    <div class="space-y-3">
                                        <template x-if="detailEvoluciones.length > 1">
                                            <div>
                                                <p class="text-3xs text-ink-muted mb-2">Selecciona a qué Pokémon evolucionar</p>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                    <template x-for="opcion in detailEvoluciones" :key="opcion.pokemon_id">
                                                        <button @click="selectedEvolucionId = opcion.pokemon_id" class="flex flex-col items-center gap-1 p-2 rounded-control border-2 transition-colors" :class="selectedEvolucionId === opcion.pokemon_id ? 'border-primary ring-2 ring-primary/30' : 'border-line hover:border-line-strong'" :aria-pressed="selectedEvolucionId === opcion.pokemon_id" :aria-label="'Evolucionar a ' + opcion.nombre">
                                                            <x-sprite size="96" x-bind:src="opcion.imagen || '/images/iconos_webp/' + opcion.pokemon_id + '.webp'" x-bind:alt="opcion.nombre" />
                                                            <span class="text-2xs text-ink capitalize truncate" x-text="opcion.nombre"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="opcionSeleccionada()">
                                            <div class="space-y-2.5">
                                                <template x-for="req in opcionSeleccionada().requisitos" :key="req.slug">
                                                    <div>
                                                        <div class="flex items-center justify-between mb-1"><span class="text-2xs text-ink-muted" x-text="req.tipo"></span><span class="text-2xs text-ink-subtle" x-text="formatExp(req.actual) + ' / ' + formatExp(req.necesario)"></span></div>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <div class="flex-1 min-w-[7rem] h-1.5 bg-surface-sunken rounded-full overflow-hidden" role="progressbar" :aria-valuenow="porcentajeExpRequisito(req)" aria-valuemin="0" aria-valuemax="100"><div class="h-full bg-primary rounded-full transition-all duration-500" :style="'width: ' + porcentajeExpRequisito(req) + '%'"></div></div>
                                                            <div class="flex shrink-0 items-center gap-1">
                                                                <span class="inline-flex items-center gap-1 rounded-full bg-surface-sunken px-1.5 py-0.5 text-2xs font-semibold text-ink-muted bg-surface-sunken text-ink" :title="'Caramelos de ' + req.tipo + ' disponibles: ' + req.caramelosDisponibles">
                                                                    <img :src="'/images/candy_type/' + req.slug + '.webp'" loading="lazy" decoding="async" :alt="'Caramelo de ' + req.tipo" class="w-3.5 h-3.5 object-contain" onerror="this.onerror=null; this.src='/images/candy_pokemon/0.webp';">
                                                                    <span x-text="'×' + req.caramelosDisponibles"></span>
                                                                </span>
                                                                <template x-for="cantidad in [1, 10, 100]" :key="'feed-'+req.slug+'-'+cantidad">
                                                                    <button type="button" @click="alimentarCaramelo(req, cantidad)" :disabled="!puedeAlimentar(req) || detailCandyFeeding" :title="'Consumir hasta ' + cantidad + ' caramelo(s) de ' + req.tipo + ' (+' + (cantidad * 100) + ' exp máx.)'" class="px-1.5 py-0.5 rounded-control bg-warning text-on-primary text-2xs font-bold hover:bg-warning/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed" x-text="'x' + cantidad"></button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="mt-3 border-t border-line pt-3">
                                        <button @click="evolvePokemon()" :disabled="detailLoading || (detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon))) || !opcionSeleccionada()?.puede_evolucionar" :title="(detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon))) ? 'El Pokémon está en exploración' : (opcionSeleccionada()?.puede_evolucionar ? 'Evolucionar Pokémon' : 'Completa la exp de tipo para evolucionar')" class="w-full px-4 py-2.5 bg-success text-on-primary rounded-control text-sm font-bold hover:bg-success/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                            <span x-text="detailLoading ? 'Evolucionando…' : 'Evolucionar'"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Habilidades -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Habilidades</h4>
                                <template x-if="(detailPokemon.habilidades_disponibles || []).length > 0">
                                    <div class="space-y-2">
                                        <template x-for="hab in detailPokemon.habilidades_disponibles" :key="'detail-hab-'+hab.id">
                                            <button type="button" @click="loadoutHabilidadId = (loadoutHabilidadId == hab.id ? '' : hab.id)" class="w-full text-left rounded-card border p-3 transition-colors" :class="loadoutHabilidadId == hab.id ? 'border-success bg-success-soft' : 'border-line bg-surface-sunken hover:border-success'">
                                                <div class="flex items-center justify-between gap-2"><span class="text-sm font-semibold text-ink" x-text="hab.name"></span><span x-show="loadoutHabilidadId == hab.id" class="text-3xs font-bold text-success-ink">ACTIVA</span></div>
                                                <p class="mt-1 text-xs leading-snug text-ink-muted" x-text="hab.description || 'Sin descripción.'"></p>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="(detailPokemon.habilidades_disponibles || []).length === 0">
                                    <p class="text-xs text-ink-subtle">Esta especie no tiene habilidades registradas.</p>
                                </template>
                            </div>

                            <!-- Movimientos asignados -->
                            <div class="rounded-panel border border-primary bg-primary-soft p-4">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-primary-ink">Movimientos asignados</h4>
                                    <span class="text-3xs text-primary-ink" x-text="loadoutMovimientos.length + '/6'"></span>
                                </div>
                                <p class="text-xs text-ink-muted mb-2">Habilidad activa: <strong x-text="nombreHabilidadLoadout()"></strong></p>
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    <template x-for="slug in loadoutMovimientos" :key="'loadout-chip-'+slug">
                                        <span class="inline-flex items-center gap-1 pl-1 pr-1.5 py-0.5 rounded-full bg-surface border border-primary text-3xs text-primary-ink">
                                            <img :src="tipoIconoUrl(movimientoLoadout(slug))" :alt="tipoDeMovimiento(movimientoLoadout(slug)) || 'Tipo'" loading="lazy" decoding="async" class="w-4 h-4 object-contain">
                                            <span x-text="nombreMovimientoLoadout(slug) || slug"></span>
                                            <button type="button" @click="toggleLoadoutMovimiento(slug)" class="w-4 h-4 flex items-center justify-center rounded-full text-primary-ink hover:text-danger-ink hover:bg-danger-soft transition-colors" :aria-label="'Quitar ' + nombreMovimientoLoadout(slug) + ' del loadout'" title="Quitar del loadout">×</button>
                                        </span>
                                    </template>
                                    <span x-show="loadoutMovimientos.length === 0" class="text-3xs text-ink-subtle">Sin selección: se usa el catálogo desbloqueado completo.</span>
                                </div>
                                <button type="button" @click="guardarLoadout()" :disabled="loadoutSaving" class="w-full px-3 py-2 bg-primary text-on-primary rounded-control text-xs font-bold hover:bg-primary-hover transition-colors disabled:opacity-40 disabled:cursor-not-allowed" x-text="loadoutSaving ? 'Guardando…' : 'Guardar loadout'"></button>
                                <div x-show="loadoutFeedback.mensaje" x-cloak role="status" class="mt-2 px-2 py-1.5 rounded text-2xs" :class="loadoutFeedback.tipo === 'error' ? 'bg-danger-soft text-danger-ink' : 'bg-success-soft text-success-ink'" x-text="loadoutFeedback.mensaje"></div>
                            </div>

                            <!-- Movimientos disponibles -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <div class="flex items-center justify-between mb-2 gap-2">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted">Movimientos disponibles</h4>
                                    <span class="text-3xs text-ink-subtle"><span x-text="movimientosFiltrados.length + ' / ' + (detailPokemon.movimientos_disponibles || []).length"></span> · <span x-text="loadoutMovimientos.length + ' asignados'"></span></span>
                                </div>
                                <template x-if="detailMovimientosLoading">
                                    <p class="text-xs text-ink-subtle animate-pulse">Cargando movimientos…</p>
                                </template>
                                <template x-if="detailMovimientosError">
                                    <p class="text-xs text-warning-ink" role="alert" x-text="detailMovimientosError"></p>
                                </template>
                                <template x-if="(detailPokemon.movimientos_disponibles || []).length > 0">
                                    <div class="space-y-2 mb-2">
                                        <div class="relative max-w-sm">
                                            <input type="text" x-model="filtroNombreMovimiento" placeholder="Filtrar movimientos por nombre..." class="w-full px-3 py-1.5 pl-9 bg-surface border border-line rounded-control text-xs text-ink placeholder:text-ink-subtle focus-ring transition-colors">
                                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <template x-for="tipo in tiposMovimientoDisponibles" :key="'filtro-tipo-'+tipo.slug">
                                                <button type="button" @click="toggleFiltroTipoMovimiento(tipo.slug)" :aria-pressed="filtroTipoMovimientoActivo(tipo.slug) ? 'true' : 'false'" :title="'Filtrar por tipo ' + tipo.nombre" class="tipo-badge inline-flex items-center gap-1 font-semibold transition-opacity" :style="'--type-color: ' + tipoBadge(tipo.slug).color" :class="filtroTipoMovimientoActivo(tipo.slug) ? 'border-ink' : 'bg-surface text-ink-muted border-line opacity-60 hover:opacity-100'">
                                                    <img :src="'/images/type/' + tipo.slug + '.webp'" :alt="tipo.nombre" loading="lazy" decoding="async" class="w-5 h-5 object-contain"><span x-text="tipo.nombre"></span>
                                                </button>
                                            </template>
                                            <span class="mx-1 h-4 w-px bg-surface-sunken" aria-hidden="true"></span>
                                            <template x-for="categoria in categoriasMovimientoDisponibles" :key="'filtro-categoria-'+categoria">
                                                <button type="button" @click="toggleFiltroCategoriaMovimiento(categoria)" :aria-pressed="filtroCategoriaMovimientoActiva(categoria) ? 'true' : 'false'" :title="'Filtrar por categoría ' + etiquetaCategoriaSlug(categoria)" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-3xs font-semibold transition-opacity" :class="filtroCategoriaMovimientoActiva(categoria) ? claseCategoria(categoria) + ' border-ink' : 'bg-surface text-ink-muted border-line opacity-60 hover:opacity-100'">
                                                    <img :src="categoriaIconoDe(categoria)" :alt="etiquetaCategoriaSlug(categoria)" loading="lazy" decoding="async" class="w-5 h-5 object-contain"><span x-text="etiquetaCategoriaSlug(categoria)"></span>
                                                </button>
                                            </template>
                                            <button type="button" x-show="hayFiltrosMovimiento()" @click="limpiarFiltrosMovimiento()" class="ml-auto text-3xs font-medium text-primary-ink hover:underline" title="Quitar todos los filtros">Limpiar filtros</button>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="(detailPokemon.movimientos_disponibles || []).length > 0">
                                    <div class="space-y-5">
                                        <!-- Movimientos de nivel -->
                                        <div>
                                            <div class="flex items-center justify-between gap-2 mb-2">
                                                <h5 class="text-xs font-bold uppercase tracking-wide text-ink-muted">Movimientos de nivel</h5>
                                                <span class="text-3xs text-ink-subtle" x-text="movimientosNivelFiltrados.length + ' movimientos'"></span>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                                                <template x-for="mov in movimientosNivelFiltrados" :key="'detail-mov-nivel-'+mov.slug">
                                                    @include('equipos.partials.movimiento-card')
                                                </template>
                                            </div>
                                            <p x-show="movimientosNivelFiltrados.length === 0" class="text-xs text-ink-subtle">Ningún movimiento de nivel coincide con los filtros.</p>
                                        </div>

                                        <!-- Desbloqueables (Sendas) -->
                                        <div>
                                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                                <div>
                                                    <h5 class="text-xs font-bold uppercase tracking-wide text-warning-ink">Desbloqueables</h5>
                                                    <p class="text-2xs text-ink-subtle">Se desbloquean en las Sendas y quedan disponibles para toda la cuenta. Los tramos 4-5 son maestrías pasivas.</p>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-3xs text-ink-subtle" x-text="movimientosSendaFiltrados.length + ' movimientos'"></span>
                                                    <a href="/sendas" class="px-2 py-1 rounded-control bg-warning text-on-primary text-2xs font-bold hover:bg-warning/90 transition-colors">Ir a Sendas</a>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                                                <template x-for="mov in movimientosSendaFiltrados" :key="'detail-mov-senda-'+mov.slug">
                                                    @include('equipos.partials.movimiento-card')
                                                </template>
                                            </div>
                                            <p x-show="movimientosSendaFiltrados.length === 0" class="text-xs text-ink-subtle">Ningún movimiento desbloqueable coincide con los filtros.</p>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="(detailPokemon.movimientos_disponibles || []).length > 0 && movimientosFiltrados.length === 0">
                                    <p class="text-xs text-ink-subtle">Ningún movimiento coincide con los filtros seleccionados.</p>
                                </template>
                                <template x-if="!detailMovimientosLoading && !detailMovimientosError && (detailPokemon.movimientos_disponibles || []).length === 0">
                                    <p class="text-xs text-ink-subtle">Esta especie no tiene movimientos de catálogo.</p>
                                </template>
                            </div>
                        </div>

                        <!-- Columna 1/3: stats -->
                        <div class="space-y-4">
                            <!-- Stats calculados con el nivel de la cuenta -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted">Stats</h4>
                                    <span class="text-2xs text-ink-subtle" title="Nivel de la cuenta">Nv <span x-text="nivelJugador"></span></span>
                                </div>
                                <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-2xs font-semibold text-ink-muted">
                                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-3 rounded-full bg-success"></span>Stats</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-3 rounded-full bg-primary"></span>IV</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-3 rounded-full bg-warning"></span>EV</span>
                                </div>
                                <div class="relative w-full" style="height: 180px;"><canvas id="detail-calculated-chart" role="img" aria-label="Gráfico hexagonal de stats"></canvas></div>
                                <div class="mt-3 space-y-1.5">
                                    <template x-for="stat in hexStatsCalculadosConAporte(detailPokemon)" :key="'calc-stat-'+stat.label">
                                        <div class="grid grid-cols-[2.5rem_1fr_2.5rem] items-center gap-2">
                                            <span class="text-2xs font-semibold text-ink-muted truncate" x-text="stat.label"></span>
                                            <div class="h-1.5 bg-surface-sunken rounded-full overflow-hidden" :title="'Stats ' + stat.base + ' + IV ' + stat.iv + ' + EV ' + stat.ev + ' = ' + stat.total">
                                                <div class="flex h-full w-full">
                                                    <div class="h-full bg-success" :style="'width: ' + Math.min(100, (stat.base / escalaCalculada(detailPokemon)) * 100) + '%'"></div>
                                                    <div x-show="stat.iv > 0" class="h-full bg-primary" :style="'width: ' + Math.min(100, (stat.iv / escalaCalculada(detailPokemon)) * 100) + '%'"></div>
                                                    <div x-show="stat.ev > 0" class="h-full bg-warning" :style="'width: ' + Math.min(100, (stat.ev / escalaCalculada(detailPokemon)) * 100) + '%'"></div>
                                                </div>
                                            </div>
                                            <span class="text-3xs font-bold text-right text-ink" x-text="stat.total"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Entrenamiento EV -->
                            <template x-if="detailPokemon && (detailPokemon.evs || detailPokemon.ev)">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted">Entrenamiento (EV)</h4>
                                        <span class="text-2xs text-ink-subtle">Máx. 255</span>
                                    </div>
                                    <template x-if="detailEvFeedback.mensaje">
                                        <div class="px-3 py-2 rounded-control text-xs mb-2" role="status" :class="detailEvFeedback.tipo === 'error' ? 'bg-danger-soft text-danger-ink' : 'bg-success-soft text-success-ink'" x-text="detailEvFeedback.mensaje"></div>
                                    </template>
                                    <div class="grid grid-cols-3 gap-2">
                                        <template x-for="stat in evStats" :key="'ev-'+stat.key">
                                            <div class="flex flex-col items-center text-center gap-1 rounded-card border border-line bg-surface-sunken p-1.5">
                                                <div class="relative">
                                                    <img :src="'/images/candy_ev/' + slugCorto(stat.key) + '.webp'" loading="lazy" decoding="async" :alt="'Caramelo EV de ' + stat.label" class="w-8 h-8 object-contain" onerror="this.style.display='none'">
                                                    <span class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full px-1 py-0.5 text-center text-2xs font-bold leading-none shadow" :class="evCaramelosDe(detailPokemon, stat.key) > 0 ? 'bg-warning text-on-primary' : 'bg-surface-sunken text-ink-muted bg-surface-sunken text-ink'" :title="'Caramelos EV disponibles: ' + evCaramelosDe(detailPokemon, stat.key)" x-text="'×' + evCaramelosDe(detailPokemon, stat.key)"></span>
                                                </div>
                                                <span class="text-2xs font-semibold text-ink-muted truncate w-full" x-text="stat.label"></span>
                                                <div class="flex items-baseline gap-0.5"><span class="text-xs font-bold text-ink" x-text="evValue(detailPokemon, stat.key)"></span><span class="text-2xs text-ink-subtle">/255</span></div>
                                                <div class="w-full h-1.5 bg-surface-sunken rounded-full overflow-hidden"><div class="h-full bg-warning rounded-full" :style="'width: ' + evPct(evValue(detailPokemon, stat.key)) + '%'"></div></div>
                                                <div class="mt-1 grid w-full grid-cols-2 gap-1">
                                                    <button @click="darEv(stat.key, 1)" :disabled="!puedeDarEv(detailPokemon, stat.key)" :title="!puedeDarEv(detailPokemon, stat.key) ? 'No puedes dar caramelos EV ahora mismo' : 'Usar hasta 1 caramelo EV (+10 a ' + stat.label + ')'" class="w-full rounded-control bg-warning px-1 py-1 text-2xs font-bold text-on-primary transition-colors hover:bg-warning/90 disabled:cursor-not-allowed disabled:opacity-40">+10</button>
                                                    <button @click="darEv(stat.key, 10)" :disabled="!puedeDarEv(detailPokemon, stat.key)" :title="!puedeDarEv(detailPokemon, stat.key) ? 'No puedes dar caramelos EV ahora mismo' : 'Usar hasta 10 caramelos EV (+100 a ' + stat.label + ')'" class="w-full rounded-control bg-warning px-1 py-1 text-2xs font-bold text-on-primary transition-colors hover:bg-warning/90 disabled:cursor-not-allowed disabled:opacity-40">+100</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Stats base -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted">Stats base</h4>
                                    <span class="text-2xs text-ink-subtle">0–<span x-text="escalaStats(detailPokemon)"></span></span>
                                </div>
                                <div class="relative w-full" style="height: 180px;"><canvas id="detail-stats-chart" role="img" aria-label="Gráfico hexagonal de stats base"></canvas></div>
                                <div class="mt-3 space-y-1.5">
                                    <template x-for="stat in hexStatsBarras(detailPokemon)" :key="'base-stat-'+stat.label">
                                        <div class="grid grid-cols-[2.5rem_1fr_2.5rem] items-center gap-2">
                                            <span class="text-2xs font-semibold text-ink-muted truncate" x-text="stat.label"></span>
                                            <div class="h-1.5 bg-surface-sunken rounded-full overflow-hidden">
                                                <div class="h-full bg-primary rounded-full" :style="'width: ' + Math.min(100, (stat.value / escalaStats(detailPokemon)) * 100) + '%'"></div>
                                            </div>
                                            <span class="text-3xs font-bold text-right text-ink" x-text="stat.value"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Valores IV -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4" :class="calidadClase(detailPokemon)">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Valores IV</h4>
                                <div class="relative w-full" style="height: 180px;"><canvas id="detail-iv-chart" role="img" aria-label="Gráfico hexagonal de valores individuales"></canvas></div>
                                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5">
                                    <template x-for="stat in evStats" :key="'iv-stat-'+stat.key">
                                        <div class="flex items-center gap-1.5" :class="ivCalidadClase(ivValue(detailPokemon, stat.key))" :title="stat.label + ': ' + ivValue(detailPokemon, stat.key) + '/15'">
                                            <span class="w-10 shrink-0 text-2xs text-ink-muted truncate" x-text="stat.corto || stat.label"></span>
                                            <div class="flex-1 h-1.5 bg-surface-sunken rounded-full overflow-hidden"><div class="h-full rounded-full iv-bar-fill" :style="'width: ' + (ivValue(detailPokemon, stat.key) / 15 * 100) + '%'"></div></div>
                                            <span class="text-3xs font-bold iv-value" x-text="ivValue(detailPokemon, stat.key)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
