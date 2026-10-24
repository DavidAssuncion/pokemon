<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Catalog\CatalogoObjetos;
use Src\Battle\Domain\Collections\CondicionesVolatilesCollection;
use Src\Battle\Domain\Effects\Capacidades\IgnoraHabilidad;
use Src\Battle\Domain\Effects\Capacidades\InmunidadTipo;
use Src\Battle\Domain\Effects\ColeccionEfectos;
use Src\Battle\Domain\Effects\InterfazEfecto;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveItem;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\ValueObjects\AjusteGolpes;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\EstadoHabilidad;
use Src\Battle\Domain\ValueObjects\EstadoMovimientos;
use Src\Battle\Domain\ValueObjects\EstadoObjeto;
use Src\Battle\Domain\ValueObjects\EstadoRecursos;
use Src\Battle\Domain\ValueObjects\EstadoTurno;
use Src\Battle\Domain\ValueObjects\JefeEstado;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;
use Src\Battle\Domain\ValueObjects\ResultadoAccion;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Battle\Domain\ValueObjects\ResultadoSustituto;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Shared\Domain\ValueObjects\MapaConteo;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;

class Combatiente
{
    private float $hpActual;

    private float $defensaHpActual;

    private float $defensaEspHpActual;

    private EstadoPokemon $estado = EstadoPokemon::NONE;

    private int $contadorVenenoGrave = 0;

    private int $cargasVeneno = 0;

    private int $turnosEstado = 0;

    private CondicionesVolatilesCollection $condiciones;

    private MultiplicadoresStats $multiplicadores;

    private string $id = '';

    private string $nombre = '';

    private string $iconName = '';

    private int $speciesId = 0;

    private string $formSuffix = '';

    private bool $shiny = false;

    private EstadoObjeto $objeto;

    private EstadoHabilidad $habilidad;

    private ?JefeEstado $jefe = null;

    private float $bonificadorDanio = 1.0;

    private float $ultimoDanoRecibido = 0.0;

    private string $ultimoAtacanteId = '';

    private float $danoRecibidoEstaRonda = 0.0;

    private int $golpesRecibidos = 0;

    private TipoClima $clima = TipoClima::NONE;

    private EstadoTurno $turno;

    private EstadoRecursos $recursos;

    private EstadoMovimientos $movimientos;

    /**
     * Invocaciones temporales: turnos restantes y combatiente que las creó.
     * Los combatientes normales tienen 0 y cadena vacía.
     */
    private int $turnosInvocacion = 0;

    private string $invocadoPorId = '';

    /**
     * Evita disparar dos veces los efectos de debilitamiento y los observers
     * cuando varias rutas (multiobjetivo, vínculo, fin de ronda) detectan la
     * misma muerte.
     */
    private bool $debilidadNotificada = false;

    private ColeccionEfectos $effects;

    private PokemonEntity $pokemon;

    private Posicion $posicion;

    private EstadoManager $estadoManager;

    private BarrerasVidaManager $barrerasVidaManager;

    public function __construct(
        PokemonEntity $pokemon,
        Posicion $posicion,
    ) {
        $this->pokemon = $pokemon;
        $this->posicion = $posicion;
        $this->hpActual = $pokemon->battleStats()->hp;
        $this->defensaHpActual = $pokemon->battleStats()->defenseHp;
        $this->defensaEspHpActual = $pokemon->battleStats()->spDefenseHp;
        $this->effects = new ColeccionEfectos();
        $this->condiciones = new CondicionesVolatilesCollection();
        $this->estadoManager = new EstadoManager();
        $this->barrerasVidaManager = new BarrerasVidaManager();
        $this->multiplicadores = MultiplicadoresStats::vacia();
        $this->objeto = new EstadoObjeto();
        $this->habilidad = new EstadoHabilidad();
        $this->turno = new EstadoTurno();
        $this->recursos = new EstadoRecursos();
        $this->movimientos = new EstadoMovimientos();
    }

    // ─── Serialización para sesión ────────────────────────────

