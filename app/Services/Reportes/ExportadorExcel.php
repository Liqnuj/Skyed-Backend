<?php

namespace App\Services\Reportes;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\HeaderFooter;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera el .xlsx con OpenSpout, que escribe fila por fila directamente al
 * destino (memoria constante aunque el reporte tenga miles de registros).
 *
 * Hoja 1 "Datos": banda de marca, título, fecha, filtros, encabezado de color y
 *                 registros (filas alternadas, estados en color, autofiltro).
 * Hoja 2 "Información": resumen del reporte, filtros usados y total.
 *
 * Cada módulo (Social, Deportivo, Administración) tiene su propia paleta.
 */
class ExportadorExcel
{
    /** Filas fijas arriba de los datos: banda, título, fecha, filtros y encabezado. */
    private const FILA_ENCABEZADO = 5;

    /** Paletas por módulo (hex sin "#"). */
    private const TEMAS = [
        'social' => [
            'primario' => '2D1454', 'acento' => '9C02AE', 'linea' => 'C9A84C', 'suave' => 'F5E6EF',
            'zebra' => 'FBF5F9', 'trazo' => 'ECE1EA', 'marca' => 'E8C97A', 'fuente' => 'Georgia',
        ],
        'deportivo' => [
            'primario' => '091D36', 'acento' => '1D8EA3', 'linea' => '2C9CAF', 'suave' => 'E3F2F5',
            'zebra' => 'F3FAFB', 'trazo' => 'DBE8EC', 'marca' => '6FD3E3', 'fuente' => 'Calibri',
        ],
        'general' => [
            'primario' => '1E2A78', 'acento' => '6D28D9', 'linea' => '8B5CF6', 'suave' => 'EDE9FE',
            'zebra' => 'F7F5FF', 'trazo' => 'E4E0F3', 'marca' => 'C4B5FD', 'fuente' => 'Calibri',
        ],
    ];

    private const BLANCO = 'FFFFFF';
    private const GRIS_TEXTO = '6B6878';
    private const GRIS_ID = '8D8A99';

    /** @var array<string, Style> caché de estilos de las filas de datos */
    private array $cacheEstilos = [];

