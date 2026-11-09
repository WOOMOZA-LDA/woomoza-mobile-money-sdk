<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Http;

use Woomoza\MobileMoney\Exception\NetworkException;

/** Transporte HTTP. Substituível (testes, proxy, PSR-18 via adaptador). */
interface HttpClient
{
    /**
     * @param string[] $headers linhas "Nome: valor"
     * @throws NetworkException em falha de transporte
     */
    public function request(string $method, string $url, array $headers, ?string $body, int $timeout, bool $verifySsl): HttpResponse;
}
