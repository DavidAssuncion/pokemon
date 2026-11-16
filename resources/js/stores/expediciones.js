/**
 * Store Alpine singleton de expediciones de la navbar.
 *
 * El backend es la fuente de verdad: `items` siempre es la colección canónica
 * que devuelve la API, sin ordenar, filtrar ni recalcular. Los fragmentos HTML
 * de historial y detalle se sirven ya renderizados desde Blade.
 */

import { confirmar, fetchHtml, fetchJson } from './comun';

const TTL_REVALIDACION_MS = 15000;

const MENSAJES_ACCION = {
    cancelar: 'Expedición cancelada.',
    recoger: 'Resultados recogidos.',
    cerrar: 'Resultados cerrados.',
    archivar: 'Expedición archivada.',
};

const CONFIRMACIONES = {
    cancelar: '¿Cancelar la expedición? No recibirás recompensas.',
    cerrar: '¿Cerrar los resultados? Esta acción eliminará el registro.',
    archivar: '¿Archivar la expedición? Saldrá del panel y se conservará en el historial.',
};

export function registrarStoreExpediciones(Alpine) {
    let alClickDocumento = null;
    let alFoco = null;
    let alVisibilidad = null;
    let alExpedicionesActualizadas = null;

    Alpine.store('expedicionesNavbar', {
        panelAbierto: false,
        historialAbierto: false,
        detalleAbierto: false,
        detalleId: null,

        items: [],
        cargadoEn: 0,
        cargando: false,
        error: '',

        historialHtml: '',
        historialCargando: false,
        historialError: '',
        detalleHtml: '',
        detalleCargando: false,
        detalleError: '',

        accionEnCurso: '',
        mensaje: '',

        _iniciado: false,

        iniciar() {
            if (this._iniciado) {
                return;
            }

            this._iniciado = true;

            const store = Alpine.store('expedicionesNavbar');

            alClickDocumento = (event) => {
                const boton = event.target.closest?.('[data-expedicion-accion]');

                if (boton) {
                    event.preventDefault();

                    const id = Number(boton.dataset.expedicionId);

                    if (!Number.isInteger(id) || id <= 0) {
                        return;
                    }

                    const contexto = boton.closest('[data-expediciones-contexto]')?.dataset.expedicionesContexto || 'panel';

                    store.ejecutarAccion(id, boton.dataset.expedicionAccion || '', contexto);

                    return;
                }

                if (!store.panelAbierto) {
                    return;
                }

                if (event.target.closest?.('[data-expediciones-panel]')) {
                    return;
                }

                if (event.target.closest?.('[data-expediciones-trigger]')) {
                    return;
                }

                store.cerrarPanel(false);
            };

            document.addEventListener('click', alClickDocumento);

            alFoco = () => {
                store.revalidarSiProcede();
            };

            window.addEventListener('focus', alFoco);

            alVisibilidad = () => {
                if (document.visibilityState === 'visible') {
                    store.revalidarSiProcede();
                }
            };

            document.addEventListener('visibilitychange', alVisibilidad);

            alExpedicionesActualizadas = (event) => {
                const expediciones = event?.detail?.expediciones;

                if (Array.isArray(expediciones)) {
                    store.reemplazar(expediciones);
                }

                if (store.historialAbierto) {
                    store.refrescarHistorial();
                }

                store.refrescarPagina();
            };

            window.addEventListener('expediciones:actualizada', alExpedicionesActualizadas);
        },

        abrirPanel() {
            this.panelAbierto = true;
            this.mensaje = '';

            if (this.items.length === 0 || Date.now() - this.cargadoEn > TTL_REVALIDACION_MS) {
                this.refrescar();
            }
        },

        /**
         * Cierra el panel. `restaurarFoco = false` se usa al cerrar por click
         * fuera: el foco debe quedarse en el elemento que el usuario acaba de
         * pulsar, no saltar al trigger.
         */
        cerrarPanel(restaurarFoco = true) {
            const estabaAbierto = this.panelAbierto;

            this.panelAbierto = false;

            if (estabaAbierto && restaurarFoco) {
                document.querySelector('[data-expediciones-trigger]')?.focus();
            }
        },

        async refrescar() {
            if (this.cargando) {
                return;
            }

            this.cargando = true;
            this.error = '';

            try {
                const data = await fetchJson('/api/exploraciones');

                this.reemplazar(data?.expediciones);
            } catch (error) {
                this.error = error?.message || 'No se pudieron cargar las expediciones.';
            } finally {
                this.cargando = false;
            }
        },

        reemplazar(expediciones) {
            this.items = Array.isArray(expediciones) ? expediciones : [];
            this.cargadoEn = Date.now();
        },

        abrirHistorial() {
            this.historialAbierto = true;
            this.refrescarHistorial();
        },

        cerrarHistorial() {
            this.historialAbierto = false;
        },

        async refrescarHistorial() {
            if (this.historialCargando) {
                return;
            }

            this.historialCargando = true;
            this.historialError = '';

            try {
                this.historialHtml = await fetchHtml('/exploraciones/panel/contenido?incluir_archivadas=1&contexto=historial');
            } catch (error) {
                this.historialError = error?.message || 'No se pudo cargar el historial.';
            } finally {
                this.historialCargando = false;
            }
        },

        abrirDetalle(id) {
            const detalleId = Number(id);

            if (!Number.isInteger(detalleId) || detalleId <= 0) {
                return;
            }

            this.detalleId = detalleId;
            this.detalleAbierto = true;
            this.detalleHtml = '';
            this.detalleError = '';
            this.refrescarDetalle();
        },

        cerrarDetalle() {
            this.detalleAbierto = false;
            this.detalleId = null;
            this.detalleHtml = '';
            this.detalleError = '';
        },

        async refrescarDetalle() {
            const detalleId = this.detalleId;

            if (!detalleId || this.detalleCargando) {
                return;
            }

            this.detalleCargando = true;
            this.detalleError = '';

            try {
                this.detalleHtml = await fetchHtml(`/exploraciones/${detalleId}/panel/detalle`);
            } catch (error) {
                if (error?.status === 404) {
                    this.cerrarDetalle();
                    this.detalleError = 'La expedición ya no está disponible.';
                    this.mensaje = this.detalleError;
                    this.refrescar();

                    return;
                }

                this.detalleError = error?.message || 'No se pudo cargar el detalle.';
            } finally {
                this.detalleCargando = false;
            }
        },

        async ejecutarAccion(id, accion, contexto) {
            if (accion === 'detalle') {
                this.abrirDetalle(id);

                return;
            }

            if (!Object.prototype.hasOwnProperty.call(MENSAJES_ACCION, accion)) {
                return;
            }

            const expedicionId = Number(id);

            if (!Number.isInteger(expedicionId) || expedicionId <= 0) {
                return;
            }

            if (this.accionEnCurso) {
                return;
            }

            if (CONFIRMACIONES[accion] && !confirmar(CONFIRMACIONES[accion])) {
                return;
            }

            this.accionEnCurso = `${accion}:${expedicionId}`;
            this.error = '';
            this.mensaje = '';

            try {
                const data = await fetchJson(`/exploraciones/${expedicionId}/${accion}`, { method: 'POST' });

                if (Array.isArray(data?.expediciones)) {
                    this.reemplazar(data.expediciones);
                }

                this.mensaje = MENSAJES_ACCION[accion];

                if (this.historialAbierto) {
                    this.refrescarHistorial();
                }

                if (this.detalleAbierto) {
                    this.cerrarDetalle();
                }

                this.refrescarPagina();
            } catch (error) {
                this.error = error?.message || 'No se pudo completar la acción.';

                if ([404, 409, 422].includes(Number(error?.status))) {
                    this.refrescar();

                    if (this.historialAbierto) {
                        this.refrescarHistorial();
                    }

                    if (this.detalleAbierto) {
                        this.refrescarDetalle();
                    }
                }
            } finally {
                this.accionEnCurso = '';
            }
        },

        async refrescarPagina() {
            const contenedor = document.getElementById('expediciones-contenido');

            if (!contenedor) {
                return;
            }

            try {
                const html = await fetchHtml('/exploraciones/panel/contenido?incluir_archivadas=0&contexto=pagina');
                const destino = document.getElementById('expediciones-contenido');

                if (destino) {
                    destino.innerHTML = html;
                }
            } catch {
                // La página conserva su contenido anterior; el aviso de acción sigue visible.
            }
        },

        revalidarSiProcede() {
            // atajo: historial y detalle reutilizan el TTL de `cargadoEn` (no tienen
            // timestamp propio), revisar si se necesita revalidación independiente.
            if (Date.now() - this.cargadoEn <= TTL_REVALIDACION_MS) {
                return;
            }

            if (this.panelAbierto) {
                this.refrescar();
            }

            if (this.historialAbierto) {
                this.refrescarHistorial();
            }

            if (this.detalleAbierto) {
                this.refrescarDetalle();
            }

            if (this.panelAbierto || this.historialAbierto || this.detalleAbierto) {
                this.refrescarPagina();
            }
        },
    });
}
