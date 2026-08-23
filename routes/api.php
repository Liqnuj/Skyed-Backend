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

<<<<<<< Updated upstream

Route::post('/login', [AuthController::class, 'login']);
=======
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

>>>>>>> Stashed changes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/cambiar-contrasena', [AuthController::class, 'changePassword']);

<<<<<<< Updated upstream
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
        // Eventos deportivos
=======
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
>>>>>>> Stashed changes
    Route::get('/eventos', [EventoDeportivoController::class, 'index']);
    Route::get('/eventos/{id}', [EventoDeportivoController::class, 'show']);
    Route::post(
    '/eventos',
    [EventoDeportivoController::class, 'store']
)->middleware('role.context:Administrador,deportivo');
    Route::put('/eventos/{id}', [EventoDeportivoController::class, 'update']);
    Route::patch(
    '/eventos/{id}/estado',
    [EventoDeportivoController::class, 'cambiarEstado']
)->middleware('role.context:Administrador,deportivo');
    Route::delete(
    '/eventos/{id}',
    [EventoDeportivoController::class, 'destroy']
)->middleware('role.context:Administrador,deportivo');
    Route::get('/eventos/{eventoId}/categorias', [CategoriaCompetenciaController::class, 'index']);
    Route::post('/eventos/{eventoId}/categorias', [CategoriaCompetenciaController::class, 'store']);

    Route::get('/categorias/{id}', [CategoriaCompetenciaController::class, 'show']);
    Route::put('/categorias/{id}', [CategoriaCompetenciaController::class, 'update']);
    Route::delete('/categorias/{id}', [CategoriaCompetenciaController::class, 'destroy']);
    Route::get('/patrocinadores', [PatrocinadorController::class, 'index']);
    Route::get('/patrocinadores/{id}', [PatrocinadorController::class, 'show']);
    Route::post('/patrocinadores', [PatrocinadorController::class, 'store']);
    Route::put('/patrocinadores/{id}', [PatrocinadorController::class, 'update']);
    Route::delete('/patrocinadores/{id}', [PatrocinadorController::class, 'destroy']);

    Route::post(
        '/patrocinadores/{id}/eventos',
        [PatrocinadorController::class, 'asignarEvento']
    );
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
    Route::get(
    '/eventos/{eventoId}/entregas-kit',
    [EntregaKitController::class, 'index']
    );

    Route::post(
        '/entregas-kit',
        [EntregaKitController::class, 'store']
    );

    Route::get(
        '/entregas-kit/{id}',
        [EntregaKitController::class, 'show']
    );

    Route::put(
        '/entregas-kit/{id}',
        [EntregaKitController::class, 'update']
    );
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
    );

    Route::put(
        '/historial-participacion/{id}',
        [HistorialParticipacionController::class, 'update']
    );
    Route::get(
    '/eventos/{eventoId}/estaciones',
    [EstacionController::class, 'index']
    );

    Route::post(
        '/eventos/{eventoId}/estaciones',
        [EstacionController::class, 'store']
    );

    Route::get(
        '/estaciones/{id}',
        [EstacionController::class, 'show']
    );

    Route::put(
        '/estaciones/{id}',
        [EstacionController::class, 'update']
    );

    Route::delete(
        '/estaciones/{id}',
        [EstacionController::class, 'destroy']
    );

    Route::get(
        '/eventos/{eventoId}/rutas',
        [RutaEventoController::class, 'index']
    );

    Route::post(
        '/eventos/{eventoId}/rutas',
        [RutaEventoController::class, 'store']
    );

    Route::get(
        '/rutas/{id}',
        [RutaEventoController::class, 'show']
    );

    Route::put(
        '/rutas/{id}',
        [RutaEventoController::class, 'update']
    );

    Route::delete(
        '/rutas/{id}',
        [RutaEventoController::class, 'destroy']
    );
    Route::get('/resultados', [ResultadoController::class, 'index']);
    Route::get('/resultados/{id}', [ResultadoController::class, 'show']);
    Route::post('/resultados', [ResultadoController::class, 'store']);
    Route::put('/resultados/{id}', [ResultadoController::class, 'update']);
    Route::delete('/resultados/{id}', [ResultadoController::class, 'destroy']);
    Route::get('/tipos-evento', [TipoEventoController::class, 'index']);
    Route::get('/tipos-evento/{id}', [TipoEventoController::class, 'show']);
    Route::post('/tipos-evento', [TipoEventoController::class, 'store']);
    Route::put('/tipos-evento/{id}', [TipoEventoController::class, 'update']);
    Route::delete('/tipos-evento/{id}', [TipoEventoController::class, 'destroy']);

    Route::get('/ambientes', [AmbienteController::class, 'index']);
    Route::get('/ambientes/{id}', [AmbienteController::class, 'show']);
    Route::post('/ambientes', [AmbienteController::class, 'store']);
    Route::put('/ambientes/{id}', [AmbienteController::class, 'update']);
    Route::delete('/ambientes/{id}', [AmbienteController::class, 'destroy']);
    Route::post('/ambientes/{id}/servicios', [AmbienteController::class, 'asignarServicio']);

    Route::get('/servicios', [ServicioController::class, 'index']);
    Route::get('/servicios/{id}', [ServicioController::class, 'show']);
    Route::post('/servicios', [ServicioController::class, 'store']);
    Route::put('/servicios/{id}', [ServicioController::class, 'update']);
    Route::delete('/servicios/{id}', [ServicioController::class, 'destroy']);
    Route::get('/eventos-sociales', [EventoRealizadoController::class, 'index']);
    Route::get('/eventos-sociales/{id}', [EventoRealizadoController::class, 'show']);
    Route::post('/eventos-sociales', [EventoRealizadoController::class, 'store']);
    Route::put('/eventos-sociales/{id}', [EventoRealizadoController::class, 'update']);
    Route::delete('/eventos-sociales/{id}', [EventoRealizadoController::class, 'destroy']);

    Route::get('/reservas', [ReservaController::class, 'index']);
    Route::get('/reservas/{id}', [ReservaController::class, 'show']);
    Route::post('/reservas', [ReservaController::class, 'store']);
    Route::put('/reservas/{id}', [ReservaController::class, 'update']);
    Route::delete('/reservas/{id}', [ReservaController::class, 'destroy']);
    Route::post('/reservas/{id}/seguimiento', [ReservaController::class, 'seguimiento']);
    Route::get('/pqr', [PqrController::class, 'index']);
    Route::get('/pqr/{id}', [PqrController::class, 'show']);
    Route::post('/pqr', [PqrController::class, 'store']);
    Route::put('/pqr/{id}', [PqrController::class, 'update']);
    Route::get('/copias-seguridad', [CopiaSeguridadController::class, 'index']);
    Route::get('/copias-seguridad/{id}', [CopiaSeguridadController::class, 'show']);
    Route::post('/copias-seguridad', [CopiaSeguridadController::class, 'store']);
    Route::get('/pagos', [PagoController::class, 'index']);

    Route::get('/pagos/{id}', [PagoController::class, 'show']);

    Route::patch(
        '/pagos/{id}/estado',
        [PagoController::class, 'cambiarEstado']
    );  
    Route::get(
        '/qr/{id}',
        [QrEntradaController::class, 'show']
    );

    Route::post(
        '/qr/validar',
        [QrEntradaController::class, 'validar']
    );
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