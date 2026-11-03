<?php

namespace App\Rules;

use App\Support\GeneradorCaptcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Comprueba lo que la persona escribió en la verificación "No soy un robot".
 * El campo trae la respuesta; el identificador de la imagen llega en captcha_id.
 */
class CaptchaValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!GeneradorCaptcha::verificar(request()->input('captcha_id'), is_string($value) ? $value : null)) {
            $fail('Los caracteres no coinciden con la imagen o ya vencieron. Escribe los de la imagen nueva.');
        }
    }
}
