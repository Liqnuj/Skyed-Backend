<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Sube imágenes para el módulo Social (ambientes y eventos sociales)
 * y las deja listas para usarse directamente como imagen_principal_a / imagen_er.
 *
 * El flujo es: el admin elige un archivo -> se sube aquí -> devolvemos la
 * URL pública -> el formulario guarda esa URL en el campo correspondiente,
 * igual que antes hacía con un enlace pegado a mano.
 */
class ImagenSocialController extends Controller
{
    private const CARPETAS_PERMITIDAS = ['ambientes', 'eventos'];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'carpeta' => 'required|string|in:' . implode(',', self::CARPETAS_PERMITIDAS),
        ]);

        $archivo = $request->file('imagen');
        $nombreArchivo = uniqid() . '.' . $archivo->getClientOriginalExtension();
        $rutaRelativa = 'social/' . $validated['carpeta'] . '/' . $nombreArchivo;

        Storage::disk('public')->put($rutaRelativa, $archivo->get());

        return response()->json([
            'message' => 'Imagen subida correctamente',
            'url' => asset('storage/' . $rutaRelativa),
        ], 201);
    }

    /**
     * Borra del disco una imagen previamente subida por este controlador,
     * si la URL guardada pertenece a nuestro storage. Las URLs externas
     * (enlaces antiguos pegados a mano) se dejan intactas.
     */
    public static function eliminarSiEsPropia(?string $url): void
    {
        if (!$url) {
            return;
        }

        $base = url('/storage/');

        if (!str_starts_with($url, $base)) {
            return;
        }

        $rutaRelativa = ltrim(substr($url, strlen($base)), '/');

        if ($rutaRelativa !== '') {
            Storage::disk('public')->delete($rutaRelativa);
        }
    }
}
