<?php

use App\Models\AyudaArticulo;
use App\Models\AyudaCategoria;
use App\Models\AyudaValoracion;
use App\Models\Role;
use App\Models\User;

function adminAyudaCat(string $modulo = 'social', array $extra = []): AyudaCategoria
{
    static $n = 0;
    $n++;

    return AyudaCategoria::create(array_merge([
        'nombre_ayc' => "Categoría admin $n",
        'slug_ayc' => "cat-admin-$n",
        'modulo_ayc' => $modulo,
        'orden_ayc' => $n,
        'activa_ayc' => true,
    ], $extra));
}

function adminAyudaArt(AyudaCategoria $cat, string $modulo = 'social', array $extra = []): AyudaArticulo
{
    static $n = 0;
    $n++;

    return AyudaArticulo::create(array_merge([
        'id_ayc' => $cat->id_ayc,
        'titulo_aya' => "Título base $n",
        'slug_aya' => "titulo-base-$n",
        'contenido_aya' => 'Contenido',
        'modulo_aya' => $modulo,
        'audiencia_aya' => 'publico',
        'estado_aya' => 'publicado',
    ], $extra));
}

function adminAyudaSuper(): User
{
    $user = userWithRole('adminSocial', 'social');
    $rol = Role::firstOrCreate(['nombre_rol' => 'adminDeportivo']);
    $user->roles()->attach($rol->id_rol, ['contexto' => 'deportivo']);

    return $user;
}

function datosArticuloAyuda(AyudaCategoria $cat, array $extra = []): array
{
    return array_merge([
        'categoria_id' => $cat->id_ayc,
        'titulo' => '¿Cómo cancelo un pedido?',
        'contenido' => "1. Entra.\n2. Cancela.",
        'modulo' => 'social',
        'audiencia' => 'publico',
        'estado' => 'borrador',
    ], $extra);
}

const RUTA_AYUDA_ADMIN = '/api/ayuda/admin';

it('solo los administradores entran a la administración de ayuda', function () {
    $this->getJson(RUTA_AYUDA_ADMIN . '/articulos')->assertStatus(401);

    $this->actingAs(userWithRole('cliente', 'social'))
        ->getJson(RUTA_AYUDA_ADMIN . '/articulos')
        ->assertStatus(403);
});

it('un admin social crea un artículo y se genera su dirección', function () {
    $cat = adminAyudaCat('social');
    $admin = userWithRole('adminSocial', 'social');

    $this->actingAs($admin)
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos', datosArticuloAyuda($cat))
        ->assertCreated()
        ->assertJsonPath('articulo.slug', 'como-cancelo-un-pedido')
        ->assertJsonPath('articulo.estado', 'borrador');

    expect(AyudaArticulo::first()->id_autor)->toBe($admin->getKey());
});

it('si la dirección ya existe agrega un número', function () {
    $cat = adminAyudaCat('social');
    $admin = userWithRole('adminSocial', 'social');

    $this->actingAs($admin)->postJson(RUTA_AYUDA_ADMIN . '/articulos', datosArticuloAyuda($cat))->assertCreated();

    $this->actingAs($admin)
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos', datosArticuloAyuda($cat))
        ->assertCreated()
        ->assertJsonPath('articulo.slug', 'como-cancelo-un-pedido-2');
});

it('valida los datos al crear', function () {
    $this->actingAs(userWithRole('adminSocial', 'social'))
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['categoria_id', 'titulo', 'contenido', 'modulo', 'audiencia', 'estado']);
});

it('un admin social no puede crear contenido deportivo ni general', function () {
    $deportivo = adminAyudaCat('deportivo');
    $general = adminAyudaCat('general');
    $admin = userWithRole('adminSocial', 'social');

    $this->actingAs($admin)
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos', datosArticuloAyuda($deportivo, ['modulo' => 'deportivo']))
        ->assertStatus(403);

    $this->actingAs($admin)
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos', datosArticuloAyuda($general, ['modulo' => 'general']))
        ->assertStatus(403);

    expect(AyudaArticulo::count())->toBe(0);
});

it('el super administrador sí puede crear contenido general', function () {
    $general = adminAyudaCat('general');

    $this->actingAs(adminAyudaSuper())
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos', datosArticuloAyuda($general, ['modulo' => 'general']))
        ->assertCreated();
});

