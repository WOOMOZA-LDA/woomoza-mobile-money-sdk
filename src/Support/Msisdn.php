<?php

declare(strict_types=1);

namespace Woomoza\Mpesa\Support;

use Woomoza\Mpesa\Exception\ValidationException;

final class Msisdn
{
    /** Normaliza para 258 + 84/85 + 7 dígitos (aceita 84x, 258x, +258x, 00258x, com espaços/hífens). */
    public static function normalize(string $msisdn): string
    {
        $m = preg_replace('/[\s\-]/', '', $msisdn) ?? '';
        if (!preg_match('/^(?:\+|00)?(?:258)?((?:84|85)\d{7})$/', $m, $g)) {
            throw new ValidationException('MSISDN inválido (só Vodacom 84/85): ' . self::mask($msisdn));
        }
        return '258' . $g[1];
    }

    /** 258841234567 → 25884****567 (para logs e mensagens de erro). */
    public static function mask(string $msisdn): string
    {
        $d = preg_replace('/\D/', '', $msisdn) ?? '';
        if (strlen($d) < 8) {
            return str_repeat('*', strlen($d));
        }
        return substr($d, 0, 5) . str_repeat('*', strlen($d) - 8) . substr($d, -3);
    }
}
