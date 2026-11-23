/**
 * Adaptador propio para reproducir las animaciones de movimientos de
 * Pokémon Showdown sobre el campo de batalla de Laravel.
 *
 * El bundle `showdown-anims.js` se genera a partir de:
 * - play.pokemonshowdown.com/src/battle-animations-moves.ts (CC0)
 * - play.pokemonshowdown.com/src/battle-animations.ts (MIT/CC0)
 *
 * Las imágenes viven en /showdown/fx y conservan sus licencias originales.
 * Este adaptador no usa jQuery: traduce las llamadas de Showdown a Web
 * Animations API y posiciona los efectos sobre el grid CSS del campo.
 */

import { BattleMoveAnims, BattleEffects } from './showdown-anims.js';
import moveMap from './move-map.js';

const FX_BASE = '/showdown/fx/';
const MAX_ANIMATION_MS = 5000;
const CARD_SELECTOR = '.pokemon-card[data-ref-id]';

const EASING = {
    linear: 'linear',
    decel: 'cubic-bezier(0.15, 0.85, 0.35, 1)',
    accel: 'cubic-bezier(0.55, 0.05, 0.85, 0.4)',
    ballistic: 'cubic-bezier(0.25, 0.75, 0.5, 1.25)',
    ballistic2: 'cubic-bezier(0.3, 0.7, 0.4, 1.15)',
    ballistic2Back: 'cubic-bezier(0.3, 0.7, 0.4, 1.15)',
    ballistic2Under: 'cubic-bezier(0.3, 0.7, 0.4, 1.15)',
    swing: 'ease-in-out',
};

