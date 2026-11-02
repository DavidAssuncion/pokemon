<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\Test;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\UI\TipoBadges;
use Tests\TestCase;

final class TipoBadgesTest extends TestCase
{
    #[Test]
    public function cubre_los_18_tipos_con_label_slug_clases_e_icono(): void
    {
        $this->assertCount(18, TipoPokemon::cases());

        foreach (TipoPokemon::cases() as $tipo) {
            $badge = TipoBadges::porId($tipo->value);

            $this->assertSame($tipo->value, $badge['id']);
            $this->assertSame($tipo->label(), $badge['label']);
            $this->assertSame($tipo->slug(), $badge['slug']);
            $this->assertNotSame('', $badge['clases']);
            $this->assertSame('/images/type/'.$tipo->slug().'.webp', $badge['icono']);
        }
    }

    #[Test]
    public function los_slugs_son_unicos_y_coinciden_con_el_enum(): void
    {
        $slugs = array_map(
            static fn (TipoPokemon $tipo): string => TipoBadges::porId($tipo->value)['slug'],
            TipoPokemon::cases(),
        );

        $this->assertSame($slugs, array_values(array_unique($slugs)));

        foreach (TipoPokemon::cases() as $tipo) {
            $this->assertSame($tipo->slug(), TipoBadges::porSlug($tipo->slug())['slug']);
        }
    }

    #[Test]
    public function por_id_por_slug_y_por_label_son_equivalentes(): void
    {
        foreach (TipoPokemon::cases() as $tipo) {
            $porId = TipoBadges::porId($tipo->value);
            $porSlug = TipoBadges::porSlug($tipo->slug());
            $porLabel = TipoBadges::porLabel($tipo->label());

            $this->assertSame($porId, $porSlug);
            $this->assertSame($porId, $porLabel);
        }
    }

    #[Test]
    public function las_clases_no_estan_vacias_en_los_18_tipos(): void
    {
        foreach (TipoPokemon::cases() as $tipo) {
            $this->assertNotSame('', TipoBadges::clases($tipo->value));
            $this->assertSame(TipoBadges::porId($tipo->value)['clases'], TipoBadges::clases($tipo->value));
        }
    }

    #[Test]
    public function los_18_iconos_existen_en_public(): void
    {
        foreach (TipoPokemon::cases() as $tipo) {
            $ruta = public_path('images/type/'.$tipo->slug().'.webp');

            $this->assertTrue(
                file_exists($ruta),
                sprintf('Falta el icono del tipo %s en %s', $tipo->name, $ruta),
            );
            $this->assertSame('/images/type/'.$tipo->slug().'.webp', TipoBadges::icono($tipo->value));
        }
    }

    #[Test]
    public function resolver_acepta_id_slug_y_label(): void
    {
        $this->assertSame('electrico', TipoBadges::resolver(13)['slug']);
        $this->assertSame('electrico', TipoBadges::resolver('13')['slug']);
        $this->assertSame('electrico', TipoBadges::resolver('electrico')['slug']);
        $this->assertSame('electrico', TipoBadges::resolver('ELECTRICO')['slug']);
        $this->assertSame('electrico', TipoBadges::resolver('Eléctrico')['slug']);
        $this->assertSame('psiquico', TipoBadges::resolver('Psíquico')['slug']);
        $this->assertSame('dragon', TipoBadges::resolver('Dragón')['slug']);
    }

    #[Test]
    public function un_tipo_desconocido_devuelve_default(): void
    {
        $this->assertSame(TipoBadges::DEFAULT, TipoBadges::porId(999));
        $this->assertSame(TipoBadges::DEFAULT, TipoBadges::porId(0));
        $this->assertSame(TipoBadges::DEFAULT, TipoBadges::porSlug('inexistente'));
        $this->assertSame(TipoBadges::DEFAULT, TipoBadges::porLabel('Inexistente'));
        $this->assertSame(TipoBadges::DEFAULT, TipoBadges::resolver(null));
        $this->assertSame(TipoBadges::DEFAULT, TipoBadges::resolver(999));
        $this->assertSame(TipoBadges::DEFAULT, TipoBadges::resolver(''));
        $this->assertSame(TipoBadges::DEFAULT, TipoBadges::resolver('no-existe'));

        $this->assertSame(TipoBadges::DEFAULT['clases'], TipoBadges::clases(999));
        $this->assertSame('', TipoBadges::icono(999));
    }

    #[Test]
    public function default_tiene_el_shape_documentado(): void
    {
        $this->assertSame(['id', 'label', 'slug', 'clases', 'icono'], array_keys(TipoBadges::DEFAULT));
        $this->assertSame(0, TipoBadges::DEFAULT['id']);
        $this->assertSame('Desconocido', TipoBadges::DEFAULT['label']);
        $this->assertSame('', TipoBadges::DEFAULT['slug']);
        $this->assertNotSame('', TipoBadges::DEFAULT['clases']);
        $this->assertSame('', TipoBadges::DEFAULT['icono']);
    }

    #[Test]
    public function etiquetas_por_id_mapea_los_18_ids_a_su_label(): void
    {
        $etiquetas = TipoBadges::etiquetasPorId();

        $this->assertCount(18, $etiquetas);

        foreach (TipoPokemon::cases() as $tipo) {
            $this->assertArrayHasKey($tipo->value, $etiquetas);
            $this->assertSame($tipo->label(), $etiquetas[$tipo->value]);
        }
    }

    #[Test]
    public function para_js_mapea_slug_a_label_clases_e_icono(): void
    {
        $datos = TipoBadges::paraJs();

        $this->assertCount(18, $datos);

        foreach (TipoPokemon::cases() as $tipo) {
            $slug = $tipo->slug();
            $badge = TipoBadges::porSlug($slug);

            $this->assertArrayHasKey($slug, $datos);
            $this->assertSame(['label', 'clases', 'icono'], array_keys($datos[$slug]));
            $this->assertSame($badge['label'], $datos[$slug]['label']);
            $this->assertSame($badge['clases'], $datos[$slug]['clases']);
            $this->assertSame($badge['icono'], $datos[$slug]['icono']);
        }
    }

    #[Test]
    public function ids_para_js_mapea_id_a_slug(): void
    {
        $ids = TipoBadges::idsParaJs();

        $this->assertCount(18, $ids);

        foreach (TipoPokemon::cases() as $tipo) {
            $this->assertArrayHasKey($tipo->value, $ids);
            $this->assertSame($tipo->slug(), $ids[$tipo->value]);
            $this->assertSame('electrico', $ids[TipoPokemon::ELECTRICO->value]);
        }
    }
}
