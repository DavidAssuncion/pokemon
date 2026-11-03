<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pruebas de presentación del componente anónimo <x-tipo-badge>.
 *
 * Verifica la resolución por slug, id y etiqueta, la marca visual del tipo, el
 * tamaño del icono y el fallback sin imagen para tipos desconocidos.
 */
class TipoBadgeBladeTest extends TestCase
{
    #[Test]
    public function renderiza_el_badge_por_slug(): void
    {
        $this->blade('<x-tipo-badge tipo="electrico" size="xs" />')
            ->assertSee('/images/type/electrico.webp', false)
            ->assertSee('alt=""', false)
            ->assertSee('rounded-full', false)
            ->assertSee('Eléctrico')
            ->assertSee('w-8 h-8', false);
    }

    #[Test]
    public function renderiza_el_badge_por_id(): void
    {
        $this->blade('<x-tipo-badge :tipo="13" />')
            ->assertSee('/images/type/electrico.webp', false)
            ->assertSee('Eléctrico');
    }

    #[Test]
    public function renderiza_el_badge_por_etiqueta(): void
    {
        $this->blade('<x-tipo-badge label="Eléctrico" size="sm" />')
            ->assertSee('/images/type/electrico.webp', false)
            ->assertSee('w-5 h-5', false)
            ->assertSee('Eléctrico');
    }

    #[Test]
    public function renderiza_el_fallback_sin_imagen_para_tipos_desconocidos(): void
    {
        $view = $this->blade('<x-tipo-badge tipo="inexistente" />');

        $view->assertSee('Desconocido');
        $view->assertDontSee('<img', false);
    }
}
