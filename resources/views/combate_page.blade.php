@extends('layouts.app')

@section('title', 'Combate')

@push('styles')
    @vite(['resources/css/combate.css'])
@endpush

@push('scripts')
    @vite(['resources/js/combate.js'])
    <script>
        /**
         * Animación de vitales y cola de eventos del combate.
         *
         * El servidor devuelve `eventos` con una instantánea por paso (daño,
         * estado, ronda, debilitamiento...). El x-init de livewire/combate.blade.php
         * captura los vitales previos, espera al morph de Livewire y llama aquí:
         *
         *   1. `restaurarVitales` devuelve las barras y contadores al estado previo.
         *   2. Cada evento se aplica en orden: barras, estela, píldoras, temblor,
         *      curación, barrera rota, estado o KO.
         *   3. El evento `fin` deja el estado final.
         *
         * `animarVitales` se conserva como degradación si el servidor no manda
         * eventos (o si el JS de la cola no está disponible).
         */
        const VITALES = ['hp', 'def', 'spdef'];

        const ESTADOS = {
            burn: { icono: '🔥', label: 'Quemadura' },
            poison: { icono: '☠', label: 'Veneno' },
            bad_poison: { icono: '☠', label: 'Tóxico' },
            paralysis: { icono: '⚡', label: 'Parálisis' },
            sleep: { icono: '💤', label: 'Sueño' },
            freeze: { icono: '❄', label: 'Congelado' },
            confusion: { icono: '😵', label: 'Confusión' },
        };

        function instantaneaVitales() {
            const instantanea = {};

            document.querySelectorAll('.pokemon-card[data-ref-id]').forEach((carta) => {
                const datos = { textos: {}, clases: {} };
                datos.hpBajo = carta.classList.contains('hp-bajo');
                datos.hpCritico = carta.classList.contains('hp-critico');
                datos.ko = !!carta.querySelector('.ko-badge');
                VITALES.forEach((recurso) => {
                    const barra = carta.querySelector('.vital-' + recurso);
                    datos[recurso] = barra ? parseFloat(barra.style.width) || 0 : 0;
                    datos.clases[recurso] = barra ? barra.className : '';
                    const numero = carta.querySelector('.vital-' + recurso + '-num');
                    datos.textos[recurso] = numero ? numero.textContent : '';
                });
                instantanea[carta.dataset.refId] = datos;
            });

            return instantanea;
        }

        function restaurarVitales(previos) {
            const cartas = document.querySelectorAll('.pokemon-card[data-ref-id]');

            cartas.forEach((carta) => {
                const datos = previos[carta.dataset.refId];
                if (!datos) {
                    return;
                }

                VITALES.forEach((recurso) => {
                    const barra = carta.querySelector('.vital-' + recurso);
                    const estela = carta.querySelector('.vital-' + recurso + '-trail');
                    const numero = carta.querySelector('.vital-' + recurso + '-num');

                    if (barra) {
                        barra.style.transition = 'none';
                        barra.style.width = datos[recurso] + '%';
                        if (datos.clases[recurso]) {
                            barra.className = datos.clases[recurso];
                        }
                    }
                    if (estela) {
                        estela.style.transition = 'none';
                        estela.style.width = '0%';
                    }
                    if (numero && datos.textos[recurso]) {
                        numero.textContent = datos.textos[recurso];
                    }
                });

                carta.classList.toggle('hp-bajo', datos.hpBajo);
                carta.classList.toggle('hp-critico', datos.hpCritico);

                if (!datos.ko && carta.querySelector('.ko-badge')) {
                    carta.classList.add('estado-final-oculto');
                }
            });

            cartas.forEach((carta) => { void carta.offsetWidth; });
            document.querySelectorAll('.pokemon-card .progress-bar').forEach((barra) => {
                barra.style.transition = '';
            });
        }

        /** Aplica una instantánea absoluta (evento o estado final) a las barras. */
        function aplicarEstadoVitales(estado, animar = true) {
            if (!estado) {
                return;
            }

            Object.entries(estado).forEach(([refId, valores]) => {
                const carta = buscarCarta(refId);
                if (!carta) {
                    return;
                }

                const cambios = [];

                VITALES.forEach((recurso) => {
                    const barra = carta.querySelector('.vital-' + recurso);
                    if (!barra) {
                        return;
                    }

                    const maximo = maximoVital(carta, recurso);
                    const valor = recurso === 'hp'
                        ? valores.hp
                        : (recurso === 'def' ? valores.defHp : valores.spDefHp);
                    const nuevo = maximo > 0
                        ? Math.max(0, Math.min(100, (valor / maximo) * 100))
                        : 0;
                    const previo = parseFloat(barra.style.width) || 0;

                    actualizarNumero(carta, recurso, valor, maximo);

                    if (recurso === 'hp') {
                        const pct = maximo > 0 ? (valor / maximo) * 100 : 0;
                        barra.classList.remove('bg-hp-low', 'bg-hp-mid', 'bg-hp-high');
                        barra.classList.add(pct <= 25 ? 'bg-hp-low' : (pct <= 50 ? 'bg-hp-mid' : 'bg-hp-high'));
                        const vivo = valores.alive !== false;
                        carta.classList.toggle('hp-bajo', vivo && pct <= 25);
                        carta.classList.toggle('hp-critico', vivo && pct <= 10);
                    }

                    if (Math.abs(nuevo - previo) < 0.1) {
                        return;
                    }

                    cambios.push({
                        barra,
                        estela: carta.querySelector('.vital-' + recurso + '-trail'),
                        previo,
                        nuevo,
                    });
                });

                if (cambios.length === 0) {
                    return;
                }

                if (!animar) {
                    cambios.forEach((cambio) => {
                        cambio.barra.style.transition = 'none';
                        cambio.barra.style.width = cambio.nuevo + '%';
                        if (cambio.estela) {
                            cambio.estela.style.width = '0%';
                        }
                    });
                    void carta.offsetWidth;
                    cambios.forEach((cambio) => { cambio.barra.style.transition = ''; });
                    return;
                }

                const barras = carta.querySelectorAll('.progress-bar');
                barras.forEach((barra) => { barra.style.transition = 'none'; });

                cambios.forEach((cambio) => {
                    cambio.barra.style.width = cambio.previo + '%';
                    if (cambio.estela) {
                        cambio.estela.style.width = cambio.nuevo < cambio.previo
                            ? cambio.previo + '%'
                            : '0%';
                    }
                });

                void carta.offsetWidth;

                barras.forEach((barra) => { barra.style.transition = ''; });

                cambios.forEach((cambio) => {
                    cambio.barra.style.width = cambio.nuevo + '%';
                    if (cambio.estela) {
                        cambio.estela.style.width = '0%';
                    }
                });
            });
        }

        function actualizarNumero(carta, recurso, valor, maximo) {
            const numero = carta.querySelector('.vital-' + recurso + '-num');
            if (numero) {
                numero.textContent = Math.ceil(valor) + '/' + Math.ceil(maximo);
            }

            const barra = carta.querySelector('.vital-' + recurso);
            if (barra && maximo > 0) {
                barra.setAttribute('aria-valuenow', String(Math.round((valor / maximo) * 100)));
            }
        }

        function buscarCarta(refId) {
            const cartas = document.querySelectorAll('.pokemon-card[data-ref-id]');
            for (const carta of cartas) {
                if (carta.dataset.refId === refId) {
                    return carta;
                }
            }

            return null;
        }

        /** Máximo del recurso según el contador visible ("actual/máximo") de la tarjeta. */
        function maximoVital(carta, recurso) {
            const contador = carta.querySelector('.vital-' + recurso + '-num');
            if (!contador) {
                return 0;
            }

            const partes = contador.textContent.trim().split('/');

            return partes.length > 1 ? parseFloat(partes[1]) || 0 : 0;
        }

        /** Degradación: anima el diff final si el servidor no manda eventos. */
        function animarVitales(previos) {
            document.querySelectorAll('.pokemon-card[data-ref-id]').forEach((carta) => {
                const anchosPrevios = previos[carta.dataset.refId];
                if (!anchosPrevios) {
                    return;
                }

                const cambios = [];
                let deltaTotal = 0;

                VITALES.forEach((recurso) => {
                    const barra = carta.querySelector('.vital-' + recurso);
                    if (!barra) {
                        return;
                    }

                    const anchoNuevo = parseFloat(barra.style.width) || 0;
                    const anchoPrevio = anchosPrevios[recurso] !== undefined ? anchosPrevios[recurso] : anchoNuevo;
                    if (Math.abs(anchoNuevo - anchoPrevio) < 0.1) {
                        return;
                    }

                    deltaTotal += (anchoNuevo - anchoPrevio) / 100 * maximoVital(carta, recurso);
                    cambios.push({
                        barra: barra,
                        estela: carta.querySelector('.vital-' + recurso + '-trail'),
                        anchoPrevio: anchoPrevio,
                        anchoNuevo: anchoNuevo,
                    });
                });

                if (cambios.length === 0) {
                    return;
                }

                const barras = carta.querySelectorAll('.progress-bar');

                barras.forEach((barra) => { barra.style.transition = 'none'; });
                cambios.forEach((cambio) => {
                    cambio.barra.style.width = cambio.anchoPrevio + '%';
                    if (cambio.estela) {
                        cambio.estela.style.width = cambio.anchoPrevio + '%';
                    }
                });

                void carta.offsetWidth;

                barras.forEach((barra) => { barra.style.transition = ''; });
                cambios.forEach((cambio) => {
                    cambio.barra.style.width = cambio.anchoNuevo + '%';
                    if (cambio.estela) {
                        cambio.estela.style.width = '0%';
                    }
                });

                carta.classList.remove('anim-hit', 'anim-cura');
                void carta.offsetWidth;
                carta.classList.add(deltaTotal < 0 ? 'anim-hit' : 'anim-cura');
                window.setTimeout(() => carta.classList.remove('anim-hit', 'anim-cura'), 1300);

                const delta = Math.round(deltaTotal);
                if (delta !== 0) {
                    mostrarPildora(
                        carta,
                        (delta > 0 ? '+' : '') + delta,
                        delta < 0 ? 'vital-delta-dano' : 'vital-delta-cura',
                        0
                    );
                }
            });
        }

        async function reproducirEventos(eventos, previos) {
            if (!Array.isArray(eventos) || eventos.length === 0) {
                if (previos) {
                    animarVitales(previos);
                }
                return;
            }

            if (previos) {
                restaurarVitales(previos);
            }

            for (const evento of eventos) {
                await reproducirEvento(evento);
            }

            const fin = eventos[eventos.length - 1];
            if (fin && fin.tipo === 'fin' && fin.estado) {
                aplicarEstadoVitales(fin.estado, false);
            }
        }

        async function reproducirEvento(evento) {
            const tipo = evento.tipo || 'damage';

            if (tipo === 'fin') {
                aplicarEstadoVitales(evento.estado, false);
                return;
            }

            if (tipo === 'battle_over') {
                const banner = document.querySelector('.fin-batalla');
                if (banner) {
                    banner.classList.remove('d-none');
                    banner.classList.add('fin-batalla-activa');
                }
                document.querySelector('.battle-over.d-none')?.classList.remove('d-none');
                await esperar(350);
                return;
            }

            if (tipo === 'round') {
                pulsoRonda();
            }

            const cambios = evento.cambios || {};
            const refs = Object.keys(cambios);

            if (refs.length === 0) {
                const ref = evento.objetivoId || evento.actorId;
                const carta = ref ? buscarCarta(ref) : null;
                if (carta && evento.texto) {
                    mostrarPildora(carta, evento.texto, 'vital-delta-bloqueo', 0);
                }
                await esperar(300);
                return;
            }

            const hayDanio = refs.some((ref) => {
                const delta = cambios[ref].delta || {};
                return (delta.hp || 0) < 0 || (delta.def || 0) < 0 || (delta.spdef || 0) < 0;
            });

            if (hayDanio) {
                await golpeStop();
            }

            const estados = {};
            refs.forEach((ref) => { estados[ref] = cambios[ref].actual; });
            aplicarEstadoVitales(estados);

            refs.forEach((ref) => {
                const carta = buscarCarta(ref);
                if (!carta) {
                    return;
                }

                const cambio = cambios[ref];
                const delta = cambio.delta || {};
                const perdida = (delta.hp || 0) < 0 || (delta.def || 0) < 0 || (delta.spdef || 0) < 0;
                const ganancia = (delta.hp || 0) > 0 || (delta.def || 0) > 0 || (delta.spdef || 0) > 0;

                limpiarPildoras(carta);

                let offset = 0;
                if (perdida) {
                    offset = animarDanio(carta, cambio, evento);
                } else if (ganancia) {
                    animarCura(carta, cambio);
                }

                const estadoCambio = cambio.actual && cambio.previo
                    && cambio.actual.status !== cambio.previo.status;
                if (estadoCambio || tipo === 'status_block') {
                    animarEstado(carta, cambio, evento, offset);
                }

                if ((cambio.rotas || []).length > 0) {
                    animarBarreraRota(carta, cambio, offset);
                    offset -= 18;
                }

                if ((evento.muertos || []).includes(ref)) {
                    animarKO(carta, offset);
                }
            });

            await esperar(duracionEvento(tipo, evento));
        }

        function reiniciarAnimacion(elemento, tokenKey, clases, duracion) {
            const token = Date.now() + ':' + Math.random();
            elemento.dataset[tokenKey] = token;

            clases.forEach((clase) => elemento.classList.remove(clase));
            void elemento.offsetWidth;
            clases.forEach((clase) => elemento.classList.add(clase));

            // Solo retira las clases si no las volvió a activar una animación
            // posterior sobre el mismo elemento (eventos encadenados).
            window.setTimeout(() => {
                if (elemento.dataset[tokenKey] !== token) {
                    return;
                }

                clases.forEach((clase) => elemento.classList.remove(clase));
            }, duracion);
        }

        function animarDanio(carta, cambio, evento) {
            const delta = cambio.delta || {};
            const maxHp = maximoVital(carta, 'hp') || 0;
            const perdidaHp = Math.abs(Math.min(0, delta.hp || 0));
            const fuerza = Math.max(0.7, Math.min(1.8, 0.7 + (maxHp > 0 ? (perdidaHp / maxHp) * 4 : 0.5)));

            carta.style.setProperty('--hit-force', fuerza.toFixed(2));
            reiniciarAnimacion(carta, 'hitToken', ['anim-hit', 'anim-impacto'], 800);

            const esTick = evento.tipo === 'round' || evento.tipo === 'status_block';
            const estado = esTick && cambio.actual && cambio.actual.status !== 'none'
                ? (ESTADOS[cambio.actual.status] || null)
                : null;
            const prefijo = estado ? estado.icono + ' ' : '';
            let offset = 0;

            if ((delta.def || 0) < 0) {
                mostrarPildora(carta, prefijo + '-' + Math.ceil(Math.abs(delta.def)) + ' DEF', 'vital-delta-barrera', offset);
                offset -= 18;
            }
            if ((delta.spdef || 0) < 0) {
                mostrarPildora(carta, prefijo + '-' + Math.ceil(Math.abs(delta.spdef)) + ' DEF.ESP', 'vital-delta-barrera', offset);
                offset -= 18;
            }
            if ((delta.hp || 0) < 0) {
                mostrarPildora(carta, prefijo + '-' + Math.ceil(Math.abs(delta.hp)) + ' PS', 'vital-delta-dano', offset);
                offset -= 18;
            }

            return offset;
        }

        function animarCura(carta, cambio) {
            const delta = cambio.delta || {};

            reiniciarAnimacion(carta, 'curaToken', ['anim-cura'], 1000);

            let offset = 0;
            if ((delta.hp || 0) > 0) {
                mostrarPildora(carta, '+' + Math.ceil(delta.hp) + ' PS', 'vital-delta-cura', offset);
                offset -= 18;
            }
            if ((delta.def || 0) > 0) {
                mostrarPildora(carta, '+' + Math.ceil(delta.def) + ' DEF', 'vital-delta-cura', offset);
                offset -= 18;
            }
            if ((delta.spdef || 0) > 0) {
                mostrarPildora(carta, '+' + Math.ceil(delta.spdef) + ' DEF.ESP', 'vital-delta-cura', offset);
            }
        }

        function animarEstado(carta, cambio, evento, desplazamiento = 0) {
            const badge = carta.querySelector('.status-badge');
            if (badge) {
                reiniciarAnimacion(badge, 'estadoToken', ['anim-estado'], 700);
            }

            if (evento.tipo === 'status_block' && evento.texto) {
                mostrarPildora(carta, evento.texto, 'vital-delta-bloqueo', desplazamiento);
                return;
            }

            const estado = cambio.actual && cambio.actual.status !== 'none'
                ? (ESTADOS[cambio.actual.status] || null)
                : null;

            if (estado) {
                mostrarPildora(carta, estado.icono + ' ' + estado.label.toUpperCase(), 'vital-delta-estado', desplazamiento);
            } else if (evento.texto) {
                mostrarPildora(carta, evento.texto, 'vital-delta-estado', desplazamiento);
            }
        }

        function animarBarreraRota(carta, cambio, desplazamiento = -36) {
            (cambio.rotas || []).forEach((rota) => {
                const barra = carta.querySelector('.vital-' + rota);
                const progress = barra ? barra.closest('.progress') : null;
                if (!progress) {
                    return;
                }

                reiniciarAnimacion(progress, 'rotaToken', ['anim-barrera-rota'], 800);
            });

            mostrarPildora(carta, 'BARRERA ROTA', 'vital-delta-barrera', desplazamiento);
        }

        function animarKO(carta, desplazamiento = 0) {
            carta.classList.add('anim-ko');
            mostrarPildora(carta, 'K.O.', 'vital-delta-ko', desplazamiento);
        }

        function limpiarPildoras(carta) {
            carta.querySelectorAll('.vital-delta').forEach((pildora) => pildora.remove());
        }

        function mostrarPildora(carta, texto, clase, desplazamiento) {
            const pildora = document.createElement('span');
            pildora.className = 'vital-delta ' + clase;
            pildora.textContent = texto;
            pildora.style.setProperty('--delta-y', (desplazamiento || 0) + 'px');
            pildora.setAttribute('aria-hidden', 'true');
            carta.appendChild(pildora);
            window.setTimeout(() => pildora.remove(), 1400);
        }

        function pulsoRonda() {
            const badge = document.querySelector('.round-info');
            if (!badge) {
                return;
            }

            reiniciarAnimacion(badge, 'roundToken', ['round-pulso'], 700);
        }

        function mostrarModalVictoria() {
            document.querySelector('.fin-batalla.d-none')?.classList.remove('d-none');
            document.querySelector('.battle-over.d-none')?.classList.remove('d-none');
            document.querySelectorAll('.reward-modal.modal-pendiente').forEach((modal) => {
                modal.classList.remove('modal-pendiente');
            });
        }

        async function golpeStop() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            const campo = document.querySelector('.battle-field');
            if (!campo) {
                return;
            }

            campo.classList.add('hit-stop');
            await esperar(60);
            campo.classList.remove('hit-stop');
        }

        function duracionEvento(tipo, evento) {
            if ((evento.muertos || []).length > 0) {
                return 1100;
            }

            const hayCura = Object.values(evento.cambios || {}).some((cambio) => {
                const delta = cambio.delta || {};
                return (delta.hp || 0) > 0 || (delta.def || 0) > 0 || (delta.spdef || 0) > 0;
            });
            if (hayCura) {
                return 900;
            }

            if (tipo === 'round') {
                return 550;
            }
            if (tipo === 'status') {
                return 450;
            }
            if (tipo === 'status_block') {
                return 550;
            }
            if (tipo === 'heal' || tipo === 'effect') {
                return 650;
            }
            if (tipo === 'damage') {
                return 700;
            }

            return 450;
        }

        function esperar(ms) {
            return new Promise((resolve) => window.setTimeout(resolve, ms));
        }

        // Al abrir el registro, mostrar siempre los eventos más recientes.
        document.addEventListener('shown.bs.modal', (event) => {
            if (event.target.id !== 'battle-log-modal') {
                return;
            }
            const cuerpo = event.target.querySelector('.modal-body');
            if (cuerpo) {
                cuerpo.scrollTop = cuerpo.scrollHeight;
            }
        });
    </script>
@endpush

@section('content')
@livewire('combate')
@endsection
