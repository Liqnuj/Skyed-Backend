<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EventoDeportivoController;
use App\Http\Controllers\CategoriaCompetenciaController;
use App\Http\Controllers\PatrocinadorController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\EntregaKitController;
use App\Http\Controllers\HistorialParticipacionController;
use App\Http\Controllers\EstacionController;
use App\Http\Controllers\RutaEventoController;
use App\Http\Controllers\ResultadoController;
use App\Http\Controllers\TipoEventoController;
use App\Http\Controllers\AmbienteController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\EventoRealizadoController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\PqrController;
use App\Http\Controllers\CopiaSeguridadController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\QrEntradaController;
use App\Http\Controllers\PremioController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\InvitadoController;
use App\Http\Controllers\KitController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS (No requieren token)
|--------------------------------------------------------------------------
*/
// Autenticación
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:3,1');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/enviar-codigo', [AuthController::class, 'enviarCodigoRecuperacion']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');

// Catálogos públicos (Lectura)
Route::get('/eventos', [EventoDeportivoController::class, 'index']);
Route::get('/eventos/{id}', [EventoDeportivoController::class, 'show']);


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

    // Notificaciones
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::get('/notificaciones/{id}', [NotificacionController::class, 'show']);
    Route::patch('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);

    // Inscripciones (Autoservicio)
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
    Route::get('/ambientes', [AmbienteController::class, 'index']);
    Route::get('/ambientes/{id}', [AmbienteController::class, 'show']);
    Route::get('/servicios', [ServicioController::class, 'index']);
    Route::get('/servicios/{id}', [ServicioController::class, 'show']);
    Route::get('/eventos-sociales', [EventoRealizadoController::class, 'index']);
    Route::get('/eventos-sociales/{id}', [EventoRealizadoController::class, 'show']);
    Route::get('/pagos', [PagoController::class, 'index']);
    Route::get('/pagos/{id}', [PagoController::class, 'show']);
    Route::get('/qr/{id}', [QrEntradaController::class, 'show']);
    Route::get('/premios', [PremioController::class, 'index']);
    Route::get('/premios/{id}', [PremioController::class, 'show']);
    Route::get('/kits', [KitController::class, 'index']);
    Route::get('/kits/{id}', [KitController::class, 'show']);

    // Invitados (Autoservicio)
    Route::get('/invitados', [InvitadoController::class, 'index']);
    Route::get('/invitados/{id}', [InvitadoController::class, 'show']);
    Route::post('/invitados', [InvitadoController::class, 'store']);
    Route::put('/invitados/{id}', [InvitadoController::class, 'update']);
    Route::delete('/invitados/{id}', [InvitadoController::class, 'destroy']);

    // Reservas y PQR (Autoservicio)
    Route::get('/reservas', [ReservaController::class, 'index']);
    Route::get('/reservas/{id}', [ReservaController::class, 'show']);
    Route::post('/reservas', [ReservaController::class, 'store']);
    Route::get('/pqr', [PqrController::class, 'index']);
    Route::get('/pqr/{id}', [PqrController::class, 'show']);
    Route::post('/pqr', [PqrController::class, 'store']);


    // ========================================================
    // 2. ZONA GLOBAL (Cualquier Administrador)
    // ========================================================
    Route::middleware('role.context:adminDeportivo|adminSocial')->group(function () {
        
        // Gestión de Usuarios
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // Copias de Seguridad
        Route::get('/copias-seguridad', [CopiaSeguridadController::class, 'index']);
        Route::get('/copias-seguridad/{id}', [CopiaSeguridadController::class, 'show']);
        Route::post('/copias-seguridad', [CopiaSeguridadController::class, 'store']);
    });


    // ========================================================
    // 3. ZONA EXCLUSIVA: Administrador Deportivo
    // ========================================================
    Route::middleware('role.context:adminDeportivo')->group(function () {
        
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

        // Eventos Sociales
        Route::post('/eventos-sociales', [EventoRealizadoController::class, 'store']);
        Route::put('/eventos-sociales/{id}', [EventoRealizadoController::class, 'update']);
        Route::patch('/eventos-sociales/{id}/estado', [EventoRealizadoController::class, 'cambiarEstado']);
        Route::delete('/eventos-sociales/{id}', [EventoRealizadoController::class, 'destroy']);

        // Reservas (Gestión) y PQR (Respuesta)
        Route::put('/reservas/{id}', [ReservaController::class, 'update']);
        Route::delete('/reservas/{id}', [ReservaController::class, 'destroy']);
        Route::post('/reservas/{id}/seguimiento', [ReservaController::class, 'seguimiento']);
        Route::put('/pqr/{id}', [PqrController::class, 'update']);
        
    });

});