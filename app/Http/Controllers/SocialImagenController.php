<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SocialImagenController extends Controller
{
    /**
     * Sube una imagen suelta (todavía no asociada a un registro) y
     * devuelve su URL pública. El admin la usa para llenar
     * imagen_principal_a o imagen_er antes de guardar el formulario.
     *
     * POST /social/imagenes (multipart/form-data)
     *   - imagen: archivo (jpg/png/webp, máx 4MB)
     *   - carpeta: "ambientes" | "eventos"
     */
    public function store(Request $request)
    {
        if ($request->hasFile('imagen') && !$request->file('imagen')->isValid()) {
            $codigo = $request->file('imagen')->getError();
            $mensajes = [
                UPLOAD_ERR_INI_SIZE => sprintf(
                    'El archivo supera upload_max_filesize (actualmente %s en el php.ini que está usando este proceso de PHP).',
                    ini_get('upload_max_filesize')
                ),
                UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite máximo definido en el formulario.',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió solo parcialmente, intenta de nuevo.',
                UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene una carpeta temporal configurada para subir archivos.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco (revisa permisos de la carpeta temporal).',
                UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la subida del archivo.',
            ];

            return response()->json([
                'message' => $mensajes[$codigo] ?? 'La subida falló por un error desconocido de PHP (código ' . $codigo . ').',
                'php_upload_error_code' => $codigo,
                'post_max_size_actual' => ini_get('post_max_size'),
                'upload_max_filesize_actual' => ini_get('upload_max_filesize'),
            ], 422);
        }

        // Si el body completo (post_max_size) se pasó del límite, PHP ni
        // siquiera llena $_FILES ni $_POST — se nota porque llega vacío.
        if (!$request->hasFile('imagen') && empty($request->all()) && $request->server('CONTENT_LENGTH') > 0) {
            return response()->json([
                'message' => sprintf(
                    'La petición completa (%.1f MB) supera post_max_size (actualmente %s). Sube post_max_size en el php.ini que usa este proceso.',
                    ((int) $request->server('CONTENT_LENGTH')) / 1024 / 1024,
                    ini_get('post_max_size')
                ),
                'post_max_size_actual' => ini_get('post_max_size'),
            ], 422);
        }

        $request->validate([
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
            'carpeta' => 'required|in:ambientes,eventos',
        ], [
            'imagen.required' => 'No llegó ningún archivo de imagen al servidor.',
            'imagen.image' => 'El archivo no es una imagen válida.',
            'imagen.mimes' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'imagen.max' => 'La imagen no debe superar 8 MB.',
            'carpeta.required' => 'Falta indicar la carpeta (ambientes o eventos).',
            'carpeta.in' => 'La carpeta debe ser "ambientes" o "eventos".',
        ]);

        $archivo = $request->file('imagen');
        $carpeta = $request->input('carpeta');
        $nombreArchivo = uniqid($carpeta . '_') . '.' . $archivo->getClientOriginalExtension();
        $contenido = $archivo->get(); // en memoria, sin depender de getRealPath()

        Storage::disk('public')->put($carpeta . '/' . $nombreArchivo, $contenido);
        $path = $carpeta . '/' . $nombreArchivo;

        return response()->json([
            'url' => asset('storage/' . $path),
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
