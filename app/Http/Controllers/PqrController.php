<?php

namespace App\Http\Controllers;

use App\Models\Pqr;
use Illuminate\Http\Request;
use App\Services\NotificacionService;

use App\Models\PqrSeguimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class PqrController extends Controller
{




    /**
     * Listar PQR. Un admin social ve todas; un usuario normal
     * solo ve las suyas.
     */

public function index(Request $request)
{
    $usuario = $request->user();

    $esAdminSocial = $usuario->hasRole('adminSocial');

    $query = Pqr::with([
        'usuario',
        'seguimientos' => function ($query) use ($esAdminSocial) {
            $query->select([
                'id_seguimiento',
                'id_pqr',
                'id_admin',
                'estado_anterior',
                'estado_nuevo',
                'tipo_evento',
                'comentario_publico',
                'nota_interna',
                'created_at',
            ]);

            if (!$esAdminSocial) {
                $query->select([
                    'id_seguimiento',
                    'id_pqr',
                    'estado_anterior',
                    'estado_nuevo',
                    'tipo_evento',
                    'comentario_publico',
                    'created_at',
                ]);
            }
        }
    ])->orderByDesc('created_at');

    // El ciudadano solo consulta sus propias solicitudes.
    if (!$esAdminSocial) {
        $query->where('id_u', $usuario->id_u);
    }

    $perPage = min(
        max((int) $request->input('per_page', 15), 1),
        100
    );

    return response()->json(
        $query->paginate($perPage)
    );
}


    /**
     * Mostrar una PQR. Un usuario normal solo puede ver la suya.
     */

public function show(Request $request, $id)
{
    $usuario = $request->user();

    $esAdminSocial = $usuario->hasRole('adminSocial');

    $pqr = Pqr::with([
        'usuario',
        'seguimientos' => function ($query) use ($esAdminSocial) {
            $query->orderBy('created_at', 'asc');

            if ($esAdminSocial) {
                $query->with('administrador');
            } else {
                // No cargar ni devolver notas internas.
                $query->select([
                    'id_seguimiento',
                    'id_pqr',
                    'estado_anterior',
                    'estado_nuevo',
                    'tipo_evento',
                    'comentario_publico',
                    'created_at',
                ]);
            }
        }
    ])->find($id);

    if (!$pqr) {
        return response()->json([
            'message' => 'PQR no encontrada'
        ], 404);
    }

    $esDueno = (int) $pqr->id_u === (int) $usuario->id_u;

    if (!$esDueno && !$esAdminSocial) {
        return response()->json([
            'message' => 'No tienes permisos para ver esta PQR'
        ], 403);
    }

    // Eliminar la posibilidad de exponer notas internas
    // al ciudadano mediante esta respuesta.
    if (!$esAdminSocial) {
        $pqr->seguimientos->each(function ($seguimiento) {
            $seguimiento->makeHidden([
                'nota_interna',
                'id_admin',
                'administrador',
            ]);
        });
    }

    return response()->json([
        'pqr' => $pqr
    ]);
}


    /**
     * Crear una PQR.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_pqr' => 'required|in:peticion,queja,reclamo,sugerencia,felicitacion',
            'asunto_pqr' => 'required|string|max:150',
            // El formulario limita a 1000 caracteres; sin max se podían
            // guardar textos de varios MB llamando a la API directamente.
            'mensaje_pqr' => 'required|string|max:2000',
        ]);

        $pqr = Pqr::create([
            'tipo_pqr' => $validated['tipo_pqr'],
            'asunto_pqr' => $validated['asunto_pqr'],
            'mensaje_pqr' => $validated['mensaje_pqr'],
            'estado_pqr' => 'abierto',
            'respuesta_pqr' => null,
            'respondido_en_pqr' => null,
            'creado_en_pqr' => now(),
            'id_u' => $request->user()->id_u,
        ]);

        return response()->json([
            'message' => 'PQR creada correctamente',
            'pqr' => $pqr->load('usuario')
        ], 201);
    }

    /**
     * Actualizar estado o responder PQR.
     */
    public function update(Request $request, $id)
    {
        $pqr = Pqr::find($id);

        if (!$pqr) {
            return response()->json([
                'message' => 'PQR no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'estado_pqr' => 'sometimes|in:abierto,en_proceso,resuelto,cerrado',
            'respuesta_pqr' => 'sometimes|nullable|string',
        ]);

        $datos = $validated;

        if (array_key_exists('respuesta_pqr', $validated)) {
            $datos['respondido_en_pqr'] = now();
        }

        $pqr->update($datos);

        // Notificar al usuario si se ha respondido a la PQR.
                if (array_key_exists('respuesta_pqr', $validated)) {
            NotificacionService::crear(
                $pqr->id_u,
                'Respuesta a tu PQR',
                'Tu PQR "' . $pqr->asunto_pqr . '" fue respondida.',
                'pqr'
            );
        }

        return response()->json([
            'message' => 'PQR actualizada correctamente',
            'pqr' => $pqr->fresh()->load('usuario')
        ]);



        
    }
    
public function seguimiento(Request $request, $id)
{
    // 1. Solo el administrador social puede gestionar PQRS.
    if (!$request->user()->hasRole('adminSocial')) {
        return response()->json([
            'message' => 'No tienes permisos para gestionar PQRS.'
        ], 403);
    }

    // 2. Buscar la solicitud.
    $pqr = Pqr::find($id);

    if (!$pqr) {
        return response()->json([
            'message' => 'No se encontró la PQRS.'
        ], 404);
    }

    if ($pqr->estado_pqr === 'cerrado') {
        return response()->json([
            'message' => 'La PQRS está cerrada.'
        ], 422);
    }

    // 3. Validar los datos recibidos del frontend.
    $datos = $request->validate([
        'estado_pqr' => [
            'sometimes',
            'required',
            'in:abierto,en_revision,en_proceso,resuelto,cerrado'
        ],
        'tipo_evento' => [
            'sometimes',
            'in:actualizacion,respuesta,solicitud_informacion,nota'
        ],
        'comentario_publico' => 'nullable|string|max:2000',
        'nota_interna' => 'nullable|string|max:2000',
        'respuesta_pqr' => 'nullable|string|max:5000',
    ]);

    $estadoAnterior = $pqr->estado_pqr;
    $estadoNuevo = $datos['estado_pqr'] ?? $estadoAnterior;

    $comentario = trim($datos['comentario_publico'] ?? '');
    $nota = trim($datos['nota_interna'] ?? '');
    $respuesta = trim($datos['respuesta_pqr'] ?? '');

    $cambioEstado = $estadoAnterior !== $estadoNuevo;

    // 4. Definir las transiciones permitidas.
    $transiciones = [
        'abierto' => ['en_revision'],
        'en_revision' => ['en_proceso', 'abierto'],
        'en_proceso' => ['resuelto', 'en_revision'],
        'resuelto' => ['cerrado', 'en_proceso'],
        'cerrado' => [],
    ];

    if (
        $cambioEstado &&
        !in_array(
            $estadoNuevo,
            $transiciones[$estadoAnterior] ?? [],
            true
        )
    ) {
        throw ValidationException::withMessages([
            'estado_pqr' =>
                'El cambio de etapa no está permitido.'
        ]);
    }

    // 5. Exigir que exista alguna actuación.
    if (
        !$cambioEstado &&
        $comentario === '' &&
        $nota === '' &&
        $respuesta === ''
    ) {
        throw ValidationException::withMessages([
            'comentario_publico' =>
                'Debes registrar un avance, una nota o una respuesta.'
        ]);
    }

    $tipoEvento = $datos['tipo_evento']
        ?? ($respuesta !== ''
            ? 'respuesta'
            : ($cambioEstado ? 'actualizacion' : 'nota'));

    // 6. Actualizar la PQRS y guardar el historial juntos.
    DB::transaction(function () use (
        $pqr,
        $request,
        $estadoAnterior,
        $estadoNuevo,
        $cambioEstado,
        $comentario,
        $nota,
        $respuesta,
        $tipoEvento
    ) {
        if ($cambioEstado) {
            $pqr->estado_pqr = $estadoNuevo;
        }

        if ($respuesta !== '') {
            $pqr->respuesta_pqr = $respuesta;
            $pqr->respondido_en_pqr = now();
        }

        $pqr->save();

        PqrSeguimiento::create([
            'id_pqr' => $pqr->id_pqr,
            'id_admin' => $request->user()->id_u,
            'estado_anterior' => $cambioEstado
                ? $estadoAnterior
                : null,
            'estado_nuevo' => $estadoNuevo,
            'tipo_evento' => $tipoEvento,
            'comentario_publico' => $comentario !== ''
                ? $comentario
                : ($respuesta !== ''
                    ? 'Se ha publicado una respuesta a tu solicitud.'
                    : ($cambioEstado
                        ? 'Tu solicitud ha avanzado a una nueva etapa.'
                        : null)),
            'nota_interna' => $nota !== '' ? $nota : null,
        ]);

        // 7. Notificar los cambios que debe conocer el ciudadano.
        if (
            $cambioEstado ||
            $comentario !== '' ||
            $respuesta !== ''
        ) {
            $titulo = $respuesta !== ''
                ? 'Respuesta a tu PQRS'
                : 'Actualización de tu PQRS';

            $mensaje = $respuesta !== ''
                ? 'Se ha registrado una respuesta para tu solicitud.'
                : 'Tu solicitud ha recibido una actualización.';

            NotificacionService::crear(
                (int) $pqr->id_u,
                $titulo,
                $mensaje,
                'pqr'
            );
        }
    });

    // 8. Devolver la información actualizada.
    $actualizada = $pqr->fresh()
        ->load('seguimientos');

    return response()->json([
        'message' => 'Seguimiento registrado correctamente.',
        'pqr' => $actualizada,
    ]);
}

}