<?php

use App\Models\AyudaArticulo;
use App\Models\AyudaCategoria;
use function Pest\Laravel\getJson;

function ayudaCategoria(array $extra = []): AyudaCategoria
{
    static $n = 0;
    $n++;

    return AyudaCategoria::create(array_merge([
        'nombre_ayc' => "Categoría $n",
        'slug_ayc' => "categoria-$n",
        'modulo_ayc' => 'general',
        'orden_ayc' => $n,
        'activa_ayc' => true,
    ], $extra));
}

function ayudaArticulo(AyudaCategoria $cat, array $extra = []): AyudaArticulo
{
    static $n = 0;
    $n++;

    return AyudaArticulo::create(array_merge([
        'id_ayc' => $cat->id_ayc,
        'titulo_aya' => "Artículo $n",
        'slug_aya' => "articulo-$n",
        'resumen_aya' => 'Resumen',
        'contenido_aya' => 'Contenido',
        'modulo_aya' => 'general',
        'audiencia_aya' => 'publico',
        'estado_aya' => 'publicado',
    ], $extra));
}

it('el inicio es público y solo muestra artículos publicados', function () {
    $cat = ayudaCategoria();
    ayudaArticulo($cat, ['slug_aya' => 'visible', 'faq_aya' => true]);
    ayudaArticulo($cat, ['slug_aya' => 'borrador', 'estado_aya' => 'borrador', 'faq_aya' => true]);

    getJson('/api/ayuda')
        ->assertOk()
        ->assertJsonCount(1, 'categorias')
        ->assertJsonPath('categorias.0.total_articulos', 1)
        ->assertJsonCount(1, 'faq')
        ->assertJsonPath('faq.0.slug', 'visible');
});

it('un borrador y un desactivado no se pueden abrir (404)', function () {
    $cat = ayudaCategoria();
    ayudaArticulo($cat, ['slug_aya' => 'es-borrador', 'estado_aya' => 'borrador']);
    ayudaArticulo($cat, ['slug_aya' => 'esta-apagado', 'estado_aya' => 'desactivado']);

    getJson('/api/ayuda/articulos/es-borrador')->assertStatus(404);
    getJson('/api/ayuda/articulos/esta-apagado')->assertStatus(404);
    getJson('/api/ayuda/articulos')->assertOk()->assertJsonCount(0, 'articulos');
});

it('un artículo publicado se abre completo', function () {
    $cat = ayudaCategoria();
    ayudaArticulo($cat, ['slug_aya' => 'abierto', 'contenido_aya' => '1. Paso uno']);

    getJson('/api/ayuda/articulos/abierto')
        ->assertOk()
        ->assertJsonPath('articulo.contenido', '1. Paso uno')
        ->assertJsonPath('articulo.categoria.slug', $cat->slug_ayc);
});

it('la audiencia usuario exige sesión', function () {
    $cat = ayudaCategoria();
    ayudaArticulo($cat, ['slug_aya' => 'solo-usuarios', 'audiencia_aya' => 'usuario']);

    getJson('/api/ayuda/articulos/solo-usuarios')->assertStatus(404);

    $this->actingAs(userWithRole('cliente', 'social'))
        ->getJson('/api/ayuda/articulos/solo-usuarios')
        ->assertOk();
});

it('las guías de admin solo las ve el admin de ese módulo', function () {
    $cat = ayudaCategoria();
    ayudaArticulo($cat, ['slug_aya' => 'guia-social', 'audiencia_aya' => 'admin', 'modulo_aya' => 'social']);
    ayudaArticulo($cat, ['slug_aya' => 'guia-deportivo', 'audiencia_aya' => 'admin', 'modulo_aya' => 'deportivo']);

    $this->actingAs(userWithRole('cliente', 'social'), 'sanctum')
        ->getJson('/api/ayuda/articulos/guia-social')->assertStatus(404);

    $admin = userWithRole('adminSocial', 'social');
    $this->actingAs($admin, 'sanctum')->getJson('/api/ayuda/articulos/guia-social')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/ayuda/articulos/guia-deportivo')->assertStatus(404);
});

it('una categoría inactiva oculta sus artículos', function () {
    $cat = ayudaCategoria(['activa_ayc' => false]);
    ayudaArticulo($cat, ['slug_aya' => 'oculto']);

    getJson('/api/ayuda/articulos/oculto')->assertStatus(404);
    getJson("/api/ayuda/categorias/{$cat->slug_ayc}")->assertStatus(404);
});

it('el filtro de módulo incluye también lo general', function () {
    $cat = ayudaCategoria();
    ayudaArticulo($cat, ['slug_aya' => 'gen', 'modulo_aya' => 'general']);
    ayudaArticulo($cat, ['slug_aya' => 'soc', 'modulo_aya' => 'social']);
    ayudaArticulo($cat, ['slug_aya' => 'dep', 'modulo_aya' => 'deportivo']);

    getJson('/api/ayuda/articulos?modulo=social')
        ->assertOk()
        ->assertJsonCount(2, 'articulos');
});

it('la búsqueda encuentra por título o palabras clave, sin importar tildes', function () {
    $cat = ayudaCategoria();
    ayudaArticulo($cat, [
        'slug_aya' => 'reservar',
        'titulo_aya' => '¿Cómo realizar una reserva?',
        'palabras_clave_aya' => 'cotizar, solicitud',
    ]);
    ayudaArticulo($cat, ['slug_aya' => 'otro', 'titulo_aya' => 'Otra cosa', 'contenido_aya' => 'Nada']);

    getJson('/api/ayuda/buscar?q=' . urlencode('¿Cómo hago una RESERVA?'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('resultados.0.slug', 'reservar');

    getJson('/api/ayuda/buscar?q=cotizacion')->assertOk()->assertJsonPath('total', 1);
});

it('la búsqueda sin coincidencias devuelve una lista vacía', function () {
    ayudaArticulo(ayudaCategoria());

    getJson('/api/ayuda/buscar?q=zzzzzz')
        ->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonCount(0, 'resultados');
});

it('la búsqueda exige al menos 2 caracteres', function () {
    getJson('/api/ayuda/buscar?q=a')->assertStatus(422);
    getJson('/api/ayuda/buscar')->assertStatus(422);
});

it('filtra por contexto para la ayuda contextual', function () {
    $cat = ayudaCategoria();
    ayudaArticulo($cat, ['slug_aya' => 'de-reservar', 'contexto_aya' => 'social.reservar']);
    ayudaArticulo($cat, ['slug_aya' => 'de-pqr', 'contexto_aya' => 'social.pqr']);

    getJson('/api/ayuda/articulos?contexto=social.reservar')
        ->assertOk()
        ->assertJsonCount(1, 'articulos')
        ->assertJsonPath('articulos.0.slug', 'de-reservar');
});

it('los relacionados son de la misma categoría o comparten palabras clave', function () {
    $a = ayudaCategoria();
    $b = ayudaCategoria();
    ayudaArticulo($a, ['slug_aya' => 'principal', 'palabras_clave_aya' => 'pago']);
    ayudaArticulo($a, ['slug_aya' => 'misma-categoria']);
    ayudaArticulo($b, ['slug_aya' => 'misma-clave', 'palabras_clave_aya' => 'Pago']);
    ayudaArticulo($b, ['slug_aya' => 'sin-relacion']);

    $slugs = collect(getJson('/api/ayuda/articulos/principal')->assertOk()->json('relacionados'))
        ->pluck('slug')->all();

    expect($slugs)->toContain('misma-categoria', 'misma-clave')
        ->and($slugs)->not->toContain('sin-relacion');
});