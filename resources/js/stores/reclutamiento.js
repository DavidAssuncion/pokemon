/**
 * Store Alpine singleton de reclutamiento de la navbar.
 *
 * `items` es siempre la lista canónica devuelta por el backend. La página y el
 * modal comparten la misma instancia: cualquier reemplazo se refleja en ambos.
 */

import { fetchJson } from './comun';

const TTL_REVALIDACION_MS = 15000;

const PORCENTAJES_DESCARTE = [25, 50, 75, 100];

export function registrarStoreReclutamiento(Alpine) {
    let alFoco = null;
    let alVisibilidad = null;

    Alpine.store('reclutamientoNavbar', {
        abierto: false,
        items: [],
        cargadoEn: 0,
        cargando: false,
        error: '',
        accionEnCurso: '',
        mensaje: '',

        descarteAbierto: false,
        descarte: null,
        descarteCantidad: 1,
        descarteTodosAbierto: false,

        _iniciado: false,

        iniciar() {
            if (this._iniciado) {
                return;
            }

            this._iniciado = true;

            const store = Alpine.store('reclutamientoNavbar');

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
        },

        hidratar(items) {
            this._reemplazar(items);
        },

        abrir() {
            this.abierto = true;
            this.mensaje = '';

            if (this.items.length === 0 || Date.now() - this.cargadoEn > TTL_REVALIDACION_MS) {
                this.refrescar();
            }
        },

        cerrar() {
            this.abierto = false;
            this.descarteAbierto = false;
            this.descarte = null;
            this.descarteCantidad = 1;
            this.descarteTodosAbierto = false;
        },

        async refrescar() {
            if (this.cargando) {
                return;
            }

            this.cargando = true;
            this.error = '';

            try {
                const data = await fetchJson('/api/reclutamiento');

                this._reemplazar(data?.reclutables);
            } catch (error) {
                this.error = error?.message || 'No se pudieron cargar los reclutables.';
            } finally {
                this.cargando = false;
            }
        },

        async reclutar(id) {
            const reclutableId = Number(id);

            if (!Number.isInteger(reclutableId) || reclutableId <= 0 || this.accionEnCurso) {
                return;
            }

            this.accionEnCurso = `reclutar:${reclutableId}`;
            this.error = '';
            this.mensaje = '';

            try {
                const data = await fetchJson('/reclutamiento/recruit', {
                    method: 'POST',
                    body: { reclutable_id: reclutableId },
                });

                this._reemplazar(data?.reclutables);
                this.mensaje = 'Pokémon reclutado.';
            } catch (error) {
                this.error = error?.message || 'No se pudo reclutar.';
                this._resincronizarSiHaceFalta(error);
            } finally {
                this.accionEnCurso = '';
            }
        },

        solicitarDescarte(id) {
            const reclutableId = Number(id);
            const item = this.items.find((actual) => Number(actual.id) === reclutableId);

            if (!item) {
                return;
            }

            this.descarte = {
                id: reclutableId,
                nombre: item.nombre,
                cantidad: Number(item.cantidad),
            };
            this.descarteCantidad = 1;
            this.descarteAbierto = true;
        },

        setPorcentaje(pct) {
            const total = Number(this.descarte?.cantidad ?? 0);
            const porcentaje = Number(pct);

            if (total <= 0 || !PORCENTAJES_DESCARTE.includes(porcentaje)) {
                return;
            }

            if (porcentaje === 100) {
                this.descarteCantidad = total;

                return;
            }

            this.descarteCantidad = Math.max(1, Math.round((total * porcentaje) / 100));
        },

        async confirmarDescarte() {
            const descarte = this.descarte;

            if (!descarte || this.accionEnCurso) {
                return;
            }

            const cantidad = Math.max(
                1,
                Math.min(Number(this.descarteCantidad) || 1, Number(descarte.cantidad) || 1),
            );

            this.accionEnCurso = `descartar:${descarte.id}`;
            this.error = '';
            this.mensaje = '';

            try {
                const data = await fetchJson('/reclutamiento/discard', {
                    method: 'POST',
                    body: { reclutable_id: descarte.id, cantidad },
                });

                this._reemplazar(data?.reclutables);
                this.mensaje = 'Pokémon descartado.';
                this.descarteAbierto = false;
                this.descarte = null;
                this.descarteCantidad = 1;
            } catch (error) {
                this.error = error?.message || 'No se pudo descartar.';
                this._resincronizarSiHaceFalta(error);
            } finally {
                this.accionEnCurso = '';
            }
        },

        solicitarDescarteTodos() {
            if (this.items.length === 0) {
                return;
            }

            this.descarteTodosAbierto = true;
        },

        async descartarTodos() {
            if (this.accionEnCurso) {
                return;
            }

            this.accionEnCurso = 'descartar-todos';
            this.error = '';
            this.mensaje = '';

            try {
                const data = await fetchJson('/reclutamiento/discard-all', { method: 'POST' });

                this._reemplazar(data?.reclutables ?? []);
                this.mensaje = 'Todos los Pokémon descartados.';
                this.descarteTodosAbierto = false;
            } catch (error) {
                this.error = error?.message || 'No se pudieron descartar los Pokémon.';
                this._resincronizarSiHaceFalta(error);
            } finally {
                this.accionEnCurso = '';
            }
        },

        revalidarSiProcede() {
            if (!this.abierto || Date.now() - this.cargadoEn <= TTL_REVALIDACION_MS) {
                return;
            }

            this.refrescar();
        },

        _reemplazar(items) {
            this.items = Array.isArray(items) ? items : [];
            this.cargadoEn = Date.now();
        },

        _resincronizarSiHaceFalta(error) {
            if ([404, 409, 422].includes(Number(error?.status))) {
                this.refrescar();
            }
        },
    });
}
