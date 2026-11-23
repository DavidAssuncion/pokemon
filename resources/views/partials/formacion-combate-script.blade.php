{{-- Estado Alpine compartido para el modal de formación (gimnasios y hábitats temporales). --}}
<script>
function formacionCombateState() {
    return {
        showFormacionPopup: false,
        selectedTeamId: null,
        selectedTeamName: '',
        formacion: {},
        popupError: '',
        combating: false,
        teams: [],
        formacionObjetivo: '',
        formacionPcUrl: '',
        formacionCombatUrl: '',
        formacionPuedeCombatir: false,

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

        openFormacionPopup() {
            if (!this.formacionPuedeCombatir) {
                return;
            }

            this.selectedTeamId = null;
            this.selectedTeamName = '';
            this.formacion = {};
            this.popupError = '';
            this.showFormacionPopup = true;
            this.limpiarComparacionPc();
            this.cargarComparacionPc(this.formacionPcUrl);
        },

        selectTeam(id, name) {
            this.selectedTeamId = id;
            this.selectedTeamName = name;
            this.formacion = this.formacionInicialDe(id);
            this.cargarComparacionPc(this.formacionPcUrl + '?team_id=' + this.selectedTeamId);
        },

        closeFormacionPopup() {
            this.showFormacionPopup = false;
            this.popupError = '';
            this.limpiarComparacionPc();
        },

        async confirmarCombate() {
            if (!this.selectedTeamId || this.combating) {
                return;
            }

            this.combating = true;
            this.popupError = '';

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(this.formacionCombatUrl, {
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
                    this.popupError = err.message || 'Error al iniciar combate';
                    return;
                }

                const data = await response.json();
                this.showFormacionPopup = false;
                window.location.href = data.redirect || '/combate?battle_id=' + data.battle_id;
            } catch (e) {
                this.popupError = 'Error al iniciar combate: ' + e.message;
            } finally {
                this.combating = false;
            }
        },

        iconoReclutado(reclutado) {
            if (!reclutado || !reclutado.pokemon_id) {
                return '/images/iconos_webp/0.webp';
            }

            if (reclutado.es_shiny) {
                return '/images/iconos_webp/shiny/' + reclutado.pokemon_id + '_s.webp';
            }

            return '/images/iconos_webp/' + reclutado.pokemon_id + '.webp';
        },

        onSpriteError(event, pokemonId, esShiny) {
            const img = event?.target;
            if (!img) {
                return;
            }

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
    };
}
</script>
