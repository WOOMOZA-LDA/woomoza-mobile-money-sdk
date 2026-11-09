<?php

declare(strict_types=1);

namespace Woomoza\Mpesa\Callback;

use Woomoza\Mpesa\Response;

final class CallbackResult
{
    public function __construct(
        /** Código HTTP a devolver à Vodacom. */
        public readonly int $httpStatus,
        /** @var array<string,mixed> Corpo JSON a devolver. */
        public readonly array $body,
        /** Resposta interpretada (null se o pedido foi rejeitado). */
        public readonly ?Response $response = null,
        /** true se este mesmo corpo já tinha sido processado antes. */
        public readonly bool $duplicate = false
    ) {
    }

    public function accepted(): bool
    {
        return $this->response !== null;
    }
}
