<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use Illuminate\Http\Request;
use App\Http\Requests\StoreAmbienteRequest;
use App\Http\Requests\UpdateAmbienteRequest;

class AmbienteController extends Controller
{
    public function index(Request $request)
    {
        $ambientes = Ambiente::with('servicios')
            ->paginate($request->input('per_page', 15));

        return response()->json($ambientes);
    }

    public function show($id)
    {
        $ambiente = Ambiente::with('servicios')->find($id);

        if (!$ambiente) {
            return response()->json([
                'message' => 'Ambiente no encontrado'
            ], 404);
        }

        return response()->json([
            'ambiente' => $ambiente
        ]);
    }

    public function store(StoreAmbienteRequest $request)
    {
        $ambiente = Ambiente::create($request->validated());

        return response()->json([
            'message' => 'Ambiente creado correctamente',
            'ambiente' => $ambiente
        ], 201);
    }
    

    public function update(UpdateAmbienteRequest $request, $id)
    {
        $ambiente = Ambiente::find($id);

        if (!$ambiente) {
            return response()->json([
                'message' => 'Ambiente no encontrado'
            ], 404);
        }

        $ambiente->update($request->validated());

        return response()->json([
            'message' => 'Ambiente actualizado correctamente',
            'ambiente' => $ambiente->fresh()->load('servicios')
        ]);
    }

    public function destroy($id)
    {
        $ambiente = Ambiente::find($id);

        if (!$ambiente) {
            return response()->json([
                'message' => 'Ambiente no encontrado'
            ], 404);
        }

        $ambiente->delete();

        return response()->json([
            'message' => 'Ambiente eliminado correctamente'
        ]);
    }

    public function asignarServicio(Request $request, $id)
    {
        $ambiente = Ambiente::find($id);

        if (!$ambiente) {
            return response()->json([
                'message' => 'Ambiente no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'id_s' => 'required|exists:servicio,id_s',
        ]);

        $ambiente->servicios()->syncWithoutDetaching([
            $validated['id_s']
        ]);

        return response()->json([
            'message' => 'Servicio asignado correctamente',
            'ambiente' => $ambiente->load('servicios')
        ]);
    }
}