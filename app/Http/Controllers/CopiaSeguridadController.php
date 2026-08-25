<?php

namespace App\Http\Controllers;

use App\Models\CopiaSeguridad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CopiaSeguridadController extends Controller
{
    /**
     * Listar copias de seguridad.
     */
    public function index(Request $request)
    {
        $copias = CopiaSeguridad::orderByDesc('fecha_cs')
            ->paginate($request->input('per_page', 15));

        return response()->json($copias);
    }

    /**
     * Mostrar una copia.
     */
    public function show($id)
    {
        $copia = CopiaSeguridad::find($id);

        if (!$copia) {
            return response()->json([
                'message' => 'Copia de seguridad no encontrada'
            ], 404);
        }

        return response()->json([
            'copia' => $copia
        ]);
    }

    /**
     * Crear una copia de una tabla permitida.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_tabla_cs' => 'required|in:usuario,roles,invitados,eventoDeportivo,inscripcion,pago,qr_entrada',
        ]);

        $tablasPermitidas = [
            'usuario',
            'roles',
            'invitados',
            'eventoDeportivo',
            'inscripcion',
            'pago',
            'qr_entrada',
        ];

        $tabla = $validated['nombre_tabla_cs'];

        if (!in_array($tabla, $tablasPermitidas, true)) {
            return response()->json([
                'message' => 'Tabla no permitida para copia de seguridad'
            ], 422);
        }

        $datos = DB::table($tabla)->get()->map(
            fn ($fila) => (array) $fila
        )->toArray();

        $copia = CopiaSeguridad::create([
            'nombre_tabla_cs' => $tabla,
            'fecha_cs' => now(),
            'datos_json_cs' => $datos,
        ]);

        return response()->json([
            'message' => 'Copia de seguridad creada correctamente',
            'copia' => $copia,
        ], 201);
    }
}