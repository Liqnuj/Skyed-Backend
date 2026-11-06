<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Totales por evento para los paneles de administración: cuántas personas se
 * inscribieron (o reservaron), cuánto dinero dejó cada evento y su detalle.
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
            ->leftJoin('invitados', 'invitados.id_inv', '=', 'inscripcion.id_inv')
            ->selectRaw("inscripcion.id_e AS id_e,
                SUM(CASE WHEN estado_i <> 'cancelada' THEN 1 ELSE 0 END) AS inscritos,
                SUM(CASE WHEN estado_i = 'confirmada' THEN 1 ELSE 0 END) AS confirmadas,
                SUM(CASE WHEN estado_i = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado_i <> 'cancelada' AND inscripcion.id_inv IS NOT NULL THEN 1 ELSE 0 END) AS invitados,
                SUM(CASE WHEN estado_i <> 'cancelada' THEN COALESCE(cupo_i, 1) ELSE 0 END) AS cupos_ocupados,
                SUM(CASE WHEN estado_i = 'confirmada' THEN COALESCE(cupo_i, 1) ELSE 0 END) AS personas_confirmadas,
                SUM(CASE WHEN estado_i <> 'cancelada' AND desea_kit_i = true THEN 1 ELSE 0 END)
                  + SUM(CASE WHEN estado_i <> 'cancelada' AND invitados.desea_kit_inv = true THEN 1 ELSE 0 END) AS kits_pedidos")
            ->groupBy('inscripcion.id_e')
            ->get()
            ->keyBy('id_e');

        // Dinero: lo confirmado, separado en inscripción, kits e invitados con
        // los precios guardados en cada inscripción. Las inscripciones antiguas
        // no tienen el desglose: todo su pago cuenta como inscripción.
        $pagos = DB::table('pago')
            ->join('inscripcion', 'inscripcion.id_i', '=', 'pago.id_i')
            ->selectRaw("inscripcion.id_e AS id_e,
                SUM(CASE WHEN pago.estado_p = 'confirmado' THEN pago.monto_p ELSE 0 END) AS recaudado,
                SUM(CASE WHEN pago.estado_p = 'pendiente' THEN pago.monto_p ELSE 0 END) AS por_validar,
                SUM(CASE WHEN pago.estado_p = 'confirmado' THEN COALESCE(precio_kit_i, 0) + COALESCE(precio_kit_invitado_i, 0) ELSE 0 END) AS dinero_kits,
                SUM(CASE WHEN pago.estado_p = 'confirmado' THEN COALESCE(precio_invitado_i, 0) ELSE 0 END) AS dinero_invitados")
            ->groupBy('inscripcion.id_e')
            ->get()
            ->keyBy('id_e');

        // Asistencia: cuántas personas entraron con su QR el día del evento.
        $asistencia = DB::table('qr_entrada')
            ->join('inscripcion', 'inscripcion.id_i', '=', 'qr_entrada.id_i')
            ->where('inscripcion.estado_i', '<>', 'cancelada')
            ->selectRaw('inscripcion.id_e AS id_e, SUM(COALESCE(qr_entrada.usos_realizados_qr, 0)) AS asistieron')
            ->groupBy('inscripcion.id_e')
            ->pluck('asistieron', 'id_e');

        $kitsEntregados = DB::table('entrega_kit')
            ->where('estado_ek', 'entregado')
            ->selectRaw('id_e, COUNT(*) AS entregados')
            ->groupBy('id_e')
            ->pluck('entregados', 'id_e');

        $ids = $inscripciones->keys()->merge($pagos->keys())->merge($asistencia->keys())->merge($kitsEntregados->keys())->unique();

        $resumen = [];
        foreach ($ids as $idE) {
            $i = $inscripciones[$idE] ?? null;
            $p = $pagos[$idE] ?? null;
            $recaudado = round((float) ($p->recaudado ?? 0), 2);
            $kits = round((float) ($p->dinero_kits ?? 0), 2);
            $invitados = round((float) ($p->dinero_invitados ?? 0), 2);

            $resumen[$idE] = [
                'inscritos' => (int) ($i->inscritos ?? 0),
                'confirmadas' => (int) ($i->confirmadas ?? 0),
                'pendientes' => (int) ($i->pendientes ?? 0),
                'invitados' => (int) ($i->invitados ?? 0),
                'cupos_ocupados' => (int) ($i->cupos_ocupados ?? 0),
                'recaudado' => $recaudado,
                'por_validar' => round((float) ($p->por_validar ?? 0), 2),
                'desglose' => [
                    'inscripciones' => max(0, round($recaudado - $kits - $invitados, 2)),
                    'kits' => $kits,
                    'invitados' => $invitados,
                ],
                'asistencia' => [
                    'esperados' => (int) ($i->personas_confirmadas ?? 0),
                    'asistieron' => (int) ($asistencia[$idE] ?? 0),
                ],
                'kits' => [
                    'pedidos' => (int) ($i->kits_pedidos ?? 0),
                    'entregados' => (int) ($kitsEntregados[$idE] ?? 0),
                ],
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
            ->keyBy('id_er');

        // Servicios más pedidos en las reservas de cada evento (en cuántas reservas aparece cada uno).
        $servicios = DB::table('reserva_servicio')
            ->join('reserva', 'reserva.id_rese', '=', 'reserva_servicio.id_rese')
            ->leftJoin('servicio', 'servicio.id_s', '=', 'reserva_servicio.id_s')
            ->whereNotNull('reserva.id_er')
            ->where('reserva.estado_rese', '<>', 'cancelada')
            ->selectRaw('reserva.id_er AS id_er,
                COALESCE(servicio.nombre_s, reserva_servicio.nombre_servicio) AS nombre,
                COUNT(DISTINCT reserva_servicio.id_rese) AS reservas')
            ->groupBy('reserva.id_er', DB::raw('COALESCE(servicio.nombre_s, reserva_servicio.nombre_servicio)'))
            ->get()
            ->groupBy('id_er');

        $datos = [];
        foreach ($resumen as $idEr => $r) {
            $ingresos = round((float) $r->ingresos, 2);
            $confirmadas = (int) $r->confirmadas;

            $datos[$idEr] = [
                'reservas' => (int) $r->reservas,
                'confirmadas' => $confirmadas,
                'pendientes' => (int) $r->pendientes,
                'invitados' => (int) $r->invitados,
                'ingresos' => $ingresos,
                'por_confirmar' => round((float) $r->por_confirmar, 2),
                'promedio' => $confirmadas > 0 ? round($ingresos / $confirmadas, 2) : 0,
                'servicios_top' => ($servicios[$idEr] ?? collect())
                    ->filter(fn ($s) => $s->nombre)
                    ->sortByDesc('reservas')
                    ->take(3)
                    ->map(fn ($s) => ['nombre' => $s->nombre, 'reservas' => (int) $s->reservas])
                    ->values()
                    ->all(),
            ];
        }

        return response()->json(['data' => (object) $datos]);
    }
}
