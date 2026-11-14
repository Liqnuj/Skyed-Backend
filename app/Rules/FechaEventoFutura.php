<?php

namespace App\Rules;

use App\Models\EventoDeportivo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * La fecha de un evento debe ser a partir de mañana (hora de Colombia):
 * no se puede programar para hoy ni para un día que ya pasó.
 *
 * Al editar, si la fecha no cambió se acepta tal cual (así se pueden corregir
 * otros datos de un evento que ya pasó sin que la fecha lo impida).
 */
class FechaEventoFutura implements ValidationRule
{
    public function __construct(private ?string $fechaActual = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $fecha = Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return; // el formato lo valida la regla "date"
        }

        if ($this->fechaActual !== null && $fecha === $this->fechaActual) {
            return;
        }

        if ($fecha <= EventoDeportivo::hoyLocal()) {
            $fail('La fecha del evento debe ser a partir de mañana: no se puede programar para hoy ni para un día que ya pasó.');
        }
    }
}
