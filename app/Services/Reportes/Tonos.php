<?php

namespace App\Services\Reportes;

/**
 * Color que se le da a un estado en el PDF y en el Excel (verde, rojo, ámbar…).
 * Solo se aplica a las columnas de estado, por la clave de la columna.
 */
class Tonos
{
    /** Claves de columna cuyo valor es un estado. */
    public const COLUMNAS_ESTADO = ['estado', 'pago', 'fase', 'inscripcion', 'competidor', 'vencida'];

    /** Fondo y texto de cada tono, sin "#" (sirven para Excel; el PDF les antepone "#"). */
    public const COLORES = [
        'ok' => ['fondo' => 'DCFCE7', 'texto' => '166534'],
        'mal' => ['fondo' => 'FEE2E2', 'texto' => '991B1B'],
        'aviso' => ['fondo' => 'FEF3C7', 'texto' => '92400E'],
        'info' => ['fondo' => 'DBEAFE', 'texto' => '1E40AF'],
        'neutro' => ['fondo' => 'ECEEF3', 'texto' => '475569'],
    ];

    public static function esColumnaDeEstado(Columna $c): bool
    {
        return in_array($c->clave, self::COLUMNAS_ESTADO, true);
    }

    /** ok = verde, mal = rojo, aviso = ámbar, info = azul, neutro = gris. */
    public static function de(string $valor): string
    {
        $v = mb_strtolower(trim($valor));
        $v = strtr($v, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return match (true) {
            in_array($v, ['activo', 'confirmada', 'confirmado', 'oficial', 'resuelto', 'resuelta', 'cerrada', 'cerrado',
                'respondida', 'respondido', 'clasificado', 'completada', 'completado', 'no'], true) => 'ok',
            in_array($v, ['inactivo', 'cancelada', 'cancelado', 'rechazado', 'rechazada', 'descalificado',
                'inhabilitado', 'vencida', 'si'], true) => 'mal',
            in_array($v, ['pendiente', 'en revision', 'en proceso', 'por validar', 'en curso'], true) => 'aviso',
            in_array($v, ['enviado', 'enviada', 'recibido', 'programado'], true) => 'info',
            default => 'neutro',
        };
    }
}