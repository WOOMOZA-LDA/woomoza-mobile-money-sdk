<?php

declare(strict_types=1);

namespace Woomoza\Mpesa\Exception;

/** Falha de transporte (DNS, TLS, timeout, ligação recusada). */
class NetworkException extends MpesaException
{
}
