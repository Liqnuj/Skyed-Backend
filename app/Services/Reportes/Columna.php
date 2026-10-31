<?php

namespace App\Services\Reportes;

/**
 * Describe una columna de un reporte: cómo se llama, qué tipo de dato
 * contiene (para darle formato correcto en PDF y en Excel) y si debe
 * aparecer en cada formato.
 */
final class Columna
{
    public const TEXTO = 'texto';
    public const ENTERO = 'entero';
    public const MONEDA = 'moneda';
    public const FECHA = 'fecha';            // solo día (no se convierte de zona horaria)
    public const FECHA_HORA = 'fechahora';   // se convierte de UTC a America/Bogota

    public function __construct(
        public readonly string $clave,
        public readonly string $titulo,
        public readonly string $tipo = self::TEXTO,
        public readonly int $peso = 10,          // ancho relativo (PDF y Excel)
        public readonly bool $enPdf = true,
        public readonly bool $enExcel = true,
    ) {
    }

    /** ¿La columna se alinea a la derecha? (números y dinero) */
    public function numerica(): bool
    {
        return in_array($this->tipo, [self::ENTERO, self::MONEDA], true);
    }
}