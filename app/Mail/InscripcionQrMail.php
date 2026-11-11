<?php

namespace App\Mail;

use App\Models\Inscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class InscripcionQrMail extends Mailable
{
    use Queueable, SerializesModels;

    public Inscripcion $inscripcion;

    public function __construct(Inscripcion $inscripcion)
    {
        $this->inscripcion = $inscripcion;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu inscripción está confirmada - SKYED Deportivo',
        );
    }

    /** Imagen PNG del QR; null si el servicio externo no responde (el correo sale igual con el código en texto). */
    private function imagenQr(string $texto): ?string
    {
        try {
            $respuesta = Http::connectTimeout(3)->timeout(6)->get('https://api.qrserver.com/v1/create-qr-code/', [
                'size' => '260x260',
                'data' => $texto,
            ]);
            return $respuesta->successful() ? $respuesta->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function content(): Content
    {
        $insc = $this->inscripcion;
        $qr = $insc->qr;
        $invitado = $insc->invitado;

        $codigo = $qr->codigo_qr;
        $codigoKit = $qr->codigo_kit_qr ?: null;

        // Quién retira kit y de qué talla (solo se muestra si hay QR del kit).
        $kits = [];
        if ($codigoKit) {
            if ($insc->desea_kit_i) {
                $kits[] = 'Tú · talla ' . ($insc->talla_camiseta_i ?: '—');
            }
            if ($invitado && $invitado->desea_kit_inv) {
                $kits[] = trim($invitado->nombre_inv . ' ' . $invitado->apellido_inv) . ' · talla ' . ($invitado->talla_kit_inv ?: '—');
            }
        }

        return new Content(
            view: 'emails.inscripcion-qr',
            with: [
                'nombreUsuario' => $insc->usuario->nombre_u,
                'nombreEvento' => $insc->evento->nombre_e,
                'codigo' => $codigo,
                'qrEntrada' => $this->imagenQr($codigo),
                'conInvitado' => (int) $qr->usos_permitidos_qr > 1,
                'codigoKit' => $codigoKit,
                'qrKit' => $codigoKit ? $this->imagenQr($codigoKit) : null,
                'kits' => $kits,
            ],
        );
    }
}