<?php

declare(strict_types=1);

namespace Woomoza\Mpesa\Exception;

use Woomoza\Mpesa\Response;

/** A API respondeu com um código de erro (lançada por Response::throwIfFailed()). */
class ApiException extends MpesaException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct(
            sprintf('M-Pesa %s: %s', $response->code !== '' ? $response->code : 'HTTP ' . $response->httpStatus, $response->message()),
            $response->httpStatus
        );
    }
}
