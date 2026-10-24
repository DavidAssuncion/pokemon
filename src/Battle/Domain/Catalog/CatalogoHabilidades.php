<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Catalog;

/**
 * Resuelve los nombres que aparecen en `poke habilidades.csv` a las claves
 * canónicas que usa FabricaEfectos. Incluye alias, erratas y etiquetas de
 * grupo separadas por “/”.
 */
final class CatalogoHabilidades
{
    /** @var array<string, string> */
    private const CLAVES = [
        'vacio' => 'vacio',
        'absorcion agua' => 'absorcion_agua',
        'absorcion electricidad' => 'absorcion_electricidad',
        'absorcion fuego' => 'absorcion_fuego',
        'alma dragon' => 'alma_dragon',
        'antidoto' => 'antidoto',
        'armadura batalla' => 'armadura_batalla',
        'baba toxica' => 'baba_toxica',
        'bromista' => 'bromista',
        'cabeza roca' => 'cabeza_roca',
        'canones' => 'canones',
        'clorofila' => 'clorofila',
        'colector' => 'colector',
        'comealmas' => 'comealmas',
        'crueldad' => 'crueldad',
        'contraataque' => 'contraataque',
        'cuadruple ataque' => 'cuadruple_ataque',
        'danza lunar' => 'danza_lunar',
        'demoledor' => 'demoledor',
        'mandibula hidraulica' => 'mandibula_hidraulica',
        'depredador de arena' => 'depredador_arena',
        'depredador' => 'depredador',
        'depredador prehistorico' => 'depredador_prehistorico',
        'depredador marino' => 'depredador_marino',
        'detonacion' => 'detonacion',
        'divide dolor' => 'divide_dolor',
        'doble ataque' => 'doble_ataque',
        'dormilon' => 'dormilon',
        'efecto espora' => 'efecto_espora',
        'electricidad estatica' => 'electricidad_estatica',
        'enjambre' => 'enjambre',
        'enredaderas' => 'enredaderas',
        'espinas' => 'espinas',
        'evasion' => 'evasion',
        'flexibilidad' => 'flexibilidad',
        'flor aromatica' => 'flor_aromatica',
        'fotosintesis' => 'fotosintesis',
        'garra dura' => 'garra_dura',
        'granizo' => 'granizo',
        'guardian de los mares' => 'guardian_mares',
        'hypnotizador' => 'hypnotizador',
        'impetu arena' => 'impetu_arena',
        'intimidacion' => 'intimidacion',
        'ira' => 'ira',
        'ira gyarados' => 'ira_gyarados',
        'llovizna' => 'llovizna',
        'mal sueno' => 'mal_sueno',
        'malvado' => 'malvado',
        'manto helado' => 'manto_helado',
        'mar llamas' => 'mar_llamas',
        'meditacion' => 'meditacion',
        'muro magico' => 'muro_magico',
        'pantallista' => 'pantallista',
        'pararrayos' => 'pararrayos',
        'prediccion' => 'prediccion',
        'predicion' => 'prediccion',
        'presion' => 'presion',
        'protector' => 'protector',
        'punto toxico' => 'punto_toxico',
        'refuerzos' => 'refuerzos',
        'regeneracion' => 'regeneracion',
        'sequia' => 'sequia',
        'sintesis' => 'sintesis',
        'sombra traviesa' => 'sombra_traviesa',
        'tormenta arena' => 'tormenta_arena',
        'triple ataque' => 'triple_ataque',
        'velo agua' => 'velo_agua',
        'velo arena' => 'velo_arena',
        'acero templado' => 'acero_templado',
        'veleta' => 'veleta',
        'actualizacion software' => 'actualizacion_software',
        'escamas' => 'escamas',
        'cuerpo llama' => 'cuerpo_llama',
        'caparazon' => 'caparazon',
        'espesura' => 'espesura',
        'bendicion' => 'bendicion',
        'campo magnetico' => 'campo_magnetico',
        'piel seca' => 'piel_seca',
        'torrente' => 'torrente',
        'espsura' => 'espesura',
        'rompemoldes' => 'rompemoldes',
        'mold breaker' => 'rompemoldes',
    ];

    /**
     * Resuelve una etiqueta simple o una etiqueta de grupo separada por “/”.
     * Para grupos devuelve la primera clave reconocida.
     */
    public static function resolver(string $nombre): ?string
    {
        $partes = explode('/', $nombre);

        foreach ($partes as $parte) {
            $clave = self::CLAVES[NormalizadorNombre::normalizar($parte)] ?? null;
            if ($clave !== null) {
                return $clave;
            }
        }

        return null;
    }

    /**
     * Devuelve todas las claves de una etiqueta de grupo
     * (“Enjambre/Mar Llamas/Espesura” → enjambre, mar_llamas, espesura).
     *
     * @return list<string>
     */
    public static function clavesDe(string $nombre): array
    {
        $claves = [];

        foreach (explode('/', $nombre) as $parte) {
            $clave = self::CLAVES[NormalizadorNombre::normalizar($parte)] ?? null;
            if ($clave !== null) {
                $claves[] = $clave;
            }
        }

        return array_values(array_unique($claves));
    }

    /**
     * Indica si el nombre está en el catálogo, aunque su efecto aún no esté
     * implementado en la fábrica.
     */
    public static function existe(string $nombre): bool
    {
        return self::resolver($nombre) !== null;
    }
}
