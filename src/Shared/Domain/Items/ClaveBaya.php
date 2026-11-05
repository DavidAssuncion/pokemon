<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Las 36 bayas del catálogo compartido. El slug es inglés para alinear con
 * los assets; el nombre visible es el español oficial.
 */
enum ClaveBaya: string
{
    case ORAN = 'oran';
    case SITRUS = 'sitrus';
    case CHERI = 'cheri';
    case PECHA = 'pecha';
    case RAWST = 'rawst';
    case ASPEAR = 'aspear';
    case KELPSY = 'kelpsy';
    case QUALOT = 'qualot';
    case HONDEW = 'hondew';
    case GREPA = 'grepa';
    case TAMATO = 'tamato';
    case LIECHI = 'liechi';
    case GANLON = 'ganlon';
    case SALAC = 'salac';
    case PETAYA = 'petaya';
    case APICOT = 'apicot';
    case LANSAT = 'lansat';
    case MICLE = 'micle';
    case OCCA = 'occa';
    case PASSHO = 'passho';
    case WACAN = 'wacan';
    case RINDO = 'rindo';
    case YACHE = 'yache';
    case CHOPLE = 'chople';
    case KEBIA = 'kebia';
    case SHUCA = 'shuca';
    case COBA = 'coba';
    case PAYAPA = 'payapa';
    case TANGA = 'tanga';
    case CHARTI = 'charti';
    case KASIB = 'kasib';
    case HABAN = 'haban';
    case COLBUR = 'colbur';
    case BABIRI = 'babiri';
    case CHILAN = 'chilan';
    case ROSELI = 'roseli';

    /**
     * Clave canónica de inventario: `baya:{slug}`.
     */
    public function clave(): string
    {
        return CategoriaItem::BAYA->prefijo().$this->value;
    }

    /**
     * Nombre visible en español.
     */
    public function nombre(): string
    {
        return match ($this) {
            self::ORAN => 'Aranja',
            self::SITRUS => 'Zidra',
            self::CHERI => 'Zreza',
            self::PECHA => 'Meloc',
            self::RAWST => 'Safre',
            self::ASPEAR => 'Perasi',
            self::KELPSY => 'Algama',
            self::QUALOT => 'Ispero',
            self::HONDEW => 'Meluce',
            self::GREPA => 'Uvav',
            self::TAMATO => 'Tamate',
            self::LIECHI => 'Lichi',
            self::GANLON => 'Gonlan',
            self::SALAC => 'Aslac',
            self::PETAYA => 'Yapati',
            self::APICOT => 'Aricoc',
            self::LANSAT => 'Zonlan',
            self::MICLE => 'Lagro',
            self::OCCA => 'Caoca',
            self::PASSHO => 'Pasio',
            self::WACAN => 'Gualot',
            self::RINDO => 'Tamar',
            self::YACHE => 'Rimoya',
            self::CHOPLE => 'Pomaro',
            self::KEBIA => 'Kebia',
            self::SHUCA => 'Acardo',
            self::COBA => 'Kouba',
            self::PAYAPA => 'Payapa',
            self::TANGA => 'Yecana',
            self::CHARTI => 'Alcho',
            self::KASIB => 'Drasi',
            self::HABAN => 'Anjiro',
            self::COLBUR => 'Dillo',
            self::BABIRI => 'Baribá',
            self::CHILAN => 'Chilan',
            self::ROSELI => 'Hibis',
        };
    }

    /**
     * Ruta pública del icono WebP.
     */
    public function archivoIcono(): string
    {
        return "/images/berry/{$this->value}-berry.webp";
    }
}
