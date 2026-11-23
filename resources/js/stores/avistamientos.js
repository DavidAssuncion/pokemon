/**
 * Store Alpine del modal de avistamientos.
 *
 * El backend entrega el contrato completo (hábitat, familia, factor, niveles,
 * especies y visto). El store solo transporta, abre/cierra el modal y
 * normaliza errores; no calcula reglas ni ordena colecciones.
 */

import { fetchJson } from './comun';

const TTL_REVALIDACION_MS = 60000;

export function registrarStoreAvistamientos(Alpine) {
    let alClickDocumento = null;
    let alTecla = null;

    Alpine.store('avistamientosNavbar', {
        abierto: false,
        cargando: false,
        error: '',
        datos: null,
        cargadoEn: 0,

        _iniciado: false,

        iniciar() {
            if (this._iniciado) {
                return;
            }

            this._iniciado = true;

            const store = Alpine.store('avistamientosNavbar');

            alClickDocumento = (event) => {
                if (!store.abierto) {
                    return;
                }

                if (event.target.closest?.('[data-avistamientos-panel]')) {
                    return;
                }

                if (event.target.closest?.('[data-avistamientos-trigger]')) {
                    return;
                }

                store.cerrar();
            };

            document.addEventListener('click', alClickDocumento);

            alTecla = (event) => {
                if (event.key === 'Escape' && store.abierto) {
                    store.cerrar();
                }
            };

            document.addEventListener('keydown', alTecla);
        },

        async alternar() {
            if (this.abierto) {
                this.cerrar();

                return;
            }

            await this.abrir();
        },

        async abrir() {
            this.abierto = true;

            if (this.datos === null || Date.now() - this.cargadoEn > TTL_REVALIDACION_MS) {
                await this.refrescar();
            }
        },

        cerrar() {
            this.abierto = false;
        },

        async refrescar() {
            if (this.cargando) {
                return;
            }

            this.cargando = true;
            this.error = '';

            try {
                this.datos = await fetchJson('/api/avistamientos/vigente');
                this.cargadoEn = Date.now();
            } catch (error) {
                this.error = error?.message || 'No se pudo cargar el avistamiento.';
            } finally {
                this.cargando = false;
            }
        },
    });
}
