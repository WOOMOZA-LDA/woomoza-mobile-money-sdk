<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Support;

use Woomoza\MobileMoney\Exception\ValidationException;

final class Reference
{
    public const MAX_LENGTH = 20;

    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Referência curta e única: prefixo + aammdd + 5 caracteres aleatórios base 36
     * (REF → 14 caracteres, TP → 13). Para o TransactionReference prefira o ID do seu pedido.
     */
    public static function generate(string $prefix = 'REF'): string
    {
        $rand = '';
        for ($i = 0; $i < 5; $i++) {
            $rand .= self::ALPHABET[random_int(0, 35)];
        }
        return substr($prefix . date('ymd') . $rand, 0, self::MAX_LENGTH);
    }

    public static function assertValid(string $ref, string $field = 'TransactionReference'): string
    {
        $len = strlen($ref);
        if ($len < 1 || $len > self::MAX_LENGTH) {
            throw new ValidationException("$field deve ter 1 a " . self::MAX_LENGTH . " caracteres (tem $len).");
        }
        return $ref;
    }
}
