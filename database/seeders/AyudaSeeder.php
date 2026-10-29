<?php

namespace Database\Seeders;

use App\Models\AyudaArticulo;
use App\Models\AyudaCategoria;
use Illuminate\Database\Seeder;

/**
 * Contenido inicial del Centro de Ayuda. No borra nada y se puede correr
 * varias veces: usa firstOrCreate por slug, así que NO pisa lo que un
 * administrador edite después. Ejecutar SOLO con:
 *   php artisan db:seed --class=AyudaSeeder
 * (un "db:seed" general reasigna la contraseña de los admins).
 */
class AyudaSeeder extends Seeder
{
    public function run(): void
    {
        $cat = [];

        foreach ([
            ['primeros-pasos', 'Primeros pasos y cuenta', 'Crea tu cuenta, inicia sesión y administra tus datos.', 'ti-rocket', 'general', 1],
            ['eventos-deportivos', 'Eventos deportivos', 'Inscripciones, pagos y entradas de SKYED Deportivo.', 'ti-bike', 'deportivo', 2],
            ['reservas', 'Reservas', 'Solicita, consulta y cancela tus reservas de SkyedSocial.', 'ti-calendar-event', 'social', 3],
            ['pqrs', 'PQRS', 'Peticiones, quejas, reclamos, sugerencias y felicitaciones.', 'ti-message-report', 'social', 4],
            ['administracion-deportivo', 'Administración Deportivo', 'Guías para el panel del administrador deportivo.', 'ti-shield-check', 'deportivo', 5],
        ] as [$slug, $nombre, $desc, $icono, $modulo, $orden]) {
            $cat[$slug] = AyudaCategoria::firstOrCreate(
                ['slug_ayc' => $slug],
                [
                    'nombre_ayc' => $nombre,
                    'descripcion_ayc' => $desc,
                    'icono_ayc' => $icono,
                    'modulo_ayc' => $modulo,
                    'orden_ayc' => $orden,
                    'activa_ayc' => true,
                ]
            );
        }

        $articulos = [
            [
                'cat' => 'primeros-pasos', 'slug' => 'como-crear-una-cuenta', 'orden' => 1,
                'modulo' => 'general', 'audiencia' => 'publico', 'contexto' => 'auth.registro',
                'titulo' => '¿Cómo crear una cuenta?',
                'resumen' => 'Regístrate gratis para inscribirte a eventos, reservar y radicar PQRS.',
                'claves' => 'registro, registrarme, crear cuenta, nueva cuenta',
                'contenido' => <<<'MD'
Necesitas una cuenta para inscribirte a eventos deportivos, solicitar reservas y radicar PQRS.

1. En el inicio de sesión pulsa **Regístrate gratis** o entra a [[Crear cuenta|/registro]].
2. Elige el **tipo de documento** y escribe tu número de documento.
3. Completa tu nombre, apellido, correo, teléfono y fecha de nacimiento.
4. Crea una contraseña segura: debe tener mayúscula, minúscula y número.
5. Envía el formulario para crear tu cuenta.

Después podrás iniciar sesión con tu correo o con tu número de documento.
MD,
            ],
            [
                'cat' => 'primeros-pasos', 'slug' => 'como-iniciar-sesion', 'orden' => 2,
                'modulo' => 'general', 'audiencia' => 'publico', 'contexto' => 'auth.login',
                'titulo' => '¿Cómo iniciar sesión?',
                'resumen' => 'Entra con tu correo o tu número de documento y tu contraseña.',
                'claves' => 'login, entrar, ingresar, iniciar sesion, contraseña',
                'contenido' => <<<'MD'
1. Entra a [[Iniciar sesión|/login]].
2. Escribe tu **correo o tu número de documento**.
3. Escribe tu contraseña y envía el formulario.

Si te equivocas 3 veces seguidas, el sistema te pide esperar un minuto antes de intentarlo de nuevo. Si no recuerdas tu contraseña, usa la opción **¿Olvidaste tu contraseña?**.
MD,
            ],
            [
                'cat' => 'primeros-pasos', 'slug' => 'olvide-mi-contrasena', 'orden' => 3,
                'modulo' => 'general', 'audiencia' => 'publico', 'contexto' => 'auth.recuperar',
                'faq' => true, 'destacado' => true,
                'titulo' => 'Olvidé mi contraseña, ¿cómo la recupero?',
                'resumen' => 'Recibe un código de 6 dígitos en tu correo y crea una contraseña nueva.',
                'claves' => 'recuperar, restablecer, olvide, clave, codigo, acceso',
                'contenido' => <<<'MD'
1. En el inicio de sesión pulsa **¿Olvidaste tu contraseña?** o entra a [[Recuperar contraseña|/recuperar]].
2. Pide el código siguiendo las instrucciones de la pantalla.
3. Revisa tu correo: te llega un **código de 6 dígitos**.
4. Escribe el código y crea tu nueva contraseña.

Si el código no llega, revisa la carpeta de spam y espera un momento antes de pedir otro: por seguridad hay un límite de intentos seguidos y el código tiene una vigencia limitada.
MD,
            ],
            [
                'cat' => 'primeros-pasos', 'slug' => 'como-cambiar-mis-datos-o-mi-contrasena', 'orden' => 4,
                'modulo' => 'general', 'audiencia' => 'usuario', 'contexto' => 'cuenta.ajustes',
                'titulo' => '¿Cómo cambiar mis datos o mi contraseña?',
                'resumen' => 'Todo está en la pestaña Ajustes de tu perfil.',
                'claves' => 'perfil, datos, ajustes, telefono, ciudad, foto, contraseña, cuenta',
                'contenido' => <<<'MD'
1. Inicia sesión y abre tu perfil: [[Perfil Deportivo|/deportivo/perfil?tab=ajustes]] o [[Perfil Social|/social/perfil?tab=ajustes]].
2. Entra a la pestaña **Ajustes**.
3. Cambia tus datos (por ejemplo teléfono, ciudad o foto) y guarda.
4. Para la contraseña, usa la sección de cambio de contraseña de esa misma pestaña.

Si eres administrador, estas opciones están en **Ajustes** dentro de tu panel.
MD,
            ],
            [
                'cat' => 'eventos-deportivos', 'slug' => 'como-inscribirme-a-un-evento', 'orden' => 1,
                'modulo' => 'deportivo', 'audiencia' => 'publico', 'contexto' => 'deportivo.inscripcion',
                'faq' => true, 'destacado' => true,
                'titulo' => '¿Cómo inscribirme a un evento deportivo?',
                'resumen' => 'La inscripción tiene 3 pasos: tus datos, el pago y la confirmación.',
                'claves' => 'inscripcion, inscribirme, evento, ciclismo, pago, comprobante, jersey, categoria',
                'contenido' => <<<'MD'
1. Inicia sesión.
2. Entra a [[Eventos|/deportivo/eventos]], abre el evento y pulsa **Inscribirme**.
3. **Paso 1, Tus datos:** revisa tus datos, llena el contacto de emergencia (nombre, teléfono y parentesco) y elige tu **categoría**. Indica si quieres tu jersey y su talla. Opcional: número de dorsal, condiciones médicas o agregar un invitado.
4. Pulsa **Continuar al pago**.
5. **Paso 2, Pago:** elige **Transferencia**, **Nequi / Daviplata** o **Efectivo**. Con transferencia o Nequi debes subir el **comprobante** (JPG, PNG o PDF, máximo 5 MB).
6. **Paso 3, Confirmar:** revisa, acepta los términos y la declaración de estado de salud, y confirma.

Al final verás "¡Inscripción confirmada!" y el botón **Ver mi entrada**, con tu código QR (también llega a tu correo). Tu pago queda pendiente hasta que el equipo administrador lo valide.
MD,
            ],
            [
                'cat' => 'reservas', 'slug' => 'como-solicitar-una-reserva', 'orden' => 1,
                'modulo' => 'social', 'audiencia' => 'publico', 'contexto' => 'social.reservar',
                'faq' => true, 'destacado' => true,
                'titulo' => '¿Cómo realizar una reserva?',
                'resumen' => 'Envía tu solicitud y el equipo la revisa, define el total y la confirma.',
                'claves' => 'reservar, reserva, cotizar, cotizacion, evento social, boda, quinceañera, solicitud',
                'contenido' => <<<'MD'
1. Inicia sesión: la reserva necesita una cuenta.
2. Pulsa **Reservar ahora** (o **Cotizar** en un evento) para abrir [[el formulario de reserva|/social/reservar]].
3. Llena tu nombre, apellido, teléfono/WhatsApp y correo.
4. Indica el **Tipo de evento**, la **Fecha estimada** (no puede ser anterior a hoy), el **N° de invitados**, la **Ubicación del evento**, el **Presupuesto aprox.** y **Cuéntanos tu visión**.
5. Pulsa **Enviar solicitud**.

Tu solicitud queda en estado **Pendiente**: el equipo la revisa, define el total y la confirma. Puedes seguirla en [[Mis reservas|/social/perfil?tab=reservas]].
MD,
            ],
            [
                'cat' => 'reservas', 'slug' => 'como-consultar-mi-reserva', 'orden' => 2,
                'modulo' => 'social', 'audiencia' => 'publico', 'contexto' => 'social.reservas',
                'faq' => true,
                'titulo' => '¿Dónde puedo consultar mis reservas?',
                'resumen' => 'En tu perfil, pestaña Reservas, con su estado y seguimiento.',
                'claves' => 'consultar reserva, ver reserva, mis reservas, seguimiento, estado, notificacion',
                'contenido' => <<<'MD'
1. Inicia sesión y abre [[tu perfil, pestaña Reservas|/social/perfil?tab=reservas]].
2. Verás tus reservas con su estado: Pendiente, Confirmada, Cancelada o Completada.
3. Abre una reserva para ver su detalle y su **seguimiento**: cada cambio que hace el equipo queda registrado ahí y además recibes una notificación.
4. Puedes ponerle un nombre a tu reserva para reconocerla más fácil.
MD,
            ],
            [
                'cat' => 'reservas', 'slug' => 'como-cancelar-una-reserva', 'orden' => 3,
                'modulo' => 'social', 'audiencia' => 'publico', 'contexto' => null,
                'faq' => true,
                'titulo' => '¿Cómo cancelo una reserva?',
                'resumen' => 'Solo puedes cancelar reservas Pendientes o Confirmadas cuyo evento aún no ha pasado.',
                'claves' => 'cancelar, cancelo, anular, reserva, motivo, cambiar fecha, modificar',
                'contenido' => <<<'MD'
Puedes cancelar tus propias reservas mientras estén **Pendientes** o **Confirmadas** y el evento todavía no haya pasado.

1. Abre [[tu perfil, pestaña Reservas|/social/perfil?tab=reservas]].
2. Abre la reserva que quieres cancelar.
3. Elige la opción de cancelar y, si quieres, escribe el motivo (es opcional).
4. Confirma.

La reserva pasa a **Cancelada** y queda registrado en su seguimiento. Una reserva ya cancelada o completada no se puede cancelar de nuevo.

Los datos de una reserva (fecha, invitados, etc.) los gestiona el equipo: si necesitas cambiarlos, radica una petición en [[PQRS|/social/pqr]] indicando tu reserva.
MD,
            ],
            [
                'cat' => 'reservas', 'slug' => 'que-significa-cada-estado-de-una-reserva', 'orden' => 4,
                'modulo' => 'social', 'audiencia' => 'publico', 'contexto' => null,
                'faq' => true,
                'titulo' => '¿Qué significa cada estado de una reserva?',
                'resumen' => 'Pendiente, Confirmada, Completada y Cancelada, explicados.',
                'claves' => 'estado, pendiente, confirmada, completada, cancelada, significa',
                'contenido' => <<<'MD'
- **Pendiente:** el equipo aún revisa tu solicitud y definirá el total. Todavía puedes cancelarla.
- **Confirmada:** el equipo la aprobó. Puedes cancelarla si el evento no ha pasado.
- **Completada:** el evento ya se realizó.
- **Cancelada:** la reserva fue cancelada, por ti o por el equipo.

Las reservas canceladas o completadas se pueden ocultar de tu lista para mantenerla ordenada.
MD,
            ],
            [
                'cat' => 'pqrs', 'slug' => 'como-radicar-una-pqrs', 'orden' => 1,
                'modulo' => 'social', 'audiencia' => 'publico', 'contexto' => 'social.pqr',
                'faq' => true, 'destacado' => true,
                'titulo' => '¿Cómo radico una PQRS?',
                'resumen' => 'Elige el tipo, escribe tu mensaje y recibe un número de radicado.',
                'claves' => 'pqr, pqrs, peticion, queja, reclamo, sugerencia, felicitacion, soporte, radicar, reportar problema',
                'contenido' => <<<'MD'
1. Inicia sesión y entra a [[PQRS|/social/pqr]].
2. Elige el tipo: **Petición**, **Queja**, **Reclamo**, **Sugerencia** o **Felicitación**.
3. Escribe el **Asunto** y la **Descripción detallada**.
4. Pulsa **Radicar solicitud**.

Recibirás un número de radicado (por ejemplo, SS-PQR-12).

**Tiempos de respuesta:** petición, 3 días hábiles; queja y reclamo, 5 días hábiles; sugerencia, acuse de recibo en 2 días hábiles; la felicitación no tiene plazo.
MD,
            ],
            [
                'cat' => 'pqrs', 'slug' => 'como-saber-el-estado-de-mi-pqrs', 'orden' => 2,
                'modulo' => 'social', 'audiencia' => 'publico', 'contexto' => null,
                'faq' => true,
                'titulo' => '¿Cómo consulto el estado de mi PQRS?',
                'resumen' => 'En tu perfil, pestaña PQRS, con su seguimiento y la respuesta del equipo.',
                'claves' => 'pqr, estado, seguimiento, respuesta, radicado, en revision, resuelto',
                'contenido' => <<<'MD'
1. Inicia sesión y abre [[tu perfil, pestaña PQRS|/social/perfil?tab=pqr]].
2. Abre tu solicitud: verás su seguimiento y la respuesta del equipo.

Una PQRS pasa por estas fases: **Enviado**, **En revisión**, **En proceso**, **Resuelto** y **Cerrada**. También puedes responder dentro de la solicitud cuando necesites aportar más información.
MD,
            ],
            [
                'cat' => 'administracion-deportivo', 'slug' => 'como-validar-un-pago', 'orden' => 1,
                'modulo' => 'deportivo', 'audiencia' => 'admin', 'contexto' => null,
                'titulo' => '¿Cómo valido el pago de una inscripción?',
                'resumen' => 'Revisa el comprobante y confirma o rechaza el pago.',
                'claves' => 'pagos, validar pago, comprobante, confirmar, rechazar, inscripciones, qr',
                'contenido' => <<<'MD'
1. Entra a [[Inscripciones y pagos|/deportivo/admin?seccion=inscripciones]] en tu panel.
2. Usa el filtro **Pagos por validar**.
3. Abre el detalle (ícono del ojo) para ver el comprobante.
4. Elige **Confirmar pago** o **Rechazar pago**.

Al confirmar, el QR del participante queda activo. Al rechazar, la inscripción se cancela y el cupo vuelve a estar disponible.
MD,
            ],
        ];

        foreach ($articulos as $a) {
            AyudaArticulo::firstOrCreate(
                ['slug_aya' => $a['slug']],
                [
                    'id_ayc' => $cat[$a['cat']]->id_ayc,
                    'titulo_aya' => $a['titulo'],
                    'resumen_aya' => $a['resumen'],
                    'contenido_aya' => $a['contenido'],
                    'palabras_clave_aya' => $a['claves'],
                    'modulo_aya' => $a['modulo'],
                    'audiencia_aya' => $a['audiencia'],
                    'estado_aya' => AyudaArticulo::PUBLICADO,
                    'faq_aya' => $a['faq'] ?? false,
                    'destacado_aya' => $a['destacado'] ?? false,
                    'orden_aya' => $a['orden'],
                    'contexto_aya' => $a['contexto'],
                ]
            );
        }
    }
}