it('rechaza un artículo en una categoría de otro módulo', function () {
    $deportivo = adminAyudaCat('deportivo');

    $this->actingAs(userWithRole('adminSocial', 'social'))
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos', datosArticuloAyuda($deportivo, ['modulo' => 'social']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('categoria_id');
});

it('un borrador no se ve hasta publicarlo y deja de verse al desactivarlo', function () {
    $cat = adminAyudaCat('social');
    $admin = userWithRole('adminSocial', 'social');

    $id = $this->actingAs($admin)
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos', datosArticuloAyuda($cat))
        ->json('articulo.id');

    $this->getJson('/api/ayuda/articulos/como-cancelo-un-pedido')->assertStatus(404);

    $this->actingAs($admin)
        ->patchJson(RUTA_AYUDA_ADMIN . "/articulos/$id", ['estado' => 'publicado'])
        ->assertOk();
    $this->getJson('/api/ayuda/articulos/como-cancelo-un-pedido')->assertOk();

    $this->actingAs($admin)
        ->patchJson(RUTA_AYUDA_ADMIN . "/articulos/$id", ['estado' => 'desactivado'])
        ->assertOk();
    $this->getJson('/api/ayuda/articulos/como-cancelo-un-pedido')->assertStatus(404);
});

it('un admin social no ve ni edita artículos de deportivo', function () {
    $cat = adminAyudaCat('deportivo');
    $art = adminAyudaArt($cat, 'deportivo');
    $admin = userWithRole('adminSocial', 'social');

    $this->actingAs($admin)->getJson(RUTA_AYUDA_ADMIN . '/articulos')
        ->assertOk()->assertJsonCount(0, 'articulos');

    $this->actingAs($admin)->getJson(RUTA_AYUDA_ADMIN . "/articulos/{$art->id_aya}")
        ->assertStatus(403);

    $this->actingAs($admin)
        ->patchJson(RUTA_AYUDA_ADMIN . "/articulos/{$art->id_aya}", ['titulo' => 'Hackeado'])
        ->assertStatus(403);

    expect($art->fresh()->titulo_aya)->not->toBe('Hackeado');
});

it('no permite pasar un artículo a un módulo que no gestiona', function () {
    $cat = adminAyudaCat('social');
    $art = adminAyudaArt($cat, 'social');

    $this->actingAs(userWithRole('adminSocial', 'social'))
        ->patchJson(RUTA_AYUDA_ADMIN . "/articulos/{$art->id_aya}", ['modulo' => 'deportivo'])
        ->assertStatus(403);

    expect($art->fresh()->modulo_aya)->toBe('social');
});

it('actualiza solo los campos enviados', function () {
    $cat = adminAyudaCat('social');
    $art = adminAyudaArt($cat, 'social', ['titulo_aya' => 'Original']);

    $this->actingAs(userWithRole('adminSocial', 'social'))
        ->patchJson(RUTA_AYUDA_ADMIN . "/articulos/{$art->id_aya}", ['destacado' => true])
        ->assertOk()
        ->assertJsonPath('articulo.destacado', true)
        ->assertJsonPath('articulo.titulo', 'Original');
});

it('no elimina artículos publicados; sí los desactivados, con sus valoraciones', function () {
    $cat = adminAyudaCat('social');
    $art = adminAyudaArt($cat, 'social');
    $lector = User::factory()->create();
    AyudaValoracion::create(['id_aya' => $art->id_aya, 'id_u' => $lector->getKey(), 'util_ayv' => true]);
    $admin = userWithRole('adminSocial', 'social');

    $this->actingAs($admin)
        ->deleteJson(RUTA_AYUDA_ADMIN . "/articulos/{$art->id_aya}")
        ->assertStatus(422);

    $this->actingAs($admin)
        ->patchJson(RUTA_AYUDA_ADMIN . "/articulos/{$art->id_aya}", ['estado' => 'desactivado'])
        ->assertOk();

    $this->actingAs($admin)
        ->deleteJson(RUTA_AYUDA_ADMIN . "/articulos/{$art->id_aya}")
        ->assertOk();

    expect(AyudaArticulo::count())->toBe(0)
        ->and(AyudaValoracion::count())->toBe(0);
});

