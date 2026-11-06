    <!-- Granjas Modal -->
    <template x-if="showGranjaModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="granja-modal-title" @keydown.escape.window="cerrarCapaSuperior()">
            <div class="absolute inset-0 scrim" @click="closeGranjaModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-[90vw] max-w-[90vw] max-h-[94vh] overflow-hidden flex flex-col">
                <div class="flex shrink-0 items-center justify-between gap-3 px-6 py-4 border-b border-line">
                    <div>
                        <h3 id="granja-modal-title" class="text-lg font-bold text-ink">
                            Granjas — <span x-text="granja?.habitat?.name || '{{ $habitat['name'] }}'"></span>
                        </h3>
                        <p class="text-xs text-ink-muted">Nidos, refugios y complementos de las familias del hábitat.</p>
                    </div>
                    <button @click="closeGranjaModal()" class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring" aria-label="Cerrar modal de granjas">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-6 space-y-4">
                    <div x-show="granjaLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando granja...
                    </div>

                    <div x-show="granjaError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span x-text="granjaError"></span>
                        <button @click="loadGranja()" class="ml-2 underline font-medium">Reintentar</button>
                    </div>

                    <template x-if="!granjaLoading && granja">
                        <div class="space-y-4">
                            <div class="rounded-card bg-surface-sunken border border-line p-3 text-xs text-ink-muted space-y-1">
                                <p><strong>Nido:</strong> cada nivel sube el rango de IV de los huevos y reduce su tiempo de gestación/eclosión.</p>
                                <p><strong>Refugio:</strong> modifica la aparición y la captura de la familia. Las condiciones insalubres invierten el efecto.</p>
                                <p><strong>Complementos:</strong> dan EV a toda la familia; al subir un complemento se aplica también a los reclutados existentes.</p>
                            </div>

                            <div x-show="granja.familias.length === 0" class="text-center py-10 text-ink-muted">
                                Este hábitat no tiene familias asignadas todavía.
                            </div>

                            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Familias del hábitat">
                                <template x-for="(familia, index) in (granja.familias || [])" :key="'familia-tab-' + familia.evolution_chain_id">
                                    <button
                                        type="button"
                                        role="tab"
                                        :id="'familia-tab-' + familia.evolution_chain_id"
                                        :aria-selected="familiaActiva === index ? 'true' : 'false'"
                                        :aria-controls="'familia-panel-' + familia.evolution_chain_id"
                                        :tabindex="familiaActiva === index ? 0 : -1"
                                        @click="familiaActiva = index"
                                        @keydown.arrow-right.prevent="navegarFamilia(1)"
                                        @keydown.arrow-left.prevent="navegarFamilia(-1)"
                                        @keydown.home.prevent="navegarFamilia('inicio')"
                                        @keydown.end.prevent="navegarFamilia('fin')"
                                        class="inline-flex items-center gap-2 px-3 py-2 rounded-control text-sm font-medium transition-colors focus-ring"
                                        :class="familiaActiva === index
                                            ? 'bg-primary text-on-primary shadow'
                                            : 'bg-surface text-ink border border-line hover:bg-surface-sunken'"
                                    >
                                        <x-sprite size="96" decorative x-bind:src="familia.base?.icon" />
                                    </button>
                                </template>
                            </div>

                            <template x-for="(familia, index) in (granja.familias || [])" :key="'familia-panel-' + familia.evolution_chain_id">
                                <div
                                    role="tabpanel"
                                    :id="'familia-panel-' + familia.evolution_chain_id"
                                    :aria-labelledby="'familia-tab-' + familia.evolution_chain_id"
                                    x-show="familiaActiva === index"
                                    x-cloak
                                    tabindex="0"
                                    class="rounded-panel border border-line bg-surface p-4 space-y-4"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-16 h-16 shrink-0 rounded-card bg-surface-sunken flex items-center justify-center overflow-hidden">
                                                <x-sprite size="64" x-bind:src="familia.base?.icon" x-bind:alt="familia.base?.name || ''" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-base font-bold text-ink" x-text="familia.base?.name || 'Familia'"></p>
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    <template x-for="tipo in (familia.types || [])" :key="'familia-tipo-' + familia.evolution_chain_id + '-' + tipo.id">
                                                        <span class="tipo-badge" :style="'--type-color: ' + tipoBadgePorId(tipo.id).color">
                                                            <template x-if="tipoBadgePorId(tipo.id).icono">
                                                                <img :src="tipoBadgePorId(tipo.id).icono" alt="" loading="lazy" decoding="async" class="w-4 h-4 object-contain">
                                                            </template>
                                                            <span x-text="tipo.name"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-2xs uppercase tracking-wide text-ink-subtle">Caramelos familia</p>
                                            <p class="text-sm font-bold text-ink"><span x-text="saldoFamilia(familia.evolution_chain_id)"></span></p>
                                        </div>
                                    </div>

                                    <div class="grid md:grid-cols-2 gap-3">
                                        <!-- Nido -->
                                        <div class="rounded-card border border-warning bg-warning-soft p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-bold text-ink">Nido</p>
                                                <span class="text-xs font-semibold text-warning-ink" x-text="'Nv ' + familia.nido.nivel + '/' + familia.nido.max"></span>
                                            </div>
                                            <p class="text-xs text-ink-muted mt-2">
                                                IV por stat: <strong x-text="familia.nido.rango_iv.min + '–' + familia.nido.rango_iv.max"></strong>
                                            </p>
                                            <p class="text-xs text-ink-muted">
                                                Tiempo de huevo: <strong x-text="familia.nido.reduccion_tiempo_pct + '% más rápido'"></strong>
                                            </p>
                                            <button
                                                type="button"
                                                @click="mejorarNido(familia.evolution_chain_id)"
                                                :disabled="granjaAccionando || !familia.nido.puede_mejorar"
                                                class="mt-3 w-full px-3 py-2 rounded-control text-xs font-bold transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed bg-warning text-on-primary hover:bg-warning/90"
                                            >
                                                <span x-text="familia.nido.coste_siguiente !== null ? (familia.nido.nivel === 0 ? 'Construir nido' : 'Mejorar nido') + ' · ' + familia.nido.coste_siguiente + ' caramelos' : 'Nido al máximo'"></span>
                                            </button>
                                        </div>

                                        <!-- Refugio -->
                                        <div class="rounded-card border border-success bg-success-soft p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-bold text-ink">Refugio</p>
                                                <span class="text-xs font-semibold text-success-ink" x-text="'Nv ' + familia.refugio.nivel + '/' + familia.refugio.max"></span>
                                            </div>
                                            <p class="text-xs text-ink-muted mt-2">
                                                Aparición ×<strong x-text="familia.refugio.multiplicador_aparicion"></strong>
                                                · Captura ×<strong x-text="familia.refugio.multiplicador_captura"></strong>
                                            </p>
                                            <p x-show="familia.refugio.exclusivo" class="text-xs font-semibold text-success-ink mt-1">
                                                Nivel 5: solo aparece esta familia.
                                            </p>
                                            <p x-show="familia.refugio.excluido" class="text-xs font-semibold text-warning-ink mt-1">
                                                Otra familia tiene un refugio nivel 5: esta familia no aparece.
                                            </p>
                                            <p x-show="familia.refugio.insalubres" class="text-xs font-semibold text-danger-ink mt-1">
                                                Condiciones insalubres activas: efecto invertido y complementos bloqueados.
                                            </p>
                                            <button
                                                type="button"
                                                @click="mejorarRefugio(familia.evolution_chain_id)"
                                                :disabled="granjaAccionando || !familia.refugio.puede_mejorar"
                                                class="mt-3 w-full px-3 py-2 rounded-control text-xs font-bold transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed bg-success text-on-primary hover:bg-success/90"
                                            >
                                                <span x-text="familia.refugio.coste_siguiente !== null ? (familia.refugio.nivel === 0 ? 'Construir refugio' : 'Mejorar refugio') + ' · ' + familia.refugio.coste_siguiente + ' caramelos' : 'Refugio al máximo'"></span>
                                            </button>
                                            <button
                                                type="button"
                                                x-show="familia.refugio.nivel > 0"
                                                @click="alternarInsalubres(familia.evolution_chain_id)"
                                                :disabled="granjaAccionando"
                                                class="mt-2 w-full px-3 py-1.5 rounded-control text-3xs font-semibold border transition-colors disabled:opacity-50 disabled:cursor-not-allowed border-danger text-danger-ink hover:bg-danger-soft"
                                                x-text="familia.refugio.insalubres ? 'Activar condiciones salubres' : 'Activar condiciones insalubres'"
                                            ></button>
                                        </div>
                                    </div>

                                    <!-- Plantaciones del nido -->
                                    <div class="rounded-card border border-line bg-surface-sunken p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-bold text-ink">
                                                Plantaciones · <span class="text-xs font-semibold text-ink-muted" x-text="familia.nido.huecos_libres + '/' + familia.nido.huecos_totales + ' huecos'"></span>
                                            </p>
                                            <span class="text-3xs text-ink-subtle">24 h por cosecha · máx. 3</span>
                                        </div>

                                        <p class="mt-1 text-2xs text-ink-muted">
                                            Cada planta +20% de peso, tope ×2.0; solo bayas de esa granja/hábitat.
                                        </p>

                                        <p x-show="familia.nido.huecos_totales === 0" class="text-xs text-ink-muted mt-2">
                                            Construye el nido para desbloquear huecos de plantación.
                                        </p>

                                        <div x-show="familia.nido.huecos_totales > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-2 mt-3">
                                            <template x-for="hueco in huecosDe(familia)" :key="familia.evolution_chain_id + '-hueco-' + hueco.slot">
                                                <div class="rounded-control border border-line bg-surface p-2">
                                                    <template x-if="hueco.plantacion">
                                                        <div class="space-y-1.5">
                                                            <div class="flex items-start justify-between gap-2">
                                                                <div class="flex items-center gap-2 min-w-0">
                                                                    <img :src="hueco.plantacion.berry_icono" alt="" loading="lazy" decoding="async" class="w-7 h-7 object-contain shrink-0">
                                                                    <div class="min-w-0">
                                                                        <p class="text-2xs font-semibold text-ink truncate" x-text="hueco.plantacion.berry_nombre"></p>
                                                                        <p class="text-3xs text-ink-muted">
                                                                            Stock <span class="font-bold text-ink" x-text="hueco.plantacion.stock + '/' + hueco.plantacion.stock_max"></span>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <span class="shrink-0 px-1.5 py-0.5 rounded-full text-3xs font-bold uppercase"
                                                                      :class="hueco.plantacion.puede_cosechar ? 'bg-success-soft text-success-ink' : 'bg-surface-sunken text-ink-muted'"
                                                                      x-text="hueco.plantacion.estado"></span>
                                                            </div>
                                                            <div class="h-1.5 rounded-full bg-line overflow-hidden"
                                                                 role="progressbar"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100"
                                                                 :aria-valuenow="hueco.plantacion.progreso_pct"
                                                                 :aria-label="'Progreso de ' + hueco.plantacion.berry_nombre">
                                                                <div class="h-full bg-success transition-all" :style="'width: ' + hueco.plantacion.progreso_pct + '%'"></div>
                                                            </div>
                                                            <div class="flex gap-1.5">
                                                                <button
                                                                    type="button"
                                                                    @click="cosecharBaya(familia.evolution_chain_id, hueco.slot)"
                                                                    :disabled="granjaAccionando || !hueco.plantacion.puede_cosechar"
                                                                    class="flex-1 px-2 py-1.5 rounded-control text-3xs font-bold uppercase transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed bg-success text-on-primary hover:bg-success/90"
                                                                >
                                                                    Cosechar
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    @click="replantarBaya(familia.evolution_chain_id, hueco.slot, hueco.plantacion)"
                                                                    :disabled="granjaAccionando || cantidadBaya(hueco.plantacion.berry_key) < 1"
                                                                    :title="cantidadBaya(hueco.plantacion.berry_key) < 1 ? 'Sin existencias de esta baya' : 'Replantar y perder el stock pendiente'"
                                                                    class="flex-1 px-2 py-1.5 rounded-control text-3xs font-bold uppercase border transition-colors disabled:opacity-40 disabled:cursor-not-allowed border-danger text-danger-ink hover:bg-danger-soft"
                                                                >
                                                                    Replantar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="!hueco.plantacion">
                                                        <div class="space-y-2">
                                                            <p class="text-3xs uppercase tracking-wide text-ink-subtle">
                                                                Hueco <span x-text="hueco.slot"></span> · vacío
                                                            </p>
                                                            <p class="text-3xs text-ink-subtle">
                                                                Elige baya para plantar.
                                                            </p>
                                                            <button
                                                                type="button"
                                                                @click="abrirSelectorBaya(familia.evolution_chain_id, hueco.slot)"
                                                                :disabled="granjaAccionando"
                                                                class="w-full px-2 py-1.5 rounded-control text-3xs font-bold uppercase transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed bg-primary text-on-primary hover:bg-primary-hover focus-ring"
                                                            >
                                                                Plantar
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Complementos -->
                                    <div>
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-bold text-ink">Complementos EV</p>
                                            <span class="text-xs font-semibold text-primary-ink" x-text="'Total familia: +' + familia.ev_total + ' EV'"></span>
                                        </div>
                                        <p x-show="familia.refugio.insalubres" class="text-xs text-danger-ink mt-1">
                                            Desactivados mientras las condiciones insalubres estén activas.
                                        </p>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mt-2">
                                            <template x-for="comp in (familia.complementos || [])" :key="familia.evolution_chain_id + '-comp-' + comp.stat">
                                                <div class="rounded-control border border-line bg-surface-sunken p-2">
                                                    <div class="flex items-center justify-between gap-1">
                                                        <span class="text-xs font-semibold text-ink" x-text="comp.nombre"></span>
                                                        <span class="text-2xs font-bold text-ink-muted" x-text="'Nv ' + comp.nivel + '/' + comp.max"></span>
                                                    </div>
                                                    <p class="text-3xs text-primary-ink mt-1">+<span x-text="comp.ev"></span> EV</p>
                                                    <p class="text-2xs text-ink-muted">
                                                        <span x-show="comp.coste_ev !== null" x-text="comp.coste_ev + ' EV + ' + comp.coste_familia + ' caramelos familia'"></span>
                                                        <span x-show="comp.coste_ev === null">Máximo</span>
                                                    </p>
                                                    <button
                                                        type="button"
                                                        @click="mejorarComplemento(familia.evolution_chain_id, comp.stat)"
                                                        :disabled="granjaAccionando || !comp.puede_mejorar"
                                                        class="mt-2 w-full px-2 py-1.5 rounded-md text-3xs font-bold transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed bg-primary text-on-primary hover:bg-primary-hover"
                                                        x-text="textoBotonComplemento(comp)"
                                                    ></button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

            </div>
        </div>
    </template>
