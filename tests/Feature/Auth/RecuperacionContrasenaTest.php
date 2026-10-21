<?php

use App\Mail\CodigoVerificacionMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Recuperación de contraseña (RecoverPage.tsx)
|--------------------------------------------------------------------------
| 1) POST /enviar-codigo-recuperacion  -> correo con un código de 6 dígitos
| 2) POST /verificar-codigo            -> valida el código
| 3) POST /reset-password              -> cambia la contraseña
*/

function usuarioConCodigo(string $codigo = '482915', ?\DateTimeInterface $expira = null): User
{
    $user = User::factory()->create(['correo_u' => 'ana@example.com']);
    $user->forceFill([
        'codigo' => $codigo,
        'codigo_expira_at' => $expira ?? now()->addMinutes(15),
    ])->save();

    return $user;
}

it('envía por correo un código de 6 dígitos válido por 15 minutos', function () {
    Mail::fake();
    $user = User::factory()->create(['correo_u' => 'ana@example.com']);

    postJson('/api/enviar-codigo-recuperacion', ['correo_u' => 'ana@example.com'])->assertOk();

    $user->refresh();
    $expira = \Carbon\Carbon::parse($user->codigo_expira_at);
    expect((string) $user->codigo)->toMatch('/^\d{6}$/')
        ->and($expira->between(now()->addMinutes(14), now()->addMinutes(16)))->toBeTrue();

    Mail::assertSent(CodigoVerificacionMail::class, fn ($mail) => $mail->hasTo('ana@example.com')
        && $mail->codigo === (string) $user->codigo);
});

it('encuentra la cuenta aunque el correo se escriba con mayúsculas', function () {
    Mail::fake();
    User::factory()->create(['correo_u' => 'ana@example.com']);

    postJson('/api/enviar-codigo-recuperacion', ['correo_u' => ' ANA@Example.com '])->assertOk();

    Mail::assertSent(CodigoVerificacionMail::class);
});

it('avisa si el correo no está registrado y no envía nada', function () {
    Mail::fake();

    postJson('/api/enviar-codigo-recuperacion', ['correo_u' => 'nadie@example.com'])
        ->assertNotFound();

    Mail::assertNothingSent();
});

it('valida el formato del correo antes de enviar el código', function () {
    postJson('/api/enviar-codigo-recuperacion', ['correo_u' => 'no-es-un-correo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('correo_u');
});

it('acepta el código correcto y rechaza uno incorrecto', function () {
    usuarioConCodigo('482915');

    postJson('/api/verificar-codigo', ['correo_u' => 'ana@example.com', 'token' => '000000'])->assertStatus(400);
    postJson('/api/verificar-codigo', ['correo_u' => 'ana@example.com', 'token' => '482915'])->assertOk();
});

it('rechaza un código vencido', function () {
    usuarioConCodigo('482915', now()->subMinute());

    postJson('/api/verificar-codigo', ['correo_u' => 'ana@example.com', 'token' => '482915'])
        ->assertStatus(400)
        ->assertJsonPath('message', 'El código ha expirado. Por favor, solicita uno nuevo.');
});

it('cambia la contraseña, cierra las sesiones abiertas y deja entrar con la nueva', function () {
    $user = usuarioConCodigo('482915');
    $user->createToken('sesion-vieja');

    postJson('/api/reset-password', [
        'correo_u' => 'ana@example.com',
        'token' => '482915',
        'contrasena_u' => CLAVE_VALIDA,
        'contrasena_u_confirmation' => CLAVE_VALIDA,
    ])->assertOk();

    $user->refresh();
    expect($user->codigo)->toBeNull()
        ->and($user->tokens()->count())->toBe(0);

    postJson('/api/login', ['correo_u' => 'ana@example.com', 'contrasena_u' => CLAVE_VALIDA])->assertOk();
    postJson('/api/login', ['correo_u' => 'ana@example.com', 'contrasena_u' => 'password'])->assertStatus(401);
});

it('no deja reutilizar el código después de cambiar la contraseña', function () {
    usuarioConCodigo('482915');
    $datos = [
        'correo_u' => 'ana@example.com',
        'token' => '482915',
        'contrasena_u' => CLAVE_VALIDA,
        'contrasena_u_confirmation' => CLAVE_VALIDA,
    ];

    postJson('/api/reset-password', $datos)->assertOk();
    postJson('/api/reset-password', $datos)->assertStatus(400);
});

it('exige la misma contraseña segura que el registro', function () {
    usuarioConCodigo('482915');

    postJson('/api/reset-password', [
        'correo_u' => 'ana@example.com',
        'token' => '482915',
        'contrasena_u' => 'clave1234',
        'contrasena_u_confirmation' => 'clave1234',
    ])->assertStatus(422)->assertJsonValidationErrors('contrasena_u');
});

it('no cambia la contraseña con un código incorrecto', function () {
    $user = usuarioConCodigo('482915');
    $hashAntes = $user->contrasena_u;

    postJson('/api/reset-password', [
        'correo_u' => 'ana@example.com',
        'token' => '111111',
        'contrasena_u' => CLAVE_VALIDA,
        'contrasena_u_confirmation' => CLAVE_VALIDA,
    ])->assertStatus(400);

    expect($user->fresh()->contrasena_u)->toBe($hashAntes);
});
