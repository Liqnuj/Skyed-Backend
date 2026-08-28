<?php

use App\Models\CategoriaCompetencia;
use App\Models\EventoDeportivo;
use function Pest\Laravel\getJson;

function crearEventoParaCategoria(): EventoDeportivo
{
    return EventoDeportivo::create([
        'nombre_e' => 'Carrera de Montaña',
        'categoria_e' => 'atletismo',
        'fecha_e' => now()->addMonth()->toDateString(),
        'hora_e' => '06:00',
        'ubicacion_e' => 'Sendero El Alto',
        'descripcion_e' => 'Carrera de montaña anual.',
        'requisitos_e' => 'Certificado médico.',
        'imagen_e' => 'eventos/carrera.jpg',
        'cupos_disponibles_e' => 100,
        'estado_e' => 'activo',
        'creado_e' => now(),
    ]);
}

it('lista las categorías de un evento, paginadas', function () {
    $evento = crearEventoParaCategoria();
    CategoriaCompetencia::create([
        'nombre_cc' => 'Élite',
        'genero_cc' => 'mixto',
        'id_e' => $evento->id_e,
    ]);

    // Este endpoint requiere autenticación (está en la zona general),
    // no en la pública.
    $user = userWithRole('participante');

    $this->actingAs($user)
        ->getJson("/api/eventos/{$evento->id_e}/categorias")
        ->assertStatus(200)
        ->assertJsonStructure([
            'evento',
            'categorias' => [
                '*' => ['id', 'nombre', 'genero'],
            ],
        ]);
});

it('no permite crear una categoría a un usuario sin el rol adminDeportivo', function () {
    $evento = crearEventoParaCategoria();
    $user = userWithRole('participante');

    $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/categorias", [
            'nombre_cc' => 'Élite',
        ])
        ->assertStatus(403);
});

it('permite a un adminDeportivo crear una categoría con edades válidas', function () {
    $evento = crearEventoParaCategoria();
    $admin = userWithRole('adminDeportivo');

    $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/categorias", [
            'nombre_cc' => 'Juvenil',
            'edad_minima_cc' => 15,
            'edad_maxima_cc' => 18,
            'genero_cc' => 'mixto',
        ])
        ->assertStatus(201)
        ->assertJsonPath('categoria.nombre', 'Juvenil');
});

it('rechaza una categoría donde la edad máxima es menor a la mínima', function () {
    $evento = crearEventoParaCategoria();
    $admin = userWithRole('adminDeportivo');

    $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/categorias", [
            'nombre_cc' => 'Inválida',
            'edad_minima_cc' => 30,
            'edad_maxima_cc' => 18,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('edad_maxima_cc');
});
