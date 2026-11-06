@extends('layouts.app')

@section('title', 'Equipos')

@section('content')
<div x-data="favoritosApp()">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">Equipos</h1>
        <p class="text-sm text-ink-muted mt-1">Gestiona tus equipos de combate</p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- EQUIPOS                                                            -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left 1/3: Teams Panel -->
            @include('equipos.partials.panel-equipos')

            <!-- Right 2/3: Available Pokemon (equipos) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Section switcher: Pokémon / Huevos -->
                <div class="flex rounded-card bg-surface-sunken border border-line p-1" role="tablist" aria-label="Secciones del equipo">
                    <button
                        @click="cambiarSeccion('pokemon')"
                        role="tab"
                        :aria-selected="activeSeccion === 'pokemon'"
                        class="flex-1 px-4 py-2 rounded-control text-sm font-medium transition-colors"
                        :class="activeSeccion === 'pokemon'
                            ? 'bg-surface text-primary-ink shadow-sm'
                            : 'text-ink-muted hover:text-ink'"
                    >
                        Pokémon
                    </button>
                    <button
                        @click="cambiarSeccion('huevos')"
                        role="tab"
                        :aria-selected="activeSeccion === 'huevos'"
                        class="flex-1 px-4 py-2 rounded-control text-sm font-medium transition-colors"
                        :class="activeSeccion === 'huevos'
                            ? 'bg-surface text-primary-ink shadow-sm'
                            : 'text-ink-muted hover:text-ink'"
                    >
                        Huevos
                    </button>
                </div>

                @include('equipos.partials.panel-pokemon')

                    @include('equipos.partials.panel-huevos')
            </div>
        </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- MODALS                                                              -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->

    @include('equipos.partials.modal-exploracion')

    @include('equipos.partials.modal-eliminar-equipo')

    @include('equipos.partials.modal-reemplazo')

    @include('equipos.partials.modal-detalle-pokemon')

</div>

