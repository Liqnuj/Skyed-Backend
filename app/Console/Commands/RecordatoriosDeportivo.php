<?php

namespace App\Console\Commands;

use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Console\Command;

class RecordatoriosDeportivo extends Command
{
    protected $signature = 'notificaciones:recordatorios-deportivo';
    protected $description = 'Avisa que se acerca el evento y la entrega del kit (3 días y 1 día antes)';

    public function handle(): int
    {
        foreach ([3, 1] as $dias) {
            $fecha = now()->addDays($dias)->toDateString();
            $cuando = $dias === 1 ? 'mañana' : 'en 3 días';

            // 1) Evento próximo
            EventoDeportivo::whereDate('fecha_e', $fecha)->where('estado_e', 'activo')->get()
                ->each(function ($evento) use ($cuando, $dias) {
                    $this->avisar(
                        $evento->id_e,
                        $dias === 1 ? '¡Tu evento es mañana!' : 'Tu evento se acerca',
                        '«' . $evento->nombre_e . '» es ' . $cuando . '. Revisa los requisitos y lleva tu QR de entrada.'
                    );
                });

            // 2) Entrega del kit (fecha_entrega_k del kit asignado al evento)
            EventoDeportivo::whereHas('kit', fn ($q) => $q->whereDate('fecha_entrega_k', $fecha))
                ->where('estado_e', 'activo')->with('kit')->get()
                ->each(function ($evento) use ($cuando) {
                    $lugar = $evento->kit->lugar_entrega_k ? ' en ' . $evento->kit->lugar_entrega_k : '';
                    $this->avisar(
                        $evento->id_e,
                        'Entrega de tu kit',
                        'La entrega del kit de «' . $evento->nombre_e . '» es ' . $cuando . $lugar . '.'
                    );
                });
        }

        return self::SUCCESS;
    }

    private function avisar(int $idEvento, string $titulo, string $mensaje): void
    {
        Inscripcion::where('id_e', $idEvento)->where('estado_i', 'confirmada')
            ->pluck('id_u')->unique()
            ->each(function ($uid) use ($titulo, $mensaje) {
                $ya = Notificacion::where('user_id', $uid)->where('tipo', 'recordatorio')
                    ->where('mensaje', $mensaje)->exists();

                if (!$ya) {
                    NotificacionService::crearSeguro((int) $uid, $titulo, $mensaje, 'recordatorio');
                }
            });
    }
}