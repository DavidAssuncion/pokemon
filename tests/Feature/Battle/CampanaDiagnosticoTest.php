<?php

declare(strict_types=1);

namespace Tests\Feature\Battle;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Battle\CatalogoEscenarios;
use Tests\Support\Battle\EjecutorBatallaHarness;
use Tests\Support\Battle\ViolacionInvariante;
use Tests\TestCase;

/**
 * Campaña de diagnóstico autogateada.
 *
 * No corre en CI: exige BATTLE_HARNESS_CAMPAIGN=1. Las violaciones
 * diagnósticas y las excepciones se reportan por STDERR y no rompen la
 * ejecución; solo las violaciones seguras de la campaña priorizada y la
 * ausencia de escenarios hacen fallar el test.
 */
#[Group('diagnostic')]
final class CampanaDiagnosticoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('BATTLE_HARNESS_CAMPAIGN') !== '1') {
            $this->markTestSkipped('Campaña desactivada: define BATTLE_HARNESS_CAMPAIGN=1');
        }
    }

    protected function tearDown(): void
    {
        mt_srand();
        parent::tearDown();
    }

    public function test_campana_priorizada(): void
    {
        $escenarios = [
            ...CatalogoEscenarios::humo(),
            ...CatalogoEscenarios::priorizados(),
            ...CatalogoEscenarios::paresRiesgo(),
        ];

        $this->assertSame(10, count(CatalogoEscenarios::humo()));
        $this->assertCount(39, CatalogoEscenarios::priorizados());
        $this->assertSame(12, count(CatalogoEscenarios::paresRiesgo()));

        $ejecutor = new EjecutorBatallaHarness();
        $semillas = $this->semillas();
        $batallas = 0;
        $estancadas = 0;
        $seguras = [];
        $diagnosticas = [];
        $inicio = microtime(true);

        foreach ($escenarios as $escenario) {
            foreach ($semillas as $semilla) {
                $resultado = $ejecutor->ejecutar($escenario->conSemilla($semilla));
                $batallas++;

                if ($resultado->estancada) {
                    $estancadas++;
                }

                $this->acumular($seguras, $resultado->violacionesSeguras);
                $this->acumular($diagnosticas, $resultado->violacionesDiagnosticas);
            }
        }

        $transcurrido = microtime(true) - $inicio;

        $this->escribirResumen(count($escenarios), $batallas, $estancadas, $seguras, $diagnosticas, $transcurrido);

        $this->assertCount(0, $seguras, 'La campaña detectó violaciones seguras: '.$this->formatear($seguras));
    }

    public function test_barrido_completo_de_claves(): void
    {
        if (getenv('BATTLE_HARNESS_FULL') !== '1') {
            $this->markTestSkipped('Barrido completo desactivado: define BATTLE_HARNESS_FULL=1');
        }

        $escenarios = CatalogoEscenarios::completo();
        $this->assertNotSame([], $escenarios, 'La fábrica no devolvió claves para el barrido completo');

        $ejecutor = new EjecutorBatallaHarness();
        $semillas = $this->semillas();
        $batallas = 0;
        $estancadas = 0;
        $seguras = [];
        $diagnosticas = [];
        $excepciones = [];
        $inicio = microtime(true);

        foreach ($escenarios as $escenario) {
            foreach ($semillas as $semilla) {
                try {
                    $resultado = $ejecutor->ejecutar($escenario->conSemilla($semilla));
                    $batallas++;

                    if ($resultado->estancada) {
                        $estancadas++;
                    }

                    $this->acumular($seguras, $resultado->violacionesSeguras);
                    $this->acumular($diagnosticas, $resultado->violacionesDiagnosticas);
                } catch (\Throwable $excepcion) {
                    $excepciones[] = "{$escenario->nombre} (semilla {$semilla}): {$excepcion->getMessage()}";
                }
            }
        }

        $transcurrido = microtime(true) - $inicio;

        $this->escribirResumenCompleto(
            count($escenarios),
            $batallas,
            $estancadas,
            count($excepciones),
            $seguras,
            $diagnosticas,
            $excepciones,
            $transcurrido,
        );

        $this->assertGreaterThan(0, $batallas, 'El barrido completo no ejecutó ninguna batalla');
        $this->assertSame(
            count($escenarios) * count($semillas),
            $batallas + count($excepciones),
            'El barrido completo no recorrió todos los escenarios y semillas',
        );
        $this->assertCount(0, $seguras, 'El barrido completo detectó violaciones seguras: '.$this->formatear($seguras));
        $this->assertSame(0, count($excepciones), 'El barrido completo registró excepciones: '.implode(' | ', array_slice($excepciones, 0, 10)));
    }

    /** @return list<int> */
    private function semillas(): array
    {
        $configuradas = (int) (getenv('BATTLE_HARNESS_SEEDS') ?: 2);
        $cantidad = max(1, min(5, $configuradas));

        return array_slice([1, 7, 13, 29, 41], 0, $cantidad);
    }

    /**
     * @param  array<string, list<string>>  $acumulador
     * @param  list<ViolacionInvariante>  $violaciones
     */
    private function acumular(array &$acumulador, array $violaciones): void
    {
        foreach ($violaciones as $violacion) {
            $acumulador[$violacion->invariante][] = $violacion->describir();
        }
    }

    /**
     * @param  array<string, list<string>>  $seguras
     * @param  array<string, list<string>>  $diagnosticas
     */
    private function escribirResumen(
        int $escenarios,
        int $batallas,
        int $estancadas,
        array $seguras,
        array $diagnosticas,
        float $transcurrido,
    ): void {
        fwrite(STDERR, "\n=== Campaña arnés IA vs IA (priorizada) ===\n");
        fwrite(STDERR, sprintf(
            "Escenarios: %d | batallas: %d | estancadas: %d | tiempo: %.3f s\n",
            $escenarios,
            $batallas,
            $estancadas,
            $transcurrido,
        ));
        fwrite(STDERR, sprintf("Violaciones seguras: %d | diagnósticas: %d\n", $this->contar($seguras), $this->contar($diagnosticas)));

        if ($seguras !== []) {
            fwrite(STDERR, "Seguras agrupadas:\n{$this->formatear($seguras)}\n");
        }

        if ($diagnosticas !== []) {
            fwrite(STDERR, "Diagnósticas agrupadas:\n{$this->formatear($diagnosticas)}\n");
        }
    }

    /**
     * @param  array<string, list<string>>  $seguras
     * @param  array<string, list<string>>  $diagnosticas
     * @param  list<string>  $excepciones
     */
    private function escribirResumenCompleto(
        int $escenarios,
        int $batallas,
        int $estancadas,
        int $totalExcepciones,
        array $seguras,
        array $diagnosticas,
        array $excepciones,
        float $transcurrido,
    ): void {
        fwrite(STDERR, "\n=== Barrido completo de claves ===\n");
        fwrite(STDERR, sprintf(
            "Escenarios: %d | batallas: %d | estancadas: %d | excepciones: %d | tiempo: %.3f s\n",
            $escenarios,
            $batallas,
            $estancadas,
            $totalExcepciones,
            $transcurrido,
        ));
        fwrite(STDERR, sprintf("Violaciones seguras: %d | diagnósticas: %d\n", $this->contar($seguras), $this->contar($diagnosticas)));

        if ($seguras !== []) {
            fwrite(STDERR, "Seguras agrupadas:\n{$this->formatear($seguras)}\n");
        }

        if ($diagnosticas !== []) {
            fwrite(STDERR, "Diagnósticas agrupadas:\n{$this->formatear($diagnosticas)}\n");
        }

        if ($excepciones !== []) {
            fwrite(STDERR, "Excepciones:\n");
            foreach (array_slice($excepciones, 0, 20) as $excepcion) {
                fwrite(STDERR, "  - {$excepcion}\n");
            }
        }
    }

    /** @param array<string, list<string>> $grupos */
    private function contar(array $grupos): int
    {
        return array_sum(array_map('count', $grupos));
    }

    /** @param array<string, list<string>> $grupos */
    private function formatear(array $grupos): string
    {
        $lineas = [];

        foreach ($grupos as $invariante => $detalles) {
            $lineas[] = sprintf('%s x%d', $invariante, count($detalles));
            foreach (array_slice($detalles, 0, 5) as $detalle) {
                $lineas[] = "  - {$detalle}";
            }
        }

        return implode("\n", $lineas);
    }
}