@push('scripts')
@include('partials.tipos-badge-script')
<script>
function favoritosApp() {
    return {
        // ─── Datos del servidor ─────────────────────────────────────────────
        nivelJugador: @json($nivelJugador ?? 1),
        reclutados: @json($reclutados ?? []),
        reclutadosEnExploracion: @json($reclutadosEnExploracion ?? []),
        teams: @json($teams ?? []),
        teamPokemonIds: @json($teamIds ?? []),
        inventarioCaramelos: @json($inventarioCaramelos ?? ['ev' => []]),

        // ─── Formación persistente (combate 3v3) ───────────────────────────
        // Draft local por equipo: {teamId: {slot: 'vanguardia'|'retaguardia'}}.
        // La formación persistida del backend (team.formacion) recién se conoce
        // tras el primer PATCH; el draft tiene prioridad sobre ella.
        formacionDraft: {},
        formacionSavingTeamId: null,
        formacionSaveError: '',
        formacionSaveSuccess: '',

        // ─── Posturas persistentes por miembro ─────────────────────────────
        // Draft local: {teamId: {slot: 'atacar'|'guardia'|'cubrir'}}.
        posturasDraft: {},
        posturaSavingTeamId: null,
        posturaSaveError: '',
        posturaSaveSuccess: '',

        // ─── Objetos equipados por miembro ──────────────────────────────────
        itemsDraft: {},
        itemSavingTeamId: null,
        itemSaveError: '',
        itemSaveSuccess: '',

        // ─── UI: Equipos ───────────────────────────────────────────────────
        // ─── UI: Buscar/filtro ─────────────────────────────────────────────
        searchQuery: '',
        typeFilters: [],

        selectedTeamId: null,
        showNewTeamForm: false,
        newTeamName: '',
        showDeleteModal: false,
        teamToDelete: null,

        // ─── Reemplazo de miembro en equipo completo ───────────────────────
        showReplaceModal: false,
        replaceTeam: null,
        replacePokemon: null,
        replaceSaving: false,
        replaceError: '',

        // ─── Detail Modal (shared) ─────────────────────────────────────────
        showDetailModal: false,
        detailPokemon: null,
        detailLoading: false,
        detailEvoluciones: [],
        detailEvolucionesLoading: false,
        detailEvolucionesError: false,
        selectedEvolucionId: null,
        detailChartInstances: [],
        // Catálogo de movimientos cargado bajo demanda al abrir el modal.
        detailMovimientosLoading: false,
        detailMovimientosError: '',
        movimientosCargadosIds: [],
        movimientosCargandoIds: [],

        // ─── Detail: loadout de combate ─────────────────────────────────────
        loadoutHabilidadId: '',
        loadoutMovimientos: [],
        filtrosMovimientoTipo: [],
        filtrosMovimientoCategoria: [],
        filtroNombreMovimiento: '',
        loadoutSaving: false,
        loadoutFeedback: { tipo: '', mensaje: '' },

        // ─── Detail: EV feeding (caramelos EV / IVs) ─────────────────────────
        evStats: [
            { key: 'hp', label: 'PS', corto: 'PS' },
            { key: 'atk', label: 'Ataque', corto: 'ATK' },
            { key: 'def', label: 'Defensa', corto: 'DEF' },
            { key: 'spAtk', label: 'Ataque Esp.', corto: 'A.Esp' },
            { key: 'spDef', label: 'Defensa Esp.', corto: 'D.Esp' },
            { key: 'speed', label: 'Velocidad', corto: 'VEL' },
        ],
        detailEvsFeeding: false,
        detailCandyFeeding: false,
        detailEvFeedback: { tipo: '', mensaje: '' },

        // ─── Huevos tab ──────────────────────────────────────────────────────
        activeSeccion: 'pokemon',
        huevos: [],
        huevosDisponibles: [],
        huevosLimites: null,
        huevosLoaded: false,
        huevosLoading: false,
        huevosError: '',
        huevosActuando: false,
        huevosAccionId: null,
        huevosFeedback: { tipo: '', mensaje: '' },

        // ─── Exploration Modal ──────────────────────────────────────────────
        showExploracionModal: false,
        exploracionPokemon: null,
        habitats: [],
        habitatsLoading: false,
        habitatsError: '',
        exploracionHabitatId: null,
        exploracionLevel: 1,
        durationMode: 'hours',
        durationHours: 4,
        returnTime: '18:00',
        preview: null,
        previewLoading: false,
        previewLoaded: false,
        previewError: '',
        sendingExploracion: false,

        // ═══════════════════════════════════════════════════════════════════════
        // COMPUTED GETTERS
        // ═══════════════════════════════════════════════════════════════════════

        /** Equipos tab: colección ordenada por CP desde el backend; el front solo filtra. */
        get pokemonsEquipoFiltrados() {
            let result = this.reclutados.slice();
            const q = this.normalizarTexto(this.searchQuery).trim();
            if (q) {
                result = result.filter(r => {
                    const texto = [
                        r.nombre,
                        r.pokemon?.name,
                        r.pokemon_id,
                    ].map(valor => this.normalizarTexto(valor)).join(' ');
                    return texto.includes(q);
                });
            }
            if (this.typeFilters.length > 0) {
                result = result.filter(r => {
                    const tipos = Array.isArray(r.pokemon?.types) ? r.pokemon.types : [];
                    return tipos.some(t => this.typeFilters.includes(this.tipoIdDe(t)));
                });
            }

            return result;
        },

        // ═══════════════════════════════════════════════════════════════════════
        // SHARED HELPERS
        // ═══════════════════════════════════════════════════════════════════════

        normalizarTexto(valor) {
            return String(valor ?? '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        },

        hayFiltrosEquipo() {
            return this.searchQuery.trim() !== ''
                || this.typeFilters.length > 0;
        },

        toggleTypeFilter(tipoId) {
            const id = Number(tipoId);
            this.typeFilters = this.typeFilters.includes(id)
                ? this.typeFilters.filter(actual => actual !== id)
                : [...this.typeFilters, id];
        },

        filtroTipoActivo(tipoId) {
            return this.typeFilters.includes(Number(tipoId));
        },

        tipoIdDe(tipo) {
            if (!tipo) return null;
            const raw = tipo.type ?? tipo.tipo_id ?? null;
            return raw === null ? null : Number(raw);
        },

        enExploracion(reclutadoId) {
            return this.reclutadosEnExploracion.includes(reclutadoId);
        },

        /** Número de miembros del equipo que están explorando ahora mismo. */
        teamEnExploracion(team) {
            if (!team || !Array.isArray(team.members)) return 0;
            return team.members.filter(m => this.enExploracion(m.pokemon_id)).length;
        },

        // ─── Nivel/exp ─────────────────────────────────────────────────────

        expTotalDe(pokemon) {
            if (!pokemon) return 0;
            if (typeof pokemon.exp_total === 'number') return pokemon.exp_total;
            return pokemon.exp && typeof pokemon.exp.total === 'number' ? pokemon.exp.total : 0;
        },

        // CP (poder de combate) expuesto por el backend en el serializador de reclutado.
        // Tolerante a data legacy que aún no lo entrega → devuelve null y se omite la badge.
        cpDe(pokemon) {
            if (!pokemon) return null;
            if (typeof pokemon.cp === 'number') return pokemon.cp;
            return null;
        },

        // Nombre visible del reclutado: si no tiene nombre personalizado (null), usa el
        // nombre por defecto del pokémon CAPITALIZADO (ucfirst). Sirve para todos los sitios
        // que muestran un nombre en la UI (tarjetas, detalle, formación).
        nombreDe(pokemon) {
            if (!pokemon) return '';
            let nombre = pokemon.nombre;
            if (!nombre && pokemon.pokemon && pokemon.pokemon.name) nombre = pokemon.pokemon.name;
            return String(nombre || '').charAt(0).toUpperCase() + String(nombre || '').slice(1);
        },

        /** Nombre legible: quita guiones y capitaliza cada palabra ("raticate-alola" → "Raticate Alola"). */
        nombreFormateado(pokemon) {
            if (!pokemon) return '';
            let raw = pokemon.nombre;
            if (!raw && pokemon.pokemon && pokemon.pokemon.name) raw = pokemon.pokemon.name;
            raw = String(raw || '').replace(/-/g, ' ');
            return raw.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        },

        /** URL del icono WebP; shiny usa `_s` y el fallback a 0.webp si falta el id. */
        iconoDe(pokemon) {
            if (!pokemon || !pokemon.pokemon_id) return '/images/iconos_webp/0.webp';
            if (pokemon.es_shiny) return '/images/iconos_webp/shiny/' + pokemon.pokemon_id + '_s.webp';
            return '/images/iconos_webp/' + pokemon.pokemon_id + '.webp';
        },

        /** URL del icono WebP; shiny usa `_s` y el fallback lo resuelve onSpriteError. */
        iconoPrincipalDe(pokemon) {
            if (!pokemon || !pokemon.pokemon_id) return '/images/iconos_webp/0.webp';
            if (pokemon.es_shiny) {
                return '/images/iconos_webp/shiny/' + pokemon.pokemon_id + '_s.webp';
            }
            return '/images/iconos_webp/' + pokemon.pokemon_id + '.webp';
        },

        /** Lee un token CSS resuelto (Chart.js no acepta `var()`). */
        tokenCss(nombre) {
            if (typeof document === 'undefined') return '';
            return getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
        },

        /** Convierte `#rrggbb` o `rgb(...)`/`rgba(...)` a "r, g, b". */
        colorARgb(color) {
            const valor = String(color || '').trim();
            if (valor.startsWith('#')) {
                let hex = valor.slice(1);
                if (hex.length === 3) {
                    hex = hex.split('').map(c => c + c).join('');
                }
                if (hex.length !== 6) return '';
                const r = parseInt(hex.slice(0, 2), 16);
                const g = parseInt(hex.slice(2, 4), 16);
                const b = parseInt(hex.slice(4, 6), 16);
                return [r, g, b].every(n => Number.isFinite(n)) ? r + ', ' + g + ', ' + b : '';
            }
            const rgb = valor.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i);
            return rgb ? Number(rgb[1]) + ', ' + Number(rgb[2]) + ', ' + Number(rgb[3]) : '';
        },

        /** Fallback de sprites: shiny → normal → 0.webp → ocultar. */
        onSpriteError(event, pokemonId, esShiny) {
            const img = event?.target;
            if (!img) return;
            if (!pokemonId) {
                img.style.display = 'none';
                return;
            }
            const intento = img.dataset.intento || (esShiny ? 'shiny' : 'normal');
            if (intento === 'shiny') {
                img.dataset.intento = 'normal';
                img.src = '/images/iconos_webp/' + pokemonId + '.webp';
                return;
            }
            if (intento === 'normal') {
                img.dataset.intento = 'cero';
                img.src = '/images/iconos_webp/0.webp';
                return;
            }
            img.style.display = 'none';
        },

        nivelDe(pokemon) {
            // El backend es el dueño del nivel; el front solo presenta el dato.
            return Number(pokemon?.nivel ?? 1);
        },

        formatExp(exp) {
            return Number(exp || 0).toLocaleString('es-ES');
        },

        porcentajeExpRequisito(req) {
            if (!req) return 0;
            const actual = Number(req.actual || 0);
            const necesario = Number(req.necesario || 0);
            if (!(necesario > 0)) return 0;
            return Math.min(100, Math.round((actual / necesario) * 100));
        },

        statsDe(pokemon) {
            if (!pokemon) return [];
            if (Array.isArray(pokemon.stats) && pokemon.stats.length > 0) return pokemon.stats;
            if (pokemon.pokemon && Array.isArray(pokemon.pokemon.stats) && pokemon.pokemon.stats.length > 0) return pokemon.pokemon.stats;
            return [];
        },

        // ═══════════════════════════════════════════════════════════════════════
        // DETAIL: chart hexagonal
        // ═══════════════════════════════════════════════════════════════════════

        hexStats(pokemon) {
            const stats = this.statsDe(pokemon);
            return [
                { label: 'PS', value: Number(stats[0]?.value ?? 0) },
                { label: 'ATK', value: Number(stats[1]?.value ?? 0) },
                { label: 'DEF', value: Number(stats[2]?.value ?? 0) },
                { label: 'VEL', value: Number(stats[5]?.value ?? 0) },
                { label: 'SpD', value: Number(stats[4]?.value ?? 0) },
                { label: 'SpA', value: Number(stats[3]?.value ?? 0) },
            ];
        },

        /** Orden de las barras horizontales: SpD y Velocidad al final. */
        hexStatsBarras(pokemon) {
            const stats = this.statsDe(pokemon);
            return [
                { label: 'PS', value: Number(stats[0]?.value ?? 0) },
                { label: 'ATK', value: Number(stats[1]?.value ?? 0) },
                { label: 'DEF', value: Number(stats[2]?.value ?? 0) },
                { label: 'SpA', value: Number(stats[3]?.value ?? 0) },
                { label: 'SpD', value: Number(stats[4]?.value ?? 0) },
                { label: 'VEL', value: Number(stats[5]?.value ?? 0) },
            ];
        },

        /** Stats finales y su desglose base/IV/EV, calculados por el backend. */
        statsCalculadosConAporte(pokemon) {
            const stats = pokemon?.stats_calculados || {};
            const claves = ['hp', 'atk', 'def', 'spAtk', 'spDef', 'speed'];
            const resultado = {};

            claves.forEach(clave => {
                const stat = stats[clave] || {};
                resultado[clave] = {
                    base: Number(stat.base ?? 0),
                    iv: Number(stat.iv ?? 0),
                    ev: Number(stat.ev ?? 0),
                    total: Number(stat.total ?? 0),
                };
            });

            return resultado;
        },

        hexStatsCalculados(pokemon) {
            const stats = this.statsCalculadosConAporte(pokemon);
            return [
                { label: 'PS', value: stats.hp.total },
                { label: 'ATK', value: stats.atk.total },
                { label: 'DEF', value: stats.def.total },
                { label: 'VEL', value: stats.speed.total },
                { label: 'SpD', value: stats.spDef.total },
                { label: 'SpA', value: stats.spAtk.total },
            ];
        },

        hexStatsCalculadosConAporte(pokemon) {
            const stats = this.statsCalculadosConAporte(pokemon);
            return [
                { label: 'PS', ...stats.hp },
                { label: 'ATK', ...stats.atk },
                { label: 'DEF', ...stats.def },
                { label: 'SpA', ...stats.spAtk },
                { label: 'SpD', ...stats.spDef },
                { label: 'VEL', ...stats.speed },
            ];
        },

        hexIvs(pokemon) {
            return [
                { label: 'PS', value: this.ivValue(pokemon, 'hp') },
                { label: 'ATK', value: this.ivValue(pokemon, 'atk') },
                { label: 'DEF', value: this.ivValue(pokemon, 'def') },
                { label: 'VEL', value: this.ivValue(pokemon, 'speed') },
                { label: 'SpD', value: this.ivValue(pokemon, 'spDef') },
                { label: 'SpA', value: this.ivValue(pokemon, 'spAtk') },
            ];
        },

        /**
         * Calidad global por media de los seis IV. El estado perfecto exige 15 en todos.
         * Devuelve la clave (clase CSS), el color resuelto y su rgb para Chart.js.
         * Los colores se leen de tokens porque los gráficos no aceptan `var()`.
         * `normal` en oscuro usa `--color-ink`: `--quality-color-normal` no cambia
         * con el tema y así se conserva el gris claro que ya usaba la vista.
         */
        calidadIvs(pokemon) {
            const valores = this.evStats.map(stat => Number(this.ivValue(pokemon, stat.key)) || 0);
            const suma = valores.reduce((total, valor) => total + valor, 0);
            const media = valores.length > 0 ? suma / valores.length : 0;
            const perfecto = valores.length === 6 && valores.every(valor => valor === 15);

            let clave = 'normal';
            if (perfecto) {
                clave = 'perfect';
            } else if (media >= 13) {
                clave = 'excellent';
            } else if (media >= 9) {
                clave = 'great';
            } else if (media >= 5) {
                clave = 'good';
            }

            const oscuro = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
            const token = clave === 'normal' && oscuro ? '--color-ink' : '--quality-color-' + clave;
            const color = this.tokenCss(token) || this.tokenCss('--quality-color-normal');
            const rgb = this.colorARgb(color);

            return { clave, color, rgb, media, valores, perfecto };
        },

        calidadClase(pokemon) {
            return 'pokemon-quality--' + this.calidadIvs(pokemon).clave;
        },

        ivCalidadClave(valor) {
            const iv = Number(valor) || 0;
            if (iv >= 15) return 'perfect';
            if (iv >= 13) return 'excellent';
            if (iv >= 9) return 'great';
            if (iv >= 5) return 'good';
            return 'normal';
        },

        ivCalidadClase(valor) {
            return 'pokemon-quality--' + this.ivCalidadClave(valor);
        },

        destroyDetailCharts() {
            (this.detailChartInstances || []).forEach(chart => {
                try {
                    chart.destroy();
                } catch (error) {
                    console.warn('No se pudo destruir un gráfico del detalle:', error);
                }
            });
            this.detailChartInstances = [];
        },

        crearRadarChart(canvasId, etiquetas, valores, maximo, color, rgb) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || typeof window.Chart === 'undefined') return null;

            const labelColor = this.tokenCss('--color-ink-muted');
            const lineRgb = this.colorARgb(this.tokenCss('--color-line'));
            const gridColor = lineRgb ? 'rgba(' + lineRgb + ', 0.35)' : this.tokenCss('--color-line');

            return new window.Chart(canvas.getContext('2d'), {
                type: 'radar',
                data: {
                    labels: etiquetas,
                    datasets: [{
                        data: valores,
                        borderColor: color,
                        backgroundColor: 'rgba(' + rgb + ', 0.18)',
                        pointBackgroundColor: color,
                        pointBorderColor: color,
                        pointRadius: 3,
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    scales: {
                        r: {
                            min: 0,
                            max: Math.max(1, maximo),
                            ticks: { display: false },
                            grid: { color: gridColor },
                            angleLines: { color: gridColor },
                            pointLabels: {
                                color: labelColor,
                                font: { size: 11, weight: '600' },
                            },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                    },
                },
            });
        },

        renderDetailCharts() {
            if (!this.detailPokemon || typeof window.Chart === 'undefined') return;

            this.destroyDetailCharts();

            const base = this.hexStats(this.detailPokemon);
            const calculados = this.hexStatsCalculados(this.detailPokemon);
            const ivs = this.hexIvs(this.detailPokemon);
            const calidad = this.calidadIvs(this.detailPokemon);

            const charts = [
                this.crearRadarChart(
                    'detail-stats-chart',
                    base.map(stat => stat.label),
                    base.map(stat => stat.value),
                    this.escalaStats(this.detailPokemon),
                    this.tokenCss('--color-primary'),
                    this.colorARgb(this.tokenCss('--color-primary'))
                ),
                this.crearRadarChart(
                    'detail-calculated-chart',
                    calculados.map(stat => stat.label),
                    calculados.map(stat => stat.value),
                    this.escalaCalculada(this.detailPokemon),
                    this.tokenCss('--color-success'),
                    this.colorARgb(this.tokenCss('--color-success'))
                ),
                this.crearRadarChart(
                    'detail-iv-chart',
                    ivs.map(stat => stat.label),
                    ivs.map(stat => stat.value),
                    15,
                    calidad.color,
                    calidad.rgb
                ),
            ].filter(chart => chart !== null);

            this.detailChartInstances = charts;
        },

        escalaStats(pokemon) {
            const valores = this.hexStats(pokemon).map(stat => Number(stat.value) || 0);
            const maxStat = Math.max(1, ...valores);
            return Math.min(255, Math.max(50, Math.ceil((maxStat * 1.15) / 10) * 10));
        },

        escalaCalculada(pokemon) {
            const valores = this.hexStatsCalculados(pokemon).map(stat => Number(stat.value) || 0);
            const maxStat = Math.max(1, ...valores);
            return Math.max(10, Math.ceil((maxStat * 1.15) / 10) * 10);
        },

        get movimientosFiltrados() {
            const movimientos = this.detailPokemon?.movimientos_disponibles || [];
            const tipos = this.filtrosMovimientoTipo;
            const categorias = this.filtrosMovimientoCategoria;
            const nombre = this.normalizarTexto(this.filtroNombreMovimiento).trim();
            if (tipos.length === 0 && categorias.length === 0 && !nombre) {
                return movimientos;
            }
            return movimientos.filter(mov => {
                const tipoOk = tipos.length === 0 || tipos.includes(this.tipoSlugDeMovimiento(mov));
                const categoriaOk = categorias.length === 0 || categorias.includes(this.categoriaSlug(mov));
                const nombreOk = !nombre || this.normalizarTexto(mov.name).includes(nombre);
                return tipoOk && categoriaOk && nombreOk;
            });
        },

        get movimientosNivelFiltrados() {
            return this.movimientosFiltrados
                .filter(mov => mov.origen === 'nivel')
                .sort((a, b) => (Number(a.nivel_requerido) - Number(b.nivel_requerido)) || String(a.name).localeCompare(String(b.name)));
        },

        get movimientosSendaFiltrados() {
            return this.movimientosFiltrados.filter(mov => mov.origen === 'senda');
        },

        get tiposMovimientoDisponibles() {
            const vistos = new Set();
            const tipos = [];
            for (const mov of this.detailPokemon?.movimientos_disponibles || []) {
                const slug = this.tipoSlugDeMovimiento(mov);
                if (!vistos.has(slug)) {
                    vistos.add(slug);
                    tipos.push({ slug, nombre: this.tipoDeMovimiento(mov) || slug });
                }
            }
            return tipos;
        },

        get categoriasMovimientoDisponibles() {
            const presentes = new Set(
                (this.detailPokemon?.movimientos_disponibles || []).map(mov => this.categoriaSlug(mov))
            );
            return ['fisico', 'especial', 'estado'].filter(categoria => presentes.has(categoria));
        },

        toggleFiltroTipoMovimiento(slug) {
            this.filtrosMovimientoTipo = this.filtrosMovimientoTipo.includes(slug)
                ? this.filtrosMovimientoTipo.filter(tipo => tipo !== slug)
                : [...this.filtrosMovimientoTipo, slug];
        },

        toggleFiltroCategoriaMovimiento(categoria) {
            this.filtrosMovimientoCategoria = this.filtrosMovimientoCategoria.includes(categoria)
                ? this.filtrosMovimientoCategoria.filter(cat => cat !== categoria)
                : [...this.filtrosMovimientoCategoria, categoria];
        },

        filtroTipoMovimientoActivo(slug) {
            return this.filtrosMovimientoTipo.includes(slug);
        },

        filtroCategoriaMovimientoActiva(categoria) {
            return this.filtrosMovimientoCategoria.includes(categoria);
        },

        hayFiltrosMovimiento() {
            return this.filtrosMovimientoTipo.length > 0
                || this.filtrosMovimientoCategoria.length > 0
                || String(this.filtroNombreMovimiento || '').trim() !== '';
        },

        limpiarFiltrosMovimiento() {
            this.filtrosMovimientoTipo = [];
            this.filtrosMovimientoCategoria = [];
            this.filtroNombreMovimiento = '';
        },

        /** Habilidad activa del reclutado para el resumen en hover de la tarjeta. */
        habilidadActivaDe(pokemon) {
            const id = pokemon?.habilidad_activa_id;
            if (id === null || id === undefined || id === '') return null;
            return (pokemon?.habilidades_disponibles || []).find(hab => String(hab.id) === String(id)) || null;
        },

        movimientoDeReclutado(pokemon, slug) {
            return (pokemon?.movimientos_disponibles || []).find(mov => mov.slug === slug)
                || (pokemon?.movimientos_seleccionados_detalle || []).find(mov => mov.slug === slug)
                || null;
        },

        /** Movimientos del loadout con los datos del catálogo cargado en /equipos. */
        movimientosAsignadosDe(pokemon) {
            const slugs = Array.isArray(pokemon?.movimientos_seleccionados) ? pokemon.movimientos_seleccionados : [];
            return slugs.map(slug => this.movimientoDeReclutado(pokemon, slug)).filter(Boolean);
        },

        /** Devuelve el movimiento del catálogo a partir de su slug (para chips equipados). */
        movimientoLoadout(slug) {
            return (this.detailPokemon?.movimientos_disponibles || []).find(mov => mov.slug === slug)
                || (this.detailPokemon?.movimientos_seleccionados_detalle || []).find(mov => mov.slug === slug)
                || null;
        },

        nombreMovimientoLoadout(slug) {
            const mov = this.movimientoLoadout(slug);
            return mov ? mov.name : slug;
        },

        nombreHabilidadLoadout() {
            if (!this.loadoutHabilidadId) return 'Automática (todas las de la especie)';
            const hab = (this.detailPokemon?.habilidades_disponibles || []).find(h => String(h.id) === String(this.loadoutHabilidadId));
            return hab ? hab.name : 'Automática';
        },

        baseExperienceDe(pokemon) {
            if (!pokemon) return null;
            if (typeof pokemon.base_experience === 'number') return pokemon.base_experience;
            if (pokemon.pokemon && typeof pokemon.pokemon.base_experience === 'number') return pokemon.pokemon.base_experience;
            return null;
        },

        tipoLabelDel(t) {
            if (!t) return 'Tipo';
            if (t.tipo_nombre) return t.tipo_nombre;
            if (typeof t.type === 'number') {
                return tipoBadge(t.type).label;
            }
            return t.type || 'Tipo';
        },

        /** Slug del asset (/images/type/{slug}.webp) a partir del tipo serializado. */
        tipoSlugDel(t) {
            if (t?.tipo_slug) return t.tipo_slug;
            return this.normalizarTexto(this.tipoLabelDel(t)).replace(/\s+/g, '');
        },

        tipoIconoDeTipo(t) {
            return '/images/type/' + this.tipoSlugDel(t) + '.webp';
        },

        tipoDeMovimiento(mov) {
            if (!mov) return null;
            if (mov.tipo_nombre) return mov.tipo_nombre;
            if (mov.type_id !== null && mov.type_id !== undefined && mov.type_id !== '') {
                return tipoBadge(Number(mov.type_id)).label;
            }
            if (mov.tipo_slug) {
                return tipoBadge(mov.tipo_slug).label;
            }
            return null;
        },

        tipoSlugDeMovimiento(mov) {
            if (mov?.tipo_slug) return mov.tipo_slug;
            return String(this.tipoDeMovimiento(mov) || 'Normal')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        },

        tipoIconoUrl(mov) {
            return '/images/type/' + this.tipoSlugDeMovimiento(mov) + '.webp';
        },

        categoriaSlug(mov) {
            const categoria = String(mov?.category || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
            return ['fisico', 'especial', 'estado'].includes(categoria) ? categoria : 'estado';
        },

        categoriaIconoUrl(mov) {
            return this.categoriaIconoDe(this.categoriaSlug(mov));
        },

        categoriaIconoDe(categoria) {
            return '/images/moves/' + categoria + '.png';
        },

        etiquetaCategoria(mov) {
            return this.etiquetaCategoriaSlug(this.categoriaSlug(mov));
        },

        etiquetaCategoriaSlug(categoria) {
            return { fisico: 'Físico', especial: 'Especial', estado: 'Estado' }[categoria] ?? categoria;
        },

        claseCategoria(categoria) {
            return {
                fisico: 'bg-primary-soft text-primary-ink',
                especial: 'bg-info-soft text-info-ink',
                estado: 'bg-warning-soft text-warning-ink',
            }[categoria] ?? 'bg-surface-sunken text-ink-muted';
        },

        riesgoClass(riesgo) {
            const clases = {
                'Bajo': 'bg-success-soft text-success-ink',
                'Medio': 'bg-warning-soft text-warning-ink',
                'Alto': 'bg-warning-soft text-warning-ink',
                'Extremo': 'bg-danger-soft text-danger-ink',
            };
            return clases[riesgo] || 'bg-surface-sunken text-ink-muted';
        },

        // ═══════════════════════════════════════════════════════════════════════
        // DETAIL MODAL (shared)
        // ═══════════════════════════════════════════════════════════════════════

        /** Sustituye el reclutado actualizado en la cuadrícula y en los equipos. */
        _actualizarReclutadoEnMemoria(actualizado) {
            const id = actualizado?.id;
            if (id === undefined || id === null) return;

            this.reclutados = this.reclutados.map(reclutado =>
                String(reclutado.id) === String(id) ? { ...reclutado, ...actualizado } : reclutado
            );

            this.teams.forEach(team => {
                (team.members || []).forEach(member => {
                    if (member.reclutado && String(member.reclutado.id) === String(id)) {
                        member.reclutado = { ...member.reclutado, ...actualizado };
                    }
                });
            });
        },

        openMemberDetail(team, slot) {
            const reclutado = this.reclutadoDeSlot(team, slot);
            if (!reclutado) return;
            this.openDetail(reclutado);
        },

        openDetail(pokemon) {
            this.detailPokemon = pokemon;
            this.detailLoading = false;
            this.detailMovimientosLoading = false;
            this.detailMovimientosError = '';
            this.showDetailModal = true;
            this.loadoutHabilidadId = pokemon?.habilidad_activa_id ?? '';
            this.loadoutMovimientos = Array.isArray(pokemon?.movimientos_seleccionados)
                ? [...pokemon.movimientos_seleccionados]
                : [];
            this.filtrosMovimientoTipo = [];
            this.filtrosMovimientoCategoria = [];
            this.filtroNombreMovimiento = '';
            this.loadoutFeedback = { tipo: '', mensaje: '' };
            this.cargarEvoluciones(pokemon);
            this.cargarMovimientosDetalle(pokemon);
            setTimeout(() => this.renderDetailCharts(), 50);
        },

        closeDetail() {
            this.showDetailModal = false;
            this.detailPokemon = null;
            this.detailLoading = false;
            this.detailEvoluciones = [];
            this.detailEvolucionesLoading = false;
            this.detailEvolucionesError = false;
            this.selectedEvolucionId = null;
            this.detailMovimientosLoading = false;
            this.detailMovimientosError = '';
            this.detailEvFeedback = { tipo: '', mensaje: '' };
            this.detailEvsFeeding = false;
            this.detailCandyFeeding = false;
            this.loadoutHabilidadId = '';
            this.loadoutMovimientos = [];
            this.filtrosMovimientoTipo = [];
            this.filtrosMovimientoCategoria = [];
            this.filtroNombreMovimiento = '';
            this.loadoutFeedback = { tipo: '', mensaje: '' };
            this.destroyDetailCharts();
        },

        toggleLoadoutMovimiento(slug) {
            const movimiento = (this.detailPokemon?.movimientos_disponibles || [])
                .find(m => m.slug === slug);
            if (movimiento?.desbloqueado === false) {
                this.loadoutFeedback = {
                    tipo: 'error',
                    mensaje: movimiento.origen === 'senda'
                        ? 'Este movimiento debe desbloquearse en Sendas.'
                        : 'Este movimiento requiere nivel ' + movimiento.nivel_requerido + ' o desbloquearse en Sendas.',
                };
                return;
            }

            if (this.loadoutMovimientos.includes(slug)) {
                this.loadoutMovimientos = this.loadoutMovimientos.filter(s => s !== slug);
                return;
            }
            if (this.loadoutMovimientos.length >= 6) {
                this.loadoutFeedback = { tipo: 'error', mensaje: 'Máximo 6 movimientos.' };
                return;
            }
            this.loadoutMovimientos.push(slug);
        },

        async guardarLoadout() {
            if (!this.detailPokemon || this.loadoutSaving) return;
            this.loadoutSaving = true;
            this.loadoutFeedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/reclutado/' + this.detailPokemon.id + '/loadout', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        habilidad_id: this.loadoutHabilidadId || null,
                        movimientos: this.loadoutMovimientos,
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.loadoutFeedback = {
                        tipo: 'error',
                        mensaje: data.error || data.message || 'No se pudo guardar el loadout.',
                    };
                    return;
                }
                if (data.reclutado) {
                    const idx = this.reclutados.findIndex(r => String(r.id) === String(data.reclutado.id));
                    if (idx >= 0) {
                        this.reclutados[idx] = { ...this.reclutados[idx], ...data.reclutado };
                        this.detailPokemon = this.reclutados[idx];
                    } else {
                        this.detailPokemon = data.reclutado;
                    }
                    this.loadoutHabilidadId = data.reclutado.habilidad_activa_id ?? '';
                    this.loadoutMovimientos = Array.isArray(data.reclutado.movimientos_seleccionados)
                        ? [...data.reclutado.movimientos_seleccionados]
                        : [];
                }
                this.loadoutFeedback = { tipo: 'ok', mensaje: '✓ Loadout guardado' };
            } catch (err) {
                console.error('Error saving loadout:', err);
                this.loadoutFeedback = { tipo: 'error', mensaje: 'Error de conexión al guardar el loadout.' };
            } finally {
                this.loadoutSaving = false;
            }
        },

        async cargarEvoluciones(pokemon) {
            if (!pokemon) return;
            this.detailEvoluciones = [];
            this.detailEvolucionesError = false;
            this.selectedEvolucionId = null;
            this.detailEvolucionesLoading = true;
            try {
                const response = await fetch('/reclutado/' + pokemon.id + '/evoluciones', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    const data = await response.json();
                    this.detailEvoluciones = data.opciones || [];
                    if (this.detailEvoluciones.length === 1) {
                        this.selectedEvolucionId = this.detailEvoluciones[0].pokemon_id;
                    }
                } else {
                    this.detailEvolucionesError = true;
                }
            } catch (err) {
                console.error('Error cargando evoluciones:', err);
                this.detailEvolucionesError = true;
            } finally {
                this.detailEvolucionesLoading = false;
            }
        },

        /**
         * El listado de /equipos ya no incluye el catálogo completo de cada
         * especie. Al abrir el detalle se pide bajo demanda y se cachea en
         * memoria para el resto de la sesión de la página.
         */
        async cargarMovimientosDetalle(pokemon) {
            const id = pokemon?.id;
            if (id === undefined || id === null) return;

            const clave = String(id);
            if (this.movimientosCargadosIds.includes(clave)) return;
            if (Array.isArray(pokemon.movimientos_disponibles) && pokemon.movimientos_disponibles.length > 0) {
                this.movimientosCargadosIds.push(clave);
                return;
            }
            if (this.movimientosCargandoIds.includes(clave)) return;

            this.movimientosCargandoIds.push(clave);
            if (String(this.detailPokemon?.id) === clave) {
                this.detailMovimientosLoading = true;
                this.detailMovimientosError = '';
            }

            try {
                const response = await fetch('/reclutado/' + id + '/movimientos', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'No se pudieron cargar los movimientos.');
                }

                const movimientos = Array.isArray(data.movimientos_disponibles) ? data.movimientos_disponibles : [];
                this.movimientosCargadosIds.push(clave);
                this._actualizarReclutadoEnMemoria({ ...pokemon, movimientos_disponibles: movimientos });

                if (String(this.detailPokemon?.id) === clave) {
                    this.detailPokemon = { ...this.detailPokemon, movimientos_disponibles: movimientos };
                }
            } catch (err) {
                console.error('Error cargando movimientos:', err);
                if (String(this.detailPokemon?.id) === clave) {
                    this.detailMovimientosError = 'No se pudieron cargar los movimientos.';
                }
            } finally {
                this.movimientosCargandoIds = this.movimientosCargandoIds.filter(cargando => cargando !== clave);
                if (String(this.detailPokemon?.id) === clave) {
                    this.detailMovimientosLoading = false;
                }
            }
        },

        opcionSeleccionada() {
            if (!this.selectedEvolucionId || this.detailEvoluciones.length === 0) return null;
            return this.detailEvoluciones.find(o => o.pokemon_id === this.selectedEvolucionId) || null;
        },

        // ═══════════════════════════════════════════════════════════════════════
        // DETAIL: IVs / EVs / HABILIDADES
        // ═══════════════════════════════════════════════════════════════════════

        /** Clave corta del backend (naming candy_ev: atksp/defsp/spd) para tolerar alias. */
        slugCorto(key) {
            const mapa = { hp: 'hp', atk: 'atk', def: 'def', spAtk: 'atksp', spDef: 'defsp', speed: 'spd' };
            return mapa[key] || key;
        },

        habilidadesDe(pokemon) {
            if (!pokemon) return [];
            const fuente = pokemon.habilidades || pokemon.abilities || [];
            const lista = Array.isArray(fuente) ? fuente : Object.values(fuente || {});
            return lista.map(h => {
                if (typeof h === 'string') return h;
                if (h && (h.nombre || h.name)) return h.nombre || h.name;
                return Object.values(h || {})[0] || '';
            }).filter(Boolean);
        },

        movimientosDe(pokemon) {
            if (!pokemon) return [];
            const fuente = pokemon.movimientos_pokemon || pokemon.movimientos || pokemon.moves || [];
            const lista = Array.isArray(fuente) ? fuente : Object.values(fuente || []);
            return lista.map(m => {
                if (typeof m === 'string') return m;
                if (m && (m.nombre || m.name)) return m.nombre || m.name;
                return '';
            }).filter(Boolean);
        },

        ivsDe(pokemon) {
            return pokemon?.ivs || pokemon?.iv || {};
        },

        ivValue(pokemon, key) {
            const ivs = this.ivsDe(pokemon);
            return ivs[key] ?? ivs[this.slugCorto(key)] ?? 0;
        },

        evsDe(pokemon) {
            return pokemon?.evs || pokemon?.ev || {};
        },

        evValue(pokemon, key) {
            const evs = this.evsDe(pokemon);
            return evs[key] ?? evs[this.slugCorto(key)] ?? 0;
        },

        evPct(value) {
            return Math.max(0, Math.min(100, (Number(value || 0) / 255) * 100));
        },

        evCaramelosDe(pokemon, key) {
            const inventario = (this.inventarioCaramelos?.ev || []).find(item => item.key === key);
            if (inventario) return inventario.cantidad;

            const mapa = pokemon?.caramelos_ev;
            if (mapa && typeof mapa === 'object') {
                return Number(mapa[key] ?? mapa[this.slugCorto(key)] ?? 0);
            }

            return 0;
        },

        actualizarInventarioEv(key, cantidad) {
            const item = (this.inventarioCaramelos?.ev || []).find(caramelo => caramelo.key === key);
            if (item) item.cantidad = Math.max(0, Number(cantidad) || 0);
        },

        puedeDarEv(pokemon, key) {
            if (!pokemon || this._pokemonBloqueado(pokemon)) return false;
            if (this.detailEvsFeeding) return false;
            if (this.evValue(pokemon, key) >= 255) return false;
            if (this.evCaramelosDe(pokemon, key) <= 0) return false;
            return true;
        },

        async darEv(key, cantidad = 1) {
            const pokemon = this.detailPokemon;
            if (!pokemon || !this.puedeDarEv(pokemon, key)) return;
            const cantidadEnvio = Math.max(1, Number(cantidad) || 1);
            const valorAnterior = this.evValue(pokemon, key);
            this.detailEvsFeeding = true;
            this.detailEvFeedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/reclutado/' + pokemon.id + '/dar-evs', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ stat: key, cantidad: cantidadEnvio }),
                });
                if (response.ok) {
                    const data = await response.json();
                    const nivelado = data.evs ?? data.ev ?? (data.actual !== undefined ? { [key]: data.actual } : null);
                    if (nivelado && typeof nivelado === 'object') {
                        const evs = { ...this.evsDe(pokemon), ...nivelado };
                        pokemon.evs = evs;
                    }
                    if (data.stats_calculados && typeof data.stats_calculados === 'object') {
                        pokemon.stats_calculados = data.stats_calculados;
                    }
                    if (data.caramelos_ev && typeof data.caramelos_ev === 'object') {
                        pokemon.caramelos_ev = data.caramelos_ev;
                    }
                    const restantes = Number(data.caramelos_disponibles);
                    if (Number.isFinite(restantes)) {
                        pokemon.caramelos_ev = { ...(pokemon.caramelos_ev || {}), [key]: restantes };
                        this.actualizarInventarioEv(key, restantes);
                    }
                    const label = (this.evStats.find(s => s.key === key) || {}).label || key;
                    const ganados = Math.max(0, Number(data.actual ?? this.evValue(pokemon, key)) - valorAnterior);
                    this.detailEvFeedback = { tipo: 'success', mensaje: '✓ ¡+' + ganados + ' EV para ' + label + '!' };
                    setTimeout(() => this.renderDetailCharts(), 0);
                } else {
                    let message = 'No se pudo dar el caramelo EV.';
                    try {
                        const data = await response.json();
                        message = data.error || data.message || message;
                    } catch (err) {}
                    this.detailEvFeedback = { tipo: 'error', mensaje: message };
                }
            } catch (err) {
                console.error('Error dando caramelo EV:', err);
                this.detailEvFeedback = { tipo: 'error', mensaje: 'Error de conexión al dar el caramelo EV.' };
            } finally {
                this.detailEvsFeeding = false;
            }
        },

        // ═══════════════════════════════════════════════════════════════════════
        // HUEVOS TAB
        // ═══════════════════════════════════════════════════════════════════════

        cambiarSeccion(seccion) {
            if (this.activeSeccion === seccion) return;
            this.activeSeccion = seccion;
            if (seccion === 'huevos') {
                this.huevosFeedback = { tipo: '', mensaje: '' };
                this.cargarHuevos();
            }
        },

        async cargarHuevos(force = false) {
            if (this.huevosLoading) return;
            if (!force && this.huevosLoaded) return;
            this.huevosLoading = true;
            this.huevosError = '';
            try {
                const response = await fetch('/api/huevos', {
                    headers: { 'Accept': 'application/json' },
                });
                if (response.status === 404) {
                    // Módulo backend aún no publicado: avisar sin romper la página.
                    this.huevosError = 'El módulo de huevos está en preparación.';
                    this.huevos = [];
                    this.huevosDisponibles = [];
                    this.huevosLimites = null;
                    this.huevosLoaded = false;
                    return;
                }
                if (!response.ok) throw new Error('No se pudieron cargar los huevos');
                const data = await response.json();
                this.huevos = Array.isArray(data.huevos) ? data.huevos : [];
                this.huevosDisponibles = Array.isArray(data.disponibles) ? data.disponibles : [];
                this.huevosLimites = data.limites || null;
                this.huevosLoaded = true;
            } catch (e) {
                console.error('Error cargando huevos:', e);
                this.huevosError = 'No se pudieron cargar los huevos.';
                this.huevosLoaded = false;
            } finally {
                this.huevosLoading = false;
            }
        },

        gestacionActiva() {
            return this.huevos.some(h => h.estado === 'gestando');
        },

        incubacionActiva() {
            return this.huevos.some(h => h.estado === 'incubando' || h.estado === 'listo');
        },

        limiteExcedido() {
            return this.gestacionActiva() || this.incubacionActiva();
        },

        limiteMensaje() {
            const gestando = this.gestacionActiva();
            const incubando = this.incubacionActiva();
            if (gestando && incubando) return 'Ya tienes 1 huevo gestando y 1 en incubación: completa uno antes de poner otro.';
            if (gestando) return 'Ya tienes 1 huevo gestando: espera a que termine antes de poner otro.';
            if (incubando) return 'Ya tienes 1 huevo incubando: espera a que eclosione antes de poner otro.';
            return '';
        },

        puedePonerHuevo() {
            return !this.huevosActuando && !this.limiteExcedido();
        },

        async ponerHuevo(evolutionChainId) {
            if (!evolutionChainId || !this.puedePonerHuevo()) return;
            this.huevosActuando = true;
            this.huevosFeedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/api/huevos', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ evolution_chain_id: evolutionChainId }),
                });
                if (response.ok) {
                    const data = await response.json();
                    this.huevosFeedback = { tipo: 'success', mensaje: data.message || 'El huevo ha empezado a gestarse.' };
                    await this.cargarHuevos(true);
                } else {
                    let message = 'No se pudo poner el huevo.';
                    try {
                        const data = await response.json();
                        message = data.error || data.message || message;
                    } catch (err) {}
                    this.huevosFeedback = { tipo: 'error', mensaje: message };
                }
            } catch (err) {
                console.error('Error poniendo huevo:', err);
                this.huevosFeedback = { tipo: 'error', mensaje: 'Error de conexión al poner el huevo.' };
            } finally {
                this.huevosActuando = false;
            }
        },

        async eclosionarHuevo(huevo) {
            if (!huevo || !huevo.puede_eclosionar || this.huevosActuando) return;
            this.huevosActuando = true;
            this.huevosAccionId = huevo.id;
            this.huevosFeedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/api/huevos/' + huevo.id + '/eclosionar', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    const data = await response.json();
                    const nacido = data.pokemon?.name || data.nombre || data.pokemon_name || '';
                    this.huevosFeedback = { tipo: 'success', mensaje: '¡Eclosionó' + (nacido ? ' un ' + nacido : '') + '!' };
                    await this.cargarHuevos(true);
                } else {
                    let message = 'No se pudo eclosionar el huevo.';
                    try {
                        const data = await response.json();
                        message = data.error || data.message || message;
                    } catch (err) {}
                    this.huevosFeedback = { tipo: 'error', mensaje: message };
                }
            } catch (err) {
                console.error('Error eclosionando huevo:', err);
                this.huevosFeedback = { tipo: 'error', mensaje: 'Error de conexión al eclosionar el huevo.' };
            } finally {
                this.huevosActuando = false;
                this.huevosAccionId = null;
            }
        },

        huevoProgreso(huevo) {
            // Contrato backend: progreso 0-100 ya calculado.
            return Math.max(0, Math.min(100, Number(huevo?.progreso ?? 0)));
        },

        huevoEtiquetaRestante(huevo) {
            if (huevo?.puede_eclosionar) return '¡Listo!';
            const horas = Number(huevo?.tiempo_restante_horas ?? huevo?.tiempo_restante ?? 0);
            return this.formatearDuracion(horas);
        },

        formatearDuracion(horas) {
            const h = Number(horas || 0);
            if (h <= 0) return 'Listo';
            if (h >= 24) {
                const d = Math.floor(h / 24);
                const rest = Math.round(h % 24);
                return d + 'd' + (rest > 0 ? ' ' + rest + 'h' : '');
            }
            const hh = Math.floor(h);
            const mm = Math.round((h - hh) * 60);
            return (hh > 0 ? hh + 'h ' : '') + mm + 'm';
        },

        estadoHuevo(huevo) {
            const mapa = {
                gestando: { texto: 'Gestando', clase: 'bg-warning-soft text-warning-ink' },
                incubando: { texto: 'Incubando', clase: 'bg-primary-soft text-primary-ink' },
                listo: { texto: '¡Listo!', clase: 'bg-success-soft text-success-ink' },
            };
            return mapa[huevo?.estado] || { texto: huevo?.estado || 'Huevo', clase: 'bg-surface-sunken text-ink-muted' };
        },

        // ═══════════════════════════════════════════════════════════════════════
        // EQUIPOS LOGIC
        // ═══════════════════════════════════════════════════════════════════════

        getMember(team, slot) {
            const member = team.members.find(m => m.slot === slot);
            return member ? member.reclutado : null;
        },

        /** Reclutado enriquecido del serializador principal para un slot concreto. */
        reclutadoDeSlot(team, slot) {
            const member = team?.members?.find(m => String(m.slot) === String(slot));
            if (!member) return null;
            return this.reclutados.find(r => String(r.id) === String(member.pokemon_id)) || member.reclutado;
        },

        getTeamName(teamId) {
            const team = this.teams.find(t => t.id === teamId);
            return team ? team.name : '';
        },

        composicionBadge(team) {
            if (!team.members || team.members.length !== 3) return null;
            const nombre = team.sinergia_nombre;
            if (typeof nombre === 'string' && nombre.trim()) {
                return {
                    texto: 'Composición: ' + nombre,
                    clase: 'bg-success-soft text-success-ink',
                };
            }
            return {
                texto: 'Composición neutra',
                clase: 'bg-surface-sunken text-ink-muted',
            };
        },

        pokemonTeam(pokemon) {
            if (!pokemon) return null;
            return this.teams.find(t => t.members.some(m => String(m.pokemon_id) === String(pokemon.id))) || null;
        },

        pokemonTeamId(pokemon) {
            return this.pokemonTeam(pokemon)?.id ?? null;
        },

        pokemonTeamNombre(pokemon) {
            return this.pokemonTeam(pokemon)?.name ?? '';
        },

        pokemonEnExploracion(pokemon) {
            if (!pokemon) return false;
            return this.enExploracion(pokemon.id);
        },

        // ─── Asignación / reemplazo en equipos completos ────────────────────

        selectTeamForPokemon(pokemon, team) {
            if (!pokemon || !team) return;
            if (team.members.length >= 3) {
                this.abrirReemplazo(pokemon, team);
                return;
            }
            this.addToTeam(pokemon, team);
        },

        abrirReemplazo(pokemon, team) {
            this.replacePokemon = pokemon;
            this.replaceTeam = team;
            this.replaceError = '';
            this.replaceSaving = false;
            this.showReplaceModal = true;
        },

        cerrarReemplazo() {
            this.showReplaceModal = false;
            this.replacePokemon = null;
            this.replaceTeam = null;
            this.replaceSaving = false;
            this.replaceError = '';
        },

        async reemplazarMiembroEquipo(slot) {
            const team = this.replaceTeam;
            const pokemon = this.replacePokemon;
            const member = team ? team.members.find(m => String(m.slot) === String(slot)) : null;
            if (!team || !pokemon || !member || this.replaceSaving) return;

            this.replaceSaving = true;
            this.replaceError = '';

            try {
                const response = await fetch('/teams/add-member', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        team_id: team.id,
                        reclutado_id: pokemon.id,
                        slot: member.slot,
                        behavior: member.behavior || 'VANGUARDIA',
                        replace: true,
                    }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.replaceError = data.error || data.message || 'No se pudo reemplazar el miembro del equipo.';
                    return;
                }

                if (data.member) {
                    team.members = team.members.filter(m => String(m.id) !== String(member.id));
                    team.members.push({
                        id: data.member.id,
                        team_id: data.member.team_id,
                        pokemon_id: data.member.pokemon_id,
                        slot: data.member.slot,
                        behavior: member.behavior || 'VANGUARDIA',
                        reclutado: pokemon,
                    });
                    this.teamPokemonIds = this.teamPokemonIds.filter(id => String(id) !== String(member.pokemon_id));
                    this.teamPokemonIds.push(pokemon.id);
                }

                this.cerrarReemplazo();
            } catch (err) {
                console.error('Error replacing team member:', err);
                this.replaceError = 'Error de conexión al reemplazar el miembro del equipo.';
            } finally {
                this.replaceSaving = false;
            }
        },

        async createTeam() {
            if (!this.newTeamName.trim()) return;
            try {
                const response = await fetch('/teams', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name: this.newTeamName.trim() }),
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.team) {
                        this.teams.push({ ...data.team, members: [] });
                        this.showNewTeamForm = false;
                        this.newTeamName = '';
                    }
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error creating team:', err);
            }
        },

        confirmDeleteTeam(team) {
            this.teamToDelete = team;
            this.showDeleteModal = true;
        },

        async deleteTeam() {
            if (!this.teamToDelete) return;
            try {
                const response = await fetch('/teams/' + this.teamToDelete.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    this.teamToDelete.members.forEach(m => {
                        if (m.pokemon_id) {
                            this.teamPokemonIds = this.teamPokemonIds.filter(id => id !== m.pokemon_id);
                        }
                    });
                    this.teams = this.teams.filter(t => t.id !== this.teamToDelete.id);
                    this.showDeleteModal = false;
                    this.teamToDelete = null;
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error deleting team:', err);
            }
        },

        startRename(team) {
            const newName = prompt('Nuevo nombre del equipo:', team.name);
            if (newName && newName.trim() && newName.trim() !== team.name) {
                fetch('/teams/' + team.id, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name: newName.trim() }),
                }).then(r => r.json()).then(data => {
                    if (data.team) {
                        team.name = data.team.name;
                    } else if (data.error) {
                        alert(data.error);
                    }
                }).catch(err => console.error('Error renaming:', err));
            }
        },

        async addToTeam(pokemon, team) {
            if (!pokemon || !team) return;

            const emptySlot = [1, 2, 3].find(s => !team.members.some(m => Number(m.slot) === s));
            if (!emptySlot) {
                this.abrirReemplazo(pokemon, team);
                return;
            }
            try {
                const response = await fetch('/teams/add-member', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        team_id: team.id,
                        reclutado_id: pokemon.id,
                        slot: emptySlot,
                        behavior: 'VANGUARDIA',
                    }),
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.member) {
                        const t = this.teams.find(t => String(t.id) === String(data.member.team_id));
                        if (t) {
                            t.members.push({
                                id: data.member.id,
                                team_id: data.member.team_id,
                                pokemon_id: data.member.pokemon_id,
                                slot: data.member.slot,
                                behavior: data.member.behavior || 'VANGUARDIA',
                                reclutado: pokemon,
                            });
                        }
                        if (!this.teamPokemonIds.some(id => String(id) === String(pokemon.id))) {
                            this.teamPokemonIds.push(pokemon.id);
                        }
                    }
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error adding member:', err);
            }
        },

        async removeMember(team, pokemon) {
            const member = team.members.find(m => String(m.pokemon_id) === String(pokemon.id));
            if (!member) return;
            try {
                const response = await fetch('/teams/remove-member', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ member_id: member.id }),
                });
                if (response.ok) {
                    team.members = team.members.filter(m => String(m.id) !== String(member.id));
                    this.teamPokemonIds = this.teamPokemonIds.filter(id => String(id) !== String(pokemon.id));
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error removing member:', err);
            }
        },

        removeFromTeam(pokemon) {
            const team = this.pokemonTeam(pokemon);
            if (team) {
                this.removeMember(team, pokemon);
            }
        },

        // ─── DEPRECATED: funciones de favoritos (UI eliminada; backend conservado) ─
        // toggleFavorito, openFavoritosModal, closeFavoritosModal eliminadas.

        async updateMemberRole(team, member, behavior) {
            // DEPRECATED: reemplazado por radios de formación vanguardia/retaguardia.
            // Se conserva por si código legacy lo llama; puede eliminarse en limpieza futura.
            if (!member || !behavior) return;
            const reclutadoId = member.reclutado?.id ?? member.pokemon_id;
            if (!reclutadoId) return;
            try {
                const response = await fetch('/api/reclutado/' + reclutadoId + '/rol', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ behavior }),
                });
                if (response.ok) {
                    location.reload();
                } else {
                    const body = await response.json().catch(() => ({}));
                    alert(body.message || body.error || 'No se pudo actualizar el rol.');
                }
            } catch (err) {
                console.error('Error updating reclutado role:', err);
                alert('Error de conexión al actualizar el rol.');
            }
        },

        rolInicialDe(member) {
            // DEPRECATED: reemplazado por formacionDe(team, slot).
            // Se conserva por si código legacy lo llama; puede eliminarse en limpieza futura.
            if (!member) return 'VANGUARDIA';
            return member.reclutado?.behavior || member.behavior || 'VANGUARDIA';
        },

        // ─── Formación persistente (combate 3v3) ─────────────────────────────

        /** Posición actual del slot: draft local > formación persistida > 'vanguardia'. */
        formacionDe(team, slot) {
            return this.formacionDraft[team.id]?.[slot]
                || team.formacion?.[slot]
                || 'vanguardia';
        },

        setFormacionSlot(team, slot, posicion) {
            if (!this.formacionDraft[team.id]) this.formacionDraft[team.id] = {};
            this.formacionDraft[team.id][slot] = posicion;
            this.formacionSaveError = '';
            this.formacionSaveSuccess = '';
        },

        async guardarFormacion(team) {
            const base = team.formacion || {};
            const draft = this.formacionDraft[team.id] || {};
            const formacionCompleta = {};
            for (let slot = 1; slot <= 3; slot++) {
                if (!this.getMember(team, slot)) continue;
                formacionCompleta[slot] = draft[slot] || base[slot] || 'vanguardia';
            }
            if (Object.keys(formacionCompleta).length === 0) {
                alert('No hay cambios en la formación para guardar.');
                return;
            }
            this.formacionSavingTeamId = team.id;
            this.formacionSaveError = '';
            this.formacionSaveSuccess = '';
            try {
                const response = await fetch('/teams/' + team.id + '/formacion', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ formacion: formacionCompleta }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.formacionSaveError = data.error || data.message || 'No se pudo guardar la formación.';
                    return;
                }
                if (data.team) {
                    // Contrato: { team: { id, formacion: {slot: pos} } }.
                    this.formacionDraft[team.id] = {};
                    team.formacion = data.team.formacion || {};
                    this.formacionSaveSuccess = '✓ Formación guardada';
                    setTimeout(() => {
                        this.formacionSaveSuccess = '';
                    }, 3000);
                }
            } catch (err) {
                console.error('Error saving formation:', err);
                this.formacionSaveError = 'Error de conexión al guardar la formación.';
            } finally {
                this.formacionSavingTeamId = null;
            }
        },

        // ─── Objetos equipados (combate 3v3) ─────────────────────────────────

        itemDe(team, slot) {
            const draft = this.itemsDraft[team.id]?.[slot];
            if (draft !== undefined) return draft;

            const member = this.getMember(team, slot);
            const items = member?.obj_equipados;
            const item = Array.isArray(items) ? items[0] : '';

            return typeof item === 'string' ? item : '';
        },

        async guardarItemSlot(team, slot, itemKey) {
            if (!this.itemsDraft[team.id]) this.itemsDraft[team.id] = {};
            this.itemsDraft[team.id][slot] = itemKey;
            this.itemSavingTeamId = team.id;
            this.itemSaveError = '';
            this.itemSaveSuccess = '';

            try {
                const response = await fetch('/teams/' + team.id + '/items', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ items: { [slot]: itemKey } }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.itemSaveError = data.error || data.message || 'No se pudo guardar el objeto.';
                    return;
                }

                const member = this.getMember(team, slot);
                if (member) {
                    member.obj_equipados = itemKey !== '' ? [itemKey] : [];
                }
                this.itemsDraft[team.id] = {};
                this.itemSaveSuccess = '✓ Objeto guardado';
                setTimeout(() => {
                    this.itemSaveSuccess = '';
                }, 2500);
            } catch (err) {
                console.error('Error saving item:', err);
                this.itemSaveError = 'Error de conexión al guardar el objeto.';
            } finally {
                this.itemSavingTeamId = null;
            }
        },

        // ─── Posturas (combate 3v3) ──────────────────────────────────────────

        /** Postura del slot: draft local > postura persistida > 'atacar'. */
        posturaDe(team, slot) {
            return this.posturasDraft[team.id]?.[slot]
                || team.posturas?.[slot]
                || 'atacar';
        },

        setPosturaSlot(team, slot, postura) {
            if (!this.posturasDraft[team.id]) this.posturasDraft[team.id] = {};
            this.posturasDraft[team.id][slot] = postura;
            this.posturaSaveError = '';
            this.posturaSaveSuccess = '';
        },

        async guardarPosturas(team) {
            const base = team.posturas || {};
            const draft = this.posturasDraft[team.id] || {};
            const posturasCompletas = {};
            for (let slot = 1; slot <= 3; slot++) {
                if (!this.getMember(team, slot)) continue;
                posturasCompletas[slot] = draft[slot] || base[slot] || 'atacar';
            }

            if (Object.keys(posturasCompletas).length === 0) {
                alert('No hay posturas para guardar.');
                return;
            }

            this.posturaSavingTeamId = team.id;
            this.posturaSaveError = '';
            this.posturaSaveSuccess = '';

            try {
                const response = await fetch('/teams/' + team.id + '/posturas', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ posturas: posturasCompletas }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.posturaSaveError = data.error || data.message || 'No se pudieron guardar las posturas.';
                    return;
                }
                if (data.team) {
                    // Contrato: { team: { id, posturas: {slot: postura} } }.
                    this.posturasDraft[team.id] = {};
                    team.posturas = data.team.posturas || {};
                    this.posturaSaveSuccess = '✓ Posturas guardadas';
                    setTimeout(() => {
                        this.posturaSaveSuccess = '';
                    }, 3000);
                }
            } catch (err) {
                console.error('Error saving postures:', err);
                this.posturaSaveError = 'Error de conexión al guardar las posturas.';
            } finally {
                this.posturaSavingTeamId = null;
            }
        },

        // ═══════════════════════════════════════════════════════════════════════
        // EVOLVE / RELEASE (shared — respects both individual & team exploration)
        // ═══════════════════════════════════════════════════════════════════════

        _pokemonBloqueado(pokemon) {
            if (!pokemon) return true;
            return this.enExploracion(pokemon.id) || this.pokemonEnExploracion(pokemon);
        },

        puedeAlimentar(requisito) {
            if (!requisito) return false;
            if (this._pokemonBloqueado(this.detailPokemon)) return false;
            if (requisito.actual >= requisito.necesario) return false;
            if (requisito.caramelosDisponibles <= 0) return false;
            return true;
        },

        async alimentarCaramelo(requisito, cantidad = 1) {
            const pokemon = this.detailPokemon;
            if (!pokemon || !requisito || !this.puedeAlimentar(requisito) || this.detailCandyFeeding) return;

            const cantidadEnvio = Math.max(1, Number(cantidad) || 1);
            this.detailCandyFeeding = true;
            try {
                const response = await fetch('/reclutado/' + pokemon.id + '/dar-caramelo', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tipo: requisito.tipo,
                        evolved_species_id: this.selectedEvolucionId,
                        cantidad: cantidadEnvio,
                    }),
                });
                if (response.ok) {
                    const data = await response.json();
                    requisito.actual = data.actual;
                    requisito.caramelosDisponibles = data.caramelos_disponibles;
                    const opcion = this.opcionSeleccionada();
                    if (opcion && typeof data.puede_evolucionar === 'boolean') {
                        opcion.puede_evolucionar = data.puede_evolucionar;
                    }
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error dando caramelo:', err);
            } finally {
                this.detailCandyFeeding = false;
            }
        },

        async evolvePokemon() {
            const pokemon = this.detailPokemon;
            if (!pokemon || this._pokemonBloqueado(pokemon)) return;
            if (this.detailEvoluciones.length > 1 && !this.selectedEvolucionId) {
                alert('Selecciona a qué pokémon evolucionar');
                return;
            }
            this.detailLoading = true;
            try {
                const body = this.selectedEvolucionId ? { evolved_species_id: this.selectedEvolucionId } : {};
                const response = await fetch('/reclutado/' + pokemon.id + '/evolucionar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.pokemon_id) {
                        pokemon.pokemon_id = data.pokemon_id;
                    }

                    if (data.reclutado) {
                        this._actualizarReclutadoEnMemoria(data.reclutado);
                        this.detailPokemon = this.reclutados.find(r => String(r.id) === String(data.reclutado.id)) || data.reclutado;
                        this.loadoutHabilidadId = this.detailPokemon.habilidad_activa_id ?? '';
                        this.loadoutMovimientos = Array.isArray(this.detailPokemon.movimientos_seleccionados)
                            ? [...this.detailPokemon.movimientos_seleccionados]
                            : [];
                        this.filtrosMovimientoTipo = [];
                        this.filtrosMovimientoCategoria = [];
                        this.filtroNombreMovimiento = '';
                        this.loadoutFeedback = { tipo: '', mensaje: '' };
                        this.detailEvFeedback = { tipo: '', mensaje: '' };
                        this.cargarEvoluciones(this.detailPokemon);
                        setTimeout(() => this.renderDetailCharts(), 50);
                        return;
                    }

                    location.reload();
                    return;
                }
                await this.handleError(response);
            } catch (err) {
                console.error('Error evolving pokemon:', err);
            } finally {
                this.detailLoading = false;
            }
        },

        async releasePokemon() {
            const pokemon = this.detailPokemon;
            if (!pokemon || this._pokemonBloqueado(pokemon)) return;
            if (!confirm('¿Seguro que quieres liberar a ' + (pokemon.nombre || 'este Pokémon') + '? Esta acción no se puede deshacer.')) return;
            this.detailLoading = true;
            try {
                const response = await fetch('/reclutado/' + pokemon.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data && data.success !== false) {
                        this.reclutados = this.reclutados.filter(r => r.id !== pokemon.id);
                        this.teamPokemonIds = this.teamPokemonIds.filter(id => id !== pokemon.id);
                        this.teams.forEach(t => {
                            t.members = t.members.filter(m => m.pokemon_id !== pokemon.id);
                        });
                        this.closeDetail();
                        return;
                    }
                    alert('No se pudo liberar el Pokémon');
                    return;
                }
                let message = 'Error al liberar el Pokémon';
                try {
                    const data = await response.json();
                    message = data.error || data.message || message;
                } catch (err) {}
                alert(message);
            } catch (err) {
                console.error('Error releasing pokemon:', err);
            } finally {
                this.detailLoading = false;
            }
        },

        async handleError(response) {
            if (response.status === 422) {
                let message = 'Error de validación';
                try {
                    const data = await response.json();
                    message = data.error || data.message || message;
                } catch (err) {}
                alert(message);
            }
        },

        // ═══════════════════════════════════════════════════════════════════════
        // EXPLORATION MODAL
        // ═══════════════════════════════════════════════════════════════════════

        async openExploracionModal(pokemon) {
            this.exploracionPokemon = pokemon;
            this.exploracionHabitatId = null;
            this.exploracionLevel = 1;
            this.durationMode = 'hours';
            this.durationHours = 4;
            this.returnTime = '18:00';
            this.preview = null;
            this.previewLoaded = false;
            this.previewError = '';
            this.sendingExploracion = false;
            this.showExploracionModal = true;

            if (this.habitats.length === 0 && !this.habitatsLoading && !this.habitatsError) {
                await this.loadHabitats();
            }
            if (this.exploracionHabitatId && this.exploracionLevel) {
                this.loadPreview();
            }
        },

        closeExploracionModal() {
            this.showExploracionModal = false;
            this.exploracionPokemon = null;
        },

        async loadHabitats() {
            this.habitatsLoading = true;
            this.habitatsError = '';
            try {
                const response = await fetch('/datagrid/habitat?per_page=200', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('No se pudieron cargar los hábitats');
                const data = await response.json();
                this.habitats = data.data || [];
                if (this.habitats.length === 0) {
                    this.habitatsError = 'No hay hábitats disponibles.';
                }
            } catch (e) {
                console.error('Error loading habitats:', e);
                this.habitatsError = 'Función en preparación.';
                this.habitats = [];
            } finally {
                this.habitatsLoading = false;
            }
        },

        async loadPreview() {
            if (!this.exploracionPokemon || !this.exploracionHabitatId || !this.exploracionLevel) return;
            this.previewLoading = true;
            this.previewLoaded = false;
            this.previewError = '';
            this.preview = null;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const params = new URLSearchParams({
                    reclutado_id: this.exploracionPokemon.id,
                    habitat_id: this.exploracionHabitatId,
                    level: this.exploracionLevel,
                });
                const response = await fetch('/exploraciones/preview?' + params.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'No se pudo calcular la preparación de la expedición.');
                }
                this.preview = await response.json();
                this.previewLoaded = true;
            } catch (e) {
                this.previewError = e.message || 'Error al calcular la preparación de la expedición.';
            } finally {
                this.previewLoading = false;
            }
        },

        async confirmExploracion() {
            if (!this.exploracionPokemon || !this.exploracionHabitatId || this.sendingExploracion) return;
            if (this.enExploracion(this.exploracionPokemon.id)) {
                alert('Este Pokémon ya está en una exploración activa.');
                this.closeExploracionModal();
                return;
            }
            this.sendingExploracion = true;
            try {
                const body = {
                    reclutado_id: this.exploracionPokemon.id,
                    habitat_id: this.exploracionHabitatId,
                    level: this.exploracionLevel,
                };
                if (this.durationMode === 'hours') {
                    body.duracion_horas = this.durationHours;
                } else if (this.durationMode === 'return_time') {
                    body.return_time = this.returnTime;
                }
                const response = await fetch('/api/exploraciones/store-individual', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                if (response.ok) {
                    location.reload();
                } else {
                    const data = await response.json().catch(() => ({}));
                    alert(data.message || 'No se pudo enviar la exploración.');
                }
            } catch (err) {
                console.error('Error sending exploration:', err);
                alert('Error de conexión al enviar la exploración.');
            } finally {
                this.sendingExploracion = false;
            }
        },
    };
}

</script>
@endpush
@endsection
