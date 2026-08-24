<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\EventoDeportivoController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
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
| Nota sobre protección por rol
|--------------------------------------------------------------------------
| 'role.context:adminDeportivo' y 'role.context:adminSocial'
| se aplican a los recursos administrativos de cada dominio (deportivo vs.
| eventos sociales), siguiendo el mismo patrón ya usado en /eventos.
| Para rutas globales (usuarios, copias de seguridad) se acepta
| cualquiera de los dos con 'role.context:adminDeportivo|adminSocial'
| (requiere el middleware CheckRoleContext actualizado con soporte de '|').
| Las acciones de autoservicio de un usuario autenticado normal
| (inscribirse a un evento, reservar un ambiente, radicar un PQR) se
| dejan solo con 'auth:sanctum', sin restricción de rol.
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/enviar-codigo', [AuthController::class, 'enviarCodigoRecuperacion']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/cambiar-contrasena', [AuthController::class, 'changePassword']);


// Notificaciones
    Route::get(
    '/notificaciones',
    [NotificacionController::class, 'index']
);

Route::get(
    '/notificaciones/{id}',
    [NotificacionController::class, 'show']
);

Route::patch(
    '/notificaciones/{id}/leer',
    [NotificacionController::class, 'marcarLeida']
);

    // Usuarios (gestión global, solo Administrador)
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('role.context:adminDeportivo|adminSocial');
    Route::get('/users/{id}', [UserController::class, 'show'])
        ->middleware('role.context:adminDeportivo|adminSocial');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('role.context:adminDeportivo|adminSocial');
    Route::put('/users/{id}', [UserController::class, 'update'])
        ->middleware('role.context:adminDeportivo|adminSocial');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])
        ->middleware('role.context:adminDeportivo|adminSocial');

    // Eventos deportivos
    Route::get('/eventos', [EventoDeportivoController::class, 'index']);
    Route::get('/eventos/{id}', [EventoDeportivoController::class, 'show']);
    Route::post(
        '/eventos',
        [EventoDeportivoController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::put(
        '/eventos/{id}',
        [EventoDeportivoController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::patch(
        '/eventos/{id}/estado',
        [EventoDeportivoController::class, 'cambiarEstado']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/eventos/{id}',
        [EventoDeportivoController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');

    // Categorías de competencia
    Route::get('/eventos/{eventoId}/categorias', [CategoriaCompetenciaController::class, 'index']);
    Route::post(
        '/eventos/{eventoId}/categorias',
        [CategoriaCompetenciaController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::get('/categorias/{id}', [CategoriaCompetenciaController::class, 'show']);
    Route::put(
        '/categorias/{id}',
        [CategoriaCompetenciaController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/categorias/{id}',
        [CategoriaCompetenciaController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');

    // Patrocinadores
    Route::get('/patrocinadores', [PatrocinadorController::class, 'index']);
    Route::get('/patrocinadores/{id}', [PatrocinadorController::class, 'show']);
    Route::post(
        '/patrocinadores',
        [PatrocinadorController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::put(
        '/patrocinadores/{id}',
        [PatrocinadorController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/patrocinadores/{id}',
        [PatrocinadorController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');
    Route::post(
        '/patrocinadores/{id}/eventos',
        [PatrocinadorController::class, 'asignarEvento']
    )->middleware('role.context:adminDeportivo');

    // Inscripciones (autoservicio del participante para inscribirse/consultar/cancelar)
    Route::get(
        '/eventos/{eventoId}/inscripciones',
        [InscripcionController::class, 'index']
    );
    Route::post(
        '/eventos/{eventoId}/inscripciones',
        [InscripcionController::class, 'store']
    );
    Route::get(
        '/inscripciones/{id}',
        [InscripcionController::class, 'show']
    );
    Route::delete(
        '/inscripciones/{id}',
        [InscripcionController::class, 'destroy']
    );

    // Entrega de kits (labor de staff en punto de entrega)
    Route::get(
        '/eventos/{eventoId}/entregas-kit',
        [EntregaKitController::class, 'index']
    );
    Route::post(
        '/entregas-kit',
        [EntregaKitController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::get(
        '/entregas-kit/{id}',
        [EntregaKitController::class, 'show']
    );
    Route::put(
        '/entregas-kit/{id}',
        [EntregaKitController::class, 'update']
    )->middleware('role.context:adminDeportivo');

    // Historial de participación
    Route::get(
        '/historial-participacion',
        [HistorialParticipacionController::class, 'index']
    );
    Route::get(
        '/usuarios/{usuarioId}/historial',
        [HistorialParticipacionController::class, 'porUsuario']
    );
    Route::post(
        '/historial-participacion',
        [HistorialParticipacionController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::put(
        '/historial-participacion/{id}',
        [HistorialParticipacionController::class, 'update']
    )->middleware('role.context:adminDeportivo');

    // Estaciones
    Route::get(
        '/eventos/{eventoId}/estaciones',
        [EstacionController::class, 'index']
    );
    Route::post(
        '/eventos/{eventoId}/estaciones',
        [EstacionController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::get(
        '/estaciones/{id}',
        [EstacionController::class, 'show']
    );
    Route::put(
        '/estaciones/{id}',
        [EstacionController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/estaciones/{id}',
        [EstacionController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');

    // Rutas de evento
    Route::get(
        '/eventos/{eventoId}/rutas',
        [RutaEventoController::class, 'index']
    );
    Route::post(
        '/eventos/{eventoId}/rutas',
        [RutaEventoController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::get(
        '/rutas/{id}',
        [RutaEventoController::class, 'show']
    );
    Route::put(
        '/rutas/{id}',
        [RutaEventoController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/rutas/{id}',
        [RutaEventoController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');

    // Resultados
    Route::get('/resultados', [ResultadoController::class, 'index']);
    Route::get('/resultados/{id}', [ResultadoController::class, 'show']);
    Route::post(
        '/resultados',
        [ResultadoController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::put(
        '/resultados/{id}',
        [ResultadoController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/resultados/{id}',
        [ResultadoController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');

    // Tipos de evento (catálogo deportivo)
    Route::get('/tipos-evento', [TipoEventoController::class, 'index']);
    Route::get('/tipos-evento/{id}', [TipoEventoController::class, 'show']);
    Route::post(
        '/tipos-evento',
        [TipoEventoController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::put(
        '/tipos-evento/{id}',
        [TipoEventoController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/tipos-evento/{id}',
        [TipoEventoController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');

    // Ambientes (catálogo eventos sociales)
    Route::get('/ambientes', [AmbienteController::class, 'index']);
    Route::get('/ambientes/{id}', [AmbienteController::class, 'show']);
    Route::post(
        '/ambientes',
        [AmbienteController::class, 'store']
    )->middleware('role.context:adminSocial');
    Route::put(
        '/ambientes/{id}',
        [AmbienteController::class, 'update']
    )->middleware('role.context:adminSocial');
    Route::delete(
        '/ambientes/{id}',
        [AmbienteController::class, 'destroy']
    )->middleware('role.context:adminSocial');
    Route::post(
        '/ambientes/{id}/servicios',
        [AmbienteController::class, 'asignarServicio']
    )->middleware('role.context:adminSocial');

    // Servicios (catálogo eventos sociales)
    Route::get('/servicios', [ServicioController::class, 'index']);
    Route::get('/servicios/{id}', [ServicioController::class, 'show']);
    Route::post(
        '/servicios',
        [ServicioController::class, 'store']
    )->middleware('role.context:adminSocial');
    Route::put(
        '/servicios/{id}',
        [ServicioController::class, 'update']
    )->middleware('role.context:adminSocial');
    Route::delete(
        '/servicios/{id}',
        [ServicioController::class, 'destroy']
    )->middleware('role.context:adminSocial');

    // Eventos sociales realizados
    Route::get('/eventos-sociales', [EventoRealizadoController::class, 'index']);
    Route::get('/eventos-sociales/{id}', [EventoRealizadoController::class, 'show']);
    Route::post(
        '/eventos-sociales',
        [EventoRealizadoController::class, 'store']
    )->middleware('role.context:adminSocial');
    Route::put(
        '/eventos-sociales/{id}',
        [EventoRealizadoController::class, 'update']
    )->middleware('role.context:adminSocial');
        Route::patch(
        '/eventos-sociales/{id}/estado',
        [EventoRealizadoController::class, 'cambiarEstado']
    )->middleware('role.context:adminSocial');
    Route::delete(
        '/eventos-sociales/{id}',
        [EventoRealizadoController::class, 'destroy']
    )->middleware('role.context:adminSocial');

    // Reservas (autoservicio del cliente: crear/consultar su reserva)
    Route::get('/reservas', [ReservaController::class, 'index']);
    Route::get('/reservas/{id}', [ReservaController::class, 'show']);
    Route::post('/reservas', [ReservaController::class, 'store']);
    Route::put(
        '/reservas/{id}',
        [ReservaController::class, 'update']
    )->middleware('role.context:adminSocial');
    Route::delete(
        '/reservas/{id}',
        [ReservaController::class, 'destroy']
    )->middleware('role.context:adminSocial');
    Route::post(
        '/reservas/{id}/seguimiento',
        [ReservaController::class, 'seguimiento']
    )->middleware('role.context:adminSocial');

    // PQR (autoservicio: cualquiera radica, solo staff actualiza estado/respuesta)
    Route::get('/pqr', [PqrController::class, 'index']);
    Route::get('/pqr/{id}', [PqrController::class, 'show']);
    Route::post('/pqr', [PqrController::class, 'store']);
    Route::put(
        '/pqr/{id}',
        [PqrController::class, 'update']
    )->middleware('role.context:adminSocial');

    // Copias de seguridad (siempre administrativo, global)
    Route::get(
        '/copias-seguridad',
        [CopiaSeguridadController::class, 'index']
    )->middleware('role.context:adminDeportivo|adminSocial');
    Route::get(
        '/copias-seguridad/{id}',
        [CopiaSeguridadController::class, 'show']
    )->middleware('role.context:adminDeportivo|adminSocial');
    Route::post(
        '/copias-seguridad',
        [CopiaSeguridadController::class, 'store']
    )->middleware('role.context:adminDeportivo|adminSocial');

    // Pagos (cambiar estado es labor de staff/admin deportivo)
    Route::get('/pagos', [PagoController::class, 'index']);
    Route::get('/pagos/{id}', [PagoController::class, 'show']);
    Route::patch(
        '/pagos/{id}/estado',
        [PagoController::class, 'cambiarEstado']
    )->middleware('role.context:adminDeportivo');

    // QR de entrada (validar es labor de staff en el punto de control)
    Route::get('/qr/{id}', [QrEntradaController::class, 'show']);
    Route::post(
        '/qr/validar',
        [QrEntradaController::class, 'validar']
    )->middleware('role.context:adminDeportivo');

    // Premios (catálogo deportivo)
    Route::get('/premios', [PremioController::class, 'index']);
    Route::get('/premios/{id}', [PremioController::class, 'show']);
    Route::post(
        '/premios',
        [PremioController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::put(
        '/premios/{id}',
        [PremioController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/premios/{id}',
        [PremioController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');

    // Invitados (registro de acompañantes vinculados a un usuario)
    Route::get('/invitados', [InvitadoController::class, 'index']);
    Route::get('/invitados/{id}', [InvitadoController::class, 'show']);
    Route::post('/invitados', [InvitadoController::class, 'store']);
    Route::put('/invitados/{id}', [InvitadoController::class, 'update']);
    Route::delete('/invitados/{id}', [InvitadoController::class, 'destroy']);

    // Kits (catálogo deportivo, referenciado por eventos y entregas)
    Route::get('/kits', [KitController::class, 'index']);
    Route::get('/kits/{id}', [KitController::class, 'show']);
    Route::post(
        '/kits',
        [KitController::class, 'store']
    )->middleware('role.context:adminDeportivo');
    Route::put(
        '/kits/{id}',
        [KitController::class, 'update']
    )->middleware('role.context:adminDeportivo');
    Route::delete(
        '/kits/{id}',
        [KitController::class, 'destroy']
    )->middleware('role.context:adminDeportivo');
});
