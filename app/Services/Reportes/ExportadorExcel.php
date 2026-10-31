<?php

namespace App\Services\Reportes;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera el .xlsx con OpenSpout, que escribe fila por fila directamente al
 * destino (memoria constante aunque el reporte tenga miles de registros).
 *
 * Hoja 1 "Datos": encabezados + registros (lista para filtrar/ordenar/tablas dinámicas).
 * Hoja 2 "Información": nombre, fecha, filtros usados y total de registros.
 */
class ExportadorExcel
{
    private const COLORES = [
        'social' => '2D1454',
        'deportivo' => '091D36',
        'general' => '1E2A78',
    ];

    /** @param resource|string $destino ruta de archivo o 'php://output' */
    public function generar(Reporte $reporte, array $filtros, string $generadoPor, string $destino): void
    {
        $columnas = array_values(array_filter($reporte->columnas(), fn (Columna $c) => $c->enExcel));

        $opciones = new Options();
        foreach ($columnas as $i => $columna) {
            $opciones->setColumnWidth(max(8, min(60, $columna->peso * 1.1)), $i + 1);
        }

        $escritor = new Writer($opciones);
        $escritor->openToFile($destino);

        $hoja = $escritor->getCurrentSheet();
        $hoja->setName('Datos');
        $hoja->setSheetView((new SheetView())->setFreezeRow(2));

        $color = self::COLORES[$reporte->tema()] ?? self::COLORES['general'];
        $estiloEncabezado = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor($color)
            ->setShouldWrapText()
            ->setCellAlignment(CellAlignment::LEFT);

        $escritor->addRow(new Row(
            array_map(fn (Columna $c) => new StringCell($c->titulo, $estiloEncabezado), $columnas)
        ));

        $estilos = $this->estilos();
        $total = 0;

        foreach ($reporte->filas($filtros) as $fila) {
            $celdas = [];
            foreach ($columnas as $columna) {
                $celdas[] = $this->celda($columna, $fila[$columna->clave] ?? null, $estilos);
            }
            $escritor->addRow(new Row($celdas));
            $total++;
        }

        // Autofiltro sobre los encabezados (permite filtrar/ordenar en Excel).
        $hoja->setAutoFilter(new AutoFilter(0, 1, max(0, count($columnas) - 1), $total + 1));

        // Hoja 2: se escribe al final porque aquí ya se conoce el total.
        $info = $escritor->addNewSheetAndMakeItCurrent();
        $info->setName('Información');
        $info->setColumnWidth(32, 1);
        $info->setColumnWidth(60, 2);

        $negrita = (new Style())->setFontBold();
        $titulo = (new Style())->setFontBold()->setFontSize(14)->setFontColor($color);

        $lineas = [
            [$reporte->titulo(), null, $titulo],
            ['Módulo', $reporte->modulo(), $negrita],
            ['Generado el', Formato::ahora()->format('d/m/Y H:i') . ' (hora de Colombia)', $negrita],
            ['Generado por', $generadoPor, $negrita],
            ['Total de registros', $total, $negrita],
        ];

        foreach ($reporte->resumen($filtros) as $etiqueta => $valor) {
            $lineas[] = [$etiqueta, $valor, $negrita];
        }

        $lineas[] = ['', null, null];
        $lineas[] = ['Filtros aplicados', null, $titulo];
        $filtrosAplicados = $reporte->filtrosAplicados($filtros);
        if ($filtrosAplicados === []) {
            $lineas[] = ['Ninguno (todos los registros)', null, null];
        }
        foreach ($filtrosAplicados as $etiqueta => $valor) {
            $lineas[] = [$etiqueta, $valor, $negrita];
        }

        foreach ($lineas as [$a, $b, $estilo]) {
            $escritor->addRow(new Row([
                new StringCell((string) $a, $estilo),
                $b === null ? Cell::fromValue(null) : (is_int($b) ? Cell::fromValue($b) : new StringCell((string) $b, null)),
            ]));
        }

        $escritor->close();
    }

    /** @return array<string, Style> */
    private function estilos(): array
    {
        return [
            Columna::TEXTO => (new Style())->setShouldWrapText()->setCellVerticalAlignment('top'),
            Columna::ENTERO => (new Style())->setFormat('0'),
            Columna::MONEDA => (new Style())->setFormat('"$"#,##0'),
            Columna::FECHA => (new Style())->setFormat('dd/mm/yyyy')->setCellAlignment(CellAlignment::LEFT),
            Columna::FECHA_HORA => (new Style())->setFormat('dd/mm/yyyy hh:mm')->setCellAlignment(CellAlignment::LEFT),
        ];
    }

    private function celda(Columna $columna, mixed $valor, array $estilos): Cell
    {
        $estilo = $estilos[$columna->tipo];

        if ($valor === null || $valor === '') {
            return Cell::fromValue(null, $estilo);
        }

        return match ($columna->tipo) {
            Columna::ENTERO => Cell::fromValue((int) $valor, $estilo),
            Columna::MONEDA => Cell::fromValue((float) $valor, $estilo),
            Columna::FECHA => Cell::fromValue(
                new \DateTimeImmutable(Formato::aCarbon($valor)->format('Y-m-d')),
                $estilo
            ),
            // Excel no maneja zonas horarias: se escribe la hora de pared de Colombia.
            Columna::FECHA_HORA => Cell::fromValue(
                new \DateTimeImmutable(
                    Formato::aCarbon($valor)->setTimezone(Formato::ZONA)->format('Y-m-d H:i:s')
                ),
                $estilo
            ),
            // StringCell explícito: así un texto que empiece por "=" NUNCA se interpreta como fórmula.
            default => new StringCell((string) $valor, $estilo),
        };
    }
}