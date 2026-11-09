<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Exception;

/**
 * Um pedido que MUDA estado (c2b, b2c, b2b, reversal) falhou no transporte ou o gateway
 * respondeu 502/503/504: não se sabe se a Vodacom o processou.
 *
 * NÃO repita o pedido. Consulte o estado com Client::query($e->thirdPartyReference).
 */
class UnknownOutcomeException extends NetworkException
{
    public function __construct(
        string $message,
        public readonly string $endpoint,
        public readonly string $thirdPartyReference,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
