<?php

use App\Models\CopiaSeguridad;
use App\Models\Pqr;
use App\Models\User;
use App\Support\CatalogoCopias;
use App\Support\GestorCopias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function superAdminDeCopias(): User
{
    $usuario = userWithRole('adminSocial', 'social');
    $rol = \App\Models\Role::firstOrCreate(['nombre_rol' => 'adminDeportivo']);
    $usuario->roles()->attach($rol->id_rol, ['contexto' => 'deportivo']);

    return $usuario;
}

function pqrDePrueba(User $usuario, string $mensaje = 'Mensaje original'): Pqr
{
    return Pqr::forceCreate([
        'id_u' => $usuario->id_u,
        'tipo_pqr' => 'peticion',
        'asunto_pqr' => 'Asunto',
        'mensaje_pqr' => $mensaje,
        'estado_pqr' => 'abierto',
    ]);
}

/** El contenido de un archivo de la copia, leído del .zip guardado. */
function archivoDeCopia(CopiaSeguridad $copia, string $entrada): ?string
{
    $temporal = tempnam(sys_get_temp_dir(), 'prueba');
    file_put_contents($temporal, Storage::disk('local')->get($copia->archivo_cs));
    $zip = new ZipArchive();
    $zip->open($temporal);
    $contenido = $zip->getFromName($entrada);
    $zip->close();
    @unlink($temporal);

    return $contenido === false ? null : $contenido;
}

it('tiene clasificadas todas las tablas de la base de datos', function () {
    $clasificadas = array_merge(CatalogoCopias::tablasDe(array_keys(CatalogoCopias::conjuntos())), CatalogoCopias::TABLAS_EXCLUIDAS);
    $sinClasificar = array_diff(array_column(Schema::getTables(), 'name'), $clasificadas);

    expect(array_values($sinClasificar))->toBe([]);
});

it('muestra a cada administrador solo lo de su módulo', function () {
    $grupos = fn (User $u) => collect($this->actingAs($u, 'sanctum')->getJson('/api/copias-seguridad')->assertOk()->json('grupos'))->pluck('clave')->all();

    expect($grupos(userWithRole('adminSocial', 'social')))->toBe(['social'])
        ->and($grupos(userWithRole('adminDeportivo', 'deportivo')))->toBe(['deportivo'])
        ->and($grupos(superAdminDeCopias()))->toBe(['deportivo', 'social', 'sistema']);
});

it('no deja entrar a quien no es administrador', function () {
    $this->actingAs(userWithRole('cliente', 'social'), 'sanctum')->getJson('/api/copias-seguridad')->assertStatus(403);
    $this->postJson('/api/copias-seguridad', ['conjuntos' => ['pqr']])->assertStatus(403);
});

it('no deja respaldar datos de otro módulo ni del sistema', function () {
    $this->actingAs(userWithRole('adminSocial', 'social'), 'sanctum');

    $this->postJson('/api/copias-seguridad', ['conjuntos' => ['inscripciones']])->assertStatus(403);
    $this->postJson('/api/copias-seguridad', ['conjuntos' => ['pqr', 'usuarios']])->assertStatus(403);
    $this->postJson('/api/copias-seguridad', ['conjuntos' => ['tabla_inventada']])->assertStatus(422);
    $this->postJson('/api/copias-seguridad', ['conjuntos' => []])->assertStatus(422);

    expect(CopiaSeguridad::count())->toBe(0);
});

it('crea la copia con JSON y CSV, y deja su registro', function () {
    $admin = userWithRole('adminSocial', 'social');
    pqrDePrueba($admin, 'Una petición con tildes y ñ');

    $respuesta = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/copias-seguridad', ['conjuntos' => ['pqr']])
        ->assertCreated()
        ->assertJsonPath('copia.alcance', 'social')
        ->assertJsonPath('copia.conjuntos', ['PQRS'])
        ->assertJsonPath('copia.registros', 1);

    $copia = CopiaSeguridad::find($respuesta->json('copia.id'));

    Storage::disk('local')->assertExists($copia->archivo_cs);
    expect($copia->id_u)->toBe($admin->id_u)
        ->and(json_decode(archivoDeCopia($copia, 'datos/pqr.json'), true)[0]['mensaje_pqr'])->toBe('Una petición con tildes y ñ')
        ->and(archivoDeCopia($copia, 'excel/pqr.csv'))->toContain('Una petición con tildes y ñ')
        ->and(archivoDeCopia($copia, 'manifiesto.json'))->toContain('"alcance": "social"');
});

