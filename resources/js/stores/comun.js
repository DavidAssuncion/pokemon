/**
 * Helpers compartidos por los stores Alpine de la navbar: transporte HTTP,
 * confirmaciones y pila de modales con gestión de foco.
 *
 * Todo el estado llega del backend: estos helpers solo transportan y
 * normalizan errores. No interpretan ni transforman la colección.
 */

const MENSAJE_SESION_EXPIRADA = 'La sesión ha expirado. Recarga la página.';
const MENSAJE_RED = 'No se pudo conectar con el servidor. Inténtalo de nuevo.';
const MENSAJE_GENERICO = 'No se pudo completar la solicitud.';

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export function confirmar(mensaje) {
    return window.confirm(mensaje);
}

function crearError(mensaje, status = 0, data = null) {
    const error = new Error(mensaje);
    error.status = status;
    error.data = data;

    return error;
}

function datosDeTexto(texto) {
    if (!texto) {
        return null;
    }

    try {
        return JSON.parse(texto);
    } catch {
        return null;
    }
}

function mensajeDeError(status, data) {
    if (status === 419) {
        return MENSAJE_SESION_EXPIRADA;
    }

    return data?.message || data?.error || MENSAJE_GENERICO;
}

function respuestaNoJson(response) {
    const tipo = response.headers.get('content-type') || '';

    return response.redirected || !tipo.includes('application/json');
}

export async function fetchJson(url, { method = 'GET', body = null, headers = {} } = {}) {
    const esEnvio = method.toUpperCase() !== 'GET';
    const cabeceras = { Accept: 'application/json', ...headers };

    if (esEnvio) {
        cabeceras['X-CSRF-TOKEN'] = csrfToken();
        cabeceras['Content-Type'] = 'application/json';
    }

    let response;

    try {
        response = await fetch(url, {
            method,
            headers: cabeceras,
            body: esEnvio && body !== null ? JSON.stringify(body) : undefined,
            cache: 'no-store',
            credentials: 'same-origin',
        });
    } catch {
        throw crearError(MENSAJE_RED);
    }

    const texto = await response.text();
    const data = datosDeTexto(texto);

    if (!response.ok) {
        throw crearError(mensajeDeError(response.status, data), response.status, data);
    }

    if (respuestaNoJson(response)) {
        throw crearError(MENSAJE_SESION_EXPIRADA, 419, data);
    }

    return data;
}

/**
 * Registra el store Alpine `modales`, que mantiene la pila de modales abiertos.
 *
 * La pila resuelve dos cosas que no se pueden delegar al consumidor:
 * - `Escape` cierra solo el modal superior (no toda la pila).
 * - Al cerrar un modal, el foco vuelve al elemento activo antes de abrirlo
 *   (también en confirmaciones anidadas, si ese elemento sigue en el DOM).
 */
export function registrarStoreModales(Alpine) {
    Alpine.store('modales', {
        pila: [],
        focoPrevio: new Map(),

        abrir(id, elemento) {
            if (this.pila.includes(id)) {
                return;
            }

            this.pila.push(id);
            this.focoPrevio.set(id, document.activeElement);

            // queueMicrotask deja que `x-show` aplique `display` antes del focus().
            queueMicrotask(() => elemento?.focus());
        },

        cerrar(id) {
            const indice = this.pila.indexOf(id);

            if (indice !== -1) {
                this.pila.splice(indice, 1);
            }

            const previo = this.focoPrevio.get(id);
            this.focoPrevio.delete(id);

            // Se restaura siempre que el elemento siga en el DOM: al cerrar una
            // confirmación anidada el foco vuelve al botón del modal padre.
            if (previo instanceof HTMLElement && previo.isConnected) {
                previo.focus();
            }
        },

        esTope(id) {
            return this.pila[this.pila.length - 1] === id;
        },
    });
}

/**
 * Trampa de tabulación para un contenedor de modal: al llegar al último
 * elemento enfocable vuelve al primero y viceversa. Si no hay enfocables,
 * mantiene el foco en el contenedor.
 */
export function trampaDeFoco(event, contenedor) {
    if (!contenedor) {
        return;
    }

    const focusables = contenedor.querySelectorAll(
        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    if (focusables.length === 0) {
        event.preventDefault();
        contenedor.focus();

        return;
    }

    const primero = focusables[0];
    const ultimo = focusables[focusables.length - 1];

    if (event.shiftKey && document.activeElement === primero) {
        event.preventDefault();
        ultimo.focus();
    } else if (!event.shiftKey && document.activeElement === ultimo) {
        event.preventDefault();
        primero.focus();
    }
}

export async function fetchHtml(url) {
    let response;

    try {
        response = await fetch(url, {
            method: 'GET',
            // Los fragmentos son HTML: se pide text/html explícitamente, con el
            // mismo transporte (no-store, same-origin) que los GET JSON.
            headers: { Accept: 'text/html' },
            cache: 'no-store',
            credentials: 'same-origin',
        });
    } catch {
        throw crearError(MENSAJE_RED);
    }

    const texto = await response.text();

    if (!response.ok) {
        const data = datosDeTexto(texto);

        throw crearError(mensajeDeError(response.status, data), response.status, data);
    }

    if (response.redirected || !(response.headers.get('content-type') || '').includes('text/html')) {
        throw crearError(MENSAJE_SESION_EXPIRADA, 419, null);
    }

    return texto;
}
