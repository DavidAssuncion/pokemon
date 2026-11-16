{{-- Estado Alpine compartido para la comparación de PC (solo presentación, sin fórmulas). --}}
<script>
function comparadorPcState() {
    return {
        pcComparacion: null,
        pcCargando: false,
        pcError: '',
        pcSolicitud: 0,
        pcPreviewId: null,

        /**
         * Carga la comparación desde el endpoint del contexto.
         * Las respuestas obsoletas se descartan con un token incremental.
         */
        async cargarComparacionPc(url) {
            const token = ++this.pcSolicitud;

            this.pcCargando = true;
            this.pcError = '';

            try {
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('No se pudo calcular la comparación.');
                }

                const data = await response.json();

                if (token !== this.pcSolicitud) {
                    return;
                }

                this.pcComparacion = data;
                this.pcPreviewId = data.preview_id || null;
            } catch (e) {
                if (token !== this.pcSolicitud) {
                    return;
                }

                console.error('Error cargando la comparación de PC:', e);
                this.pcComparacion = null;
                this.pcError = 'No se pudo calcular la comparación. Puedes combatir igualmente.';
            } finally {
                if (token === this.pcSolicitud) {
                    this.pcCargando = false;
                }
            }
        },

        limpiarComparacionPc() {
            this.pcSolicitud += 1;
            this.pcComparacion = null;
            this.pcPreviewId = null;
            this.pcCargando = false;
            this.pcError = '';
        },

        /**
         * Elimina el rival de ruta fijado. No bloquea el cierre del modal ni el combate.
         */
        limpiarPreviewRuta(urlBase) {
            const previewId = this.pcPreviewId;
            this.pcPreviewId = null;

            if (!previewId) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            fetch(urlBase + '/' + encodeURIComponent(previewId), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            }).catch(() => {});
        },

        formatoPc(valor) {
            if (valor === null || valor === undefined) {
                return '—';
            }

            return new Intl.NumberFormat('es-ES').format(valor);
        },

        textoVeredicto(veredicto) {
            const textos = {
                favorable: 'Favorable',
                parejo: 'Parejo',
                desfavorable: 'Desfavorable',
            };

            return textos[veredicto] || '—';
        },

        iconoVeredicto(veredicto) {
            const iconos = {
                favorable: '▲',
                parejo: '＝',
                desfavorable: '▼',
            };

            return iconos[veredicto] || '';
        },

        claseVeredicto(veredicto) {
            if (veredicto === 'favorable') {
                return 'text-success-ink bg-success-soft border-success';
            }

            if (veredicto === 'desfavorable') {
                return 'text-danger-ink bg-danger-soft border-danger';
            }

            return 'text-ink-muted bg-surface-sunken border-line';
        },

        etiquetaPcRival() {
            return this.pcComparacion?.rival?.aproximado
                ? 'PC estimado del rival'
                : 'PC del rival';
        },
    };
}
</script>
