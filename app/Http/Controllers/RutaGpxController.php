<?php

namespace App\Http\Controllers;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RutaGpxController extends Controller
{
    public function show(Request $request)
{
    $path = (string) $request->query('path', '');

    // Solo archivos .gpx dentro de rutas-gpx/, sin "..": evita leer otros archivos.
    if (!preg_match('#^rutas-gpx/[A-Za-z0-9._-]+\.gpx$#', $path)) {
        return response()->json(['message' => 'Ruta de archivo no válida.'], 422);
    }

    $disco = Storage::disk('public');
    if (!$disco->exists($path)) {
        return response()->json(['message' => 'El archivo GPX no existe.'], 404);
    }

    return response($disco->get($path), 200, ['Content-Type' => 'application/gpx+xml']);
}
    public function store(Request $request): JsonResponse
    {
        
        $request->validate([
            'gpx' => ['required', 'file', 'max:5120'], // KB -> 5 MB
        ]);

        $archivo = $request->file('gpx');

        // Los .gpx suelen detectarse como text/xml, por eso se valida extensión y contenido.
        if (strtolower($archivo->getClientOriginalExtension()) !== 'gpx') {
            return response()->json(['message' => 'El archivo debe ser un .gpx.'], 422);
        }

        $inicio = file_get_contents($archivo->getRealPath(), false, null, 0, 2048);
        if ($inicio === false || stripos($inicio, '<gpx') === false) {
            return response()->json(['message' => 'El archivo no es un GPX válido.'], 422);
        }

        $path = $archivo->storeAs('rutas-gpx', Str::uuid() . '.gpx', 'public');

        return response()->json([
    'path' => $path,
    'url'  => asset('storage/' . $path),
], 201);
    }
}