function prefiereMovimientoReducido() {
    return typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function normalizarIdMovimiento(valor) {
    return String(valor ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');
}

function buscarCarta(refId) {
    if (!refId) {
        return null;
    }

    const selector = `.pokemon-card[data-ref-id="${String(refId).replace(/"/g, '\\"')}"]`;

    return document.querySelector(selector);
}

function datosEfecto(efecto) {
    if (typeof efecto === 'string') {
        return BattleEffects[efecto] ?? BattleEffects.wisp ?? null;
    }

    return efecto ?? BattleEffects.wisp ?? null;
}

function urlEfecto(datos) {
    const url = String(datos?.url ?? '');

    if (url === '') {
        return '';
    }

    if (/^(?:https?:|data:|\/)/.test(url)) {
        return url;
    }

    return FX_BASE + url;
}

function resolverAnimacionMovimiento(slug) {
    const normalizado = normalizarIdMovimiento(slug);
    const mapeado = moveMap[slug] ?? moveMap[normalizado] ?? normalizado;

    // Sin animación específica: la cola usará la embestida estándar de antes.
    return BattleMoveAnims[mapeado] ?? BattleMoveAnims[normalizado] ?? null;
}

async function reproducirAtaqueGenerico(carta) {
    if (!carta) {
        return;
    }

    carta.classList.remove('anim-ataque-generico');
    void carta.offsetWidth;
    carta.classList.add('anim-ataque-generico');

    await new Promise((resolve) => {
        window.setTimeout(resolve, 450);
    });

    carta.classList.remove('anim-ataque-generico');
}

function crearCapas(campo) {
    let fx = campo.querySelector(':scope > .battle-fx-layer');

    if (!fx) {
        fx = document.createElement('div');
        fx.className = 'battle-fx-layer';
        fx.setAttribute('aria-hidden', 'true');
        campo.appendChild(fx);
    }

    let bg = campo.querySelector(':scope > .battle-bg-effect');

    if (!bg) {
        bg = document.createElement('div');
        bg.className = 'battle-bg-effect';
        bg.setAttribute('aria-hidden', 'true');
        campo.insertBefore(bg, fx);
    }

    return { fx, bg };
}

function crearProxyFondo(campo) {
    let temblorProgramado = false;

    const proxy = {
        animate() {
            if (!temblorProgramado) {
                temblorProgramado = true;
                campo.classList.remove('battle-tierra');
                void campo.offsetWidth;
                campo.classList.add('battle-tierra');
                window.setTimeout(() => {
                    campo.classList.remove('battle-tierra');
                    temblorProgramado = false;
                }, 900);
            }

            return proxy;
        },
        delay() {
            return proxy;
        },
        css() {
            return proxy;
        },
        queue() {
            return proxy;
        },
        promise() {
            return Promise.resolve();
        },
    };

    return proxy;
}

function crearEscena(campo) {
    const capas = crearCapas(campo);
    const rect = campo.getBoundingClientRect();
    const factor = Math.max(0.35, Math.min(rect.width / 640, rect.height / 360));

    const escena = {
        campo,
        capaFx: capas.fx,
        capaFondo: capas.bg,
        rect,
        factor,
        timeOffset: 0,
        maxEnd: 0,
        temporizadores: [],
        combatientes: [],
        battle: {
            gen: 9,
            gameType: 'singles',
            mySide: { x: 0, y: 0, z: 0, leftof: (offset) => offset },
            farSide: { x: 0, y: 0, z: 0, leftof: (offset) => offset },
        },
        $bg: crearProxyFondo(campo),

        wait(ms) {
            this.timeOffset += Math.max(0, Number(ms) || 0);
        },

        pos(loc = {}, datos = {}) {
            const z = Number(loc.z) || 0;
            const x = Number(loc.x) || 0;
            const y = Number(loc.y) || 0;
            const profundidad = Math.max(0, Math.min(1, z / 200));
            const baseX = this.rect.width * (0.25 + 0.5 * profundidad);
            const baseY = this.rect.height * 0.62;
            const escalaSolicitada = Number(loc.scale);
            const escalaBase = Math.max(
                0.01,
                (Number.isFinite(escalaSolicitada) ? escalaSolicitada : 1) * this.factor,
            );
            const xSolicitada = Number(loc.xscale);
            const ySolicitada = Number(loc.yscale);
            const escalaX = Number.isFinite(xSolicitada)
                ? Math.max(0.01, xSolicitada)
                : escalaBase;
            const escalaY = Number.isFinite(ySolicitada)
                ? Math.max(0.01, ySolicitada)
                : escalaBase;
            const ancho = Math.max(1, (Number(datos.w) || 64) * escalaX);
            const alto = Math.max(1, (Number(datos.h) || 64) * escalaY);

            return {
                left: baseX + x * this.factor - ancho / 2,
                top: baseY - y * this.factor - alto / 2,
                width: ancho,
                height: alto,
                opacity: loc.opacity ?? 1,
            };
        },

        showEffect(efecto, inicio = {}, fin = {}, transicion = 'linear', despues = null) {
            const datos = datosEfecto(efecto);
            const url = urlEfecto(datos);

            if (url === '') {
                return null;
            }

            const inicioMs = Math.max(0, Number(inicio.time) || 0) + this.timeOffset;
            const finSolicitado = Number(fin.time);
            const finMs = Math.max(
                inicioMs + 1,
                Number.isFinite(finSolicitado) ? finSolicitado : inicioMs + 500,
            );
            const duracion = Math.max(1, finMs - inicioMs);
            const posInicio = this.pos({ ...inicio, time: undefined }, datos);
            const posFin = this.pos({ ...inicio, ...fin, time: undefined }, datos);
            const imagen = document.createElement('img');

            imagen.src = url;
            imagen.alt = '';
            imagen.className = 'battle-fx';
            imagen.setAttribute('aria-hidden', 'true');

            if (datos?.pixelated) {
                imagen.classList.add('pixelated');
            }

            Object.assign(imagen.style, {
                left: `${posInicio.left}px`,
                top: `${posInicio.top}px`,
                width: `${posInicio.width}px`,
                height: `${posInicio.height}px`,
                opacity: String(posInicio.opacity),
            });

            this.capaFx.appendChild(imagen);

            imagen.animate(
                [
                    {
                        left: `${posInicio.left}px`,
                        top: `${posInicio.top}px`,
                        width: `${posInicio.width}px`,
                        height: `${posInicio.height}px`,
                        opacity: posInicio.opacity,
                    },
                    {
                        left: `${posFin.left}px`,
                        top: `${posFin.top}px`,
                        width: `${posFin.width}px`,
                        height: `${posFin.height}px`,
                        opacity: posFin.opacity,
                    },
                ],
                {
                    duration: duracion,
                    delay: inicioMs,
                    easing: EASING[transicion] ?? EASING.linear,
                    fill: 'forwards',
                },
            );

            let extra = 0;

            if (despues === 'fade') {
                extra = 100;
                imagen.animate(
                    [{ opacity: posFin.opacity }, { opacity: 0 }],
                    {
                        duration: 100,
                        delay: inicioMs + duracion,
                        easing: 'linear',
                        fill: 'forwards',
                    },
                );
            } else if (despues === 'explode') {
                extra = 200;
                imagen.animate(
                    [
                        {
                            left: `${posFin.left}px`,
                            top: `${posFin.top}px`,
                            width: `${posFin.width}px`,
                            height: `${posFin.height}px`,
                            opacity: posFin.opacity,
                        },
                        {
                            left: `${posFin.left - posFin.width}px`,
                            top: `${posFin.top - posFin.height}px`,
                            width: `${posFin.width * 3}px`,
                            height: `${posFin.height * 3}px`,
                            opacity: 0,
                        },
                    ],
                    {
                        duration: 200,
                        delay: inicioMs + duracion,
                        easing: 'linear',
                        fill: 'forwards',
                    },
                );
            }

            this.seguir(inicioMs + duracion + extra + 80);

            window.setTimeout(() => imagen.remove(), Math.min(MAX_ANIMATION_MS, inicioMs + duracion + extra + 120));

            return imagen;
        },

        animateEffect(efecto, inicio, fin, transicion, despues) {
            return this.showEffect(efecto, inicio, fin, transicion, despues);
        },

        backgroundEffect() {
            // Los fondos de Showdown (oscurecer el campo, teñirlo de un color)
            // se omiten: se conserva solo la animación de ataque.
        },

        seguir(ms) {
            this.maxEnd = Math.max(this.maxEnd, Math.max(0, Number(ms) || 0));
        },

        finishAnimations() {
            const total = Math.min(MAX_ANIMATION_MS, Math.max(0, this.maxEnd));

            return new Promise((resolve) => {
                if (total <= 0) {
                    resolve();

                    return;
                }

                this.temporizadores.push(window.setTimeout(resolve, total));
            }).finally(() => {
                this.limpiar();
            });
        },

        limpiar() {
            this.temporizadores.forEach((temporizador) => window.clearTimeout(temporizador));
            this.temporizadores = [];
            this.maxEnd = 0;
            this.timeOffset = 0;

            this.capaFx.querySelectorAll('.battle-fx').forEach((elemento) => elemento.remove());
            this.capaFondo.querySelectorAll('.battle-bg-fx').forEach((elemento) => elemento.remove());
            this.campo.classList.remove('battle-tierra');

            this.combatientes.forEach((combatiente) => combatiente.limpiar());
            this.combatientes = [];
        },
    };

    return escena;
}

function crearCombatiente(carta, escena) {
    const equipo = Number(carta.dataset.team) || 0;
    const esRival = equipo === 1;
    const sprite = carta.querySelector('.pokemon-sprite') ?? carta;
    const rectCarta = carta.getBoundingClientRect();
    const rectSprite = sprite.getBoundingClientRect();
    const rectReferencia = rectSprite.width > 0 && rectSprite.height > 0
        ? rectSprite
        : rectCarta;
    const rectCampo = escena.rect;
    const profundidad = esRival ? 200 : 0;
    const baseX = rectCampo.width * (esRival ? 0.75 : 0.25);
    const baseY = rectCampo.height * 0.62;
    const centroX = rectReferencia.left + rectReferencia.width / 2 - rectCampo.left;
    const centroY = rectReferencia.top + rectReferencia.height / 2 - rectCampo.top;
    const x = (centroX - baseX) / escena.factor;
    const y = (baseY - centroY) / escena.factor;
    const homeX = baseX + x * escena.factor;
    const homeY = baseY - y * escena.factor;
    const estado = { x: homeX, y: homeY, scale: 1, xscale: 1, yscale: 1, opacity: 1 };
    const animaciones = [];
    const estilosPrevios = {
        overflow: carta.style.overflow,
        position: carta.style.position,
        zIndex: carta.style.zIndex,
    };

    // La carta recorta su contenido (`overflow: hidden`); el sprite debe poder
    // salir de la carta para las embestidas y proyectiles de Showdown.
    carta.style.overflow = 'visible';
    carta.style.position = 'relative';
    carta.style.zIndex = '40';

    const combatiente = {
        carta,
        el: sprite,
        scene: escena,
        x,
        y,
        z: profundidad,
        homeX,
        homeY,
        baseY,
        isFrontSprite: esRival,
        sp: {
            w: sprite.naturalWidth || sprite.width || 96,
            h: sprite.naturalHeight || sprite.height || 96,
            url: sprite.currentSrc || sprite.src || '',
            gen: 9,
        },
        $el: {
            css() {},
            animate() {},
            delay() {},
        },
        _timeline: 0,
        _state: estado,
        _animaciones: animaciones,

        anim(fin = {}, transicion = 'linear') {
            const tiempoSolicitado = Number(fin.time);
            const duracion = Number.isFinite(tiempoSolicitado)
                ? Math.max(0, tiempoSolicitado)
                : 500;

            // La posición se calcula en coordenadas absolutas del campo. El
            // desplazamiento debe incluir el salto entre la base del jugador
            // (25 %) y la del rival (75 %), no solo la diferencia de offsets.
            const destinoZ = Math.max(0, Math.min(200, fin.z ?? this.z));
            const destinoBaseX = escena.rect.width * (0.25 + 0.5 * (destinoZ / 200));
            const destinoX = destinoBaseX + (fin.x ?? this.x) * escena.factor;
            const destinoY = this.baseY - (fin.y ?? this.y) * escena.factor;
            const escala = fin.scale ?? 1;
            const destino = {
                x: destinoX,
                y: destinoY,
                scale: escala,
                xscale: fin.xscale ?? escala,
                yscale: fin.yscale ?? escala,
                opacity: fin.opacity ?? 1,
            };

            const desde = { ...this._state };

            if (duracion <= 0) {
                this._state = destino;
                this.aplicarEstado(destino);

                return this;
            }

            const animacion = this.el.animate(
                [
                    {
                        transform: `translate(${desde.x - this.homeX}px, ${desde.y - this.homeY}px) scale(${desde.xscale}, ${desde.yscale})`,
                        opacity: desde.opacity,
                    },
                    {
                        transform: `translate(${destino.x - this.homeX}px, ${destino.y - this.homeY}px) scale(${destino.xscale}, ${destino.yscale})`,
                        opacity: destino.opacity,
                    },
                ],
                {
                    duration: duracion,
                    delay: this._timeline,
                    easing: EASING[transicion] ?? EASING.linear,
                    fill: 'forwards',
                },
            );

            animaciones.push(animacion);
            this._timeline += duracion;
            this._state = destino;
            escena.seguir(this._timeline);

            return this;
        },

        delay(ms) {
            this._timeline += Math.max(0, Number(ms) || 0);
            escena.seguir(this._timeline);

            return this;
        },

        behind(offset) {
            return this.z + (esRival ? Number(offset) || 0 : -(Number(offset) || 0));
        },

        leftof(offset) {
            return this.x + (esRival ? Number(offset) || 0 : -(Number(offset) || 0));
        },

        behindx(offset) {
            return this.x + (esRival ? Number(offset) || 0 : -(Number(offset) || 0));
        },

        behindy(offset) {
            return this.y + (esRival ? -(Number(offset) || 0) : Number(offset) || 0);
        },

        aplicarEstado(valor) {
            this.el.style.transform = `translate(${valor.x - this.homeX}px, ${valor.y - this.homeY}px) scale(${valor.xscale}, ${valor.yscale})`;
            this.el.style.opacity = String(valor.opacity);
        },

        limpiar() {
            animaciones.forEach((animacion) => animacion.cancel());
            animaciones.length = 0;
            this.el.style.transform = '';
            this.el.style.opacity = '';
            this._timeline = 0;
            this._state = { x: homeX, y: homeY, scale: 1, xscale: 1, yscale: 1, opacity: 1 };

            carta.style.overflow = estilosPrevios.overflow;
            carta.style.position = estilosPrevios.position;
            carta.style.zIndex = estilosPrevios.zIndex;
        },
    };

    return combatiente;
}

function mostrarPildorasDetonacion(objetivos) {
    (Array.isArray(objetivos) ? objetivos : []).forEach((objetivo) => {
        const carta = buscarCarta(objetivo?.id);
        const dano = Math.ceil(Number(objetivo?.dano) || 0);

        if (!carta || dano <= 0) {
            return;
        }

        const pildora = document.createElement('span');
        pildora.className = 'vital-delta vital-delta-dano';
        pildora.textContent = `-${dano} PS`;
        pildora.style.setProperty('--delta-y', '0px');
        pildora.setAttribute('aria-hidden', 'true');
        carta.appendChild(pildora);
        window.setTimeout(() => pildora.remove(), 1400);
    });
}

async function reproducirAnimacionDetonacion(portadorId, objetivos = []) {
    if (prefiereMovimientoReducido()) {
        mostrarPildorasDetonacion(objetivos);

        return;
    }

    const campo = document.querySelector('.battle-field');
    const carta = buscarCarta(portadorId);

    if (!campo || !carta) {
        mostrarPildorasDetonacion(objetivos);

        return;
    }

    const escena = crearEscena(campo);
    const portador = crearCombatiente(carta, escena);
    escena.combatientes.push(portador);

    const { x, y, z } = portador;

    escena.showEffect(
        'fireball',
        { x: x + 40, y, z, scale: 0, opacity: 0.6, time: 0 },
        { scale: 6, opacity: 0, time: 500 },
        'decel',
    );
    escena.showEffect(
        'fireball',
        { x: x - 40, y: y - 20, z, scale: 0, opacity: 0.6, time: 150 },
        { scale: 6, opacity: 0, time: 650 },
        'decel',
    );
    escena.showEffect(
        'fireball',
        { x: x + 10, y: y + 20, z, scale: 0, opacity: 0.6, time: 300 },
        { scale: 6, opacity: 0, time: 800 },
        'decel',
    );
    escena.$bg.animate();

    await escena.finishAnimations();
    mostrarPildorasDetonacion(objetivos);
}

let reproduciendo = false;

function objetivosDelEvento(actorId, objetivoId, cambios) {
    const objetivoBase = Array.isArray(objetivoId)
        ? objetivoId
        : (objetivoId ? [objetivoId] : []);
    const danados = [];

    const agregar = (refId) => {
        if (!refId || refId === actorId || danados.includes(refId)) {
            return;
        }

        danados.push(refId);
    };

    objetivoBase.forEach(agregar);

    Object.entries(cambios || {}).forEach(([refId, cambio]) => {
        const delta = cambio?.delta || {};
        const recibioDanio = (delta.hp || 0) < 0
            || (delta.def || 0) < 0
            || (delta.spdef || 0) < 0;

        if (recibioDanio) {
            agregar(refId);
        }
    });

    return danados;
}

async function reproducirAnimacionMovimiento(slug, actorId, objetivoId, categoria = '', cambios = {}) {
    if (prefiereMovimientoReducido() || reproduciendo) {
        return;
    }

    const campo = document.querySelector('.battle-field');
    const cartaAtacante = buscarCarta(actorId);

    if (!campo || !cartaAtacante) {
        return;
    }

    const animacion = resolverAnimacionMovimiento(slug);

    if (!animacion || typeof animacion.anim !== 'function') {
        reproduciendo = true;

        try {
            await reproducirAtaqueGenerico(cartaAtacante);
        } finally {
            reproduciendo = false;
        }

        return;
    }

    reproduciendo = true;
    const escena = crearEscena(campo);
    const atacante = crearCombatiente(cartaAtacante, escena);
    atacante.carta.style.zIndex = '85';

    const defensores = [];
    const vistos = new Set();

    objetivosDelEvento(actorId, objetivoId, cambios).forEach((refId) => {
        if (vistos.has(refId)) {
            return;
        }

        vistos.add(refId);

        if (refId === actorId) {
            defensores.push(atacante);

            return;
        }

        const carta = buscarCarta(refId);

        if (carta) {
            defensores.push(crearCombatiente(carta, escena));
        }
    });

    if (defensores.length === 0) {
        defensores.push(atacante);
    }

    escena.combatientes.push(
        atacante,
        ...defensores.filter((defensor) => defensor !== atacante),
    );

    try {
        animacion.anim(escena, [atacante, ...defensores]);
        await escena.finishAnimations();
    } catch (error) {
        console.warn('[battle-animations] No se pudo reproducir la animación', slug, error);
        escena.limpiar();
    } finally {
        reproduciendo = false;
    }
}

if (typeof window !== 'undefined') {
    window.reproducirAnimacionMovimiento = reproducirAnimacionMovimiento;
    window.reproducirAnimacionDetonacion = reproducirAnimacionDetonacion;
}

export { reproducirAnimacionDetonacion, reproducirAnimacionMovimiento };
