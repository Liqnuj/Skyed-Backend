<?php

namespace App\Support;

use App\Models\Reserva;
use App\Models\ReservaGestion;
use App\Services\ReservaFlujo;

/**
 * Qué relaciones se cargan y qué información de apoyo (meta) acompaña
 * a una reserva cuando la consulta el Administrador Social.
 */
class ReservaDetalle
{
    private const USUARIO = 'usuario:id_u,nombre_u,apellido_u,correo_u,telefono_u,ciudad_u,tipo_documento_u,documento_u';

    /** Para el detalle completo. */
    public static function relacionesAdmin(): array
    {
        return [
            self::USUARIO,
            'evento',
            'tipoEvento',
            'gestion.responsable:id_u,nombre_u,apellido_u',
            'gestion.ambiente:id_a,nombre_a,capacidad_a',
            'servicios.servicio:id_s,nombre_s',
            'servicios.responsable:id_u,nombre_u,apellido_u',
            'seguimientos' => fn ($q) => $q->orderBy('fecha_actualizacion')->orderBy('id_seguimiento'),
            'seguimientos.administrador:id_u,nombre_u,apellido_u',
        ];
    }

    /** Para el listado (más liviano). */
    public static function relacionesListado(): array
    {
        return [
            self::USUARIO,
            'evento',
            'tipoEvento',
            'gestion.responsable:id_u,nombre_u,apellido_u',
            'gestion.ambiente:id_a,nombre_a,capacidad_a',
        ];
    }

    public static function meta(Reserva $reserva): array
    {
        $grupo = CamposEvento::grupoPara($reserva->tipoEvento?->nombre_tipo_eves);
        $fecha = ReservaFechas::efectiva($reserva);

        return [
            'grupo_tipo' => $grupo,
            'campos_tipo' => CamposEvento::definicion($grupo),
            'etapas' => ReservaGestion::ETIQUETAS_ETAPA,
            'etapas_permitidas' => ReservaFlujo::etapasPermitidas($reserva),
            'estados_permitidos' => ReservaFlujo::estadosPermitidos($reserva),
            'medios_contacto' => ReservaGestion::MEDIOS_CONTACTO,
            'estado_visual' => ReservaFlujo::estadoVisual($reserva),
            'fecha_efectiva' => $fecha + ReservaFechas::horas($reserva),
        ];
    }
}