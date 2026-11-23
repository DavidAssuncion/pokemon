@extends('layouts.app')

@section('title', 'Hábitat - ' . ($habitat['name'] ?? ''))

@section('content')
<div x-data="habitatShow()" x-init="init()">
        <!-- Main: Left 1/5 (back/image/construction) + Right 4/5 (explorations/teams/levels) -->
        <div class="grid lg:grid-cols-5 gap-6">
            @include('habitats.partials.columna-izquierda')

            @include('habitats.partials.columna-derecha')
        </div>

    @include('habitats.partials.modal-exploracion')

    @include('habitats.partials.modal-gestion-favoritos')

    @include('habitats.partials.modal-formacion')

    @include('habitats.partials.modal-gestion-admin')

    @include('habitats.partials.modal-mazmorra')

    @include('habitats.partials.modal-granja')

    @include('habitats.partials.modal-baya')
</div>

@push('scripts')
@include('partials.tipos-badge-script')
@include('partials.comparacion-pc-script')
<script>
function habitatShow() {
    return {
        ...comparadorPcState(),
        habitatId: {{ (int) ($habitat['id'] ?? 0) }},
        selectedTeamId: null,
        selectedTeamName: '',
        selectedLevel: null,
        showExplorationModal: false,
        modoExploracion: 'experiencia',
        durationMode: 'hours',
        durationHours: 4,
        returnTime: '18:00',
        // Preview de la expedición (riesgo)
        preview: null,
        previewLoading: false,
        previewLoaded: false,
        previewError: '',
        sightedPokemonIds: @json($sightedPokemonIds ?? []),
        // TEMPORAL: modo QA, muestra todos los Pokémon del hábitat aunque no estén avistados.
        unlockAll: @json($unlockAll ?? false),
        teams: @json($teams),
        // Manifiesto de sprites de entrenador (clases, gimnasios y hábitats).
        sprites: @json($sprites ?? []),
        // Favoritos (exploración individual): se cargan desde /api/reclutados/favoritos?habitat_id={id}.
        favoritos: [],
        favoritosLoading: false,
        favoritosError: '',
        selectedFavoritoId: null,
        selectedFavoritoName: '',
        // Gestión de favoritos del hábitat
        showGestionFavoritosModal: false,
        gestionFavoritosLoading: false,
        gestionFavoritosError: '',
        allReclutados: [],
        reclutadosEnExploracion: @json($reclutadosEnExploracion ?? ($exploracionesActivas ? $exploracionesActivas->pluck('reclutado_id')->all() : [])),
        minLvls: {
            1: {{ $habitat['min_lvl_1'] ?? 'null' }},
            2: {{ $habitat['min_lvl_2'] ?? 'null' }},
            3: {{ $habitat['min_lvl_3'] ?? 'null' }},
        },
        levelsCount: {
            1: {{ count($habitat['levels'][1] ?? []) }},
            2: {{ count($habitat['levels'][2] ?? []) }},
            3: {{ count($habitat['levels'][3] ?? []) }},
        },
        nivelJugador: {{ $nivelJugador ?? 1 }},
        avisoNivel: '',

        // ─── Entrenadores mode ────────────────────────────
        modo: 'ruta',
        trainers: null,
        trainersLoading: false,
        selectedTrainer: null,
        showFormacionPopup: false,
        formacion: {},
        formacionContexto: 'entrenadores',

        // ─── Ruta mode (combate 3v3; default) ─────────────
        rutaNivel: 1,
        rutaCombatiendo: false,
        rutaCombatError: '',

        get selectedTeamMembers() {
            if (!this.selectedTeamId || !this.teams) return [];
            const team = this.teams.find(t => t.id === this.selectedTeamId);
            if (!team || !team.members) return [];
            return team.members.sort((a, b) => a.slot - b.slot).filter(m => m.reclutado && m.reclutado.pokemon);
        },

        /** Slug de la tarjeta de entrenador: 1 = clase base, 2-3 = variantes fijas. */
        spriteEntrenador(indice) {
            const habitats = (this.sprites && this.sprites.habitats) ? this.sprites.habitats : {};
            const habitat = habitats[this.habitatId] || null;
            if (!habitat) return '';
            if (indice === 1) return habitat.clase || '';
            const variantes = habitat.variantes || [];
            return variantes[indice - 2] || habitat.clase || '';
        },

        /** URL del sprite de entrenador; silueta genérica si falta el slug. */
        spriteUrl(slug) {
            return slug ? '/images/trainers/' + slug + '.png' : '/images/misc/trainer.webp';
        },

        /** Nombre visible de la clase según el manifiesto; vacío si no existe. */
        spriteAlt(slug) {
            const clases = (this.sprites && this.sprites.clases) ? this.sprites.clases : {};
            return (slug && clases[slug]) ? clases[slug] : '';
        },

        async toggleEntrenadores() {
            if (this.modo === 'entrenadores') {
                this.modo = 'ruta';
                this.selectedTrainer = null;
                this.selectedLevel = null;
                return;
            }
            this.modo = 'entrenadores';
            this.selectedLevel = null;
            this.selectedTrainer = null;
            await this.loadTrainers();
        },

        // Exploraciones (modo individual = pokemon). Toggle: vuelve al modo por defecto ruta.
        toggleExploraciones() {
            if (this.modo === 'pokemon') {
                this.modo = 'ruta';
                this.selectedLevel = null;
                return;
            }
            this.modo = 'pokemon';
            this.selectedLevel = null;
            this.cargarFavoritos();
        },

        // Vuelta explícita al modo por defecto (botón "Combate de ruta").
        setModoRuta() {
            this.modo = 'ruta';
            this.selectedTrainer = null;
            this.selectedLevel = null;
        },

        selectRutaNivel(nivel) {
            if (this.levelBlocked(nivel)) {
                const min = this.minLvls ? this.minLvls[nivel] : null;
                this.rutaCombatError = min !== null && min !== undefined
                    ? 'Requiere Nv ' + min + ' de jugador para combatir en este nivel.'
                    : 'Ese nivel del hábitat no tiene Pokémon disponibles.';
                return;
            }
            this.rutaNivel = nivel;
            this.rutaCombatError = '';
            if (this.showFormacionPopup && this.formacionContexto === 'ruta') {
                this.limpiarPreviewRuta('/api/habitats/' + this.habitatId + '/ruta/preview');
                this.cargarComparacionPc(this.urlComparacionPcRuta());
            }
        },

        /** URL de la comparación de PC del combate de ruta 3v3. */
        urlComparacionPcRuta() {
            let url = '/api/habitats/' + this.habitatId + '/ruta/pc?team_id=' + this.selectedTeamId + '&nivel=' + this.rutaNivel;
            if (this.pcPreviewId) {
                url += '&preview_id=' + encodeURIComponent(this.pcPreviewId);
            }
            return url;
        },

        /** URL de la comparación de PC de un entrenador del hábitat. */
        urlComparacionPcEntrenador(level, trainerIndex) {
            return '/api/habitats/' + this.habitatId + '/entrenadores/' + level + '/' + trainerIndex + '/pc?team_id=' + this.selectedTeamId;
        },

        /** Recarga la comparación según el contexto abierto en el modal. */
        recargarComparacionPc() {
            if (!this.selectedTeamId) {
                this.limpiarComparacionPc();
                return;
            }

            if (this.formacionContexto === 'ruta') {
                this.cargarComparacionPc(this.urlComparacionPcRuta());
                return;
            }

            if (this.selectedLevel && this.selectedTrainer) {
                this.cargarComparacionPc(
                    this.urlComparacionPcEntrenador(this.selectedLevel, this.selectedTrainer.indice)
                );
            }
        },

        openRutaFormacionPopup() {
            if (!this.selectedTeamId) {
                return;
            }
            this.formacionContexto = 'ruta';
            this.selectedTrainer = null;
            // Formación completa (3 slots): la persistida del backend o 'vanguardia'
            // por defecto. Se envía explícitamente; el backend nunca auto-clasifica.
            this.formacion = this.formacionInicialDe(this.selectedTeamId);
            this.rutaCombatError = '';
            this.showFormacionPopup = true;
            this.cargarComparacionPc(this.urlComparacionPcRuta());
        },

        async loadTrainers() {
            this.trainersLoading = true;
            this.trainers = null;
            try {
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/entrenadores', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar entrenadores');
                this.trainers = await response.json();
            } catch (e) {
                console.error('Error loading trainers:', e);
                this.trainers = null;
            } finally {
                this.trainersLoading = false;
            }
        },

        selectTrainer(level, trainerIndex) {
            if (!this.selectedTeamId) return;
            const trainer = this.trainers?.[level]?.[trainerIndex - 1];
            if (!trainer || !trainer.desbloqueado) return;
            this.selectedLevel = level;
            this.selectedTrainer = trainer;
            this.formacionContexto = 'entrenadores';
            this.rutaCombatError = '';
            // Formación completa (3 slots) desde la persistida del equipo.
            this.formacion = this.formacionInicialDe(this.selectedTeamId);
            this.showFormacionPopup = true;
            this.cargarComparacionPc(this.urlComparacionPcEntrenador(level, trainerIndex));
        },

        openFormacionPopup() {
            if (!this.selectedTeamId || !this.selectedTrainer) {
                return;
            }
            this.formacionContexto = 'entrenadores';
            this.formacion = this.formacionInicialDe(this.selectedTeamId);
            this.showFormacionPopup = true;
            this.recargarComparacionPc();
        },

        /** Formación inicial para un equipo: formación persistida o 'vanguardia'. */
        formacionInicialDe(teamId) {
            const team = this.teams.find(t => t.id === teamId);
            const persistida = team && team.formacion ? team.formacion : {};
            return {
                1: persistida[1] || 'vanguardia',
                2: persistida[2] || 'vanguardia',
                3: persistida[3] || 'vanguardia',
            };
        },

        toggleFormacionSlot(slot) {
            this.formacion[slot] = this.formacion[slot] === 'vanguardia' ? 'retaguardia' : 'vanguardia';
        },

        closeFormacionPopup() {
            this.showFormacionPopup = false;
            this.selectedTrainer = null;
            this.rutaCombatError = '';
            this.limpiarPreviewRuta('/api/habitats/' + this.habitatId + '/ruta/preview');
            this.limpiarComparacionPc();
        },

        async confirmarCombate() {
            if (this.formacionContexto === 'ruta') {
                await this.confirmarCombateRuta();
                return;
            }
            if (!this.selectedTeamId || !this.selectedLevel || !this.selectedTrainer) return;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/entrenadores/' + this.selectedLevel + '/' + this.selectedTrainer.indice + '/combatir', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        team_id: this.selectedTeamId,
                        formacion: this.formacion,
                    }),
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    alert(err.message || 'Error al iniciar combate');
                    return;
                }
                const data = await response.json();
                this.showFormacionPopup = false;
                window.location.href = data.redirect || '/combate?battle_id=' + data.battle_id;
            } catch (e) {
                alert('Error al iniciar combate: ' + e.message);
            }
        },

        async confirmarCombateRuta() {
            if (!this.selectedTeamId || this.rutaCombatiendo) return;

            if (this.levelBlocked(this.rutaNivel)) {
                const min = this.minLvls ? this.minLvls[this.rutaNivel] : null;
                this.rutaCombatError = min !== null && min !== undefined
                    ? 'Requiere Nv ' + min + ' de jugador para combatir en este nivel.'
                    : 'Ese nivel del hábitat no tiene Pokémon disponibles.';
                return;
            }

            this.rutaCombatiendo = true;
            this.rutaCombatError = '';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/ruta/iniciar', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        team_id: this.selectedTeamId,
                        nivel: this.rutaNivel,
                        formacion: this.formacion,
                        ...(this.pcPreviewId ? { preview_id: this.pcPreviewId } : {}),
                    }),
                });
                if (!response.ok) {
                    // 422 (equipo ≠3, pool vacío...) → error inline en el popup, sin redirigir.
                    const err = await response.json().catch(() => ({}));
                    this.rutaCombatError = err.message || err.error || 'No se pudo iniciar el combate de ruta.';
                    return;
                }
                const data = await response.json();
                this.showFormacionPopup = false;
                window.location.href = data.redirect || '/combate?battle_id=' + data.battle_id;
            } catch (e) {
                console.error('Error iniciando combate de ruta:', e);
                this.rutaCombatError = 'Error al iniciar combate: ' + e.message;
            } finally {
                this.rutaCombatiendo = false;
            }
        },

        // ─── Mazmorra ─────────────────────────────────────────────────────
        showMazmorraModal: false,
        mazmorraLoading: false,
        mazmorraError: '',
        pisos: null,
        pisoSeleccionado: null,
        mazmorraTeamId: null,
        mazmorraTeamName: '',
        mazmorraFormacion: {},
        mazmorraCombatiendo: false,
        mazmorraCombatError: '',

        get mazmorraTeamMembers() {
            if (!this.mazmorraTeamId || !this.teams) return [];
            const team = this.teams.find(t => t.id === this.mazmorraTeamId);
            if (!team || !team.members) return [];
            return team.members.sort((a, b) => a.slot - b.slot).filter(m => m.reclutado && m.reclutado.pokemon);
        },

        async openMazmorraModal() {
            this.showMazmorraModal = true;
            this.pisoSeleccionado = null;
            this.mazmorraTeamId = null;
            this.mazmorraFormacion = {};
            this.mazmorraCombatError = '';
            await this.loadMazmorra();
        },

        closeMazmorraModal() {
            this.showMazmorraModal = false;
            this.pisoSeleccionado = null;
            this.mazmorraCombatError = '';
        },

        async loadMazmorra() {
            this.mazmorraLoading = true;
            this.mazmorraError = '';
            try {
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/mazmorra', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar la mazmorra');
                const data = await response.json();
                this.pisos = Array.isArray(data) ? data : (data.pisos || []);
            } catch (e) {
                console.error('Error loading mazmorra:', e);
                this.mazmorraError = 'No se pudo cargar la mazmorra. Inténtalo de nuevo.';
            } finally {
                this.mazmorraLoading = false;
            }
        },

        estadoPisoClass(piso) {
            if (piso.estado === 'ganado') return 'border-success bg-success-soft';
            if (piso.estado === 'cooldown') return 'border-warning bg-warning-soft opacity-70';
            return 'border-line hover:border-primary';
        },

        estadoPisoIconoClass(piso) {
            if (piso.estado === 'ganado') return 'bg-success-soft text-success-ink';
            if (piso.estado === 'cooldown') return 'bg-warning-soft text-warning-ink';
            return 'bg-primary-soft text-primary-ink';
        },

        estadoPisoTexto(piso) {
            if (piso.estado === 'ganado') return 'Piso superado';
            if (piso.estado === 'cooldown') {
                const hasta = piso.cooldown_hasta;
                if (hasta) return 'Cooldown hasta ' + new Date(hasta).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                return 'Cooldown activo (1 h)';
            }
            return 'Disponible — enfréntate al pokémon más débil del hábitat';
        },

        selectPiso(piso) {
            if (piso.estado !== 'disponible') return;
            this.pisoSeleccionado = piso;
            this.mazmorraTeamId = null;
            this.mazmorraFormacion = {};
            this.mazmorraCombatError = '';
        },

        selectMazmorraTeam(id, name) {
            this.mazmorraTeamId = id;
            this.mazmorraTeamName = name;
            this.mazmorraFormacion = {};
            const team = this.teams.find(t => t.id === id);
            if (team && team.members) {
                team.members.forEach(m => {
                    this.mazmorraFormacion[m.slot] = 'vanguardia';
                });
            }
        },

        async confirmarPiso() {
            if (!this.pisoSeleccionado || !this.mazmorraTeamId || this.mazmorraCombatiendo) return;
            this.mazmorraCombatiendo = true;
            this.mazmorraCombatError = '';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/mazmorra/' + this.pisoSeleccionado.piso + '/combatir', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        team_id: this.mazmorraTeamId,
                        formacion: this.mazmorraFormacion,
                    }),
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    this.mazmorraCombatError = err.message || 'Error al iniciar combate';
                    return;
                }
                const data = await response.json();
                this.showMazmorraModal = false;
                window.location.href = data.redirect || '/combate?battle_id=' + data.battle_id;
            } catch (e) {
                this.mazmorraCombatError = 'Error al iniciar combate: ' + e.message;
            } finally {
                this.mazmorraCombatiendo = false;
            }
        },

        // Granja
        showGranjaModal: false,
        granjaLoading: false,
        granjaError: '',
        granja: null,
        granjaAccionando: false,
        // Familia seleccionada en las tabs del modal de granja.
        familiaActiva: 0,
        // Tabs de Pokémon por familia y detalle bajo demanda del modal de granja.
        familiaTabActiva: {},
        detallePokemonCache: {},
        detallePokemonLoading: {},
        detallePokemonError: {},
        // Selector de baya (modal hermano de la granja)
        showBayaModal: false,
        slotPlantando: null,
        bayasCatalogo: [],
        bayasCatalogoCargado: false,
        bayasCargando: false,
        bayaError: '',
        bayaBusqueda: '',
        bayaGrupoActivo: 'todos',
        bayasPlantando: false,
        bayaGrupos: [
            { valor: 'todos', etiqueta: 'Todas' },
            { valor: 'medicina', etiqueta: 'Medicinas' },
            { valor: 'mejora', etiqueta: 'Mejoras' },
            { valor: 'reductor', etiqueta: 'Reductores' },
            { valor: 'resistencia', etiqueta: 'Resistencias' },
        ],

        openGranjaModal() {
            this.showGranjaModal = true;
            this.granjaError = '';
            this.familiaActiva = 0;
            this.loadGranja();
        },

        closeGranjaModal() {
            this.showGranjaModal = false;
            this.cerrarBayaModal();
            this.bayasCatalogoCargado = false;
        },

        /** URL del endpoint de granja; propaga el flag QA de desbloqueo cuando está activo. */
        urlGranja(ruta = '') {
            const url = '/api/habitats/{{ $habitat['id'] }}/farm' + ruta;
            return this.unlockAll ? url + '?unlock=1' : url;
        },

        /** Escape: cierra primero el modal de bayas si está abierto. */
        cerrarCapaSuperior() {
            if (this.showBayaModal) {
                this.cerrarBayaModal();
                return;
            }

            this.closeGranjaModal();
        },

        async loadGranja(opciones = {}) {
            const preservarFamilia = opciones.preservarFamilia === true;
            const silenciosa = opciones.silenciosa === true;

            if (!silenciosa) {
                this.granjaLoading = true;
                this.granjaError = '';
            }

            if (!preservarFamilia) {
                this.familiaActiva = 0;
            }

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);
            try {
                const response = await fetch(this.urlGranja(), {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'No se pudo cargar la granja');
                }
                this.granja = await response.json();
                this.ajustarFamiliaActiva();
                if (typeof this.sincronizarCatalogoBayas === 'function') {
                    this.sincronizarCatalogoBayas();
                }
            } catch (e) {
                console.error('Error loading granja:', e);
                if (!silenciosa) {
                    this.granjaError = e.name === 'AbortError'
                        ? 'La operación tardó demasiado. Inténtalo de nuevo.'
                        : (e.message || 'No se pudo cargar la granja.');
                    this.granja = null;
                }
            } finally {
                clearTimeout(timeout);
                if (!silenciosa) {
                    this.granjaLoading = false;
                }
            }
        },

        /** Tabs de la familia ordenadas por etapa, especie e id (defaults a 0). */
        tabsFamilia(familia) {
            const base = familia?.base ? [familia.base] : [];
            const evoluciones = Array.isArray(familia?.evolutions) ? familia.evolutions : [];
            const vistos = new Set();

            return [...base, ...evoluciones]
                .filter((pokemon) => {
                    if (!pokemon || pokemon.id === null || pokemon.id === undefined) {
                        return false;
                    }

                    const id = Number(pokemon.id);
                    if (vistos.has(id)) {
                        return false;
                    }
                    vistos.add(id);

                    return true;
                })
                .sort((a, b) => {
                    const etapa = (Number(a?.stage) || 0) - (Number(b?.stage) || 0);
                    if (etapa !== 0) {
                        return etapa;
                    }

                    const especie = (Number(a?.species_id) || 0) - (Number(b?.species_id) || 0);
                    if (especie !== 0) {
                        return especie;
                    }

                    return (Number(a?.id) || 0) - (Number(b?.id) || 0);
                });
        },

        tabActivaFamilia(familia) {
            const tabs = this.tabsFamilia(familia);
            if (tabs.length === 0) {
                return null;
            }

            const chainId = familia?.evolution_chain_id;
            const activaId = this.familiaTabActiva?.[chainId];

            return tabs.find((pokemon) => Number(pokemon.id) === Number(activaId)) || tabs[0];
        },

        esTabActivaFamilia(familia, pokemon) {
            const activa = this.tabActivaFamilia(familia);

            return Boolean(activa && pokemon && Number(activa.id) === Number(pokemon.id));
        },

        async seleccionarPokemonFamilia(familia, pokemon) {
            if (!familia || !pokemon) {
                return;
            }

            const chainId = familia.evolution_chain_id;
            const pokemonId = pokemon.id;
            this.familiaTabActiva = { ...this.familiaTabActiva, [chainId]: pokemonId };

            if (this.detallePokemonCache?.[pokemonId] || this.detallePokemonLoading?.[pokemonId]) {
                return;
            }

            this.detallePokemonLoading = { ...this.detallePokemonLoading, [pokemonId]: true };
            this.detallePokemonError = { ...this.detallePokemonError, [pokemonId]: '' };
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);

            try {
                const response = await fetch(
                    '/api/habitats/{{ $habitat['id'] }}/families/' + chainId + '/pokemon/' + pokemonId,
                    {
                        headers: { 'Accept': 'application/json' },
                        signal: controller.signal,
                    }
                );

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'No se pudo cargar la información del Pokémon');
                }

                const detalle = await response.json();
                this.detallePokemonCache = { ...this.detallePokemonCache, [pokemonId]: detalle };
            } catch (e) {
                console.error('Error loading detalle de Pokémon:', e);
                this.detallePokemonError = {
                    ...this.detallePokemonError,
                    [pokemonId]: e.name === 'AbortError'
                        ? 'La operación tardó demasiado. Inténtalo de nuevo.'
                        : (e.message || 'No se pudo cargar la información del Pokémon.'),
                };
            } finally {
                clearTimeout(timeout);
                this.detallePokemonLoading = { ...this.detallePokemonLoading, [pokemonId]: false };
            }
        },

        /** Navegación por teclado del tablist (flechas, Inicio y Fin). */
        navegarTabFamilia(familia, direccion) {
            const tabs = this.tabsFamilia(familia);
            if (tabs.length === 0) {
                return;
            }

            const activa = this.tabActivaFamilia(familia);
            const posicion = tabs.findIndex((pokemon) => Number(pokemon.id) === Number(activa?.id));
            let indice = posicion < 0 ? 0 : posicion;

            if (direccion === 'inicio') {
                indice = 0;
            } else if (direccion === 'fin') {
                indice = tabs.length - 1;
            } else {
                indice = (indice + Number(direccion) + tabs.length) % tabs.length;
            }

            const destino = tabs[indice];
            this.seleccionarPokemonFamilia(familia, destino);
            this.$nextTick(() => {
                document.getElementById('familia-tab-' + familia.evolution_chain_id + '-' + destino.id)?.focus();
            });
        },

        /** Mantiene el índice de familia dentro del array tras una recarga. */
        ajustarFamiliaActiva() {
            const total = Array.isArray(this.granja?.familias) ? this.granja.familias.length : 0;

            if (total === 0) {
                this.familiaActiva = 0;
                return;
            }

            this.familiaActiva = Math.min(Math.max(this.familiaActiva, 0), total - 1);
        },

        /** Navegación por teclado del tablist de familias (flechas, Inicio y Fin). */
        navegarFamilia(direccion) {
            const total = Array.isArray(this.granja?.familias) ? this.granja.familias.length : 0;

            if (total === 0) {
                return;
            }

            let indice = this.familiaActiva;

            if (direccion === 'inicio') {
                indice = 0;
            } else if (direccion === 'fin') {
                indice = total - 1;
            } else {
                indice = (indice + Number(direccion) + total) % total;
            }

            this.familiaActiva = indice;
            this.$nextTick(() => {
                const familia = this.granja.familias[indice];
                document.getElementById('familia-tab-' + familia.evolution_chain_id)?.focus();
            });
        },

        detalleDe(pokemonId) {
            if (pokemonId === null || pokemonId === undefined) {
                return null;
            }

            return this.detallePokemonCache?.[pokemonId] ?? null;
        },

        cargandoDetalle(pokemonId) {
            return Boolean(this.detallePokemonLoading?.[pokemonId]);
        },

        errorDetalle(pokemonId) {
            return this.detallePokemonError?.[pokemonId] || '';
        },

        detalleActivoFamilia(familia) {
            const activa = this.tabActivaFamilia(familia);

            return activa ? this.detalleDe(activa.id) : null;
        },

        async accionGranja(url, payload = null) {
            if (this.granjaAccionando) return;
            this.granjaAccionando = true;
            this.granjaError = '';
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: payload !== null ? JSON.stringify(payload) : undefined,
                    signal: controller.signal,
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'No se pudo completar la acción.');
                }
                this.granja = await response.json();
                this.sincronizarCatalogoBayas();
            } catch (e) {
                console.error('Error updating granja:', e);
                this.granjaError = e.name === 'AbortError'
                    ? 'La operación tardó demasiado. Inténtalo de nuevo.'
                    : (e.message || 'Error de conexión al actualizar la granja.');
            } finally {
                clearTimeout(timeout);
                this.granjaAccionando = false;
            }
        },

        /** Abre el modal de bayas para el hueco indicado, cargando el catálogo la primera vez. */
        abrirSelectorBaya(chainId, slot) {
            this.slotPlantando = { chainId, slot };
            this.bayaBusqueda = '';
            this.bayaGrupoActivo = 'todos';
            this.bayaError = '';
            this.showBayaModal = true;

            if (!this.bayasCatalogoCargado && !this.bayasCargando) {
                this.cargarCatalogoBayas();
            }

            this.$nextTick(() => this.$refs.bayaBuscar?.focus());
        },

        cerrarBayaModal() {
            this.showBayaModal = false;
            this.slotPlantando = null;
            this.bayaBusqueda = '';
            this.bayaGrupoActivo = 'todos';
            this.bayaError = '';
        },

        async cargarCatalogoBayas() {
            this.bayasCargando = true;
            this.bayaError = '';
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);
            try {
                const response = await fetch(this.urlGranja('/berries'), {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'No se pudo cargar el catálogo de bayas.');
                }
                const data = await response.json();
                this.bayasCatalogo = Array.isArray(data.bayas) ? data.bayas : [];
                this.bayasCatalogoCargado = true;
            } catch (e) {
                console.error('Error loading catálogo de bayas:', e);
                this.bayaError = e.name === 'AbortError'
                    ? 'La operación tardó demasiado. Inténtalo de nuevo.'
                    : (e.message || 'No se pudo cargar el catálogo de bayas.');
            } finally {
                clearTimeout(timeout);
                this.bayasCargando = false;
            }
        },

        /** Normaliza texto para búsquedas: sin mayúsculas ni diacríticos (fallback: solo minúsculas). */
        normalizarBusqueda(texto) {
            const base = String(texto || '');

            if (typeof base.normalize !== 'function') {
                return base.toLowerCase();
            }

            return base
                .normalize('NFD')
                .replace(/\p{Diacritic}/gu, '')
                .toLowerCase();
        },

        /** Bayas filtradas por búsqueda y categoría; las que tienen stock van primero (orden estable). */
        get bayasFiltradas() {
            const termino = this.normalizarBusqueda(this.bayaBusqueda.trim());

            return (this.bayasCatalogo || [])
                .filter((baya) => this.bayaGrupoActivo === 'todos' || baya.grupo === this.bayaGrupoActivo)
                .filter((baya) => termino === ''
                    || this.normalizarBusqueda(baya.nombre).includes(termino)
                    || this.normalizarBusqueda(baya.grupo_nombre).includes(termino))
                .slice()
                .sort((a, b) => Number(b.cantidad > 0) - Number(a.cantidad > 0));
        },

        /** Construye los huecos visibles emparejando cada slot con su plantación. */
        huecosDe(familia) {
            const total = Number(familia?.nido?.huecos_totales ?? 0);
            const plantaciones = familia?.nido?.plantaciones || [];
            const huecos = [];

            for (let slot = 1; slot <= total; slot += 1) {
                huecos.push({
                    slot,
                    plantacion: plantaciones.find((plantacion) => Number(plantacion.slot) === slot) || null,
                });
            }

            return huecos;
        },

        cantidadBaya(clave) {
            const baya = (this.granja?.bayas || []).find((item) => item.clave === clave);

            return baya?.cantidad ?? 0;
        },

        async plantarBayaDesdeModal(baya) {
            if (this.bayasPlantando || !this.slotPlantando || !baya || baya.cantidad < 1) return;

            const { chainId, slot } = this.slotPlantando;
            this.bayasPlantando = true;
            this.bayaError = '';
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(this.urlGranja('/nests/' + chainId + '/plantings'), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ slot: Number(slot), berry: baya.clave }),
                    signal: controller.signal,
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'No se pudo plantar la baya.');
                }
                this.granja = await response.json();
                this.sincronizarCatalogoBayas();
                this.showBayaModal = false;
                this.slotPlantando = null;
            } catch (e) {
                console.error('Error planting baya:', e);
                this.bayaError = e.name === 'AbortError'
                    ? 'La operación tardó demasiado. Inténtalo de nuevo.'
                    : (e.message || 'No se pudo plantar la baya.');
            } finally {
                clearTimeout(timeout);
                this.bayasPlantando = false;
            }
        },

        /** Refleja en el catálogo las existencias devueltas por la granja; las ausentes quedan a 0. */
        sincronizarCatalogoBayas() {
            const existencias = new Map((this.granja?.bayas || []).map((baya) => [baya.clave, baya.cantidad]));
            this.bayasCatalogo = (this.bayasCatalogo || []).map((baya) => ({
                ...baya,
                cantidad: existencias.has(baya.clave) ? Number(existencias.get(baya.clave)) : 0,
            }));
        },

        cosecharBaya(chainId, slot) {
            return this.accionGranja(
                this.urlGranja('/nests/' + chainId + '/plantings/' + slot + '/harvest')
            );
        },

        replantarBaya(chainId, slot, plantacion) {
            if (!plantacion || this.cantidadBaya(plantacion.berry_key) < 1) return;

            const confirmado = window.confirm(
                '¿Replantar ' + plantacion.berry_nombre + ' en este hueco? Se perderá el stock pendiente (' + plantacion.stock + '/' + plantacion.stock_max + ').'
            );

            if (!confirmado) return;

            return this.accionGranja(
                this.urlGranja('/nests/' + chainId + '/plantings'),
                { slot: Number(slot), berry: plantacion.berry_key }
            );
        },

        saldoFamilia(chainId) {
            const item = (this.granja?.inventario?.familia || []).find(
                (caramelo) => Number(caramelo.evolution_chain_id) === Number(chainId)
            );

            return item?.cantidad ?? 0;
        },

        textoBotonComplemento(comp) {
            if (comp?.coste_ev === null) return 'Máximo';
            if (comp?.motivo === 'refugio') return 'Refugio primero';
            if (comp?.motivo === 'insalubres') return 'Insalubres';
            if (comp?.motivo === 'saldo') return 'Faltan caramelos';

            return comp?.nivel === 0 ? 'Crear' : 'Subir';
        },

        mejorarNido(chainId) {
            return this.accionGranja(this.urlGranja('/nests/' + chainId + '/upgrade'));
        },

        mejorarRefugio(chainId) {
            return this.accionGranja(this.urlGranja('/shelters/' + chainId + '/upgrade'));
        },

        alternarInsalubres(chainId) {
            return this.accionGranja(this.urlGranja('/shelters/' + chainId + '/toggle-unhealthy'));
        },

        mejorarComplemento(chainId, stat) {
            return this.accionGranja(this.urlGranja('/shelters/' + chainId + '/complements/' + encodeURIComponent(stat) + '/upgrade'));
        },

        // Admin Gestion state
        showGestionModal: false,
        gestionTab: 'assign',
        gestionLoading: false,
        unassignedFamilies: [],
        assignedFamilies: [],
        assignSearch: '',
        assignTypeFilter: '',
        allTypes: @json(collect(\App\Enums\TipoEnum::options())->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values()->all()),

        get canStartExploration() {
            return this.selectedFavoritoId !== null
                && this.selectedLevel !== null
                && !this.levelBlocked(this.selectedLevel);
        },

        get availableTeams() {
            return this.teams || [];
        },

        init() {
            window.addEventListener('inventario:actualizado', () => {
                if (this.showGranjaModal && !this.granjaAccionando) {
                    this.loadGranja({ preservarFamilia: true, silenciosa: true });
                }
            });

            // Cargar favoritos para la exploración individual. Tolerante: si el
            // endpoint aún no está disponible, se muestra el estado vacío/error.
            this.cargarFavoritos();

            // Nivel de ruta por defecto: el primero disponible para el jugador.
            const nivelRuta = [1, 2, 3].find(level => !this.levelBlocked(level));
            if (nivelRuta !== undefined) {
                this.rutaNivel = nivelRuta;
            }
        },

        /** URL del sprite de un reclutado; shiny usa el sufijo _s. */
        iconoReclutado(reclutado) {
            if (!reclutado || !reclutado.pokemon_id) return '/images/iconos_webp/0.webp';
            if (reclutado.es_shiny) return '/images/iconos_webp/shiny/' + reclutado.pokemon_id + '_s.webp';
            return '/images/iconos_webp/' + reclutado.pokemon_id + '.webp';
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

        async cargarFavoritos() {
            this.favoritosLoading = true;
            this.favoritosError = '';
            try {
                const response = await fetch('/api/reclutados/favoritos?habitat_id={{ $habitat['id'] }}', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    throw new Error('No se pudieron cargar tus favoritos');
                }
                // Tolerante: si el backend aún devuelve un objeto envuelto, normaliza a array.
                const data = await response.json();
                this.favoritos = Array.isArray(data) ? data : (data.data || []);
            } catch (e) {
                console.error('Error loading favoritos:', e);
                this.favoritosError = 'Función en preparación.';
                this.favoritos = [];
            } finally {
                this.favoritosLoading = false;
            }
        },

        esFavoritoEnExploracion(id) {
            return this.reclutadosEnExploracion.includes(id);
        },

        selectFavorito(id, name) {
            if (this.reclutadosEnExploracion.includes(id)) {
                alert('Este Pokémon ya está en una exploración activa.');
                return;
            }
            this.selectedFavoritoId = id;
            this.selectedFavoritoName = name;
            this.checkAndOpenModal();
        },

        // ─── Gestión de favoritos del hábitat ───────────────────────────────

        esFavoritoHabitat(reclutadoId) {
            return this.favoritos.some(f => Number(f.id) === Number(reclutadoId));
        },

        // El usuario quiere enviar este favorito a explorar directamente (ya con
        // el hábitat preseleccionado). Inicializa el nivel y abre el modal.
        openEnviarHabitat(favorito) {
            if (this.esFavoritoEnExploracion(favorito.id)) {
                return;
            }
            // Resetear estado de envío.
            this.durationMode = 'hours';
            this.durationHours = 4;
            this.returnTime = '18:00';
            this.preview = null;
            this.previewLoaded = false;
            this.previewError = '';
            this.selectedFavoritoId = favorito.id;
            this.selectedFavoritoName = favorito.nombre;
            // Nivel por defecto: el primero disponible (no bloqueado). Si ninguno
            // está bloqueado usa el nivel 1; en caso contrario lo deja para que la
            // preview indique el error y el usuario ajuste desde el panel.
            this.avisoNivel = '';
            const nivelDefecto = [1, 2, 3].find(level => !this.levelBlocked(level)) || null;
            this.selectedLevel = nivelDefecto;
            if (nivelDefecto !== null) {
                this.openExplorationModal();
            } else {
                alert('Ningún nivel está disponible para tu nivel de jugador.');
            }
        },

        openGestionFavoritosHabitat() {
            this.showGestionFavoritosModal = true;
            this.gestionFavoritosError = '';
            this.cargarReclutadosParaGestion();
        },

        closeGestionFavoritosModal() {
            this.showGestionFavoritosModal = false;
        },

        async cargarReclutadosParaGestion() {
            this.gestionFavoritosLoading = true;
            this.gestionFavoritosError = '';
            try {
                const response = await fetch('/api/reclutados', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    throw new Error('No se pudieron cargar tus Pokémon');
                }
                const data = await response.json();
                this.allReclutados = Array.isArray(data) ? data : (data.data || []);
            } catch (e) {
                console.error('Error loading reclutados:', e);
                this.gestionFavoritosError = 'Función en preparación.';
                this.allReclutados = [];
                this.favoritos = [];
            } finally {
                this.gestionFavoritosLoading = false;
            }
        },

        async toggleFavoritoHabitat(pokemon) {
            // Máximo 6 favoritos por hábitat (aviso preventivo; el backend valida).
            if (!this.esFavoritoHabitat(pokemon.id) && this.favoritos.length >= 6) {
                alert('Máximo 6 favoritos por hábitat.');
                return;
            }
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            try {
                const response = await fetch('/api/reclutados/' + pokemon.id + '/toggle-favorito', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ habitat_id: {{ $habitat['id'] }} }),
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    alert(data.message || 'No se pudo actualizar el favorito.');
                    return;
                }
                const data = await response.json();
                // Mutación local sin recargar.
                if (data.favorito) {
                    if (!this.esFavoritoHabitat(pokemon.id) && this.favoritos.length < 6) {
                        this.favoritos.push({
                            ...pokemon,
                            nivel: pokemon.nivel || 1,
                        });
                    }
                } else {
                    this.favoritos = this.favoritos.filter(f => Number(f.id) !== Number(pokemon.id));
                }
            } catch (err) {
                console.error('Error toggling habitat favorito:', err);
                alert('Error de conexión al actualizar el favorito.');
            }
        },

        levelBlocked(level) {
            const cantidad = this.levelsCount ? this.levelsCount[level] : 0;
            if (!cantidad) {
                return true;
            }
            const min = this.minLvls ? this.minLvls[level] : null;
            return min !== null && min !== undefined && this.nivelJugador < min;
        },

        selectTeam(id, name) {
            this.selectedTeamId = id;
            this.selectedTeamName = name;
            if (this.modo === 'pokemon') {
                this.checkAndOpenModal();
            }
            if (this.showFormacionPopup) {
                this.recargarComparacionPc();
            }
        },

        selectLevel(level) {
            if (this.levelBlocked(level)) {
                this.avisoNivel = 'Requiere Nv ' + this.minLvls[level] + ' de jugador para explorar este nivel.';
                return;
            }
            this.selectedLevel = level;
            this.avisoNivel = '';
            this.checkAndOpenModal();
        },

        checkAndOpenModal() {
            if (this.selectedLevel !== null && this.levelBlocked(this.selectedLevel)) {
                this.avisoNivel = 'Requiere Nv ' + this.minLvls[this.selectedLevel] + ' de jugador para explorar este nivel.';
                this.selectedLevel = null;
                return;
            }
            if (this.selectedFavoritoId !== null && this.selectedLevel !== null) {
                this.openExplorationModal();
            }
        },

        isSighted(pokemonId) {
            return this.unlockAll || this.sightedPokemonIds.includes(pokemonId);
        },

        openExplorationModal() {
            this.showExplorationModal = true;
            this.loadPreview();
        },

        async loadPreview() {
            if (!this.selectedFavoritoId || !this.selectedLevel) {
                return;
            }
            this.previewLoading = true;
            this.previewLoaded = false;
            this.previewError = '';
            this.preview = null;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const params = new URLSearchParams({
                    reclutado_id: this.selectedFavoritoId,
                    habitat_id: {{ $habitat['id'] }},
                    level: this.selectedLevel,
                });
                params.set('modo', this.modoExploracion);
                if (this.durationMode === 'hours') {
                    params.set('duracion_horas', String(this.durationHours));
                } else if (this.durationMode === 'return_time') {
                    params.set('return_time', this.returnTime);
                }
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

        starRating(n) {
            const valor = Math.max(0, Math.min(5, Number(n) || 0));
            return valor + '/5';
        },

        modoLabel(modo) {
            const etiquetas = {
                experiencia: 'Pelear',
                reclutamiento: 'Buscar',
                botin: 'Explorar',
            };
            return etiquetas[modo] || modo;
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

        closeExplorationModal() {
            this.showExplorationModal = false;
        },

        confirmExploration() {
            // Client-side validation: ensure favorite is not already exploring
            if (this.selectedFavoritoId === null) {
                alert('Selecciona un Pokémon favorito para enviar.');
                return;
            }
            if (this.reclutadosEnExploracion.includes(this.selectedFavoritoId)) {
                alert('Este Pokémon ya está en una exploración activa.');
                this.closeExplorationModal();
                return;
            }

            if (!this.previewLoaded || !this.preview) {
                alert('Espera a que se calcule la preparación de la expedición.');
                return;
            }

            // Riesgo extremo: confirmación reforzada antes de enviar.
            if (this.preview.riesgo === 'Extremo') {
                const confirmacion = window.confirm(
                    '¡Riesgo EXTREMO! ¿Seguro que quieres enviar esta expedición?'
                );
                if (!confirmacion) {
                    return;
                }
            }

            const data = {
                reclutado_id: this.selectedFavoritoId,
                level: this.selectedLevel,
                habitat_id: {{ $habitat['id'] }},
            };

            data.modo = this.modoExploracion;

            if (this.durationMode === 'hours') {
                data.duracion_horas = this.durationHours;
            } else if (this.durationMode === 'return_time') {
                data.return_time = this.returnTime;
            }

            // POST to individual exploration API
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            fetch('/api/exploraciones/store-individual', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(data),
            })
                .then(async (response) => {
                    if (response.ok) {
                        const data = await response.json().catch(() => ({}));

                        this.reclutadosEnExploracion.push(Number(this.selectedFavoritoId));
                        this.closeExplorationModal();
                        this.selectedFavoritoId = null;
                        this.selectedLevel = null;
                        this.previewLoaded = false;
                        this.preview = null;

                        window.dispatchEvent(new CustomEvent('expediciones:actualizada', {
                            detail: { expediciones: data.expediciones ?? [] },
                        }));

                        return;
                    }
                    const body = await response.json().catch(() => ({}));
                    alert(body.message || 'No se pudo enviar la exploración.');
                })
                .catch((err) => {
                    console.error('Error sending exploration:', err);
                    alert('Error de conexión al enviar la exploración.');
                });
        },

        get filteredUnassigned() {
            const query = (this.assignSearch || '').trim().toLowerCase();
            const typeId = this.assignTypeFilter ? Number(this.assignTypeFilter) : null;

            return (this.unassignedFamilies || []).filter(family => {
                const nameMatch = !query || (family.base.name || '').toLowerCase().includes(query);
                let typeMatch = true;
                if (typeId) {
                    typeMatch = (family.types || []).some(t => Number(t.id) === typeId);
                }
                return nameMatch && typeMatch;
            });
        },

        openGestionModal() {
            this.showGestionModal = true;
            this.gestionTab = 'assign';
            this.assignSearch = '';
            this.assignTypeFilter = '';
            this.loadUnassignedFamilies();
            this.loadAssignedFamilies();
        },

        closeGestionModal() {
            this.showGestionModal = false;
        },

        async loadUnassignedFamilies() {
            this.gestionLoading = true;
            try {
                const response = await fetch('/api/habitats/unassigned-families', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar familias');
                const data = await response.json();
                this.unassignedFamilies = (data || []).map(f => ({
                    ...f,
                    total_stages: 1 + (f.evolutions?.length || 0),
                }));
            } catch (e) {
                alert('Error al cargar familias: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        async loadAssignedFamilies() {
            this.gestionLoading = true;
            try {
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/families', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar familias asignadas');
                const data = await response.json();
                this.assignedFamilies = (data || []).map(f => ({
                    ...f,
                    total_stages: 1 + (f.evolutions?.length || 0),
                }));
            } catch (e) {
                alert('Error al cargar familias asignadas: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        get assignedByLevel() {
            const grouped = { 1: [], 2: [], 3: [] };
            (this.assignedFamilies || []).forEach((family) => {
                if (Number.isFinite(Number(family.base?.level))) {
                    grouped[Number(family.base.level)]?.push({
                        ...family.base,
                        evolution_chain_id: family.evolution_chain_id,
                        is_base: true,
                    });
                }
                (family.evolutions || []).forEach((evo) => {
                    if (Number.isFinite(Number(evo.level))) {
                        grouped[Number(evo.level)]?.push({
                            ...evo,
                            evolution_chain_id: family.evolution_chain_id,
                            is_base: false,
                        });
                    }
                });
            });
            return grouped;
        },

        async assignFamily(family) {
            if (this.gestionLoading) return;
            this.gestionLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/families', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ evolution_chain_id: family.evolution_chain_id }),
                });
                if (!response.ok) {
                    const err = await response.json();
                    throw new Error(err.message || 'Error al asignar la familia');
                }

                // Refresco local SIN recargar listados: quito de no asignadas siempre.
                this.unassignedFamilies = (this.unassignedFamilies || []).filter(f => f.evolution_chain_id !== family.evolution_chain_id);

                // El POST devuelve (201) la familia COMPLETA con los niveles reales por miembro
                // (base.level / evolutions[].level). La añadimos decorada con total_stages, igual
                // que hacen los loaders, para que assignedByLevel agrupe con datos reales y no
                // con la inferencia client-side del antiguo helper.
                const data = await response.json().catch(() => null);
                if (data && data.evolution_chain_id && data.base) {
                    this.assignedFamilies = [
                        { ...data, total_stages: 1 + (data.evolutions?.length || 0) },
                        ...(this.assignedFamilies || []),
                    ];
                }
                // Fallback defensivo: si el body NO trae la estructura completa (p. ej. el backend
                // aún no entrega el nuevo shape), NO inferimos niveles y no añadimos la familia a
                // assignedFamilies (un objeto mínimo sin level rompería la pestaña Ya Asignados y
                // assignedByLevel). Como ya la quitamos de unassignedFamilies, no hay duplicados:
                // se auto-corrige al reabrir el modal, que sí recarga los listados.
            } catch (e) {
                alert('Error al asignar: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        async updatePokemonLevel(chainId, pokemonId, level) {
            if (this.gestionLoading) return;
            this.gestionLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/pokemon/' + pokemonId, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ level: level }),
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'Error al mover el pokémon de nivel');
                }

                // Refresco local SIN recargar: muto el nivel de ese pokémon para que re-agrupe solo.
                const family = (this.assignedFamilies || []).find(f => f.evolution_chain_id === chainId);
                if (!family) return;

                if (Number(family.base?.id) === pokemonId) {
                    family.base.level = level;
                    return;
                }

                const evo = (family.evolutions || []).find(e => Number(e.id) === pokemonId);
                if (evo) {
                    evo.level = level;
                }
            } catch (e) {
                alert('Error al mover de nivel: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        /** Actualiza los mínimos por nivel tras un ajuste manual o automático. */
        aplicarMinLvls(data) {
            if (!data.min_lvls) return;
            this.minLvls = {
                1: data.min_lvls[1] ?? null,
                2: data.min_lvls[2] ?? null,
                3: data.min_lvls[3] ?? null,
            };
        },

        /** Localiza un pokémon asignado (base o evolución) en el estado local. */
        findAssignedPokemon(pokemonId) {
            for (const family of (this.assignedFamilies || [])) {
                if (Number(family.base?.id) === pokemonId) {
                    return family.base;
                }
                const evo = (family.evolutions || []).find(e => Number(e.id) === pokemonId);
                if (evo) {
                    return evo;
                }
            }
            return null;
        },

        async updatePokemonMinLevel(pokemonId, minLevel) {
            if (this.gestionLoading) return;

            const valor = Number(minLevel);
            if (!Number.isInteger(valor) || valor < 1 || valor > 100) {
                alert('El nivel mínimo debe estar entre 1 y 100.');
                return;
            }

            this.gestionLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/pokemon/' + pokemonId + '/min-level', {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ min_level: valor, manual: true }),
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'Error al actualizar el nivel mínimo');
                }

                const data = await response.json();
                this.aplicarMinLvls(data);
                const pokemon = this.findAssignedPokemon(pokemonId);
                if (pokemon) {
                    pokemon.min_level = data.new_min_level;
                    pokemon.manual = data.manual;
                }
            } catch (e) {
                alert('Error al actualizar el nivel mínimo: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        async resetPokemonMinLevel(pokemonId) {
            if (this.gestionLoading) return;
            this.gestionLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/pokemon/' + pokemonId + '/min-level', {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ manual: false }),
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'Error al restaurar el nivel mínimo');
                }

                const data = await response.json();
                this.aplicarMinLvls(data);
                const pokemon = this.findAssignedPokemon(pokemonId);
                if (pokemon) {
                    pokemon.min_level = data.new_min_level;
                    pokemon.manual = data.manual;
                }
            } catch (e) {
                alert('Error al restaurar el nivel mínimo: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        async removeFamily(chainId) {
            if (this.gestionLoading) return;
            this.gestionLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/families/' + chainId, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                if (!response.ok) {
                    const err = await response.json();
                    throw new Error(err.message || 'Error al desasignar la familia');
                }

                // Refresco local SIN recargar: quito de asignadas y devuelvo a no asignadas (sin levels, con types).
                const family = (this.assignedFamilies || []).find(f => f.evolution_chain_id === chainId);
                this.assignedFamilies = (this.assignedFamilies || []).filter(f => f.evolution_chain_id !== chainId);

                if (family) {
                    this.unassignedFamilies = [
                        {
                            evolution_chain_id: family.evolution_chain_id,
                            base: { id: family.base?.id, name: family.base?.name, icon: family.base?.icon },
                            evolutions: (family.evolutions || []).map(evo => ({ id: evo.id, name: evo.name, icon: evo.icon })),
                            types: family.types || [],
                        },
                        ...(this.unassignedFamilies || []),
                    ];
                }
            } catch (e) {
                alert('Error al desasignar: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },
    };
}
</script>
@endpush
@endsection
