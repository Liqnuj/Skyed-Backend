<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tu inscripción está confirmada</title>
</head>
<body style="margin:0;padding:0;background:#eef3f6;font-family:Arial,Helvetica,sans-serif;color:#0f2740;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3f6;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;">

      <tr><td align="center" bgcolor="#0f2740" style="background:#0f2740;padding:26px 20px;">
        <div style="font-size:12px;letter-spacing:2px;color:#7fd3e3;font-weight:bold;">SKYED DEPORTIVO</div>
        <div style="font-size:24px;color:#ffffff;font-weight:bold;margin-top:8px;">¡Inscripción confirmada!</div>
      </td></tr>

      <tr><td style="padding:26px 24px 6px;font-size:15px;line-height:1.6;text-align:center;">
        Hola <b>{{ $nombreUsuario }}</b>, validamos tu pago. Estos son tus códigos para <b>{{ $nombreEvento }}</b>.
      </td></tr>

      {{-- QR de entrada --}}
      <tr><td style="padding:16px 24px 8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #d7e4ea;border-radius:12px;">
          <tr><td align="center" style="padding:18px 12px 6px;">
            <div style="font-size:13px;letter-spacing:1px;color:#1f8a9e;font-weight:bold;">ENTRADA AL EVENTO</div>
            <div style="font-size:13px;color:#5b6b78;margin-top:4px;">
              {{ $conInvitado ? 'Sirve para 2 ingresos (tú y tu invitado).' : 'Muéstralo en el punto de control el día del evento.' }}
            </div>
          </td></tr>
          <tr><td align="center" style="padding:10px 12px;">
            @if($qrEntrada)
              <img src="{{ $message->embedData($qrEntrada, 'qr-entrada.png', 'image/png') }}" width="200" height="200" alt="QR de entrada" style="display:block;margin:0 auto;border:0;">
            @endif
          </td></tr>
          <tr><td align="center" style="padding:0 12px 18px;font-size:12px;color:#5b6b78;word-break:break-all;">
            Código: {{ $codigo }}
          </td></tr>
        </table>
      </td></tr>

      {{-- QR del kit (solo si pidió kit) --}}
      @if($codigoKit)
      <tr><td style="padding:8px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #d7e4ea;border-radius:12px;">
          <tr><td align="center" style="padding:18px 12px 6px;">
            <div style="font-size:13px;letter-spacing:1px;color:#1f8a9e;font-weight:bold;">RETIRO DEL KIT</div>
            <div style="font-size:13px;color:#5b6b78;margin-top:4px;">Preséntalo cuando vayas a recoger tu kit, antes del evento.</div>
          </td></tr>
          <tr><td align="center" style="padding:10px 12px;">
            @if($qrKit)
              <img src="{{ $message->embedData($qrKit, 'qr-kit.png', 'image/png') }}" width="200" height="200" alt="QR del kit" style="display:block;margin:0 auto;border:0;">
            @endif
          </td></tr>
          @foreach($kits as $linea)
          <tr><td align="center" style="padding:0 12px 4px;font-size:13px;font-weight:bold;">{{ $linea }}</td></tr>
          @endforeach
          <tr><td align="center" style="padding:6px 12px 18px;font-size:12px;color:#5b6b78;word-break:break-all;">
            Código: {{ $codigoKit }}
          </td></tr>
        </table>
      </td></tr>
      @endif

      <tr><td align="center" style="padding:16px 24px 26px;font-size:12px;line-height:1.6;color:#5b6b78;">
        También puedes verlos en tu perfil, en <b>Inscripciones</b>.<br>
        No compartas estos códigos.
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>