it('nunca incluye contraseñas ni códigos de recuperación', function () {
    $super = superAdminDeCopias();
    $super->forceFill(['contrasena_u' => 'HASH-SECRETO', 'codigo' => '123456'])->save();

    $copia = GestorCopias::crear(['usuarios'], $super);
    $usuarios = archivoDeCopia($copia, 'datos/usuario.json');

    expect($usuarios)->not->toContain('HASH-SECRETO')
        ->and($usuarios)->not->toContain('contrasena_u')
        ->and($usuarios)->not->toContain('123456')
        ->and($usuarios)->toContain($super->correo_u)
        ->and(archivoDeCopia($copia, 'excel/usuario.csv'))->not->toContain('contrasena_u');
});

it('evita que un texto se ejecute como fórmula al abrir el CSV en Excel', function () {
    $admin = userWithRole('adminSocial', 'social');
    pqrDePrueba($admin, '=HYPERLINK("http://malo.test")');

    $copia = GestorCopias::crear(['pqr'], $admin);

    expect(archivoDeCopia($copia, 'excel/pqr.csv'))->toContain("'=HYPERLINK");
});

it('solo deja descargar, ver o eliminar las copias del propio módulo', function () {
    $social = userWithRole('adminSocial', 'social');
    $deportivo = userWithRole('adminDeportivo', 'deportivo');
    $copia = GestorCopias::crear(['pqr'], $social);

    $this->actingAs($deportivo, 'sanctum');
    expect($this->getJson('/api/copias-seguridad')->json('copias'))->toBe([]);
    $this->getJson("/api/copias-seguridad/{$copia->id_cs}/descargar")->assertNotFound();
    $this->deleteJson("/api/copias-seguridad/{$copia->id_cs}")->assertNotFound();
    $this->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'RESTAURAR'])->assertNotFound();

    $this->actingAs($social, 'sanctum');
    expect($this->getJson('/api/copias-seguridad')->json('copias'))->toHaveCount(1);
    $this->getJson("/api/copias-seguridad/{$copia->id_cs}/descargar")->assertOk();
});

it('un admin de módulo no ve una copia que incluya datos de otro módulo', function () {
    $super = superAdminDeCopias();
    GestorCopias::crear(['pqr', 'inscripciones'], $super);

    $this->actingAs(userWithRole('adminSocial', 'social'), 'sanctum');
    expect($this->getJson('/api/copias-seguridad')->json('copias'))->toBe([]);

    $this->actingAs($super, 'sanctum');
    expect($this->getJson('/api/copias-seguridad')->json('copias.0.alcance'))->toBe('general');
});

it('elimina la copia y su archivo', function () {
    $admin = userWithRole('adminSocial', 'social');
    $copia = GestorCopias::crear(['pqr'], $admin);

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/copias-seguridad/{$copia->id_cs}")->assertOk();

    Storage::disk('local')->assertMissing($copia->archivo_cs);
    expect(CopiaSeguridad::find($copia->id_cs))->toBeNull();
});

it('restaura lo borrado y lo cambiado, sin tocar lo creado después', function () {
    $admin = userWithRole('adminSocial', 'social');
    $borrada = pqrDePrueba($admin, 'Se va a borrar');
    $cambiada = pqrDePrueba($admin, 'Texto original');

    $copia = GestorCopias::crear(['pqr'], $admin);

    $borrada->delete();
    $cambiada->update(['mensaje_pqr' => 'Texto cambiado por error']);
    $nueva = pqrDePrueba($admin, 'Creada después de la copia');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'RESTAURAR'])
        ->assertOk()
        ->assertJsonPath('registros', 2);

    expect(Pqr::find($borrada->id_pqr)?->mensaje_pqr)->toBe('Se va a borrar')
        ->and(Pqr::find($cambiada->id_pqr)->mensaje_pqr)->toBe('Texto original')
        ->and(Pqr::find($nueva->id_pqr)?->mensaje_pqr)->toBe('Creada después de la copia');
});

it('restaura una copia hecha cuando la tabla tenía otras columnas', function () {
    $admin = userWithRole('adminSocial', 'social');
    $pqr = pqrDePrueba($admin, 'Copia antigua');
    $copia = GestorCopias::crear(['pqr'], $admin);

    // Una copia vieja: no trae una columna que se agregó después y trae una que ya no existe.
    $filas = json_decode(archivoDeCopia($copia, 'datos/pqr.json'), true);
    $filas = array_map(function (array $fila) {
        unset($fila['respuesta_pqr']);

        return $fila + ['columna_eliminada' => 'x'];
    }, $filas);

    $temporal = tempnam(sys_get_temp_dir(), 'prueba');
    file_put_contents($temporal, Storage::disk('local')->get($copia->archivo_cs));
    $zip = new ZipArchive();
    $zip->open($temporal);
    $zip->addFromString('datos/pqr.json', json_encode($filas));
    $zip->close();
    $contenido = file_get_contents($temporal);
    @unlink($temporal);
    Storage::disk('local')->put($copia->archivo_cs, $contenido);
    $copia->update(['checksum_cs' => hash('sha256', $contenido)]);

    $pqr->delete();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'RESTAURAR'])
        ->assertOk();

    expect(Pqr::find($pqr->id_pqr)?->mensaje_pqr)->toBe('Copia antigua');
});