it('reordena artículos', function () {
    $cat = adminAyudaCat('social');
    $a = adminAyudaArt($cat, 'social', ['orden_aya' => 5]);
    $b = adminAyudaArt($cat, 'social', ['orden_aya' => 5]);

    $this->actingAs(userWithRole('adminSocial', 'social'))
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos/orden', ['ids' => [$b->id_aya, $a->id_aya]])
        ->assertOk();

    expect($b->fresh()->orden_aya)->toBe(1)
        ->and($a->fresh()->orden_aya)->toBe(2);
});

it('no reordena si alguno es de otro módulo', function () {
    $social = adminAyudaArt(adminAyudaCat('social'), 'social', ['orden_aya' => 7]);
    $deportivo = adminAyudaArt(adminAyudaCat('deportivo'), 'deportivo', ['orden_aya' => 7]);

    $this->actingAs(userWithRole('adminSocial', 'social'))
        ->postJson(RUTA_AYUDA_ADMIN . '/articulos/orden', ['ids' => [$deportivo->id_aya, $social->id_aya]])
        ->assertStatus(403);

    expect($social->fresh()->orden_aya)->toBe(7);
});

it('crea, edita y elimina categorías de su módulo', function () {
    $admin = userWithRole('adminSocial', 'social');

    $id = $this->actingAs($admin)
        ->postJson(RUTA_AYUDA_ADMIN . '/categorias', ['nombre' => 'Pagos y facturas', 'modulo' => 'social'])
        ->assertCreated()
        ->assertJsonPath('categoria.slug', 'pagos-y-facturas')
        ->assertJsonPath('categoria.icono', 'ti-help-circle')
        ->json('categoria.id');

    $this->actingAs($admin)
        ->patchJson(RUTA_AYUDA_ADMIN . "/categorias/$id", ['activa' => false])
        ->assertOk()
        ->assertJsonPath('categoria.activa', false);

    $this->actingAs($admin)
        ->deleteJson(RUTA_AYUDA_ADMIN . "/categorias/$id")
        ->assertOk();

    expect(AyudaCategoria::count())->toBe(0);
});

it('no crea categorías de otro módulo', function () {
    $this->actingAs(userWithRole('adminSocial', 'social'))
        ->postJson(RUTA_AYUDA_ADMIN . '/categorias', ['nombre' => 'Intrusa', 'modulo' => 'general'])
        ->assertStatus(403);
});

it('no elimina ni cambia de módulo una categoría con artículos', function () {
    $cat = adminAyudaCat('social');
    adminAyudaArt($cat, 'social');
    $admin = userWithRole('adminSocial', 'social');

    $this->actingAs($admin)
        ->deleteJson(RUTA_AYUDA_ADMIN . "/categorias/{$cat->id_ayc}")
        ->assertStatus(422);

    $this->actingAs(adminAyudaSuper())
        ->patchJson(RUTA_AYUDA_ADMIN . "/categorias/{$cat->id_ayc}", ['modulo' => 'general'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('modulo');
});

it('las opciones muestran los módulos y categorías que puede usar', function () {
    adminAyudaCat('social');
    adminAyudaCat('deportivo');
    $general = adminAyudaCat('general');

    $res = $this->actingAs(userWithRole('adminSocial', 'social'))
        ->getJson(RUTA_AYUDA_ADMIN . '/opciones')
        ->assertOk()
        ->assertJsonPath('modulos', ['social']);

    $categorias = collect($res->json('categorias'));
    expect($categorias->pluck('modulo')->all())->not->toContain('deportivo')
        ->and($categorias->firstWhere('id', $general->id_ayc)['gestionable'])->toBeFalse();
});

it('el detalle muestra los comentarios sin datos personales', function () {
    $cat = adminAyudaCat('social');
    $art = adminAyudaArt($cat, 'social');
    $lector = User::factory()->create();
    AyudaValoracion::create([
        'id_aya' => $art->id_aya, 'id_u' => $lector->getKey(),
        'util_ayv' => false, 'comentario_ayv' => 'Faltó el paso del pago',
    ]);

    $this->actingAs(userWithRole('adminSocial', 'social'))
        ->getJson(RUTA_AYUDA_ADMIN . "/articulos/{$art->id_aya}")
        ->assertOk()
        ->assertJsonPath('articulo.no_utiles', 1)
        ->assertJsonPath('comentarios.0.comentario', 'Faltó el paso del pago')
        ->assertJsonMissingPath('comentarios.0.id_u');
});