    /** @param resource|string $destino ruta de archivo o 'php://output' */
    public function generar(Reporte $reporte, array $filtros, string $generadoPor, string $destino): void
    {
        $t = self::TEMAS[$reporte->tema()] ?? self::TEMAS['general'];
        $this->cacheEstilos = [];

        $columnas = array_values(array_filter($reporte->columnas(), fn (Columna $c) => $c->enExcel));
        $n = count($columnas);
        $ultima = max(0, $n - 1);
        $hFiltros = $reporte->filtrosAplicados($filtros);

        $opciones = new Options();
        foreach ($columnas as $i => $columna) {
            $opciones->setColumnWidth(max(9, min(60, $columna->peso * 1.15)), $i + 1);
        }

        // Filas de la cabecera que ocupan todo el ancho (banda, título, fecha y filtros).
        if ($n > 1) {
            for ($fila = 1; $fila <= 4; $fila++) {
                $opciones->mergeCells(0, $fila, $ultima, $fila);
            }
        }

        // Impresión: horizontal si hay muchas columnas, ajustada al ancho de la hoja.
        $opciones->setPageSetup(new PageSetup(
            $n > 6 ? PageOrientation::LANDSCAPE : PageOrientation::PORTRAIT,
            PaperSize::Letter,
            0,
            1
        ));
        // OpenSpout copia este texto tal cual al XML: los "&" propios del formato de Excel
        // (&L, &R, &P, &N) se escriben como "&amp;"; y un "&" del nombre del módulo, como "&amp;&amp;".
        $modulo = htmlspecialchars(str_replace('&', '&&', $reporte->modulo()), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $opciones->setHeaderFooter(new HeaderFooter(
            oddFooter: '&amp;LSKYED · ' . $modulo . '&amp;RPágina &amp;P de &amp;N'
        ));
        // Estos textos también se copian sin escapar al XML: se escapan aquí ("&", "<", ">").
        $xml = fn (string $texto) => htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $opciones->setProperties(new Properties(
            title: $xml($reporte->titulo()),
            subject: $xml($reporte->modulo()),
            application: 'SKYED',
            creator: 'SKYED',
            lastModifiedBy: $xml($generadoPor),
        ));

        $escritor = new Writer($opciones);
        $escritor->openToFile($destino);

        $hoja = $escritor->getCurrentSheet();
        $hoja->setName('Datos');
        $hoja->setSheetView((new SheetView())->setFreezeRow(self::FILA_ENCABEZADO + 1));

        $fuente = $t['fuente'];
        $fechaTexto = Formato::ahora()->format('d/m/Y H:i') . ' (hora de Colombia) · por ' . $generadoPor;

        $textoFiltros = $hFiltros === []
            ? 'Filtros aplicados: ninguno · todos los registros'
            : 'Filtros aplicados: ' . implode('   |   ', array_map(
                fn ($etiqueta, $valor) => $etiqueta . ': ' . $valor,
                array_keys($hFiltros),
                $hFiltros
            ));

        // 1) Banda de marca
        $estBanda = (new Style())->setFontBold()->setFontSize(11)->setFontName('Calibri')
            ->setFontColor($t['marca'])->setBackgroundColor($t['primario'])
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, $t['linea'], Border::WIDTH_THICK)));
        $this->escribirBanner($escritor, $n, 'SKYED  ·  ' . mb_strtoupper($reporte->modulo()), $estBanda, 24);

        // 2) Título
        $titulo = $reporte->titulo();
        $estTitulo = (new Style())->setFontBold()->setFontSize(20)->setFontName($fuente)
            ->setFontColor($t['primario'])
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $this->escribirBanner($escritor, $n, $titulo, $estTitulo, 34);

        // 3) Fecha y autor
        $estMeta = (new Style())->setFontSize(9)->setFontName('Calibri')->setFontItalic()
            ->setFontColor(self::GRIS_TEXTO)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $this->escribirBanner($escritor, $n, 'Generado el ' . $fechaTexto, $estMeta, 18);

        // 4) Filtros aplicados
        $estFiltros = (new Style())->setFontSize(9)->setFontName('Calibri')->setFontColor($t['primario'])
            ->setBackgroundColor($t['suave'])->setShouldWrapText()
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(new BorderPart(Border::LEFT, $t['linea'], Border::WIDTH_THICK)));
        $alto = mb_strlen($textoFiltros) > 110 ? 32 : 20;
        $this->escribirBanner($escritor, $n, $textoFiltros, $estFiltros, $alto);

        // 5) Encabezado de la tabla
        $estEnc = (new Style())->setFontBold()->setFontSize(10)->setFontName('Calibri')
            ->setFontColor(self::BLANCO)->setBackgroundColor($t['primario'])
            ->setShouldWrapText()->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, $t['linea'], Border::WIDTH_MEDIUM)));
        $estEncDer = (clone $estEnc)->setCellAlignment(CellAlignment::RIGHT);
        $celdasEnc = [];
        foreach ($columnas as $c) {
            $celdasEnc[] = new StringCell(mb_strtoupper($c->titulo), $c->numerica() ? $estEncDer : $estEnc);
        }
        $escritor->addRow((new Row($celdasEnc))->setHeight(26));

        // Registros
        $total = 0;
        $primeraTexto = $this->indicePrimeraTexto($columnas);

        foreach ($reporte->filas($filtros) as $fila) {
            $alt = $total % 2 === 1;
            $celdas = [];
            foreach ($columnas as $j => $columna) {
                $valor = $fila[$columna->clave] ?? null;
                $tono = null;
                if (Tonos::esColumnaDeEstado($columna) && $valor !== null && $valor !== '') {
                    $tono = Tonos::de(Formato::humano((string) $valor));
                }
                $estilo = $this->estiloDato($t, $columna, $alt, $tono, $j === $primeraTexto);
                $celdas[] = $this->celda($columna, $valor, $estilo);
            }
            $escritor->addRow(new Row($celdas));
            $total++;
        }

        // Cierre: el total no se conoce al escribir la banda, por eso va debajo de los datos.
        $estCierre = (new Style())->setFontSize(9)->setFontBold()->setFontName('Calibri')
            ->setFontColor($t['acento'])->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(new BorderPart(Border::TOP, $t['linea'], Border::WIDTH_MEDIUM)));
        $textoCierre = $total === 0
            ? 'No hay registros con los filtros seleccionados.'
            : '— Fin del reporte · ' . number_format($total, 0, ',', '.') . ($total === 1 ? ' registro —' : ' registros —');
        $cierre = [];
        for ($k = 0; $k < max(1, $n); $k++) {
            $cierre[] = $k === 0 ? new StringCell($textoCierre, $estCierre) : Cell::fromValue(null, $estCierre);
        }
        $escritor->addRow((new Row($cierre))->setHeight(22));

        // Autofiltro sobre los encabezados (permite filtrar/ordenar en Excel).
        if ($total > 0) {
            $hoja->setAutoFilter(new AutoFilter(0, self::FILA_ENCABEZADO, $ultima, self::FILA_ENCABEZADO + $total));
        }

        $this->hojaInformacion($escritor, $reporte, $filtros, $generadoPor, $total, $t, $hFiltros);

        $escritor->close();
    }

    /** Escribe una fila de cabecera: el texto en la primera celda y el mismo estilo en todas las combinadas. */
    private function escribirBanner(Writer $escritor, int $n, string $texto, Style $estilo, float $alto): void
    {
        $celdas = [new StringCell($texto, $estilo)];
        for ($k = 1; $k < $n; $k++) {
            $celdas[] = Cell::fromValue(null, $estilo);
        }
        $escritor->addRow((new Row($celdas))->setHeight($alto));
    }

    /** Índice de la primera columna de texto que no es el ID (se resalta en negrita). */
    private function indicePrimeraTexto(array $columnas): ?int
    {
        foreach ($columnas as $j => $c) {
            if ($c->clave !== 'id' && $c->tipo === Columna::TEXTO && ! Tonos::esColumnaDeEstado($c)) {
                return $j;
            }
        }

        return null;
    }

    private function estiloDato(array $t, Columna $columna, bool $alt, ?string $tono, bool $fuerte): Style
    {
        $clave = $columna->tipo . '|' . ($alt ? 1 : 0) . '|' . ($tono ?? '-') . '|' . ($columna->clave === 'id' ? 'id' : ($fuerte ? 'f' : 'n'));
        if (isset($this->cacheEstilos[$clave])) {
            return $this->cacheEstilos[$clave];
        }

        $estilo = (new Style())->setFontSize(10)->setFontName('Calibri')
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, $t['trazo'], Border::WIDTH_THIN)));

        if ($alt) {
            $estilo->setBackgroundColor($t['zebra']);
        }

        match ($columna->tipo) {
            Columna::ENTERO => $estilo->setFormat('#,##0')->setCellAlignment(CellAlignment::RIGHT),
            Columna::MONEDA => $estilo->setFormat('"$"#,##0')->setCellAlignment(CellAlignment::RIGHT),
            Columna::FECHA => $estilo->setFormat('dd/mm/yyyy')->setCellAlignment(CellAlignment::LEFT),
            Columna::FECHA_HORA => $estilo->setFormat('dd/mm/yyyy hh:mm')->setCellAlignment(CellAlignment::LEFT),
            default => $estilo->setShouldWrapText()->setCellAlignment(CellAlignment::LEFT),
        };

        if ($tono !== null) {
            $estilo->setFontBold()
                ->setFontColor(Tonos::COLORES[$tono]['texto'])
                ->setBackgroundColor(Tonos::COLORES[$tono]['fondo'])
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        } elseif ($columna->clave === 'id') {
            $estilo->setFontColor(self::GRIS_ID);
        } elseif ($fuerte) {
            $estilo->setFontBold()->setFontColor($t['primario']);
        } else {
            $estilo->setFontColor('1F2430');
        }

        return $this->cacheEstilos[$clave] = $estilo;
    }

    private function hojaInformacion(Writer $escritor, Reporte $reporte, array $filtros, string $generadoPor, int $total, array $t, array $hFiltros): void
    {
        $info = $escritor->addNewSheetAndMakeItCurrent();
        $info->setName('Información');
        $info->setColumnWidth(30, 1);
        $info->setColumnWidth(62, 2);

        $estBanda = (new Style())->setFontBold()->setFontSize(11)->setFontName('Calibri')
            ->setFontColor($t['marca'])->setBackgroundColor($t['primario'])
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, $t['linea'], Border::WIDTH_THICK)));
        $estTitulo = (new Style())->setFontBold()->setFontSize(18)->setFontName($t['fuente'])
            ->setFontColor($t['primario'])->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $estSeccion = (new Style())->setFontBold()->setFontSize(10)->setFontName('Calibri')
            ->setFontColor($t['acento'])->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, $t['linea'], Border::WIDTH_MEDIUM)));
        $estEtiqueta = (new Style())->setFontBold()->setFontSize(10)->setFontName('Calibri')
            ->setFontColor($t['primario'])->setBackgroundColor($t['suave'])
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, self::BLANCO, Border::WIDTH_THIN)));
        $estValor = (new Style())->setFontSize(10)->setFontName('Calibri')->setFontColor('1F2430')
            ->setShouldWrapText()->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, $t['trazo'], Border::WIDTH_THIN)));

        $par = fn (string $a, string|int|null $b, Style $sa, ?Style $sb, float $alto = 20) => $escritor->addRow(
            (new Row([
                new StringCell($a, $sa),
                $b === null ? Cell::fromValue(null, $sb) : (is_int($b) ? Cell::fromValue($b, $sb) : new StringCell((string) $b, $sb)),
            ]))->setHeight($alto)
        );

        $par('SKYED  ·  ' . mb_strtoupper($reporte->modulo()), null, $estBanda, $estBanda, 24);
        $par($reporte->titulo(), null, $estTitulo, null, 34);
        $par('', null, $estValor, null, 8);

        $par('DATOS DEL REPORTE', null, $estSeccion, $estSeccion);
        $par('Módulo', $reporte->modulo(), $estEtiqueta, $estValor);
        $par('Generado el', Formato::ahora()->format('d/m/Y H:i') . ' (hora de Colombia)', $estEtiqueta, $estValor);
        $par('Generado por', $generadoPor, $estEtiqueta, $estValor);
        $par('Total de registros', $total, $estEtiqueta, (clone $estValor)->setFormat('#,##0'));
        foreach ($reporte->resumen($filtros) as $etiqueta => $valor) {
            $par((string) $etiqueta, is_int($valor) ? $valor : (string) $valor, $estEtiqueta, $estValor);
        }

        $par('', null, $estValor, null, 10);
        $par('FILTROS APLICADOS', null, $estSeccion, $estSeccion);
        if ($hFiltros === []) {
            $par('Ninguno', 'Todos los registros', $estEtiqueta, $estValor);
        }
        foreach ($hFiltros as $etiqueta => $valor) {
            $par((string) $etiqueta, (string) $valor, $estEtiqueta, $estValor);
        }
    }

    private function celda(Columna $columna, mixed $valor, Style $estilo): Cell
    {
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