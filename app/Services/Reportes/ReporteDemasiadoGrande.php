<?php

namespace App\Services\Reportes;

use RuntimeException;

/** El reporte supera el máximo de filas que admite el PDF. */
class ReporteDemasiadoGrande extends RuntimeException
{
    public function __construct(public readonly int $limite)
    {
        parent::__construct(
            "El reporte tiene más de {$limite} registros y no cabe en un PDF. "
            . 'Aplica filtros para reducirlo o exporta a Excel.'
        );
    }
}