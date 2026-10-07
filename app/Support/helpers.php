<?php

use Morilog\Jalali\Jalalian;

if (! function_exists('jalali_date')) {
    function jalali_date($date, string $format = 'Y/m/d'): ?string
    {
        if (! $date) {
            return null;
        }

        return Jalalian::fromDateTime($date)->format($format);
    }
}

if (! function_exists('miladi_date')) {
    function miladi_date(?string $date, string $format = 'Y-m-d'): ?string
    {
        if (! $date) {
            return null;
        }

        return Jalalian::fromFormat('Y/m/d', $date)
            ->toCarbon()
            ->format($format);
    }
}
