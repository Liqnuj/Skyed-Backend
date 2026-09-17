<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu código QR de inscripción</title>
</head>
<body style="font-family: Arial, sans-serif; background:#0b1220; color:#e5e9f0; padding: 2rem;">
    <div style="max-width: 480px; margin: 0 auto; background:#101a2c; border-radius: 12px; padding: 2rem; text-align:center;">
        <h2 style="color:#fff; margin-top:0;">¡Inscripción confirmada!</h2>
        <p>Hola {{ $nombreUsuario }}, este es tu código QR para <strong>{{ $nombreEvento }}</strong>.</p>

        <img src="{{ $message->embedData($qrImagen, 'qr.png', 'image/png') }}"
             alt="Código QR de la inscripción" width="260" height="260"
             style="margin: 1.5rem 0; border-radius: 8px;">

        <p style="font-size: .85rem; color:#9aa5b8;">Código: {{ $codigo }}</p>
        <p style="font-size: .9rem;">Muestra este código en el punto de control el día del evento.</p>
    </div>
</body>
</html>