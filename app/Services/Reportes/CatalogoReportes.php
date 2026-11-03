<?php

namespace App\Services\Reportes;

use App\Services\Reportes\Definiciones\Deportivo\EventosReporte as EventosDeportivosReporte;
use App\Services\Reportes\Definiciones\Deportivo\InscripcionesPagosReporte;
use App\Services\Reportes\Definiciones\Deportivo\KitsReporte;
use App\Services\Reportes\Definiciones\Deportivo\ResultadosReporte;
use App\Services\Reportes\Definiciones\Deportivo\UsuariosReporte as UsuariosDeportivoReporte;
use App\Services\Reportes\Definiciones\Social\AmbientesReporte;
use App\Services\Reportes\Definiciones\Social\EventosReporte as EventosSocialesReporte;
use App\Services\Reportes\Definiciones\Social\PqrsReporte;
use App\Services\Reportes\Definiciones\Social\ReservasReporte;
use App\Services\Reportes\Definiciones\Social\ServiciosReporte;
use App\Services\Reportes\Definiciones\Social\UsuariosReporte as UsuariosSocialReporte;

/**
 * Lista de reportes disponibles, agrupados por módulo. Cada módulo se
 * expone en rutas distintas protegidas por el rol correspondiente, así que
 * un reporte de Social nunca se puede pedir por la ruta de Deportivo.
 */
final class CatalogoReportes
{
    private const MAPA = [
        'social' => [
            'ambientes' => AmbientesReporte::class,
            'servicios' => ServiciosReporte::class,
            'eventos' => EventosSocialesReporte::class,
            'reservas' => ReservasReporte::class,
            'pqrs' => PqrsReporte::class,
            'usuarios' => UsuariosSocialReporte::class,
        ],
        'deportivo' => [
            'eventos' => EventosDeportivosReporte::class,
            'inscripciones-pagos' => InscripcionesPagosReporte::class,
            'resultados' => ResultadosReporte::class,
            'kits' => KitsReporte::class,
            'usuarios' => UsuariosDeportivoReporte::class,
        ],
    ];

    public static function buscar(string $modulo, string $clave): ?Reporte
    {
        $clase = self::MAPA[$modulo][$clave] ?? null;

        return $clase ? new $clase() : null;
    }
}