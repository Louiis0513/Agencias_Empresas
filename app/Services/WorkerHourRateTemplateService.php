<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class WorkerHourRateTemplateService
{
    /**
     * @return list<string>
     */
    public function expectedRateKeys(): array
    {
        $keys = array_keys((array) config('worker_schedule_hour_rates', []));
        if ($keys !== []) {
            return $keys;
        }

        return [
            'HorasOrdinarias',
            'HorasExtrasDiurnas',
            'HorasExtrasNocturnas',
            'HorasRecargoNocturno',
            'HorasOrdinariasFestivas',
            'HorasExtrasDiurnasFestivas',
            'HorasExtrasNocturnasFestivas',
            'HorasRecargoNocturnoFestivo',
            'HorasFestivasNoCompensa',
        ];
    }

    /**
     * @param  array<string, mixed>  $rawRates
     * @return array<string, float>
     */
    public function normalizeAndValidateRates(array $rawRates): array
    {
        $expectedKeys = $this->expectedRateKeys();
        $errors = [];
        $normalized = [];

        foreach ($expectedKeys as $key) {
            if (! array_key_exists($key, $rawRates)) {
                $errors['rates.'.$key] = 'Debes ingresar el valor para '.$key.'.';

                continue;
            }

            $value = $rawRates[$key];
            if (! is_numeric($value)) {
                $errors['rates.'.$key] = 'El valor para '.$key.' debe ser numérico.';

                continue;
            }

            $floatValue = (float) $value;
            if ($floatValue < 0) {
                $errors['rates.'.$key] = 'El valor para '.$key.' no puede ser negativo.';

                continue;
            }

            $normalized[$key] = round($floatValue, 2);
        }

        $extraKeys = array_diff(array_keys($rawRates), $expectedKeys);
        if ($extraKeys !== []) {
            $errors['rates'] = 'Se detectaron tipos de hora no válidos en la plantilla.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }
}
