<?php

namespace App\Services\Reportes;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

/**
 * Genera el PDF de un reporte con Dompdf (renderiza HTML/CSS a PDF).
 * Dompdf arma el documento completo en memoria, por eso hay un tope de filas.
 */
class ExportadorPdf
{
    /** Dompdf usa ~100 MB por cada 500 filas; 1000 filas ≈ 260 MB y ~6 s (medido). */
    public const LIMITE_FILAS = 1000;

    /** @throws ReporteDemasiadoGrande */
    public function generar(Reporte $reporte, array $filtros, string $generadoPor): string
    {
        // Dompdf necesita más memoria/tiempo que el límite por defecto de PHP (128 MB / 30 s).
        $limite = $this->bytes((string) ini_get('memory_limit'));
        if ($limite !== -1 && $limite < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }
        set_time_limit(120);

        $columnas = array_values(array_filter($reporte->columnas(), fn (Columna $c) => $c->enPdf));

        $filas = [];
        foreach ($reporte->filas($filtros) as $fila) {
            if (count($filas) >= self::LIMITE_FILAS) {
                throw new ReporteDemasiadoGrande(self::LIMITE_FILAS);
            }

            $filas[] = array_map(
                fn (Columna $c) => Formato::paraPdf($c, $fila[$c->clave] ?? null),
                $columnas
            );
        }

        $orientacion = count($columnas) > 6 ? 'landscape' : 'portrait';

        $html = View::make('reportes.pdf', [
            'reporte' => $reporte,
            'columnas' => $columnas,
            'filas' => $filas,
            'total' => count($filas),
            'filtros' => $reporte->filtrosAplicados($filtros),
            'resumen' => $reporte->resumen($filtros),
            'generado' => Formato::ahora()->format('d/m/Y H:i'),
            'generadoPor' => $generadoPor,
            'colores' => $this->colores($reporte->tema()),
            'logo' => $this->logo(),
            'sumaPesos' => max(1, array_sum(array_map(fn (Columna $c) => $c->peso, $columnas))),
        ])->render();

        $opciones = new Options();
        $opciones->set('defaultFont', 'DejaVu Sans');       // incluye tildes y ñ
        $opciones->set('isRemoteEnabled', false);            // nunca descargar recursos externos
        $opciones->set('isPhpEnabled', false);
        $opciones->set('isHtml5ParserEnabled', true);
        $opciones->set('tempDir', $this->carpeta('dompdf/tmp'));
        $opciones->set('fontCache', $this->carpeta('dompdf/fonts'));
        $opciones->set('chroot', base_path());

        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('letter', $orientacion);
        $pdf->render();

        // Pie de página con paginación (se dibuja en cada hoja).
        $canvas = $pdf->getCanvas();
        $fuente = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $ancho = $canvas->get_width();
        $alto = $canvas->get_height();
        $gris = [0.45, 0.45, 0.5];
        $canvas->page_text(28, $alto - 26, 'SKYED · ' . $reporte->modulo() . ' · ' . $reporte->titulo(), $fuente, 7.5, $gris);
        $canvas->page_text($ancho - 100, $alto - 26, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 7.5, $gris);

        return $pdf->output();
    }

    /** Convierte "128M", "1G", "-1"… a bytes (-1 = sin límite). */
    private function bytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '-1') {
            return -1;
        }

        $numero = (int) $valor;

        return match (strtoupper(substr($valor, -1))) {
            'G' => $numero * 1024 ** 3,
            'M' => $numero * 1024 ** 2,
            'K' => $numero * 1024,
            default => $numero,
        };
    }

    private function colores(string $tema): array
    {
        return match ($tema) {
            'social' => ['primario' => '#2d1454', 'acento' => '#9c02ae', 'suave' => '#f5e6ef', 'linea' => '#c9a84c'],
            'deportivo' => ['primario' => '#091d36', 'acento' => '#2c9caf', 'suave' => '#e6f4f6', 'linea' => '#2c9caf'],
            default => ['primario' => '#1e2a78', 'acento' => '#6d28d9', 'suave' => '#ede9fe', 'linea' => '#6d28d9'],
        };
    }

    /** Logo de SKYED embebido (public/img/logoP.png). Si no existe, el PDF sale solo con texto. */
    private function logo(): ?string
    {
        $ruta = public_path('img/logoP.png');

        return is_file($ruta)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($ruta))
            : null;
    }

    private function carpeta(string $relativa): string
    {
        $ruta = storage_path('app/' . $relativa);

        if (!is_dir($ruta)) {
            mkdir($ruta, 0775, true);
        }

        return $ruta;
    }
}