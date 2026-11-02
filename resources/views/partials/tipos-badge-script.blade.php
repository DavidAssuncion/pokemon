@php
    $tiposBadge = \Src\Shared\UI\TipoBadges::paraJs();
    $tiposBadgePorId = \Src\Shared\UI\TipoBadges::idsParaJs();
    $tiposBadgeDefault = \Src\Shared\UI\TipoBadges::DEFAULT;
@endphp

<script>
    window.TIPOS_BADGE = @json($tiposBadge);
    window.TIPOS_BADGE_POR_ID = @json($tiposBadgePorId);
    window.TIPOS_BADGE_DEFAULT = @json($tiposBadgeDefault);

    (function () {
        const copiaDefault = () => Object.assign({}, window.TIPOS_BADGE_DEFAULT);

        window.tipoBadgePorId = function (id) {
            const slug = window.TIPOS_BADGE_POR_ID[String(id)];

            return slug ? window.tipoBadge(slug) : copiaDefault();
        };

        window.tipoBadge = function (valor) {
            if (valor === null || typeof valor === 'undefined') {
                return copiaDefault();
            }

            const texto = String(valor).trim();

            if (texto === '') {
                return copiaDefault();
            }

            if (/^\d+$/.test(texto)) {
                return window.tipoBadgePorId(texto);
            }

            const slug = texto.toLowerCase();
            const badge = window.TIPOS_BADGE[slug];

            if (badge) {
                return Object.assign({ slug: slug }, badge);
            }

            const entrada = Object.entries(window.TIPOS_BADGE).find(
                ([, datos]) => String(datos.label).toLowerCase() === slug
            );

            return entrada ? Object.assign({ slug: entrada[0] }, entrada[1]) : copiaDefault();
        };
    })();
</script>
