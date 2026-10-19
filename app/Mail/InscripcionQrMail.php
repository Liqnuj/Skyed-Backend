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
            subject: 'Tu código QR de inscripción - Skyed',
        );
    }

    public function content(): Content
    {
        $codigo = $this->inscripcion->qr->codigo_qr;

        // Mismo servicio y mismo texto que usa Participant.tsx -> mismo QR.
        $qrImagen = Http::get('https://api.qrserver.com/v1/create-qr-code/', [
            'size' => '260x260',
            'data' => $codigo,
        ])->body();

        return new Content(
            view: 'emails.inscripcion-qr',
            with: [
                'nombreUsuario' => $this->inscripcion->usuario->nombre_u,
                // AJUSTA el nombre del campo si en EventoDeportivo no se llama nombre_e:
                'nombreEvento' => $this->inscripcion->evento->nombre_e,
                'codigo' => $codigo,
                'qrImagen' => $qrImagen,
            ],
        );
    }
}