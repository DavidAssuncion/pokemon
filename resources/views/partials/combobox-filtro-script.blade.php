{{--
    Factoría Alpine del combobox de filtros de la Pokédex.

    Se declara como función global (no módulo) por la misma razón que pokedexApp():
    los scripts inline del body se ejecutan antes del módulo diferido que arranca
    Alpine. No conoce el estado de la página: emite `combobox-seleccionado` con
    { name, id, label } y cada vista decide qué hacer.
--}}
<script>
function comboboxFiltro(opciones, nombre, etiqueta) {
    return {
        nombre: nombre,
        etiqueta: etiqueta,
        opciones: Array.isArray(opciones) ? opciones : [],
        abierto: false,
        consulta: '',
        seleccion: null,
        resaltado: 0,

        get filtradas() {
            const consulta = this.normalizar(this.consulta).trim();
            if (consulta === '') {
                return this.opciones;
            }
            return this.opciones.filter((opcion) => this.normalizar(opcion.name).includes(consulta));
        },

        get opcionActivaId() {
            if (!this.abierto) {
                return null;
            }
            const opcion = this.filtradas[this.resaltado];
            return opcion ? this.$root.id + '-op-' + opcion.id : null;
        },

        // Buscador insensible a mayúsculas y acentos (NFD + eliminación de diacríticos).
        normalizar(texto) {
            return String(texto ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        },

        abrir() {
            this.abierto = true;
            this.consulta = '';
            const indice = this.seleccion
                ? this.filtradas.findIndex((opcion) => opcion.id === this.seleccion.id)
                : -1;
            this.resaltado = indice >= 0 ? indice : 0;
            this.$nextTick(() => this.$refs.buscador?.focus());
        },

        cerrar(devolverFoco) {
            this.abierto = false;
            this.consulta = '';
            if (devolverFoco) {
                this.$nextTick(() => this.$refs.trigger?.focus());
            }
        },

        alternar() {
            if (this.abierto) {
                this.cerrar(true);
            } else {
                this.abrir();
            }
        },

        mover(delta) {
            const total = this.filtradas.length;
            if (total === 0) {
                this.resaltado = 0;
                return;
            }
            this.resaltado = (this.resaltado + delta + total) % total;
        },

        confirmar() {
            const opcion = this.filtradas[this.resaltado];
            if (opcion) {
                this.elegir(opcion);
            }
        },

        elegir(opcion) {
            if (!opcion) {
                return;
            }
            this.seleccion = opcion;
            this.$dispatch('combobox-seleccionado', { name: this.nombre, id: opcion.id, label: opcion.name });
            this.cerrar(true);
        },

        limpiar() {
            this.seleccion = null;
            this.consulta = '';
            this.$dispatch('combobox-seleccionado', { name: this.nombre, id: null, label: null });
            this.cerrar(true);
        },
    };
}
</script>
