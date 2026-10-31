<?php

namespace App\Http\Controllers;

use App\Models\CopiaSeguridad;
use App\Support\CatalogoCopias;
use App\Support\GestorCopias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Copias de seguridad del panel de administración. La ruta exige ser admin; aquí
 * se decide de qué: cada admin maneja su módulo y el super admin, todo. Una copia
 * que incluya algo que la persona no puede manejar ni siquiera se le muestra.
 */
class CopiaSeguridadController extends Controller
{
    /** Lo que esta persona puede respaldar y las copias que puede ver. */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $copias = CopiaSeguridad::with('autor:id_u,nombre_u,apellido_u')
            ->orderByDesc('fecha_cs')
            ->orderByDesc('id_cs')
            ->limit(200)
            ->get()
            ->filter(fn (CopiaSeguridad $c) => CatalogoCopias::puedeManejar($usuario, $c->conjuntos_cs ?? []))
            ->values()
            ->map(fn (CopiaSeguridad $c) => $this->presentar($c));

        return response()->json([
            'grupos' => CatalogoCopias::paraMostrar($usuario),
            'copias' => $copias,
            'es_super' => $usuario->esSuperAdmin(),
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $copia = $this->copiaAccesible($request, $id);
        if ($copia instanceof JsonResponse) {
            return $copia;
        }

        return response()->json(['copia' => $this->presentar($copia->load('autor:id_u,nombre_u,apellido_u'))]);
    }

    /** Crea una copia de los conjuntos elegidos. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conjuntos' => 'required|array|min:1',
            'conjuntos.*' => ['required', 'string', 'distinct', Rule::in(array_keys(CatalogoCopias::conjuntos()))],
        ], [
            'conjuntos.required' => 'Elige al menos un conjunto de datos.',
            'conjuntos.min' => 'Elige al menos un conjunto de datos.',
        ]);

        if (!CatalogoCopias::puedeManejar($request->user(), $validated['conjuntos'])) {
            return $this->sinPermiso();
        }

        try {
            $copia = GestorCopias::crear($validated['conjuntos'], $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Copia de seguridad creada correctamente',
            'copia' => $this->presentar($copia->load('autor:id_u,nombre_u,apellido_u')),
        ], 201);
    }

    /** Descarga el archivo. Solo por aquí: la carpeta no es accesible por URL. */
    public function descargar(Request $request, $id): Response
    {
        $copia = $this->copiaAccesible($request, $id);
        if ($copia instanceof JsonResponse) {
            return $copia;
        }

        if (!GestorCopias::archivoDisponible($copia)) {
            return response()->json(['message' => 'El archivo de esta copia ya no está en el servidor.'], 404);
        }

        Log::info('Copia de seguridad descargada', ['copia' => $copia->id_cs, 'usuario' => $request->user()->id_u]);

        return Storage::disk('local')->download($copia->archivo_cs, basename($copia->archivo_cs));
    }

    /** Recupera los datos de una copia. Pide escribir RESTAURAR para evitar accidentes. */
    public function restaurar(Request $request, $id): JsonResponse
    {
        $copia = $this->copiaAccesible($request, $id);
        if ($copia instanceof JsonResponse) {
            return $copia;
        }

        $request->validate(['confirmacion' => 'required|string|in:RESTAURAR'], [
            'confirmacion.required' => 'Escribe RESTAURAR para confirmar.',
            'confirmacion.in' => 'Escribe RESTAURAR para confirmar.',
        ]);

        try {
            $resultado = GestorCopias::restaurar($copia, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Se restauraron {$resultado['registros']} registros.",
            'registros' => $resultado['registros'],
            'tablas' => $resultado['tablas'],
            'copia_previa' => $this->presentar($resultado['copia_previa']->load('autor:id_u,nombre_u,apellido_u')),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $copia = $this->copiaAccesible($request, $id);
        if ($copia instanceof JsonResponse) {
            return $copia;
        }

        GestorCopias::eliminar($copia);
        Log::info('Copia de seguridad eliminada', ['copia' => $id, 'usuario' => $request->user()->id_u]);

        return response()->json(['message' => 'Copia de seguridad eliminada']);
    }

    private function copiaAccesible(Request $request, $id): CopiaSeguridad|JsonResponse
    {
        $copia = CopiaSeguridad::find($id);

        if (!$copia) {
            return response()->json(['message' => 'Copia de seguridad no encontrada'], 404);
        }

        // La copia de otro módulo responde igual que si no existiera.
        return CatalogoCopias::puedeManejar($request->user(), $copia->conjuntos_cs ?? [])
            ? $copia
            : response()->json(['message' => 'Copia de seguridad no encontrada'], 404);
    }

    private function sinPermiso(): JsonResponse
    {
        return response()->json(['message' => 'No tienes permisos para respaldar esos datos.'], 403);
    }

    private function presentar(CopiaSeguridad $c): array
    {
        return [
            'id' => $c->id_cs,
            'fecha' => $c->fecha_cs?->toIso8601String(),
            'alcance' => $c->alcance_cs,
            'conjuntos' => CatalogoCopias::nombres($c->conjuntos_cs ?? []),
            'tamano' => $c->tamano_cs,
            'registros' => $c->registros_cs,
            'origen' => $c->origen_cs,
            'autor' => $c->autor ? trim("{$c->autor->nombre_u} {$c->autor->apellido_u}") : 'Sistema',
            'archivo' => $c->archivo_cs ? basename($c->archivo_cs) : null,
            'disponible' => GestorCopias::archivoDisponible($c),
        ];
    }
}
