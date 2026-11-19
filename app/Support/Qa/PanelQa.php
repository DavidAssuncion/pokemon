<?php

declare(strict_types=1);

namespace App\Support\Qa;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\PlayerInventory;
use App\Models\User;
use App\Support\ItemCatalogo;
use Illuminate\Support\Collection;
use Src\Shared\Domain\NivelHelper;

/**
 * Read model del panel /debug: experiencia del usuario y las 24 claves de
 * caramelos permitidas (18 tipos + 6 estadísticas) con su stock actual.
 */
final class PanelQa
{
    /**
     * @return list<string>
     */
    public static function clavesPermitidas(): array
    {
        $claves = [];

        foreach (TipoEnum::cases() as $tipo) {
            $claves[] = ItemCatalogo::keyTipo($tipo->label());
        }

        foreach (StatEnum::cases() as $stat) {
            $claves[] = ItemCatalogo::keyEv($stat->value);
        }

        return $claves;
    }

    public function contenido(int $userId): ContenidoPanelQa
    {
        $usuario = User::query()->findOrFail($userId);
        $experiencia = $usuario->experiencia;
        $nivel = NivelHelper::nivelDesdeExperiencia($experiencia);
        $cantidades = $this->cantidades($userId);

        /** @var Collection<int, FilaAjusteQa> $tipos */
        $tipos = new Collection();
        foreach (TipoEnum::cases() as $tipo) {
            $clave = ItemCatalogo::keyTipo($tipo->label());
            $tipos->push($this->fila($clave, $cantidades[$clave] ?? 0));
        }

        /** @var Collection<int, FilaAjusteQa> $evs */
        $evs = new Collection();
        foreach (StatEnum::cases() as $stat) {
            $clave = ItemCatalogo::keyEv($stat->value);
            $evs->push($this->fila($clave, $cantidades[$clave] ?? 0));
        }

        return new ContenidoPanelQa(
            experiencia: $experiencia,
            nivel: $nivel,
            experienciaSiguienteNivel: NivelHelper::experienciaParaNivel($nivel + 1),
            caramelosTipo: $tipos,
            caramelosEv: $evs,
        );
    }

    /**
     * @return array<string, int>
     */
    private function cantidades(int $userId): array
    {
        $consulta = PlayerInventory::withoutUserScope()->where('user_id', $userId);
        $consulta->getQuery()->whereIn('item_key', self::clavesPermitidas());
        $filas = $consulta->get(['item_key', 'cantidad']);

        $cantidades = [];
        foreach ($filas as $fila) {
            $cantidades[(string) $fila->getAttribute('item_key')] = (int) $fila->getAttribute('cantidad');
        }

        return $cantidades;
    }

    private function fila(string $clave, int $cantidad): FilaAjusteQa
    {
        $resuelto = ItemCatalogo::resolve($clave);

        return new FilaAjusteQa(
            clave: $clave,
            nombre: $resuelto->nombre,
            imagen: $resuelto->imagen,
            cantidad: $cantidad,
        );
    }
}
