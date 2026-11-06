<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Totales por evento para los paneles de administración: cuántas personas se
 * inscribieron (o reservaron) y cuánto dinero dejó cada evento.
 *
 * Va aparte del listado público de eventos porque son datos de dinero que
 * solo ve el administrador. Responde un mapa { id del evento: totales }.
 */
class EstadisticasEventoController extends Controller
{
    /**
     * SKYED Deportivo. Una inscripción cancelada (o con el pago rechazado, que
     * también la cancela) no cuenta. El dinero recaudado es solo el de pagos
     * confirmados; lo que falta revisar va aparte en "por_validar".
     */
    public function deportivos(): JsonResponse
    {
        $inscripciones = DB::table('inscripcion')
            ->selectRaw("id_e,
                SUM(CASE WHEN estado_i <> 'cancelada' THEN 1 ELSE 0 END) AS inscritos,
                SUM(CASE WHEN estado_i = 'confirmada' THEN 1 ELSE 0 END) AS confirmadas,
                SUM(CASE WHEN estado_i = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado_i <> 'cancelada' AND id_inv IS NOT NULL THEN 1 ELSE 0 END) AS invitados")
            ->groupBy('id_e')
            ->get()
            ->keyBy('id_e');

        $pagos = DB::table('pago')
            ->join('inscripcion', 'inscripcion.id_i', '=', 'pago.id_i')
            ->selectRaw("inscripcion.id_e AS id_e,
                SUM(CASE WHEN pago.estado_p = 'confirmado' THEN pago.monto_p ELSE 0 END) AS recaudado,
                SUM(CASE WHEN pago.estado_p = 'pendiente' THEN pago.monto_p ELSE 0 END) AS por_validar")
            ->groupBy('inscripcion.id_e')
            ->get()
            ->keyBy('id_e');

        $resumen = [];
        foreach ($inscripciones->keys()->merge($pagos->keys())->unique() as $idE) {
            $i = $inscripciones[$idE] ?? null;
            $p = $pagos[$idE] ?? null;
            $resumen[$idE] = [
                'inscritos' => (int) ($i->inscritos ?? 0),
                'confirmadas' => (int) ($i->confirmadas ?? 0),
                'pendientes' => (int) ($i->pendientes ?? 0),
                'invitados' => (int) ($i->invitados ?? 0),
                'recaudado' => round((float) ($p->recaudado ?? 0), 2),
                'por_validar' => round((float) ($p->por_validar ?? 0), 2),
            ];
        }

        return response()->json(['data' => (object) $resumen]);
    }

    /**
     * SkyedSocial. Cuentan las reservas hechas desde el evento (botón
     * "Cotizar"), menos las canceladas. Los ingresos son el total de las
     * reservas confirmadas o completadas; las pendientes van en "por_confirmar".
     */
    public function sociales(): JsonResponse
    {
        $resumen = DB::table('reserva')
            ->whereNotNull('id_er')
            ->selectRaw("id_er,
                SUM(CASE WHEN estado_rese <> 'cancelada' THEN 1 ELSE 0 END) AS reservas,
                SUM(CASE WHEN estado_rese IN ('confirmada', 'completada') THEN 1 ELSE 0 END) AS confirmadas,
                SUM(CASE WHEN estado_rese = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado_rese <> 'cancelada' THEN COALESCE(invitados_rese, 0) ELSE 0 END) AS invitados,
                SUM(CASE WHEN estado_rese IN ('confirmada', 'completada') THEN COALESCE(total_rese, 0) ELSE 0 END) AS ingresos,
                SUM(CASE WHEN estado_rese = 'pendiente' THEN COALESCE(total_rese, 0) ELSE 0 END) AS por_confirmar")
            ->groupBy('id_er')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->id_er => [
                'reservas' => (int) $r->reservas,
                'confirmadas' => (int) $r->confirmadas,
                'pendientes' => (int) $r->pendientes,
                'invitados' => (int) $r->invitados,
                'ingresos' => round((float) $r->ingresos, 2),
                'por_confirmar' => round((float) $r->por_confirmar, 2),
            ]]);

        return response()->json(['data' => (object) $resumen->all()]);
    }
}
