<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EventoDeportivoController;
use App\Http\Controllers\CategoriaCompetenciaController;
use App\Http\Controllers\HistorialParticipacionController;
use App\Http\Controllers\RutaEventoController;
use App\Http\Controllers\TipoEventoController;
use App\Http\Controllers\AmbienteController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\EventoRealizadoController;
use App\Http\Controllers\SocialImagenController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\ReservaGestionController;
use App\Http\Controllers\PqrController;
use App\Http\Controllers\CopiaSeguridadController;
use App\Http\Controllers\QrEntradaController;
use App\Http\Controllers\PremioController;
use App\Http\Controllers\InvitadoController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SkaiController;
use App\Http\Controllers\TestimonioController;
use App\Http\Controllers\AyudaController;
use App\Http\Controllers\AyudaAdminController;
use App\Http\Controllers\UserRoleController;

use App\Models\TipoEvento;



// AGREGADOS: Importaciones de los controladores generales que faltaban
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\PatrocinadorController;
use App\Http\Controllers\EntregaKitController;
use App\Http\Controllers\EstacionController;
use App\Http\Controllers\ResultadoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\KitController;


// ALIAS PARA ADMIN: Para evitar choque de nombres con los de arriba, les damos un alias (As)
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UsuarioController as AdminUsuarioController;
use App\Http\Controllers\Admin\EventoController as AdminEventoController;
use App\Http\Controllers\Admin\KitController as AdminKitController;
use App\Http\Controllers\Admin\CategoriaController as AdminCategoriaController;
use App\Http\Controllers\Admin\PatrocinadorController as AdminPatrocinadorController;
use App\Http\Controllers\Admin\EventoPatrocinadorController as AdminEventoPatroController;
use App\Http\Controllers\Admin\ResultadoController as AdminResultadoController;
use App\Http\Controllers\Admin\InscripcionController as AdminInscripcionController;
use App\Http\Controllers\Admin\PagoController as AdminPagoController;
use App\Http\Controllers\Admin\EntregaKitController as AdminEntregaKitController;
use App\Http\Controllers\Admin\RutaController as AdminRutaController;
use App\Http\Controllers\Admin\EstacionController as AdminEstacionController;
use App\Http\Controllers\Admin\NotificacionController as AdminNotificacionController;
use App\Http\Controllers\Admin\RespaldoController as AdminRespaldoController;

use App\Http\Middleware\CheckRoleContext;


/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS (No requieren token)
|--------------------------------------------------------------------------
*/
// Autenticación
// El tercer parámetro de throttle es el nombre del contador. Sin él, TODAS
// las rutas con throttle compartían un solo contador por IP: 3 mensajes a
// SKAI (que está también en /login) bloqueaban el inicio de sesión.

Route::get('/pagos/{id}/comprobante/archivo', [PagoController::class, 'archivoComprobante'])
    ->name('pagos.comprobante')
    ->middleware('signed');


Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:3,1,login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1,register');
// FIX: sin throttle se podía probar el código de 6 dígitos por fuerza
// bruta y enviar correos de recuperación sin límite (spam al usuario).
Route::post('/enviar-codigo-recuperacion', [AuthController::class, 'enviarCodigoRecuperacion'])->middleware('throttle:3,1,enviar-codigo');
Route::post('/verificar-codigo', [AuthController::class, 'verificarCodigo'])->middleware('throttle:6,1,verificar-codigo');
Route::post('/enviar-codigo-documento', [AuthController::class, 'enviarCodigoPorDocumento'])->middleware('throttle:3,1,enviar-codigo-doc');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1,reset-password');

// Catálogos públicos (Lectura)
Route::get('/eventos', [EventoDeportivoController::class, 'index']);
Route::get('/eventos/{id}', [EventoDeportivoController::class, 'show']);

// Catálogo público de Social (para las páginas de Eventos y Lugares, sin login)
Route::get('/ambientes', [AmbienteController::class, 'index']);
Route::get('/ambientes/{id}', [AmbienteController::class, 'show']);
Route::get('/eventos-sociales', [EventoRealizadoController::class, 'index']);
Route::get('/eventos-sociales/{id}', [EventoRealizadoController::class, 'show']);

// Asistente virtual SKAI (funciona para visitantes sin sesión también).
// Throttle para no dejar la key de Gemini abierta a abuso.
Route::post('/asistente', [SkaiController::class, 'responder'])->middleware('throttle:20,1,skai');


// Testimonios de SkyedSocial (lectura pública).
Route::get('/testimonios', [TestimonioController::class, 'index'])->middleware('throttle:120,1,testimonios');

