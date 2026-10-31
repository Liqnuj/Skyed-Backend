<?php

namespace App\Http\Controllers;

use App\Services\Reportes\CatalogoReportes;
use App\Services\Reportes\ExportadorExcel;
use App\Services\Reportes\ExportadorPdf;
use App\Services\Reportes\Formato;
use App\Services\Reportes\ReporteDemasiadoGrande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Exporta a PDF o Excel los datos de las tablas del panel.
 *
 * La autorización NO se hace aquí: cada ruta vive dentro del grupo de
 * middleware `role.context:...` que ya protege ese módulo en routes/api.php.
 */
class ReporteController extends Controller
{
    public function exportar(Request $request, string $modulo, string $reporte, string $formato)
    {
        $definicion = CatalogoReportes::buscar($modulo, $reporte);

        if ($definicion === null) {
            return response()->json(['message' => 'Reporte no encontrado'], 404);
        }

        // Valida los filtros (422 con mensaje claro) y descarta los vacíos.
        $filtros = array_filter(
            $request->validate($definicion->reglas()),
            fn ($valor) => $valor !== null && $valor !== ''
        );

        $usuario = $request->user();
        $generadoPor = trim($usuario->nombre_u . ' ' . $usuario->apellido_u);

        Log::info('Reporte exportado', [
            'id_u' => $usuario->id_u,
            'reporte' => "$modulo/$reporte",
            'formato' => $formato,
            'filtros' => $filtros,
        ]);

        $nombre = Formato::nombreArchivo($definicion->archivoBase());
        $cabeceras = ['Cache-Control' => 'no-store, private'];

        if ($formato === 'pdf') {
            try {
                $contenido = app(ExportadorPdf::class)->generar($definicion, $filtros, $generadoPor);
            } catch (ReporteDemasiadoGrande $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response($contenido, 200, $cabeceras + [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nombre . '.pdf"',
            ]);
        }

        return response()->streamDownload(
            fn () => app(ExportadorExcel::class)->generar($definicion, $filtros, $generadoPor, 'php://output'),
            $nombre . '.xlsx',
            $cabeceras + ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}