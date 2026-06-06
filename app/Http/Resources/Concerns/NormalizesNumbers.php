<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

trait NormalizesNumbers
{
    /**
     * Konversi nilai decimal (Laravel cast `decimal:x` mengembalikan string,
     * mis. "5.00") menjadi float agar dikirim sebagai JSON number.
     * Null-safe: null tetap null, tidak dipaksa jadi 0.
     */
    protected static function num(int|float|string|null $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}