    public function __serialize(): array
    {
        return [
            'hpActual' => $this->hpActual,
            'defensaHpActual' => $this->defensaHpActual,
            'defensaEspHpActual' => $this->defensaEspHpActual,
            'vecesActuadoEstaRonda' => $this->turno->vecesActuado(),
            'accionesExtra' => $this->turno->accionesExtra(),
            'extraOtorgada' => $this->turno->extraOtorgada(),
            'estado' => $this->estado->value,
            'contadorVenenoGrave' => $this->contadorVenenoGrave,
            'cargasVeneno' => $this->cargasVeneno,
            'turnosEstado' => $this->turnosEstado,
            'multiplicadores' => $this->multiplicadores->toArray(),
            'id' => $this->id,
            'nombre' => $this->nombre,
            'iconName' => $this->iconName,
            'speciesId' => $this->speciesId,
            'formSuffix' => $this->formSuffix,
            'shiny' => $this->shiny,
            'item' => $this->objeto->clave(),
            'itemCargas' => $this->objeto->cargas(),
            'itemConsumido' => $this->objeto->consumido(),
            'itemBloqueadoHastaRonda' => $this->objeto->bloqueadoHastaRonda(),
            'movimientoBloqueado' => $this->objeto->movimientoBloqueado(),
            'habilidadAnulada' => $this->habilidad->anulada(),
            'habilidadAnuladaPorGas' => $this->habilidad->anuladaPorGas(),
            'efectosHabilidadGuardados' => serialize($this->habilidad->guardados()->all()),
            'jefe' => serialize($this->jefe),
            'bonificadorDanio' => $this->bonificadorDanio,
            'ultimoDanoRecibido' => $this->ultimoDanoRecibido,
            'ultimoAtacanteId' => $this->ultimoAtacanteId,
            'danoRecibidoEstaRonda' => $this->danoRecibidoEstaRonda,
            'golpesRecibidos' => $this->golpesRecibidos,
            'costeVelocidad' => $this->turno->costeVelocidad(),
            'rondaActual' => $this->turno->rondaActual(),
            'costeVelocidadHastaRonda' => $this->turno->costeVelocidadHastaRonda(),
            'clima' => $this->clima->value,
            'postura' => $this->turno->postura()->value,
            'objetivoCubiertoId' => $this->turno->objetivoCubiertoId(),
            'prioridadDeclarada' => $this->turno->prioridad(),
            'cargasMovimiento' => $this->movimientos->cargas()->toArray(),
            'usosMovimiento' => $this->movimientos->usos()->toArray(),
            'turnoRetrasado' => $this->turno->turnoRetrasado(),
            'retrasoUsado' => $this->turno->retrasoUsado(),
            'combo' => $this->recursos->combo(),
            'comboMovimiento' => $this->recursos->comboMovimiento(),
            'turnosInvocacion' => $this->turnosInvocacion,
            'invocadoPorId' => $this->invocadoPorId,
            'debilidadNotificada' => $this->debilidadNotificada,
            'condiciones' => serialize($this->condiciones),
            'effects' => serialize($this->effects),
            'pokemon' => serialize($this->pokemon),
            'posicion' => $this->posicion->value,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->hpActual = (float) ($data['hpActual'] ?? 0);
        $this->defensaHpActual = (float) ($data['defensaHpActual'] ?? 0);
        $this->defensaEspHpActual = (float) ($data['defensaEspHpActual'] ?? 0);
        // Compatibilidad: los payloads v≤10 traen 'velocidadAcumulada'; se ignora
        // (el motor de turnos ya no la usa y el contador de acciones manda).
        $this->estado = EstadoPokemon::tryFrom($data['estado'] ?? EstadoPokemon::NONE->value) ?? EstadoPokemon::NONE;
        $this->contadorVenenoGrave = (int) ($data['contadorVenenoGrave'] ?? 0);
        $this->cargasVeneno = (int) ($data['cargasVeneno'] ?? 0);
        $this->turnosEstado = (int) ($data['turnosEstado'] ?? 0);
        // Compatibilidad: payloads antiguos ('etapas' con int) y nuevos ('multiplicadores' con float)
        if (isset($data['multiplicadores']) && is_array($data['multiplicadores'])) {
            $this->multiplicadores = MultiplicadoresStats::desdeSerializado($data['multiplicadores']);
        } elseif (isset($data['etapas']) && is_array($data['etapas'])) {
            $this->multiplicadores = MultiplicadoresStats::desdeEtapas($data['etapas']);
        } else {
            $this->multiplicadores = MultiplicadoresStats::vacia();
        }
        $this->id = (string) ($data['id'] ?? '');
        $this->nombre = (string) ($data['nombre'] ?? '');
        $this->iconName = (string) ($data['iconName'] ?? '');
        $this->speciesId = (int) ($data['speciesId'] ?? 0);
        $this->formSuffix = (string) ($data['formSuffix'] ?? '');
        $this->shiny = (bool) ($data['shiny'] ?? false);
        $item = (string) ($data['item'] ?? '');
        $this->objeto = new EstadoObjeto();
        $this->objeto->restaurar(
            clave: $item,
            cargas: (int) ($data['itemCargas'] ?? ($item !== '' ? CatalogoObjetos::cargasDe($item) : 0)),
            consumido: (string) ($data['itemConsumido'] ?? ''),
            bloqueadoHastaRonda: (int) ($data['itemBloqueadoHastaRonda'] ?? 0),
            movimientoBloqueado: (string) ($data['movimientoBloqueado'] ?? ''),
        );
        $guardadosRaw = $data['efectosHabilidadGuardados'] ?? [];
        $guardados = is_string($guardadosRaw) ? unserialize($guardadosRaw) : $guardadosRaw;
        $guardados = is_array($guardados)
            ? array_values(array_filter(
                $guardados,
                static fn (mixed $efecto): bool => $efecto instanceof InterfazEfecto,
            ))
            : [];
        $this->habilidad = new EstadoHabilidad();
        $efectosGuardados = new ColeccionEfectos();
        foreach ($guardados as $efecto) {
            $efectosGuardados->add($efecto);
        }
        $this->habilidad->restaurarDesde(
            (bool) ($data['habilidadAnulada'] ?? false),
            $efectosGuardados,
            (bool) ($data['habilidadAnuladaPorGas'] ?? false),
        );

        $jefeRaw = $data['jefe'] ?? null;
        $jefe = is_string($jefeRaw) ? unserialize($jefeRaw) : $jefeRaw;
        $this->jefe = $jefe instanceof JefeEstado ? $jefe : null;
        $this->bonificadorDanio = (float) ($data['bonificadorDanio'] ?? 1.0);
        $this->ultimoDanoRecibido = (float) ($data['ultimoDanoRecibido'] ?? 0.0);
        $this->ultimoAtacanteId = (string) ($data['ultimoAtacanteId'] ?? '');
        $this->danoRecibidoEstaRonda = (float) ($data['danoRecibidoEstaRonda'] ?? 0.0);
        $this->golpesRecibidos = max(0, (int) ($data['golpesRecibidos'] ?? 0));
        $this->clima = TipoClima::tryFrom((string) ($data['clima'] ?? TipoClima::NONE->value)) ?? TipoClima::NONE;
        $prioridad = $data['prioridadDeclarada'] ?? null;
        $this->turno = new EstadoTurno();
        $this->turno->restaurar(
            vecesActuado: (int) ($data['vecesActuadoEstaRonda'] ?? 0),
            accionesExtra: (int) ($data['accionesExtra'] ?? 0),
            extraOtorgada: (bool) ($data['extraOtorgada'] ?? false),
            turnoRetrasado: (bool) ($data['turnoRetrasado'] ?? false),
            retrasoUsado: (bool) ($data['retrasoUsado'] ?? false),
            rondaActual: (int) ($data['rondaActual'] ?? 0),
            postura: PosturaCombate::tryFrom((string) ($data['postura'] ?? PosturaCombate::ATACAR->value))
                ?? PosturaCombate::ATACAR,
            objetivoCubiertoId: (string) ($data['objetivoCubiertoId'] ?? ''),
            prioridad: $prioridad instanceof MovimientoBatalla ? $prioridad : null,
            costeVelocidad: (float) ($data['costeVelocidad'] ?? 0.0),
            costeVelocidadHastaRonda: (int) ($data['costeVelocidadHastaRonda'] ?? 0),
        );
        $cargas = is_array($data['cargasMovimiento'] ?? null) ? $data['cargasMovimiento'] : [];
        $usos = is_array($data['usosMovimiento'] ?? null) ? $data['usosMovimiento'] : [];
        $this->movimientos = new EstadoMovimientos();
        $this->movimientos->restaurar(
            MapaConteo::desdeArray($cargas),
            MapaConteo::desdeArray($usos),
        );
        $this->recursos = new EstadoRecursos();
        $this->recursos->restaurar(
            (int) ($data['combo'] ?? 0),
            (string) ($data['comboMovimiento'] ?? ''),
        );
        $this->turnosInvocacion = max(0, (int) ($data['turnosInvocacion'] ?? 0));
        $this->invocadoPorId = (string) ($data['invocadoPorId'] ?? '');
        $this->debilidadNotificada = (bool) ($data['debilidadNotificada'] ?? false);
        $this->condiciones = isset($data['condiciones'])
            ? unserialize($data['condiciones'])
            : new CondicionesVolatilesCollection();
        // Compatibilidad v≤13: confusión vivía en 'estado'.
        if ($this->estado === EstadoPokemon::CONFUSION) {
            $this->estado = EstadoPokemon::NONE;
            if (! $this->condiciones->tiene(ClaveCondicion::CONFUSION)) {
                $this->condiciones->add(new CondicionVolatil(ClaveCondicion::CONFUSION, $this->turnosEstado));
            }
        }
        $this->effects = isset($data['effects']) ? unserialize($data['effects']) : new ColeccionEfectos();
        $this->pokemon = isset($data['pokemon']) ? unserialize($data['pokemon']) : throw new \RuntimeException('Missing pokemon data');
        $this->posicion = Posicion::tryFrom($data['posicion'] ?? Posicion::VANGUARDIA->value) ?? Posicion::VANGUARDIA;
        $this->estadoManager = new EstadoManager();
        $this->barrerasVidaManager = new BarrerasVidaManager();
    }

    // ─── Getters ──────────────────────────────────────────────

    public function hpActual(): float
    {
        return $this->hpActual;
    }

    public function defensaHpActual(): float
    {
        return $this->defensaHpActual;
    }

    public function defensaEspHpActual(): float
    {
        return $this->defensaEspHpActual;
    }

    public function vecesActuadoEstaRonda(): int
    {
        return $this->turno->vecesActuado();
    }

    /**
     * ¿Le queda acción pendiente en la ronda actual?
     */
    public function tieneAccionPendienteEstaRonda(): bool
    {
        return $this->turno->tieneAccionPendiente();
    }

    public function accionesExtra(): int
    {
        return $this->turno->accionesExtra();
    }

    public function setAccionesExtra(int $accionesExtra): void
    {
        $this->turno->setAccionesExtra($accionesExtra);
    }

    public function otorgarAccionExtra(): void
    {
        $this->turno->otorgarAccionExtra();
    }

    public function reiniciarAccionExtra(): void
    {
        $this->turno->reiniciarAccionExtra();
    }

    // ─── Retraso voluntario de turno ─────────────────────────

    /**
     * Mueve la acción del combatiente al final de la ronda. Solo puede
     * retrasarse una vez por ronda para evitar bucles de espera.
     */
    public function retrasarTurno(): bool
    {
        return $this->estaVivo() && $this->turno->retrasar();
    }

    public function turnoRetrasado(): bool
    {
        return $this->turno->turnoRetrasado();
    }

    public function retrasoUsado(): bool
    {
        return $this->turno->retrasoUsado();
    }

    public function reiniciarRetraso(): void
    {
        $this->turno->reiniciarRetraso();
    }

    public function limpiarRetraso(): void
    {
        $this->turno->limpiarRetraso();
    }

    public function estado(): EstadoPokemon
    {
        return $this->tieneCondicion(ClaveCondicion::CONFUSION)
            ? EstadoPokemon::CONFUSION
            : $this->estado;
    }

    public function estadoMayor(): EstadoPokemon
    {
        return $this->estado;
    }

    public function turnosEstadoMayor(): int
    {
        return $this->turnosEstado;
    }

    public function contadorVenenoGrave(): int
    {
        return $this->contadorVenenoGrave;
    }

    public function cargasVeneno(): int
    {
        return $this->cargasVeneno;
    }

    public function aplicarCargaVeneno(int $cargas = 1): void
    {
        $this->cargasVeneno = max(0, min(8, $this->cargasVeneno + max(1, $cargas)));
    }

    // ─── Combo e invocaciones ────────────────────────────────

    public function combo(): int
    {
        return $this->recursos->combo();
    }

    public function comboMovimiento(): string
    {
        return $this->recursos->comboMovimiento();
    }

    public function comboMultiplicador(): float
    {
        return $this->recursos->comboMultiplicador();
    }

    public function comboMultiplicadorPara(MovimientoBatalla $movimiento): float
    {
        return $this->recursos->comboMultiplicadorPara(
            $movimiento->nombre,
            $movimiento->perfil->potenciaCreciente,
        );
    }

    public function prepararCombo(MovimientoBatalla $movimiento): void
    {
        $this->recursos->prepararCombo($movimiento->nombre, $movimiento->perfil->potenciaCreciente);
    }

    public function registrarGolpeCombo(MovimientoBatalla $movimiento): void
    {
        if (! $movimiento->perfil->potenciaCreciente) {
            $this->recursos->romperCombo();

            return;
        }

        $this->recursos->registrarGolpe($movimiento->nombre);
    }

    public function romperCombo(): void
    {
        $this->recursos->romperCombo();
    }

    public function esInvocacion(): bool
    {
        return $this->invocadoPorId !== '';
    }

    public function invocadoPorId(): string
    {
        return $this->invocadoPorId;
    }

    public function turnosInvocacion(): int
    {
        return $this->turnosInvocacion;
    }

    /**
     * Avanza la duración de una invocación al final de la ronda.
     * Devuelve true mientras le queden turnos.
     */
    public function avanzarTurnoInvocacion(): bool
    {
        if (! $this->esInvocacion()) {
            return false;
        }

        $this->turnosInvocacion = max(0, $this->turnosInvocacion - 1);

        return $this->turnosInvocacion > 0;
    }

    /**
     * Convierte una copia del invocador en una invocación temporal. Se
     * limpian estados, condiciones, objetos, cargas y recursos: el esbirro
     * solo conserva la apariencia y las estadísticas base.
     */
    public function prepararComoInvocacion(
        string $id,
        string $nombre,
        string $invocadoPorId,
        int $turnos,
        float $fraccionHp,
    ): void {
        $this->id = $id;
        $this->nombre = $nombre;
        $this->invocadoPorId = $invocadoPorId;
        $this->turnosInvocacion = max(1, $turnos);

        $maxHp = max(1.0, $this->pokemon->battleStats()->hp);
        $this->hpActual = max(1.0, $maxHp * max(0.01, min(1.0, $fraccionHp)));
        $this->defensaHpActual = 0.0;
        $this->defensaEspHpActual = 0.0;

        $this->estado = EstadoPokemon::NONE;
        $this->contadorVenenoGrave = 0;
        $this->cargasVeneno = 0;
        $this->turnosEstado = 0;
        $this->condiciones = new CondicionesVolatilesCollection();
        $this->effects = new ColeccionEfectos();
        $this->multiplicadores = MultiplicadoresStats::vacia();
        $this->recursos = new EstadoRecursos();
        $this->movimientos = new EstadoMovimientos();
        $this->turno = new EstadoTurno();
        $this->ultimoDanoRecibido = 0.0;
        $this->ultimoAtacanteId = '';
        $this->objeto = new EstadoObjeto();
        $this->habilidad = new EstadoHabilidad();
        $this->jefe = null;
        $this->bonificadorDanio = 1.0;
        $this->debilidadNotificada = false;
    }

    /**
     * Revive a un combatiente debilitado. Devuelve false si ya estaba vivo.
     */
    public function revivir(float $fraccion): bool
    {
        if ($this->estaVivo()) {
            return false;
        }

        $maxHp = max(1.0, $this->pokemon->battleStats()->hp);
        $this->hpActual = max(1.0, $maxHp * max(0.01, min(1.0, $fraccion)));
        $this->defensaHpActual = 0.0;
        $this->defensaEspHpActual = 0.0;
        $this->estado = EstadoPokemon::NONE;
        $this->contadorVenenoGrave = 0;
        $this->cargasVeneno = 0;
        $this->turnosEstado = 0;
        $this->condiciones = new CondicionesVolatilesCollection();
        $this->multiplicadores = MultiplicadoresStats::vacia();
        $this->recursos = new EstadoRecursos();
        $this->turno->reiniciarParaRevivir();
        $this->debilidadNotificada = false;

        return true;
    }

    // ─── Carga y usos de movimientos ─────────────────────────

    /**
     * Avanza 1/3 (o lo indicado por el perfil) las cargas de todos los
     * movimientos que las requieren. Se llama al inicio de cada ronda.
     */
    public function avanzarCargasMovimiento(): void
    {
        foreach ($this->pokemon->moves()->all() as $movimiento) {
            $this->movimientos->avanzarCarga($movimiento);
        }
    }

    public function cargaMovimiento(MovimientoBatalla $movimiento): int
    {
        return $this->movimientos->carga($movimiento);
    }

    public function usosRestantes(MovimientoBatalla $movimiento): int
    {
        return $this->movimientos->usosRestantes($movimiento);
    }

    /**
     * ¿El movimiento se puede seleccionar ahora mismo? La carga debe estar
     * completa, debe quedar algún uso y el silencio bloquea movimientos de
     * estado.
     */
    public function movimientoDisponible(MovimientoBatalla $movimiento): bool
    {
        return $this->motivoMovimientoNoDisponible($movimiento) === null;
    }

    public function motivoMovimientoNoDisponible(MovimientoBatalla $movimiento): ?string
    {
        if (($this->tieneCondicion(ClaveCondicion::SILENCIO) || $this->tieneCondicion(ClaveCondicion::MOFA))
            && $movimiento->esEstado()) {
            return 'silencio';
        }

        $cargaMaxima = $movimiento->perfil->cargaMaxima;
        if ($cargaMaxima > 0 && $this->cargaMovimiento($movimiento) < $cargaMaxima) {
            return 'cargando';
        }

        if ($this->usosRestantes($movimiento) === 0) {
            return 'sin_usos';
        }

        if ($this->itemActivo()
            && in_array($this->item(), [ClaveItem::CINTA_ELECCION->value, ClaveItem::GAFAS_ELECCION->value], true)
            && $this->objeto->movimientoBloqueado() !== ''
            && $this->objeto->movimientoBloqueado() !== $movimiento->nombre) {
            return 'objeto_eleccion';
        }

        return null;
    }

    /**
     * Marca el movimiento como usado: reinicia su carga y consume un uso si
     * el perfil los limita. No decide si el movimiento acertó.
     */
    public function consumirMovimiento(MovimientoBatalla $movimiento): void
    {
        $this->movimientos->consumir($movimiento);

        if ($this->itemActivo()
            && in_array($this->item(), [ClaveItem::CINTA_ELECCION->value, ClaveItem::GAFAS_ELECCION->value], true)) {
            $this->objeto->bloquearMovimiento($movimiento->nombre);
        }

        if ($movimiento->perfil->recarga) {
            $this->condiciones->quitar(ClaveCondicion::RECARGA);
            $this->condiciones->add(new CondicionVolatil(ClaveCondicion::RECARGA, 1));
            $this->romperCombo();
        }
    }

    public function cancelarCargasMovimiento(): void
    {
        $this->movimientos->cancelarCargas();
    }

    public function cargasMovimientoPorNombre(): MapaConteo
    {
        return $this->movimientos->cargas();
    }

    public function usosMovimientoPorNombre(): MapaConteo
    {
        return $this->movimientos->usos();
    }

    public function turnosEstado(): int
    {
        $confusion = $this->condicion(ClaveCondicion::CONFUSION);

        return $confusion !== null ? $confusion->turnos : $this->turnosEstado;
    }

    public function condiciones(): CondicionesVolatilesCollection
    {
        return $this->condiciones;
    }

    public function tieneCondicion(ClaveCondicion $clave): bool
    {
        return $this->condiciones->tiene($clave);
    }

    public function condicion(ClaveCondicion $clave): ?CondicionVolatil
    {
        return $this->condiciones->buscar($clave);
    }

    public function agregarCondicion(CondicionVolatil $condicion): void
    {
        if (! $this->condiciones->tiene($condicion->clave)) {
            $this->condiciones->add($condicion);
        }
    }

    /**
     * Sustituye una condición existente. La usan las marcas y vínculos, que
     * refrescan duración/factor en lugar de acumularse.
     */
    public function reemplazarCondicion(CondicionVolatil $condicion): void
    {
        $this->condiciones->quitar($condicion->clave);
        $this->condiciones->add($condicion);
    }

    public function quitarCondicion(ClaveCondicion $clave): void
    {
        $this->condiciones->quitar($clave);
    }

    /**
     * Impide usar movimientos de estado durante N turnos. No bloquea ataques.
     */
    public function aplicarSilencio(int $turnos = 2): void
    {
        $turnos = max(1, $turnos);
        $existente = $this->condicion(ClaveCondicion::SILENCIO);
        if ($existente !== null) {
            $existente->turnos = max($existente->turnos, $turnos);

            return;
        }

        $this->condiciones->add(new CondicionVolatil(ClaveCondicion::SILENCIO, $turnos));
    }

    /**
     * Cancela la acción declarada y las cargas del objetivo. La condición se
     * consume la próxima vez que intente actuar.
     */
    public function aplicarInterrupcion(): void
    {
        $this->limpiarPrioridad();
        $this->cancelarCargasMovimiento();
        $this->romperCombo();

        if (! $this->tieneCondicion(ClaveCondicion::INTERRUPCION)) {
            $this->condiciones->add(new CondicionVolatil(ClaveCondicion::INTERRUPCION, 1));
        }
    }

    /**
     * Rompe la postura defensiva y hace perder la siguiente acción.
     */
    public function aturdir(): void
    {
        $this->setPostura(PosturaCombate::ATACAR);
        $this->setObjetivoCubiertoId('');
        $this->limpiarPrioridad();
        $this->romperCombo();

        if (! $this->tieneCondicion(ClaveCondicion::ATURDIDO)) {
            $this->condiciones->add(new CondicionVolatil(ClaveCondicion::ATURDIDO, 1));
        }
    }

    public function multiplicadores(): MultiplicadoresStats
    {
        return $this->multiplicadores;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function iconName(): string
    {
        return $this->iconName;
    }

    public function speciesId(): int
    {
        return $this->speciesId;
    }

    public function formSuffix(): string
    {
        return $this->formSuffix;
    }

    public function shiny(): bool
    {
        return $this->shiny;
    }

    public function item(): string
    {
        return $this->objeto->clave();
    }

    public function itemCargas(): int
    {
        return $this->objeto->cargas();
    }

    public function itemConsumido(): string
    {
        return $this->objeto->consumido();
    }

    public function movimientoBloqueado(): string
    {
        return $this->objeto->movimientoBloqueado();
    }

    public function itemBloqueado(): bool
    {
        return $this->objeto->bloqueado($this->turno->rondaActual(), $this->tieneCondicion(ClaveCondicion::EMBARGO));
    }

    public function itemActivo(): bool
    {
        return $this->objeto->activo($this->turno->rondaActual(), $this->tieneCondicion(ClaveCondicion::EMBARGO));
    }

    public function consumirCargaObjeto(): bool
    {
        return $this->objeto->consumir();
    }

    public function bloquearObjeto(int $turnos = 5): void
    {
        $turnos = max(1, $turnos);
        $this->objeto->bloquear($this->turno->rondaActual(), $turnos);

        if (! $this->tieneCondicion(ClaveCondicion::EMBARGO)) {
            $this->condiciones->add(new CondicionVolatil(ClaveCondicion::EMBARGO, $turnos));
        }
    }

    public function destruirObjeto(): string
    {
        return $this->objeto->destruir();
    }

    public function robarObjetoDe(Combatiente $otro): bool
    {
        return $this->objeto->robarDe($otro->objeto);
    }

    public function intercambiarObjetoCon(Combatiente $otro): bool
    {
        return $this->objeto->intercambiarCon($otro->objeto);
    }

    public function reciclarObjeto(): bool
    {
        return $this->objeto->reciclar();
    }

    public function habilidadAnulada(): bool
    {
        return $this->habilidad->anulada() || $this->tieneCondicion(ClaveCondicion::HABILIDAD_ANULADA);
    }

    public function habilidadAnuladaPorGas(): bool
    {
        return $this->habilidad->anuladaPorGas();
    }

    /**
     * Anula la habilidad del portador: retira sus efectos de habilidad de la
     * colección activa y los guarda para restaurarlos al expirar.
     */
    public function anularHabilidad(int $turnos = 5, bool $porGas = false): void
    {
        if ($this->habilidad->anulada()) {
            return;
        }

        $this->habilidad->marcarAnulada($this->efectosHabilidad(), $porGas);

        $restantes = [];
        foreach ($this->effects->all() as $efecto) {
            if ($this->esEfectoDeObjeto($efecto)) {
                $restantes[] = $efecto;
            }
        }
        $this->effects->reemplazar($restantes);

        $this->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::HABILIDAD_ANULADA,
            turnos: max(1, $turnos),
        ));
    }