// Centro de Ayuda (público). Si llega token se reconoce a la persona para
// filtrar por rol; sin token solo se ve el contenido 'publico' ya publicado.
Route::prefix('ayuda')->middleware('throttle:120,1,ayuda')->group(function () {
    Route::get('/', [AyudaController::class, 'inicio']);
    Route::get('/categorias', [AyudaController::class, 'categorias']);
    Route::get('/categorias/{slug}', [AyudaController::class, 'categoria'])->where('slug', '[a-z0-9-]+');
    Route::get('/articulos', [AyudaController::class, 'articulos']);
    Route::get('/articulos/{slug}', [AyudaController::class, 'mostrar'])->where('slug', '[a-z0-9-]+');
    Route::get('/buscar', [AyudaController::class, 'buscar']);

    Route::post('/articulos/{slug}/visita', [AyudaController::class, 'registrarVisita'])
        ->where('slug', '[a-z0-9-]+')
        ->middleware('throttle:60,1,ayuda-visita');
    Route::post('/articulos/{slug}/valoracion', [AyudaController::class, 'valorar'])
        ->where('slug', '[a-z0-9-]+')
        ->middleware(['auth:sanctum', 'throttle:20,1,ayuda-valorar']);

});


/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS (Requieren inicio de sesión)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // ========================================================
    // 1. ZONA GENERAL (Cualquier usuario autenticado)
    // ========================================================
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/cambiar-contrasena', [AuthController::class, 'changePassword']);
    Route::put('/perfil', [AuthController::class, 'updatePerfil']);
    Route::post('/perfil/foto', [AuthController::class, 'updateFoto']);
    Route::post('/verificar-contrasena', [AuthController::class, 'verificarContrasena']);
    Route::put('/cambiar-contrasena', [AuthController::class, 'changePassword']);
    Route::delete('/perfil', [AuthController::class, 'desactivarCuenta']);

    // Gestión de Roles de Usuario
    Route::get('/users/{id}/roles', [UserRoleController::class, 'index'])
        ->middleware('role.context:adminDeportivo|adminSocial');
    Route::post('/users/{id}/roles', [UserRoleController::class, 'store'])
        ->middleware('role.context:adminDeportivo|adminSocial');
    Route::delete('/users/{id}/roles/{rolId}', [UserRoleController::class, 'destroy'])
        ->middleware('role.context:adminDeportivo|adminSocial');

    // Notificaciones
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::get('/notificaciones/{id}', [NotificacionController::class, 'show']);
    Route::patch('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);
    Route::patch('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);

    // Inscripciones (Autoservicio)
    Route::get('/mis-inscripciones', [InscripcionController::class, 'misInscripciones']);
    Route::get('/eventos/{eventoId}/inscripciones', [InscripcionController::class, 'index']);
    Route::post('/eventos/{eventoId}/inscripciones', [InscripcionController::class, 'store']);
    Route::get('/inscripciones/{id}', [InscripcionController::class, 'show']);
    Route::delete('/inscripciones/{id}', [InscripcionController::class, 'destroy']);
    Route::put('/inscripciones/{id}',[InscripcionController::class, 'update']);

    // Historial y Lecturas Generales
    Route::get('/historial-participacion', [HistorialParticipacionController::class, 'index']);
    Route::get('/usuarios/{usuarioId}/historial', [HistorialParticipacionController::class, 'porUsuario']);
    Route::get('/eventos/{eventoId}/categorias', [CategoriaCompetenciaController::class, 'index']);
    Route::get('/categorias/{id}', [CategoriaCompetenciaController::class, 'show']);
    Route::get('/patrocinadores', [PatrocinadorController::class, 'index']);
    Route::get('/patrocinadores/{id}', [PatrocinadorController::class, 'show']);
    Route::get('/eventos/{eventoId}/entregas-kit', [EntregaKitController::class, 'index']);
    Route::get('/entregas-kit/{id}', [EntregaKitController::class, 'show']);
    Route::get('/eventos/{eventoId}/estaciones', [EstacionController::class, 'index']);
    Route::get('/estaciones/{id}', [EstacionController::class, 'show']);
    Route::get('/eventos/{eventoId}/rutas', [RutaEventoController::class, 'index']);
    Route::get('/rutas/{id}', [RutaEventoController::class, 'show']);
    Route::get('/resultados', [ResultadoController::class, 'index']);
    Route::get('/resultados/{id}', [ResultadoController::class, 'show']);
    Route::get('/tipos-evento', [TipoEventoController::class, 'index']);
    Route::get('/tipos-evento/{id}', [TipoEventoController::class, 'show']);
    Route::get('/servicios', [ServicioController::class, 'index']);
    Route::get('/servicios/{id}', [ServicioController::class, 'show']);
    Route::get('/pagos', [PagoController::class, 'index']);
    Route::get('/pagos/{id}', [PagoController::class, 'show']);
    Route::post('/pagos/{id}/comprobante', [PagoController::class, 'subirComprobante']);   
    Route::get('/pagos/{id}/comprobante', [PagoController::class, 'verComprobante']);    
    Route::get('/qr/{id}', [QrEntradaController::class, 'show']);
    Route::get('/premios', [PremioController::class, 'index']);
    Route::get('/premios/{id}', [PremioController::class, 'show']);
    Route::get('/kits', [KitController::class, 'index']);
    Route::get('/kits/{id}', [KitController::class, 'show']);





        // Gestión de reservas y eventos (Administrador Social).
    // Va ANTES de /reservas/{id} para que "resumen" y "calendario" no se lean como un id.
    Route::middleware('role.context:adminSocial')->group(function () {
        Route::get('/reservas/resumen', [ReservaGestionController::class, 'resumen']);
        Route::get('/reservas/calendario', [ReservaGestionController::class, 'calendario']);
        Route::get('/reservas/responsables', [ReservaGestionController::class, 'responsables']);

        Route::post('/reservas/{id}/abrir', [ReservaGestionController::class, 'abrir']);
        Route::put('/reservas/{id}/gestion', [ReservaGestionController::class, 'actualizar']);
        Route::post('/reservas/{id}/etapa', [ReservaGestionController::class, 'cambiarEtapa']);
        Route::post('/reservas/{id}/estado', [ReservaGestionController::class, 'cambiarEstado']);
        Route::post('/reservas/{id}/contacto', [ReservaGestionController::class, 'registrarContacto']);
        Route::post('/reservas/{id}/nota', [ReservaGestionController::class, 'nota']);

        Route::post('/reservas/{id}/servicios', [ReservaGestionController::class, 'agregarServicio']);
        Route::put('/reservas/{id}/servicios/{servicioId}', [ReservaGestionController::class, 'actualizarServicio']);
        Route::delete('/reservas/{id}/servicios/{servicioId}', [ReservaGestionController::class, 'quitarServicio']);
    });



    // Reservas y PQR (Autoservicio)
    Route::get('/reservas', [ReservaController::class, 'index']);
    Route::get('/reservas/{id}', [ReservaController::class, 'show']);
    Route::post('/reservas', [ReservaController::class, 'store']);
    Route::patch('/reservas/{id}/nombre', [ReservaController::class, 'renombrar']);
    Route::post('/reservas/{id}/ocultar', [ReservaController::class, 'ocultar']);
    Route::post('/pqr/{id}/ocultar', [PqrController::class, 'ocultar']);
    Route::post('/reservas/{id}/cancelar', [ReservaController::class, 'cancelar']);

    // Testimonios: solo con una reserva completada, una reseña por reserva.
    Route::get('/reservas/{id}/testimonio', [TestimonioController::class, 'mio']);
    Route::post('/reservas/{id}/testimonio', [TestimonioController::class, 'store'])->middleware('throttle:10,1,testimonio');
    Route::put('/testimonios/{id}', [TestimonioController::class, 'update'])->middleware('throttle:20,1,testimonio-editar');
    Route::delete('/testimonios/{id}', [TestimonioController::class, 'destroy'])->middleware('throttle:20,1,testimonio-borrar');
    Route::get('/pqr', [PqrController::class, 'index']);
    Route::get('/pqr/{id}', [PqrController::class, 'show']);
    Route::post('/pqr', [PqrController::class, 'store']);
    
    Route::post('/pqr/{id}/responder', [PqrController::class, 'responder'])->middleware('throttle:20,1');

    // PQR: gestión del administrador social (el backend valida el flujo).
    Route::post('/pqr/{id}/abrir', [PqrController::class, 'abrir'])
        ->middleware('role.context:adminSocial');
    Route::post('/pqr/{id}/iniciar-gestion', [PqrController::class, 'iniciarGestion'])
        ->middleware(['role.context:adminSocial', 'throttle:30,1']);
    Route::post('/pqr/{id}/resolver', [PqrController::class, 'resolver'])
        ->middleware(['role.context:adminSocial', 'throttle:30,1']);

    // ========================================================
    // 2. ZONA GLOBAL (Cualquier Administrador)
    // ========================================================
    Route::middleware('role.context:adminDeportivo|adminSocial')->group(function () {

        // Roles disponibles (para el panel de administración de usuarios).
        // FIX: vivía en la zona general sin ninguna restricción, aunque
        // el propio test del equipo (tests/Feature/RoleRouteTest.php)
        // espera 403 para un usuario sin rol de admin.
        Route::get('/roles', [RoleController::class, 'index']);
        
        // Centro de Ayuda: administración del contenido. Además de ser admin, cada
        // operación valida el módulo del contenido (User::modulosGestionablesAyuda).
        Route::prefix('ayuda/admin')->group(function () {
            Route::get('/opciones', [AyudaAdminController::class, 'opciones']);

            Route::get('/categorias', [AyudaAdminController::class, 'categorias']);
            Route::post('/categorias', [AyudaAdminController::class, 'guardarCategoria']);
            Route::post('/categorias/orden', [AyudaAdminController::class, 'ordenarCategorias']);
            Route::match(['put', 'patch'], '/categorias/{id}', [AyudaAdminController::class, 'actualizarCategoria']);
            Route::delete('/categorias/{id}', [AyudaAdminController::class, 'eliminarCategoria']);

            Route::get('/articulos', [AyudaAdminController::class, 'articulos']);
            Route::post('/articulos', [AyudaAdminController::class, 'guardarArticulo']);
            Route::post('/articulos/orden', [AyudaAdminController::class, 'ordenarArticulos']);
            Route::get('/articulos/{id}', [AyudaAdminController::class, 'verArticulo']);
            Route::match(['put', 'patch'], '/articulos/{id}', [AyudaAdminController::class, 'actualizarArticulo']);
            Route::delete('/articulos/{id}', [AyudaAdminController::class, 'eliminarArticulo']);
        });

        // Gestión de Usuarios
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // Invitados (acompañantes de una inscripción).
        // FIX: estaban en la zona general, así que cualquier usuario con
        // sesión podía listar, editar y borrar los invitados de TODOS
        // (documento, teléfono, RH, fecha de nacimiento). El participante
        // registra a su invitado dentro de POST /eventos/{id}/inscripciones.
        Route::get('/invitados', [InvitadoController::class, 'index']);
        Route::get('/invitados/{id}', [InvitadoController::class, 'show']);
        Route::post('/invitados', [InvitadoController::class, 'store']);
        Route::put('/invitados/{id}', [InvitadoController::class, 'update']);
        Route::delete('/invitados/{id}', [InvitadoController::class, 'destroy']);

        // Copias de Seguridad
        // (cada admin solo maneja su módulo; el controlador lo verifica)
        Route::get('/copias-seguridad', [CopiaSeguridadController::class, 'index']);
        Route::get('/copias-seguridad/{id}', [CopiaSeguridadController::class, 'show']);
        Route::get('/copias-seguridad/{id}/descargar', [CopiaSeguridadController::class, 'descargar'])->middleware('throttle:30,1,copias-descargar');
        Route::post('/copias-seguridad', [CopiaSeguridadController::class, 'store'])->middleware('throttle:10,1,copias-crear');
        Route::post('/copias-seguridad/{id}/restaurar', [CopiaSeguridadController::class, 'restaurar'])->middleware('throttle:5,1,copias-restaurar');
        Route::delete('/copias-seguridad/{id}', [CopiaSeguridadController::class, 'destroy']);
    });


    // ========================================================
    // 3. ZONA EXCLUSIVA: Administrador Deportivo
    // ========================================================
    Route::middleware('role.context:adminDeportivo')->group(function () {
        
        // Subida de la imagen de un evento deportivo (panel /deportivo/admin)
        Route::post('/deportivo/imagenes', [SocialImagenController::class, 'storeDeportivo']);

        // Eventos
        Route::post('/eventos', [EventoDeportivoController::class, 'store']);
        Route::put('/eventos/{id}', [EventoDeportivoController::class, 'update']);
        Route::patch('/eventos/{id}/estado', [EventoDeportivoController::class, 'cambiarEstado']);
        Route::delete('/eventos/{id}', [EventoDeportivoController::class, 'destroy']);

        // Categorías
        Route::post('/eventos/{eventoId}/categorias', [CategoriaCompetenciaController::class, 'store']);
        Route::put('/categorias/{id}', [CategoriaCompetenciaController::class, 'update']);
        Route::delete('/categorias/{id}', [CategoriaCompetenciaController::class, 'destroy']);

        // Patrocinadores
        Route::post('/patrocinadores', [PatrocinadorController::class, 'store']);
        Route::put('/patrocinadores/{id}', [PatrocinadorController::class, 'update']);
        Route::delete('/patrocinadores/{id}', [PatrocinadorController::class, 'destroy']);
        Route::post('/patrocinadores/{id}/eventos', [PatrocinadorController::class, 'asignarEvento']);

        // Entregas Kit, Historial y Estaciones
        Route::post('/entregas-kit', [EntregaKitController::class, 'store']);
        Route::put('/entregas-kit/{id}', [EntregaKitController::class, 'update']);
        Route::post('/historial-participacion', [HistorialParticipacionController::class, 'store']);
        Route::put('/historial-participacion/{id}', [HistorialParticipacionController::class, 'update']);
        Route::post('/eventos/{eventoId}/estaciones', [EstacionController::class, 'store']);
        Route::put('/estaciones/{id}', [EstacionController::class, 'update']);
        Route::delete('/estaciones/{id}', [EstacionController::class, 'destroy']);

        // Rutas, Resultados y Tipos de Evento
        Route::post('/eventos/{eventoId}/rutas', [RutaEventoController::class, 'store']);
        Route::put('/rutas/{id}', [RutaEventoController::class, 'update']);
        Route::delete('/rutas/{id}', [RutaEventoController::class, 'destroy']);
        Route::post('/resultados', [ResultadoController::class, 'store']);
        Route::put('/resultados/{id}', [ResultadoController::class, 'update']);
        Route::delete('/resultados/{id}', [ResultadoController::class, 'destroy']);
        Route::post('/tipos-evento', [TipoEventoController::class, 'store']);
        Route::put('/tipos-evento/{id}', [TipoEventoController::class, 'update']);
        Route::delete('/tipos-evento/{id}', [TipoEventoController::class, 'destroy']);

        // Pagos, Validación QR, Premios y Kits
        Route::patch('/pagos/{id}/estado', [PagoController::class, 'cambiarEstado']);
        Route::post('/qr/validar', [QrEntradaController::class, 'validar']);
        Route::post('/premios', [PremioController::class, 'store']);
        Route::put('/premios/{id}', [PremioController::class, 'update']);
        Route::delete('/premios/{id}', [PremioController::class, 'destroy']);
        Route::post('/kits', [KitController::class, 'store']);
        Route::put('/kits/{id}', [KitController::class, 'update']);
        Route::delete('/kits/{id}', [KitController::class, 'destroy']);
    });


    // ========================================================
    // 4. ZONA EXCLUSIVA: Administrador Social
    // ========================================================
    Route::middleware('role.context:adminSocial')->group(function () {
        
        // Ambientes y Servicios
        Route::post('/ambientes', [AmbienteController::class, 'store']);
        Route::put('/ambientes/{id}', [AmbienteController::class, 'update']);
        Route::delete('/ambientes/{id}', [AmbienteController::class, 'destroy']);
        Route::post('/ambientes/{id}/servicios', [AmbienteController::class, 'asignarServicio']);
        Route::post('/servicios', [ServicioController::class, 'store']);
        Route::put('/servicios/{id}', [ServicioController::class, 'update']);
        Route::delete('/servicios/{id}', [ServicioController::class, 'destroy']);

        // Subida de imágenes (ambientes/eventos) usada por los formularios del panel
        Route::post('/social/imagenes', [SocialImagenController::class, 'store']);

        // Eventos Sociales
        Route::post('/eventos-sociales', [EventoRealizadoController::class, 'store']);
        Route::put('/eventos-sociales/{id}', [EventoRealizadoController::class, 'update']);
        Route::patch('/eventos-sociales/{id}/estado', [EventoRealizadoController::class, 'cambiarEstado']);
        Route::delete('/eventos-sociales/{id}', [EventoRealizadoController::class, 'destroy']);

        // Reservas (Gestión) y PQR (Respuesta)
        Route::put('/reservas/{id}', [ReservaController::class, 'update']);
        Route::delete('/reservas/{id}', [ReservaController::class, 'destroy']);
        Route::post('/reservas/{id}/seguimiento', [ReservaController::class, 'seguimiento']);

        // PQR: solo adminSocial gestiona. abrir / iniciar-gestion / resolver
        // están arriba, junto a las demás rutas de PQR (con su propio límite).
        Route::put('/pqr/{id}', [PqrController::class, 'update']);
        Route::post('/pqr/{id}/seguimiento', [PqrController::class, 'seguimiento']);
    });

});