<?php

use App\Models\User;
use App\Support\GeneradorCaptcha;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['services.captcha.enabled' => true]);
});

function datosRegistroConCaptcha(array $extra = []): array
{
    return array_merge([
        'tipo_documento_u' => 'CC',
        'documento_u' => 1056351234,
        'nombre_u' => 'Ana',
        'apellido_u' => 'Gómez',
        'telefono_u' => '3001234567',
        'correo_u' => 'ana.captcha@example.com',
        'fecha_nacimiento_u' => '2000-05-10',
        'contrasena_u' => 'Segura123!',
        'contrasena_u_confirmation' => 'Segura123!',
    ], $extra);
}

/** Una imagen nueva y su respuesta correcta (en las pruebas se fija el código). */
function captchaConRespuesta(string $codigo = 'ACD34'): array
{
    $captcha = GeneradorCaptcha::crear();
    Cache::put('captcha:' . $captcha['id'], hash('sha256', $codigo), now()->addMinutes(5));

    return ['captcha_id' => $captcha['id'], 'captcha' => $codigo];
}

it('entrega una imagen sin los caracteres escritos como texto', function () {
    $respuesta = $this->getJson('/api/captcha')->assertOk();
    $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $respuesta->json('imagen')));

    expect($respuesta->json('id'))->toBeString()
        ->and($svg)->toStartWith('<svg')
        ->and($svg)->not->toContain('<text');
});

it('registra cuando escribe bien los caracteres, sin importar mayúsculas', function () {
    $captcha = captchaConRespuesta('ACD34');

    $this->postJson('/api/register', datosRegistroConCaptcha(['captcha' => 'acd 34'] + $captcha))->assertCreated();

    expect(User::where('correo_u', 'ana.captcha@example.com')->exists())->toBeTrue();
});

it('no registra sin escribir los caracteres', function () {
    $this->postJson('/api/register', datosRegistroConCaptcha())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['captcha', 'captcha_id']);

    expect(User::count())->toBe(0);
});

it('no registra si los caracteres no coinciden', function () {
    $captcha = captchaConRespuesta('ACD34');

    $this->postJson('/api/register', datosRegistroConCaptcha(['captcha' => 'XXXXX'] + $captcha))
        ->assertStatus(422)
        ->assertJsonValidationErrors('captcha');

    expect(User::count())->toBe(0);
});

it('cada imagen sirve una sola vez', function () {
    $captcha = captchaConRespuesta('ACD34');

    // Primer intento equivocado: la imagen queda descartada.
    $this->postJson('/api/register', datosRegistroConCaptcha(['captcha' => 'XXXXX'] + $captcha))->assertStatus(422);
    // Ni siquiera la respuesta correcta sirve después.
    $this->postJson('/api/register', datosRegistroConCaptcha($captcha))->assertStatus(422);

    expect(User::count())->toBe(0);
});

it('no acepta una imagen inventada', function () {
    $this->postJson('/api/register', datosRegistroConCaptcha(['captcha_id' => 'inventado', 'captcha' => 'ACD34']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('captcha');
});
