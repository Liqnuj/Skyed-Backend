<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;
use App\Http\Requests\StoreServicioRequest;
use App\Http\Requests\UpdateServicioRequest;

class ServicioController extends Controller
{
    public function index(Request $request)
    {
        $servicios = Servicio::with('ambientes')
            ->paginate($request->input('per_page', 15));

        return response()->json($servicios);
    }

    public function show($id)
    {
        $servicio = Servicio::with('ambientes')->find($id);

        if (!$servicio) {
            return response()->json([
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        return response()->json([
            'servicio' => $servicio
        ]);
    }

    public function store(StoreServicioRequest $request)
    {
        $servicio = Servicio::create($request->validated());

        return response()->json([
            'message' => 'Servicio creado correctamente',
            'servicio' => $servicio
        ], 201);
    }


    public function update(UpdateServicioRequest $request, $id)
    {
        $servicio = Servicio::find($id);

        if (!$servicio) {
            return response()->json([
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        $servicio->update($request->validated());

        return response()->json([
            'message' => 'Servicio actualizado correctamente',
            'servicio' => $servicio->fresh()->load('ambientes')
        ]);
    }

    public function destroy($id)
    {
        $servicio = Servicio::find($id);

        if (!$servicio) {
            return response()->json([
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        $servicio->delete();

        return response()->json([
            'message' => 'Servicio eliminado correctamente'
        ]);
    }
}