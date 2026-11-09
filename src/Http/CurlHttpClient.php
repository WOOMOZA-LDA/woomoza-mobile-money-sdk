<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Http;

use Woomoza\MobileMoney\Exception\NetworkException;

final class CurlHttpClient implements HttpClient
{
    public function request(string $method, string $url, array $headers, ?string $body, int $timeout, bool $verifySsl): HttpResponse
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NetworkException('Não foi possível iniciar o curl.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new NetworkException('Falha de rede: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new HttpResponse($status, (string) $raw);
    }
}