it('antes de restaurar guarda una copia del estado actual', function () {
    $admin = userWithRole('adminSocial', 'social');
    pqrDePrueba($admin);
    $copia = GestorCopias::crear(['pqr'], $admin);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'RESTAURAR'])
        ->assertOk()
        ->assertJsonPath('copia_previa.origen', 'previa_restauracion');

    expect(CopiaSeguridad::where('origen_cs', 'previa_restauracion')->count())->toBe(1);
});

it('pide confirmación escrita para restaurar', function () {
    $admin = userWithRole('adminSocial', 'social');
    $copia = GestorCopias::crear(['pqr'], $admin);

    $this->actingAs($admin, 'sanctum');
    $this->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar")->assertStatus(422);
    $this->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'si'])->assertStatus(422);
});

it('no restaura un archivo que fue modificado', function () {
    $admin = userWithRole('adminSocial', 'social');
    $pqr = pqrDePrueba($admin, 'Original');
    $copia = GestorCopias::crear(['pqr'], $admin);
    $pqr->update(['mensaje_pqr' => 'Actual']);

    Storage::disk('local')->put($copia->archivo_cs, 'archivo alterado');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'RESTAURAR'])
        ->assertStatus(422);

    expect($pqr->fresh()->mensaje_pqr)->toBe('Actual');
});

it('si la restauración falla no aplica ningún cambio', function () {
    $super = superAdminDeCopias();
    $dueno = userWithRole('cliente', 'social');
    $pqr = pqrDePrueba($dueno, 'Original');
    $copia = GestorCopias::crear(['pqr'], $super);

    // La PQR depende de un usuario que ya no existe: restaurarla es imposible.
    $pqr->update(['mensaje_pqr' => 'Actual']);
    $otra = pqrDePrueba($super, 'De otro usuario');
    DB::table('pqr')->where('id_pqr', $pqr->id_pqr)->delete();
    DB::table('usuario')->where('id_u', $dueno->id_u)->delete();

    $this->actingAs($super, 'sanctum')
        ->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'RESTAURAR'])
        ->assertStatus(422);

    expect(Pqr::find($pqr->id_pqr))->toBeNull()
        ->and(Pqr::find($otra->id_pqr)?->mensaje_pqr)->toBe('De otro usuario');
});

it('al restaurar conserva la contraseña de las cuentas que existen', function () {
    $super = superAdminDeCopias();
    $copia = GestorCopias::crear(['usuarios'], $super);
    $hash = DB::table('usuario')->where('id_u', $super->id_u)->value('contrasena_u');

    $this->actingAs($super, 'sanctum')
        ->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'RESTAURAR'])
        ->assertOk();

    expect(DB::table('usuario')->where('id_u', $super->id_u)->value('contrasena_u'))->toBe($hash);
});

it('un usuario borrado vuelve sin poder entrar con ninguna contraseña conocida', function () {
    $super = superAdminDeCopias();
    $otro = userWithRole('cliente', 'social');
    $copia = GestorCopias::crear(['usuarios'], $super);
    $hashAnterior = DB::table('usuario')->where('id_u', $otro->id_u)->value('contrasena_u');

    DB::table('usuario')->where('id_u', $otro->id_u)->delete();

    $this->actingAs($super, 'sanctum')
        ->postJson("/api/copias-seguridad/{$copia->id_cs}/restaurar", ['confirmacion' => 'RESTAURAR'])
        ->assertOk();

    $restaurado = DB::table('usuario')->where('id_u', $otro->id_u)->first();
    expect($restaurado)->not->toBeNull()
        ->and($restaurado->contrasena_u)->not->toBe($hashAnterior);
});

it('el comando crea una copia de todo y conserva solo las automáticas recientes', function () {
    $this->artisan('skyed:copia-seguridad --conservar=2')->assertSuccessful();
    $this->artisan('skyed:copia-seguridad --conservar=2')->assertSuccessful();
    $this->artisan('skyed:copia-seguridad --conservar=2')->assertSuccessful();

    $copias = CopiaSeguridad::where('origen_cs', 'automatica')->get();

    expect($copias)->toHaveCount(2)
        ->and($copias->first()->alcance_cs)->toBe('general')
        ->and(Storage::disk('local')->allFiles('copias-seguridad'))->toHaveCount(2);
});