    public function restaurarHabilidad(): void
    {
        foreach ($this->habilidad->restaurar() as $efecto) {
            $this->effects->add($efecto);
        }

        $this->quitarCondicion(ClaveCondicion::HABILIDAD_ANULADA);
    }

    public function efectosHabilidad(): ColeccionEfectos
    {
        $efectos = new ColeccionEfectos();
        foreach ($this->effects->all() as $efecto) {
            if (! $this->esEfectoDeObjeto($efecto)) {
                $efectos->add($efecto);
            }
        }

        return $efectos;
    }

    public function copiarHabilidadDe(Combatiente $otro): void
    {
        $this->restaurarHabilidad();
        $copias = array_map(
            static fn (InterfazEfecto $efecto): InterfazEfecto => clone $efecto,
            $otro->efectosHabilidad()->all(),
        );

        $this->reemplazarEfectosHabilidad($copias);
    }

    public function intercambiarHabilidadesCon(Combatiente $otro): void
    {
        $this->restaurarHabilidad();
        $otro->restaurarHabilidad();

        $propias = $this->efectosHabilidad();
        $ajenas = $otro->efectosHabilidad();

        $this->reemplazarEfectosHabilidad($ajenas->all());
        $otro->reemplazarEfectosHabilidad($propias->all());
    }

