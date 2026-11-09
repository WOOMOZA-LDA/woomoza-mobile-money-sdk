<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Support;

use Woomoza\MobileMoney\Exception\ValidationException;

/**
 * Valor monetário como string decimal (evita erros de arredondamento de float).
 * Aceita int, float ou string ("10", "10.5", "10,50"); no máximo 2 casas decimais.
 */
final class Amount
{
    public static function format(int|float|string $value): string
    {
        $s = is_string($value) ? str_replace(',', '.', trim($value)) : (is_float($value) ? rtrim(rtrim(sprintf('%.10F', $value), '0'), '.') : (string) $value);

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
            throw new ValidationException('Valor inválido (use até 2 casas decimais): ' . (string) $value);
        }
        if ((float) $s <= 0) {
            throw new ValidationException('O valor deve ser maior que zero.');
        }
        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }
}
