<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Tests;

use Woomoza\MobileMoney\Exception\NetworkException;
use Woomoza\MobileMoney\Http\HttpClient;
use Woomoza\MobileMoney\Http\HttpResponse;

/** Transporte falso: devolve respostas em fila e guarda os pedidos feitos. */
final class FakeHttpClient implements HttpClient
{
    /** @var array<int,HttpResponse|NetworkException> */
    private array $queue = [];
    /** @var array<int,array{method:string,url:string,headers:array,body:?string}> */
    public array $requests = [];

    public function push(HttpResponse|NetworkException $r): self
    {
        $this->queue[] = $r;
        return $this;
    }

    public function request(string $method, string $url, array $headers, ?string $body, int $timeout, bool $verifySsl): HttpResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $next = array_shift($this->queue) ?? throw new \LogicException('FakeHttpClient: fila vazia');
        if ($next instanceof NetworkException) {
            throw $next;
        }
        return $next;
    }
}