    /**
     * @param  list<InterfazEfecto>  $efectos
     */
    private function reemplazarEfectosHabilidad(array $efectos): void
    {
        $items = [];
        foreach ($this->effects->all() as $efecto) {
            if ($this->esEfectoDeObjeto($efecto)) {
                $items[] = $efecto;
            }
        }

        $this->effects->reemplazar([...$items, ...$efectos]);
    }

    private function esEfectoDeObjeto(InterfazEfecto $efecto): bool
    {
        return CatalogoObjetos::clavesDeEfecto()->contiene($efecto->obtenerClave());
    }

    public function ignorarHabilidadRival(int $turnos = 5): void
    {
        $this->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::IGNORA_HABILIDAD,
            turnos: max(1, $turnos),
        ));
    }

    public function ignoraHabilidadRival(): bool
    {
        if ($this->tieneCondicion(ClaveCondicion::IGNORA_HABILIDAD)) {
            return true;
        }

        foreach ($this->effects->all() as $efecto) {
            if ($efecto instanceof IgnoraHabilidad && $efecto->ignoraHabilidadRival()) {
                return true;
            }
        }

        return false;
    }

    public function aplicarTipoOverride(TipoPokemon $tipo, int $turnos = 0): void
    {
        $this->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::TIPO_ALTERADO,
            turnos: max(0, $turnos),
            tipoOverride: $tipo,
        ));
    }

    public function tiposDefensivos(): TiposCollection
    {
        $alterado = $this->condicion(ClaveCondicion::TIPO_ALTERADO);
        if ($alterado?->tipoOverride !== null) {
            return new TiposCollection([$alterado->tipoOverride]);
        }

        return $this->pokemon->tiposCollection();
    }

    public function efectividadContra(TipoPokemon $tipo): float
    {
        $multiplicador = 1.0;
        foreach ($this->tiposDefensivos() as $defensor) {
            $multiplicador *= $tipo->effectivenessAgainst($defensor);
        }

        return $multiplicador;
    }

    /**
     * Crea/reemplaza el sustituto con una fracción del HP máximo del portador.
     */
    public function aplicarSustituto(float $fraccion = 0.25, int $turnos = 0, bool $pagarCoste = true): float
    {
        $hpMax = max(1.0, $this->pokemon->battleStats()->hp * max(0.01, min(1.0, $fraccion)));

        if ($pagarCoste) {
            if ($this->hpActual <= $hpMax) {
                return 0.0;
            }

            $this->hpActual -= $hpMax;
        }

        $this->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::SUSTITUTO,
            turnos: max(0, $turnos),
            hpSustituto: $hpMax,
            hpMaxSustituto: $hpMax,
        ));

        return $hpMax;
    }

    /**
     * Absorbe daño con el sustituto. Devuelve el daño restante y si se rompió.
     */
    public function absorberConSustituto(float $dano): ResultadoSustituto
    {
        $sustituto = $this->condicion(ClaveCondicion::SUSTITUTO);
        if ($sustituto === null || $sustituto->hpSustituto <= 0.0 || $dano <= 0.0) {
            return new ResultadoSustituto(restante: $dano, absorbido: 0.0, roto: false);
        }

        $absorbido = min($dano, $sustituto->hpSustituto);
        $sustituto->hpSustituto -= $absorbido;
        $roto = $sustituto->hpSustituto <= 0.0;

        if ($roto) {
            $this->quitarCondicion(ClaveCondicion::SUSTITUTO);
        }

        return new ResultadoSustituto(
            restante: max(0.0, $dano - $absorbido),
            absorbido: $absorbido,
            roto: $roto,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $fases
     * @param  list<array<string, mixed>>  $partes
     */
    public function configurarJefe(array $fases, array $partes): void
    {
        $this->jefe = new JefeEstado(
            fases: $fases,
            partes: $partes,
            hpMax: max(1.0, $this->pokemon->battleStats()->hp),
        );
    }

    public function esJefe(): bool
    {
        return $this->jefe !== null;
    }

    public function jefe(): ?JefeEstado
    {
        return $this->jefe;
    }

    /**
     * Activa fases y rompe puntos débiles tras aplicar daño al jefe.
     */
    public function procesarDanoJefe(float $dano, AgregadoBatalla $battle): void
    {
        if ($this->jefe === null || $dano <= 0.0) {
            return;
        }

        foreach ($this->jefe->procesarDanio(
            $this->hpActual,
            $this->pokemon->battleStats()->hp,
        ) as $evento) {
            $battle->agregarLog(
                "¡{$this->nombre} entra en {$evento->nombre}! (+".
                (int) round(($evento->bonificadorDano - 1.0) * 100).'% daño)',
            );
        }

        if ($this->jefe->daniarParte($dano)) {
            $this->reemplazarCondicion(new CondicionVolatil(
                clave: ClaveCondicion::VULNERABLE,
                turnos: 3,
                multiplicadorDanoRecibido: 1.25,
            ));
            $battle->agregarLog("¡Se rompe una parte de {$this->nombre}! Queda vulnerable");
        }
    }

    public function multiplicadorDanioJefe(): float
    {
        return $this->jefe?->multiplicadorDanioInfligido() ?? 1.0;
    }

    public function multiplicadorVelocidadJefe(): float
    {
        return $this->jefe?->multiplicadorVelocidad() ?? 1.0;
    }

    public function bonificadorDanio(): float
    {
        return $this->bonificadorDanio;
    }

    public function clima(): TipoClima
    {
        return $this->clima;
    }

    public function ultimoDanoRecibido(): float
    {
        return $this->ultimoDanoRecibido;
    }

    public function ultimoAtacanteId(): string
    {
        return $this->ultimoAtacanteId;
    }

    public function registrarDanoRecibido(float $dano, string $atacanteId): void
    {
        if ($dano > 0.0) {
            $this->ultimoDanoRecibido = $dano;
            $this->ultimoAtacanteId = $atacanteId;
            $this->danoRecibidoEstaRonda += $dano;
            $this->golpesRecibidos++;
        }
    }

    public function danoRecibidoEstaRonda(): float
    {
        return $this->danoRecibidoEstaRonda;
    }

    public function golpesRecibidos(): int
    {
        return $this->golpesRecibidos;
    }

    /**
     * Suma de etapas positivas de las estadísticas, usada por Poder Reserva y
     * Castigo. Cada factor > 1.0 aporta sus etapas equivalentes.
     */
    public function bonificacionesPositivas(): int
    {
        $total = 0;
        foreach ($this->multiplicadores->obtenerEtapas()->toArray() as $etapas) {
            if ($etapas > 0) {
                $total += $etapas;
            }
        }

        return $total;
    }

    public function peso(): float
    {
        $factor = $this->tieneEfecto('metal_pesado') ? 2.0 : ($this->tieneEfecto('metal_liviano') ? 0.5 : 1.0);

        return max(0.1, $this->pokemon->peso() * $factor);
    }

    public function setRondaActual(int $ronda): void
    {
        $this->turno->setRondaActual($ronda);
    }

    /**
     * Gasta velocidad para el siguiente turno (Velocidad Extrema).
     */
    public function aplicarCosteVelocidad(float $fraccion): void
    {
        $this->turno->aplicarCosteVelocidad($fraccion);
    }

    public function setClima(TipoClima $clima): void
    {
        $this->clima = $clima;
    }

    public function effects(): ColeccionEfectos
    {
        return $this->effects;
    }

    public function pokemon(): PokemonEntity
    {
        return $this->pokemon;
    }

    public function posicion(): Posicion
    {
        return $this->posicion;
    }

    // ─── Setters (mínimos necesarios) ─────────────────────────

    public function setHpActual(float $hpActual): void
    {
        $this->hpActual = $hpActual;
    }

    public function setDefensaHpActual(float $defensaHpActual): void
    {
        $this->defensaHpActual = $defensaHpActual;
    }

    public function setDefensaEspHpActual(float $defensaEspHpActual): void
    {
        $this->defensaEspHpActual = $defensaEspHpActual;
    }

    public function setVecesActuadoEstaRonda(int $vecesActuadoEstaRonda): void
    {
        if ($vecesActuadoEstaRonda === 0) {
            $this->danoRecibidoEstaRonda = 0.0;
        }

        $this->turno->setVecesActuado($vecesActuadoEstaRonda);
    }

    public function setEstado(EstadoPokemon $estado): void
    {
        if ($estado === EstadoPokemon::CONFUSION) {
            if (! $this->condiciones->tiene(ClaveCondicion::CONFUSION)) {
                $this->condiciones->add(new CondicionVolatil(
                    clave: ClaveCondicion::CONFUSION,
                    turnos: 2,
                    origenId: '',
                ));
            }

            return;
        }

        if ($estado !== EstadoPokemon::POISON && $estado !== EstadoPokemon::BAD_POISON) {
            $this->cargasVeneno = 0;
        }

        $this->estado = $estado;
    }

    public function setContadorVenenoGrave(int $contadorVenenoGrave): void
    {
        $this->contadorVenenoGrave = $contadorVenenoGrave;
    }

    public function setTurnosEstado(int $turnosEstado): void
    {
        $confusion = $this->condicion(ClaveCondicion::CONFUSION);
        if ($confusion !== null) {
            $confusion->turnos = $turnosEstado;

            return;
        }

        $this->turnosEstado = $turnosEstado;
    }

    public function setMultiplicadores(MultiplicadoresStats $multiplicadores): void
    {
        $this->multiplicadores = $multiplicadores;
    }

    public function avanzarMultiplicadoresTemporales(): void
    {
        $this->multiplicadores = $this->multiplicadores->avanzarTemporales();
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function setIconName(string $iconName): void
    {
        $this->iconName = $iconName;
    }

    public function setSpeciesId(int $speciesId): void
    {
        $this->speciesId = $speciesId;
    }

    public function setFormSuffix(string $formSuffix): void
    {
        $this->formSuffix = $formSuffix;
    }

    public function setShiny(bool $shiny): void
    {
        $this->shiny = $shiny;
    }

    public function setItem(string $item): void
    {
        $this->objeto->asignar($item);
    }

    public function setBonificadorDanio(float $bonificador): void
    {
        $this->bonificadorDanio = $bonificador;
    }

    public function setPosicion(Posicion $posicion): void
    {
        $this->posicion = $posicion;
    }

    public function postura(): PosturaCombate
    {
        return $this->turno->postura();
    }

    public function setPostura(PosturaCombate $postura): void
    {
        $this->turno->setPostura($postura);

        if ($postura !== PosturaCombate::ATACAR) {
            $this->turno->limpiarPrioridad();
            $this->romperCombo();
        }
    }

    public function declararPrioridad(MovimientoBatalla $movimiento): void
    {
        $this->turno->declararPrioridad($movimiento);
    }

    public function prioridadDeclarada(): ?MovimientoBatalla
    {
        return $this->turno->prioridad();
    }

    public function limpiarPrioridad(): void
    {
        $this->turno->limpiarPrioridad();
    }

    public function tienePrioridadDeclarada(): bool
    {
        return $this->turno->tienePrioridad();
    }

    public function objetivoCubiertoId(): string
    {
        return $this->turno->objetivoCubiertoId();
    }

    public function setObjetivoCubiertoId(string $objetivoCubiertoId): void
    {
        $this->turno->setObjetivoCubiertoId($objetivoCubiertoId);
    }

    // ─── Métodos existentes (adaptados a getters/setters) ────

    /**
     * @return array<string, mixed>
     */
    public function aArrayVista(int $teamIdx): array
    {
        return CombatienteVista::para($this, $teamIdx);
    }

    public function debilidadNotificada(): bool
    {
        return $this->debilidadNotificada;
    }

    public function estaVivo(): bool
    {
        return $this->barrerasVidaManager->estaVivo($this->hpActual);
    }

    // ─── Stat Stages ─────────────────────────────────────────

    /**
     * Retorna el stat base modificado por los multiplicadores actuales.
     * La parálisis reduce la velocidad a la mitad.
     */
    public function obtenerStatEfectivo(StatClave $stat): float
    {
        $baseStat = match ($stat) {
            StatClave::ATAQUE => $this->pokemon->battleStats()->attack,
            StatClave::DEFENSA => $this->pokemon->battleStats()->defense,
            StatClave::ATAQUE_ESPECIAL => $this->pokemon->battleStats()->spAtk,
            StatClave::DEFENSA_ESPECIAL => $this->pokemon->battleStats()->spDef,
            StatClave::VELOCIDAD => $this->pokemon->battleStats()->speed,
            default => 0,
        };

        $value = $baseStat * $this->multiplicadores->obtenerMultiplicador($stat);
        $value *= $this->effects->multiplicadorEstadistica($this, $stat);

        // La parálisis reduce la velocidad a la mitad
        if ($stat === StatClave::VELOCIDAD && $this->estado === EstadoPokemon::PARALYSIS) {
            $value *= ReglasBatalla::REDUCCION_VELOCIDAD_PARALISIS;
        }

        return $value;
    }

    /**
     * Velocidad efectiva: stat de velocidad con multiplicadores de etapas y
     * la penalización por parálisis aplicadas (StatClave::VELOCIDAD).
     */
    public function velocidadEfectiva(): float
    {
        $velocidad = $this->obtenerStatEfectivo(StatClave::VELOCIDAD);

        if ($this->turno->costeVelocidad() > 0.0
            && $this->turno->rondaActual() <= $this->turno->costeVelocidadHastaRonda()) {
            $velocidad *= max(0.0, 1.0 - $this->turno->costeVelocidad());
        }

        return $velocidad * $this->multiplicadorVelocidadJefe();
    }

    // ─── Estados (parálisis, sueño, hielo, confusión) ────────

    /**
     * Verifica si el combatiente puede actuar este turno según su estado.
     * Delega la lógica a EstadoManager y aplica las mutaciones resultantes.
     */
    public function puedeActuar(): ResultadoAccion
    {
        if (! $this->estaVivo()) {
            return ResultadoAccion::permitida();
        }

        if ($this->tieneCondicion(ClaveCondicion::INTERRUPCION)) {
            $this->quitarCondicion(ClaveCondicion::INTERRUPCION);
            $this->cancelarCargasMovimiento();
            $this->romperCombo();

            return ResultadoAccion::denegada('fue interrumpido');
        }

        if ($this->tieneCondicion(ClaveCondicion::ATURDIDO)) {
            $this->quitarCondicion(ClaveCondicion::ATURDIDO);
            $this->romperCombo();

            return ResultadoAccion::denegada('está aturdido');
        }

        if ($this->tieneCondicion(ClaveCondicion::RECARGA)) {
            $this->quitarCondicion(ClaveCondicion::RECARGA);
            $this->romperCombo();

            return ResultadoAccion::denegada('está recargando');
        }

        if ($this->tieneCondicion(ClaveCondicion::FLINCH)) {
            $this->quitarCondicion(ClaveCondicion::FLINCH);
            $this->romperCombo();

            return ResultadoAccion::denegada('retrocede');
        }

        $confusion = $this->condicion(ClaveCondicion::CONFUSION);
        if ($confusion !== null) {
            $resultado = $this->estadoManager->puedeActuar(
                EstadoPokemon::CONFUSION,
                $confusion->turnos,
                $this->hpActual,
                fn (StatClave $stat): float => $this->obtenerStatEfectivo($stat),
            );

            $confusion->turnos = $resultado['turnosResultado'];
            $this->hpActual = $resultado['hpResultado'];

            if ($resultado['estadoResultado'] === EstadoPokemon::NONE) {
                $this->quitarCondicion(ClaveCondicion::CONFUSION);
            }

            if (! $resultado['canAct']) {
                $this->romperCombo();
            }

            return self::resultadoAccionDesde($resultado);
        }

        if ($this->estado === EstadoPokemon::NONE) {
            return ResultadoAccion::permitida();
        }

        $resultado = $this->estadoManager->puedeActuar(
            $this->estado,
            $this->turnosEstado,
            $this->hpActual,
            fn (StatClave $stat): float => $this->obtenerStatEfectivo($stat),
        );

        $this->estado = $resultado['estadoResultado'];
        $this->turnosEstado = $resultado['turnosResultado'];
        $this->hpActual = $resultado['hpResultado'];

        if (! $resultado['canAct']) {
            $this->romperCombo();
        }

        return self::resultadoAccionDesde($resultado);
    }

    /**
     * Traduce el veredicto de EstadoManager (canAct/reason/selfDamage) al VO.
     *
     * @param  array{canAct: bool, reason: string, selfDamage: float}  $resultado
     */
    private static function resultadoAccionDesde(array $resultado): ResultadoAccion
    {
        if (! $resultado['canAct']) {
            return $resultado['selfDamage'] > 0
                ? ResultadoAccion::denegadaConAutoDanio($resultado['reason'], $resultado['selfDamage'])
                : ResultadoAccion::denegada($resultado['reason']);
        }

        return $resultado['reason'] === ''
            ? ResultadoAccion::permitida()
            : ResultadoAccion::permitidaConMotivo($resultado['reason']);
    }

    public function tieneEfecto(string $clave): bool
    {
        return $this->effects->find($clave) !== null;
    }

    /**
     * Porcentaje de daño directo a HP que ignora barreras,
     * calculado a partir de los efectos del portador.
     */
    public function obtenerPorcentajeDanioDirecto(): float
    {
        $pct = 0.0;
        foreach ($this->effects->all() as $effect) {
            $pct += $effect->obtenerPorcentajeDanioDirecto();
        }

        return min($pct, 1.0);
    }

    /**
     * Aplica daño desglosado entre barrera y HP.
     *
     * @param  float  $multiplicadorEscudo  Factor sobre el daño que intenta absorber la barrera.
     * @param  float  $multiplicadorSalud  Factor sobre el daño que llega a HP.
     */
    public function recibirDaño(
        float $daño,
        bool $isSpecial,
        float $directPct = 0.0,
        float $multiplicadorEscudo = 1.0,
        float $multiplicadorSalud = 1.0,
    ): ResultadoDanio {
        if ($this->tieneEfecto('divide_dolor') && $daño > 0.0) {
            return $this->recibirDañoDividido(
                $daño,
                $directPct,
                $multiplicadorEscudo,
                $multiplicadorSalud,
            );
        }

        $barreraActual = $isSpecial ? $this->defensaEspHpActual : $this->defensaHpActual;
        $resultado = $this->barrerasVidaManager->desglosarDaño(
            $daño,
            $barreraActual,
            $directPct,
            $multiplicadorEscudo,
            $multiplicadorSalud,
        );

        $this->hpActual = max(0.0, $this->hpActual - $resultado->danoHp);

        if ($isSpecial) {
            $this->defensaEspHpActual = max(0.0, $this->defensaEspHpActual - $resultado->danoEscudo);
        } else {
            $this->defensaHpActual = max(0.0, $this->defensaHpActual - $resultado->danoEscudo);
        }

        return $resultado;
    }

    /**
     * Divide Dolor: reparte el daño a partes iguales entre barrera física y
     * especial, incluyendo el excedente que llega a HP.
     */
    private function recibirDañoDividido(
        float $daño,
        float $directPct,
        float $multiplicadorEscudo,
        float $multiplicadorSalud,
    ): ResultadoDanio {
        $mitad = $daño / 2.0;
        $fisico = $this->barrerasVidaManager->desglosarDaño(
            $mitad,
            $this->defensaHpActual,
            $directPct,
            $multiplicadorEscudo,
            $multiplicadorSalud,
        );
        $especial = $this->barrerasVidaManager->desglosarDaño(
            $mitad,
            $this->defensaEspHpActual,
            $directPct,
            $multiplicadorEscudo,
            $multiplicadorSalud,
        );

        $this->hpActual = max(0.0, $this->hpActual - $fisico->danoHp - $especial->danoHp);
        $this->defensaHpActual = max(0.0, $this->defensaHpActual - $fisico->danoEscudo);
        $this->defensaEspHpActual = max(0.0, $this->defensaEspHpActual - $especial->danoEscudo);

        $resultado = new ResultadoDanio(
            danoHp: $fisico->danoHp + $especial->danoHp,
            danoEscudo: $fisico->danoEscudo + $especial->danoEscudo,
            excedente: $fisico->excedente + $especial->excedente,
            rompioEscudo: $fisico->rompioEscudo || $especial->rompioEscudo,
        );

        return $resultado;
    }

    public function curarHp(float $porcentaje): void
    {
        $this->hpActual = $this->barrerasVidaManager->curarHp(
            $this->hpActual,
            $this->pokemon->battleStats()->hp,
            $porcentaje,
        );
    }

    public function curarHpCantidad(float $cantidad): float
    {
        if ($cantidad <= 0.0 || ! $this->estaVivo()) {
            return 0.0;
        }

        $maxHp = $this->pokemon->battleStats()->hp;
        $curado = min($cantidad, max(0.0, $maxHp - $this->hpActual));
        $this->hpActual += $curado;

        return $curado;
    }

    /**
     * Cura una cantidad absoluta de HP y convierte el excedente en escudo.
     * Orden del excedente: especial → físico (Danza Lunar) o físico → especial.
     */
    public function curarConExcedente(float $cantidad, bool $excedenteAEscudoEspecial = true): float
    {
        if ($cantidad <= 0.0 || ! $this->estaVivo()) {
            return 0.0;
        }

        $maxHp = $this->pokemon->battleStats()->hp;
        $curadoHp = min($cantidad, max(0.0, $maxHp - $this->hpActual));
        $this->hpActual += $curadoHp;
        $restante = $cantidad - $curadoHp;

        if ($restante <= 0.0) {
            return $curadoHp;
        }

        $maxDef = $this->pokemon->battleStats()->defenseHp;
        $maxSpDef = $this->pokemon->battleStats()->spDefenseHp;

        if ($excedenteAEscudoEspecial) {
            $curadoEsp = min($restante, max(0.0, $maxSpDef - $this->defensaEspHpActual));
            $this->defensaEspHpActual += $curadoEsp;
            $restante -= $curadoEsp;
            $curadoFis = min($restante, max(0.0, $maxDef - $this->defensaHpActual));
            $this->defensaHpActual += $curadoFis;
        } else {
            $curadoFis = min($restante, max(0.0, $maxDef - $this->defensaHpActual));
            $this->defensaHpActual += $curadoFis;
            $restante -= $curadoFis;
            $curadoEsp = min($restante, max(0.0, $maxSpDef - $this->defensaEspHpActual));
            $this->defensaEspHpActual += $curadoEsp;
        }

        return $curadoHp;
    }

    /**
     * Destruye una cantidad absoluta de una barrera (Presión) y devuelve lo
     * realmente destruido.
     */
    public function daniarBarreras(float $cantidad, bool $especial = true): float
    {
        if ($cantidad <= 0.0) {
            return 0.0;
        }

        if ($especial) {
            $destruido = min($cantidad, $this->defensaEspHpActual);
            $this->defensaEspHpActual -= $destruido;

            return $destruido;
        }

        $destruido = min($cantidad, $this->defensaHpActual);
        $this->defensaHpActual -= $destruido;

        return $destruido;
    }

    /**
     * Aplica el daño por efecto de estado al final de la ronda.
     * Delega el cálculo a EstadoManager y aplica el daño a la barrera especial
     * (con desbordamiento a HP), igual que un ataque especial.
     *
     * @return float Daño real infligido
     */
    public function aplicarDañoStatus(): float
    {
        if (! $this->estaVivo() || $this->estado === EstadoPokemon::NONE) {
            return 0;
        }

        $resultado = $this->estadoManager->calcularDanoStatus(
            $this->estado,
            $this->contadorVenenoGrave,
            $this->pokemon->battleStats()->hp,
            $this->cargasVeneno,
        );

        $this->contadorVenenoGrave = $resultado['contadorNuevo'];

        if ($resultado['dano'] > 0) {
            $this->recibirDaño($resultado['dano'], true);
        }

        return $resultado['dano'];
    }

    public function curarBarreras(float $porcentaje): void
    {
        $resultado = $this->barrerasVidaManager->curarBarreras(
            $this->defensaHpActual,
            $this->defensaEspHpActual,
            $this->pokemon->battleStats()->defenseHp,
            $this->pokemon->battleStats()->spDefenseHp,
            $porcentaje,
        );

        $this->defensaHpActual = $resultado['defensaHpNuevo'];
        $this->defensaEspHpActual = $resultado['defensaEspHpNuevo'];
    }

    public function estaEnVanguardia(): bool
    {
        return $this->posicion === Posicion::VANGUARDIA;
    }

    public function estaEnRetaguardia(): bool
    {
        return $this->posicion === Posicion::RETAGUARDIA;
    }

    // ─── Event triggers (delegan a ColeccionEfectos) ─────────

    public function dispararDanioInfligido(Combatiente $target, float $daño, AgregadoBatalla $battle): void
    {
        $this->effects->dispararDanioInfligido($this, $target, $daño, $battle);
    }

    public function dispararDanioRecibido(float $daño, AgregadoBatalla $battle): void
    {
        $this->effects->dispararDanioRecibido($this, $daño, $battle);
    }

    public function dispararDanioRecibidoCompleto(
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        $this->effects->dispararDanioRecibidoCompleto($this, $accion, $resultado, $battle);
    }

    public function contraatacar(AccionBatalla $accion, float $danoRecibido, AgregadoBatalla $battle): void
    {
        $this->effects->contraatacar($this, $accion, $danoRecibido, $battle);
    }

    public function dispararContacto(Combatiente $atacante, AgregadoBatalla $battle): void
    {
        $this->effects->dispararContacto($this, $atacante, $battle);
    }

    public function dispararDebilitamiento(AgregadoBatalla $battle): void
    {
        $this->effects->dispararDebilitamiento($this, $battle);
    }

    /**
     * Notifica una muerte una sola vez, aunque la detecten varias rutas.
     */
    public function notificarDebilitamiento(AgregadoBatalla $battle): bool
    {
        if ($this->estaVivo() || $this->debilidadNotificada) {
            return false;
        }

        $this->debilidadNotificada = true;
        $this->dispararDebilitamiento($battle);
        $battle->subject()->notifyFainted($this);
        $battle->sincronizarGasReactivo();

        return true;
    }

    public function dispararEscudoRoto(
        Combatiente $atacante,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        $this->effects->dispararEscudoRoto($this, $atacante, $resultado, $battle);
    }

    public function dispararSerObjetivo(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        $this->effects->dispararSerObjetivo($this, $accion, $battle);
    }

    // ─── Capacidades (delegan a ColeccionEfectos) ────────────

    public function ajustarGolpes(AccionBatalla $accion): ?AjusteGolpes
    {
        return $this->effects->ajustarGolpes($accion);
    }

    public function multiplicadorDanioInfligido(AccionBatalla $accion): float
    {
        return $this->effects->multiplicadorDanioInfligido($accion);
    }

    public function multiplicadorDanioEscudo(AccionBatalla $accion): float
    {
        return $this->effects->multiplicadorDanioEscudo($accion);
    }

    public function multiplicadorEfectoEstado(): float
    {
        return $this->tieneEfecto('meditacion') ? 2.0 : 1.0;
    }

    public function multiplicadorDanioRecibido(AccionBatalla $accion): float
    {
        return $this->effects->multiplicadorDanioRecibido($accion);
    }

    public function multiplicadorPrecisionAtaque(): float
    {
        return $this->effects->multiplicadorPrecisionAtaque();
    }

    public function multiplicadorPrecisionEstado(): float
    {
        return $this->effects->multiplicadorPrecisionEstado();
    }

    public function esInmuneAFlag(\Src\Battle\Domain\Enums\FlagMovimiento $flag): bool
    {
        return $this->effects->inmuneAFlag($flag);
    }

    public function inmunidadTipo(TipoPokemon $tipo): ?InmunidadTipo
    {
        return $this->effects->inmunidadTipo($tipo);
    }

    public function esInmuneACritico(): bool
    {
        return $this->effects->esInmuneACritico();
    }

    public function multiplicadorProbabilidadCritico(): float
    {
        return $this->effects->multiplicadorProbabilidadCritico();
    }

    public function multiplicadorStab(AccionBatalla $accion): float
    {
        return $this->effects->multiplicadorStab($accion);
    }

    public function tipoEfectivoMovimiento(AccionBatalla $accion): TipoPokemon
    {
        return $this->effects->tipoEfectivoMovimiento($accion) ?? $accion->move->tipo;
    }

    public function probabilidadEsquiva(): float
    {
        return $this->effects->probabilidadEsquiva($this);
    }

    public function esInmuneARecoil(): bool
    {
        return $this->effects->esInmuneARecoil();
    }

    public function bloqueaDanioIndirecto(): bool
    {
        return $this->effects->bloqueaDanioIndirecto();
    }

    public function esInmuneAClima(TipoClima $clima): bool
    {
        return $this->effects->esInmuneAClima($clima);
    }
}
