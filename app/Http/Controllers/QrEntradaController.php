<?php

namespace App\Http\Controllers;

use App\Models\EntregaKit;
use App\Models\QrEntrada;
use App\Services\NotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrEntradaController extends Controller
{
    /**
     * Mostrar un QR específico. Solo el dueño de la inscripción
     * asociada o un adminDeportivo pueden verlo.
     *
     * FIX: antes cualquier usuario autenticado podía consultar el QR
     * (código, estado, fecha de uso, datos del dueño) de cualquier
     * otra inscripción con solo cambiar el id en la URL (IDOR).
     */
    public function show(Request $request, $id)
    {
        $qr = QrEntrada::with([
            'inscripcion.usuario',
            'inscripcion.evento',
        ])->find($id);

        if (!$qr) {
            return response()->json([
                'message' => 'QR no encontrado'
            ], 404);
        }

        $esDueno = $qr->inscripcion && $qr->inscripcion->id_u === $request->user()->id_u;
        $esAdmin = $request->user()->hasRole('adminDeportivo');

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver este QR'
            ], 403);
        }

        return response()->json([
            'qr' => $qr,
        ]);
    }

    /**
     * Validar un QR mediante su código.
     *
     * Cada QR permite tantas entradas como usos_permitidos_qr:
     * 1 si la inscripción es solo del titular, 2 si lleva invitado.
     * El QR sigue "activo" mientras le queden usos y pasa a "usado"
     * cuando se agotan.
     */
    public function validar(Request $request)
    {
        $validated = $request->validate([
            'codigo_qr' => 'required|string|max:255',
        ]);

        $resultado = DB::transaction(function () use ($validated) {

            // Bloquear el QR durante toda la validación para que dos
            // escaneos simultáneos no gasten el mismo uso.
            $qr = QrEntrada::with([
                'inscripcion.usuario',
                'inscripcion.evento',
                'inscripcion.invitado',
            ])
                ->where('codigo_qr', $validated['codigo_qr'])
                ->lockForUpdate()
                ->first();

            if (!$qr) {
                // Escanearon el QR del kit en la entrada del evento.
                if (str_starts_with(strtoupper($validated['codigo_qr']), 'KIT-')) {
                    return response()->json([
                        'message' => 'Este es el QR del kit, no el de entrada. Pide el QR de "Entrada al evento".',
                        'tipo' => 'kit',
                    ], 422);
                }

                return response()->json([
                    'message' => 'Código QR no encontrado'
                ], 404);
            }

            // El QR debe estar disponible.
            if ($qr->estado_qr !== 'activo') {
                return response()->json([
                    'message' => $qr->estado_qr === 'usado'
                        ? 'Este QR ya se usó completo'
                        : 'El QR no está disponible',
                    'estado_qr' => $qr->estado_qr,
                    'usos_permitidos' => $qr->usos_permitidos_qr,
                    'usos_realizados' => $qr->usos_realizados_qr,
                ], 422);
            }

            // Defensa extra: aunque el estado diga "activo", no puede
            // haber más entradas que usos permitidos.
            if ($qr->usos_realizados_qr >= $qr->usos_permitidos_qr) {
                return response()->json([
                    'message' => 'Este QR ya se usó completo',
                    'estado_qr' => $qr->estado_qr,
                    'usos_permitidos' => $qr->usos_permitidos_qr,
                    'usos_realizados' => $qr->usos_realizados_qr,
                ], 422);
            }

            $inscripcion = $qr->inscripcion;

            if (!$inscripcion) {
                return response()->json([
                    'message' => 'La inscripción asociada no existe'
                ], 404);
            }

            // La inscripción debe estar confirmada.
            if ($inscripcion->estado_i !== 'confirmada') {
                return response()->json([
                    'message' => 'La inscripción no está confirmada',
                    'estado_i' => $inscripcion->estado_i,
                ], 422);
            }

            $evento = $inscripcion->evento;

            if (!$evento) {
                return response()->json([
                    'message' => 'El evento asociado no existe'
                ], 404);
            }

            // El evento debe estar activo.
            if ($evento->estado_e !== 'activo') {
                return response()->json([
                    'message' => 'El evento no está activo',
                    'estado_e' => $evento->estado_e,
                ], 422);
            }

            // Registrar la entrada dentro de la misma transacción.
            $usosRealizados = $qr->usos_realizados_qr + 1;
            $agotado = $usosRealizados >= $qr->usos_permitidos_qr;

            $qr->update([
                'usos_realizados_qr' => $usosRealizados,
                'fecha_uso_qr' => now(),
                'estado_qr' => $agotado ? 'usado' : 'activo',
            ]);

            // El historial pasa de "inscrito" a "asistio" con la primera
            // entrada. Solo toca filas "inscrito", así que el segundo
            // escaneo (invitado) no lo vuelve a modificar.
            DB::table('historial_participacion')
                ->where('id_i', $inscripcion->id_i)
                ->where('estado_hp', 'inscrito')
                ->update([
                    'estado_hp' => 'asistio',
                    'fecha_hp' => now(),
                    'updated_at' => now(),
                ]);

            // Datos para que el admin verifique a las personas contra
            // su documento en la puerta.
            $usuario = $inscripcion->usuario;
            $invitado = $inscripcion->invitado;

            $personas = [];

            if ($usuario) {
                $personas[] = [
                    'rol' => 'titular',
                    'nombre' => trim($usuario->nombre_u . ' ' . $usuario->apellido_u),
                    'documento' => $usuario->documento_u,
                ];
            }

            if ($invitado) {
                $personas[] = [
                    'rol' => 'invitado',
                    'nombre' => trim($invitado->nombre_inv . ' ' . $invitado->apellido_inv),
                    'documento' => $invitado->documento_inv,
                ];
            }

            // El QR es solo para la asistencia del día del evento. Los kits se
            // entregan días antes, desde Kits → Entregas kits (EntregaKitController).
            return response()->json([
                'message' => $agotado
                    ? 'QR validado correctamente. Ya ingresaron todas las personas.'
                    : 'QR validado correctamente. Falta que ingrese una persona más.',
                'qr' => $qr->fresh()->load([
                    'inscripcion.usuario',
                    'inscripcion.evento',
                    'inscripcion.invitado',
                ]),
                'acceso' => [
                    'ingreso_numero' => $usosRealizados,
                    'usos_permitidos' => $qr->usos_permitidos_qr,
                    'usos_restantes' => $qr->usos_permitidos_qr - $usosRealizados,
                    'personas' => $personas,
                ],
            ]);
        });

        return $resultado;
    }

    /* ------------------------------------------------------------------
     * QR del kit ("KIT-…"): con él se retira el kit, días antes del evento.
     * Primero se consulta (para ver tallas y a quién se entrega) y luego se
     * confirma la entrega de los kits que falten (titular y/o invitado).
     * ------------------------------------------------------------------ */

    /** POST /qr/kit/consultar { codigo_kit } — no cambia nada. */
    public function consultarKit(Request $request)
    {
        $codigo = $this->codigoKit($request);
        [$qr, $error] = $this->buscarQrKit($codigo);
        if ($error) {
            return $error;
        }

        $kits = $this->kitsDeInscripcion($qr->inscripcion);
        $faltan = collect($kits)->where('estado', '!=', 'entregado')->count();

        return response()->json([
            'message' => $faltan > 0
                ? ($faltan === 1 ? 'Hay 1 kit por entregar.' : "Hay {$faltan} kits por entregar.")
                : 'Los kits de esta inscripción ya fueron entregados.',
        ] + $this->resumenKit($qr, $kits));
    }

    /** POST /qr/kit/entregar { codigo_kit } — marca como entregados los kits pendientes. */
    public function entregarKit(Request $request)
    {
        $codigo = $this->codigoKit($request);

        return DB::transaction(function () use ($request, $codigo) {
            [$qr, $error] = $this->buscarQrKit($codigo, bloquear: true);
            if ($error) {
                return $error;
            }

            $inscripcion = $qr->inscripcion;
            $kits = $this->kitsDeInscripcion($inscripcion);
            $pendientes = array_values(array_filter($kits, fn ($k) => $k['estado'] !== 'entregado'));

            if (!$pendientes) {
                return response()->json([
                    'message' => 'Los kits de esta inscripción ya fueron entregados.',
                ] + $this->resumenKit($qr, $kits), 409);
            }

            $staff = $request->user();
            $quien = mb_substr(trim(($staff->nombre_u ?? '') . ' ' . ($staff->apellido_u ?? '')), 0, 80);

            foreach ($pendientes as $k) {
                $datos = [
                    'estado_ek' => 'entregado',
                    'fecha_entrega_real_ek' => now(),
                    'persona_entrega_ek' => $quien,
                ];

                $entrega = EntregaKit::where('id_i', $inscripcion->id_i)
                    ->where('id_inv', $k['id_inv'])
                    ->lockForUpdate()
                    ->first();

                if ($entrega) {
                    $entrega->update($datos);
                } else {
                    EntregaKit::create($datos + [
                        'id_k' => $inscripcion->evento->id_k,
                        'id_u' => $inscripcion->id_u,
                        'id_e' => $inscripcion->id_e,
                        'id_i' => $inscripcion->id_i,
                        'id_inv' => $k['id_inv'],
                    ]);
                }
            }

            NotificacionService::crearSeguro(
                (int) $inscripcion->id_u,
                'Kit entregado',
                'Registramos la entrega de tu kit para «' . ($inscripcion->evento->nombre_e ?? 'tu evento') . '». ¡Prepárate para la carrera!',
                'kit'
            );

            $entregados = count($pendientes);

            return response()->json([
                'message' => $entregados === 1 ? 'Kit entregado.' : "{$entregados} kits entregados.",
                'entregados' => $entregados,
            ] + $this->resumenKit($qr, $this->kitsDeInscripcion($inscripcion)));
        });
    }

    /** El código escrito o escaneado, normalizado ("kit-ABC…" → "KIT-abc…"). */
    private function codigoKit(Request $request): string
    {
        $datos = $request->validate([
            'codigo_kit' => 'required|string|max:255',
        ]);

        $codigo = trim($datos['codigo_kit']);

        return str_starts_with(strtoupper($codigo), 'KIT-')
            ? 'KIT-' . strtolower(substr($codigo, 4))
            : $codigo;
    }

    /**
     * Busca el QR del kit y revisa que se pueda usar.
     * Devuelve [qr, null] o [null, respuesta de error].
     */
    private function buscarQrKit(string $codigo, bool $bloquear = false): array
    {
        $consulta = QrEntrada::with(['inscripcion.usuario', 'inscripcion.evento', 'inscripcion.invitado'])
            ->where('codigo_kit_qr', $codigo);
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
        $qr = $consulta->first();

        if (!$qr) {
            $esDeEntrada = QrEntrada::where('codigo_qr', $codigo)->exists();
            return [null, response()->json([
                'message' => $esDeEntrada
                    ? 'Este es el QR de entrada al evento, no el del kit. Pide el QR de "Retiro del kit".'
                    : 'Código del kit no encontrado.',
                'tipo' => $esDeEntrada ? 'entrada' : null,
            ], $esDeEntrada ? 422 : 404)];
        }

        $inscripcion = $qr->inscripcion;
        if (!$inscripcion || !$inscripcion->evento) {
            return [null, response()->json(['message' => 'La inscripción asociada no existe.'], 404)];
        }

        if ($inscripcion->estado_i !== 'confirmada') {
            return [null, response()->json([
                'message' => $inscripcion->estado_i === 'cancelada'
                    ? 'La inscripción está cancelada: no se entrega el kit.'
                    : 'El pago de esta inscripción aún no está confirmado: no se entrega el kit.',
                'estado_i' => $inscripcion->estado_i,
            ], 422)];
        }

        if (!$inscripcion->evento->id_k) {
            return [null, response()->json(['message' => 'El evento ya no tiene kit asignado.'], 422)];
        }

        return [$qr, null];
    }

    /** Kits que pidió la inscripción (titular y/o invitado) con su estado de entrega. */
    private function kitsDeInscripcion($inscripcion): array
    {
        $entregas = EntregaKit::where('id_i', $inscripcion->id_i)->get();
        $estado = function (?int $idInv) use ($entregas) {
            $fila = $entregas->first(fn ($e) => $idInv === null
                ? $e->id_inv === null
                : (int) $e->id_inv === $idInv);
            return [
                'estado' => $fila->estado_ek ?? 'pendiente',
                'fecha_entrega' => $fila->fecha_entrega_real_ek ?? null,
                'entregado_por' => $fila->persona_entrega_ek ?? null,
            ];
        };

        $kits = [];
        $usuario = $inscripcion->usuario;
        if ($inscripcion->desea_kit_i) {
            $kits[] = [
                'id_inv' => null,
                'rol' => 'titular',
                'nombre' => $usuario ? trim($usuario->nombre_u . ' ' . $usuario->apellido_u) : null,
                'documento' => $usuario->documento_u ?? null,
                'talla' => $inscripcion->talla_camiseta_i,
            ] + $estado(null);
        }

        $invitado = $inscripcion->invitado;
        if ($invitado && $invitado->desea_kit_inv) {
            $kits[] = [
                'id_inv' => $invitado->id_inv,
                'rol' => 'invitado',
                'nombre' => trim($invitado->nombre_inv . ' ' . $invitado->apellido_inv),
                'documento' => $invitado->documento_inv,
                'talla' => $invitado->talla_kit_inv,
            ] + $estado((int) $invitado->id_inv);
        }

        return $kits;
    }

    private function resumenKit(QrEntrada $qr, array $kits): array
    {
        $evento = $qr->inscripcion->evento;

        return [
            'evento' => ['id' => $evento->id_e, 'nombre' => $evento->nombre_e],
            'kit' => optional($evento->kit)->nombre_k,
            'kits' => $kits,
            'pendientes' => collect($kits)->where('estado', '!=', 'entregado')->count(),
        ];
    }
}