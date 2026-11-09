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
        $tema = $this->tema($reporte->tema());

        // Columnas de estado: se pintan como "píldoras" de color (verde, rojo, ámbar…).
        $tonos = [];
        foreach ($filas as $i => $fila) {
            foreach ($columnas as $j => $c) {
                if (Tonos::esColumnaDeEstado($c)) {
                    $tonos[$i][$j] = Tonos::de((string) $fila[$j]);
                }
            }
        }

        $html = View::make('reportes.pdf', [
            'reporte' => $reporte,
            'columnas' => $columnas,
            'filas' => $filas,
            'total' => count($filas),
            'filtros' => $reporte->filtrosAplicados($filtros),
            'resumen' => $reporte->resumen($filtros),
            'generado' => Formato::ahora()->format('d/m/Y H:i'),
            'generadoPor' => $generadoPor,
            'tema' => $tema,
            'tonos' => $tonos,
            'anchoPagina' => $orientacion === 'landscape' ? 1056 : 816, // px CSS de una hoja carta
            'ahora' => Formato::ahora(),
            'logo' => $this->logo($reporte->tema()),
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
        $tinta = $this->rgb($tema['primario']);
        $canvas->page_text(26, $alto - 17, 'SKYED · ' . $reporte->modulo() . ' · ' . $reporte->titulo(), $fuente, 7, $tinta);
        $canvas->page_text($ancho - 98, $alto - 17, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 7, $tinta);

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

    /**
     * Identidad visual de cada panel: Social (ciruela y dorado, titulares con serifa),
     * Deportivo (azul marino y turquesa, titulares en mayúsculas) y Administración (índigo).
     */
    private function tema(string $tema): array
    {
        return match ($tema) {
            'social' => [
                'clave' => 'social', 'primario' => '#2d1454', 'acento' => '#9c02ae', 'dorado' => '#c9a84c',
                'linea' => '#c9a84c', 'suave' => '#f5e6ef', 'zebra' => '#fbf5f9', 'trazo' => '#ece1ea',
                'marca' => '#e8c97a', 'fuente_titulo' => 'DejaVu Serif', 'mayusculas' => false,
            ],
            'deportivo' => [
                'clave' => 'deportivo', 'primario' => '#091d36', 'acento' => '#1d8ea3', 'dorado' => '#2c9caf',
                'linea' => '#2c9caf', 'suave' => '#e3f2f5', 'zebra' => '#f3fafb', 'trazo' => '#dbe8ec',
                'marca' => '#6fd3e3', 'fuente_titulo' => 'DejaVu Sans', 'mayusculas' => true,
            ],
            default => [
                'clave' => 'general', 'primario' => '#1e2a78', 'acento' => '#6d28d9', 'dorado' => '#8b5cf6',
                'linea' => '#8b5cf6', 'suave' => '#ede9fe', 'zebra' => '#f7f5ff', 'trazo' => '#e4e0f3',
                'marca' => '#c4b5fd', 'fuente_titulo' => 'DejaVu Sans', 'mayusculas' => false,
            ],
        };
    }

    /** "#2d1454" → [r, g, b] entre 0 y 1 (formato que usa el canvas de Dompdf). */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /**
     * Logo de cada módulo, embebido en el PDF. Se busca en public/img/ así:
     *   social    → logo-social.png   (o .jpg / .jpeg)
     *   deportivo → logo-deportivo.png
     *   general   → logoP.png
     * Si el logo del módulo no existe, se usa logoP.png; si tampoco, el PDF sale solo con texto.
     *
     * @return array{src: string, w: int, h: int}|null  w/h = tamaño ya ajustado a la insignia (40 × 26 px)
     */
    private function logo(string $tema): ?array
    {
        $candidatos = [];
        if (in_array($tema, ['social', 'deportivo'], true)) {
            foreach (['png', 'jpg', 'jpeg'] as $ext) {
                $candidatos[] = public_path("img/logo-{$tema}.{$ext}");
            }
        }
        $candidatos[] = public_path('img/logoP.png');

        foreach ($candidatos as $ruta) {
            if (! is_file($ruta)) {
                continue;
            }
            $medidas = @getimagesize($ruta);
            if (! $medidas || ! in_array($medidas[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
                continue;
            }
            $escala = min(40 / $medidas[0], 26 / $medidas[1]);

            return [
                'src' => 'data:' . $medidas['mime'] . ';base64,' . base64_encode(file_get_contents($ruta)),
                'w' => max(1, (int) round($medidas[0] * $escala)),
                'h' => max(1, (int) round($medidas[1] * $escala)),
            ];
        }

        return null;
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