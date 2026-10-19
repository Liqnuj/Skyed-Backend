<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:40px 0">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">

        <!-- HEADER -->
        <tr>
          <td style="background:linear-gradient(135deg,#1a6fa0 0%,#0d4f7a 100%);border-radius:12px 12px 0 0;padding:36px 40px;text-align:center">
            
            <!-- CORRECCIÓN DEL LOGO: Usa una URL absoluta que empiece con https:// -->
            <!-- Cambia esta URL por la dirección real donde esté subido tu logo -->
            <img src="{{ $message->embed(public_path('img/logoP.png')) }}" width="72" height="72" alt="SKYED"
                style="border-radius:50%;background:#fff;padding:8px;margin-bottom:16px;display:block;margin-left:auto;margin-right:auto; object-fit:contain;" />
            <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;letter-spacing:.5px">
              Código de verificación
            </h1>
            <p style="margin:10px 0 0;color:#cce4f5;font-size:14px;line-height:1.6">
              Has solicitado recuperar tu contraseña en SKYED. Usa este código para<br>
              continuar con la verificación y proteger tu cuenta.
            </p>
          </td>
        </tr>

        <!-- BODY -->
        <tr>
          <td style="background:#ffffff;padding:40px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0">
            <p style="margin:0 0 8px;color:#1a202c;font-size:15px">Hola,</p>
            <p style="margin:0 0 24px;color:#4a5568;font-size:14px;line-height:1.7">
              Este es tu código único de 6 dígitos para verificar tu identidad y restablecer tu
              contraseña. Ingresa el código en la pantalla de verificación para continuar.
            </p>

            <!-- CÓDIGO -->
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center" style="padding:8px 0 28px">
                  
                  <!-- CORRECCIÓN DEL CAJÓN: Se quitaron las barras invertidas en font-family -->
                  <div style="display:inline-block;background:#1a3a5c;color:#ffffff;
                              font-size:30px;font-weight:700;letter-spacing:10px;
                              padding:16px 36px;border-radius:10px;
                              font-family:'Courier New', monospace;">
                    {{ $codigo }}
                  </div>

                </td>
              </tr>
            </table>

            <!-- PASOS -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px">
              <tr>
                <td>
                  <p style="margin:0 0 10px;color:#1a202c;font-size:14px;font-weight:700">
                    Pasos a seguir
                  </p>
                  <ol style="margin:0;padding-left:18px;color:#4a5568;font-size:14px;line-height:1.9">
                    <li>Introduce el código en la página de verificación.</li>
                    <li>Crea una nueva contraseña segura.</li>
                    <li>Accede de nuevo y sigue disfrutando de los eventos SKYED.</li>
                  </ol>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:0 0 12px 12px;
                     padding:24px 40px;text-align:center">
            <p style="margin:0 0 8px;color:#718096;font-size:12px;line-height:1.6">
              Si no solicitaste este código, ignora este correo o contáctanos si necesitas ayuda.
            </p>
            <p style="margin:0">
              <a href="mailto:soporte@skyed.com" style="color:#1a6fa0;font-size:12px;text-decoration:none;font-weight:600">
                SKYED soporte@skyed.com
              </a>
              &nbsp;·&nbsp;
              <a href="https://www.skyed.com" style="color:#1a6fa0;font-size:12px;text-decoration:none;font-weight:600">
                www.skyed.com
              </a>
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>