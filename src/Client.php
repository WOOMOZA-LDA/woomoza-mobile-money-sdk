<?php

declare(strict_types=1);

namespace Woomoza\Mpesa;

use Woomoza\Mpesa\Exception\ConfigException;
use Woomoza\Mpesa\Exception\NetworkException;
use Woomoza\Mpesa\Exception\UnknownOutcomeException;
use Woomoza\Mpesa\Http\CurlHttpClient;
use Woomoza\Mpesa\Http\HttpClient;
use Woomoza\Mpesa\Support\Amount;
use Woomoza\Mpesa\Support\Msisdn;
use Woomoza\Mpesa\Support\Reference;

/**
 * Cliente M-Pesa Moçambique (Vodacom). Sem dependências externas.
 *
 * Pagamentos (c2b, b2c, b2b, reversal): NUNCA são repetidos automaticamente. Se o resultado
 * for incerto (timeout, 502/503/504) lança UnknownOutcomeException: consulte query() antes de repetir.
 * Consultas (query, queryCustomerName): repetidas com backoff exponencial + jitter.
 */
final class Client
{
    /** nome => [método, path] (a porta vem de Config::port()). */
    private const ENDPOINTS = [
        'c2b'          => ['POST', '/ipg/v1x/c2bPayment/singleStage/'],
        'b2c'          => ['POST', '/ipg/v1x/b2cPayment/'],
        'b2b'          => ['POST', '/ipg/v1x/b2bPayment/'],
        'reversal'     => ['PUT',  '/ipg/v1x/reversal/'],
        'query'        => ['GET',  '/ipg/v1x/queryTransactionStatus/'],
        'customerName' => ['GET',  '/ipg/v1x/queryCustomerName/'],
    ];

    private HttpClient $http;

    /**
     * @param object|null $logger qualquer objeto com log(string $level, string $message, array $context) (ex.: PSR-3)
     */
    public function __construct(private Config $config, ?HttpClient $http = null, private ?object $logger = null)
    {
        $this->http = $http ?? new CurlHttpClient();
    }

    public static function fromFile(string $path, ?HttpClient $http = null, ?object $logger = null): self
    {
        return new self(Config::fromFile($path), $http, $logger);
    }

    public function config(): Config
    {
        return $this->config;
    }

    /** Cobra ao cliente (Customer → Business). */
    public function c2b(int|float|string $amount, string $msisdn, ?string $reference = null, ?string $thirdPartyRef = null): Response
    {
        $tp = $thirdPartyRef ?? Reference::generate('TP');
        return $this->call('c2b', [
            'input_ServiceProviderCode'  => $this->config->serviceProviderCode(),
            'input_CustomerMSISDN'       => Msisdn::normalize($msisdn),
            'input_Amount'               => Amount::format($amount),
            'input_TransactionReference' => Reference::assertValid($reference ?? Reference::generate('REF')),
            'input_ThirdPartyReference'  => Reference::assertValid($tp, 'ThirdPartyReference'),
        ], $tp);
    }

    /** Paga ao cliente (Business → Customer). */
    public function b2c(int|float|string $amount, string $msisdn, ?string $reference = null, ?string $thirdPartyRef = null): Response
    {
        $tp = $thirdPartyRef ?? Reference::generate('TP');
        return $this->call('b2c', [
            'input_ServiceProviderCode'  => $this->config->serviceProviderCode(),
            'input_CustomerMSISDN'       => Msisdn::normalize($msisdn),
            'input_Amount'               => Amount::format($amount),
            'input_TransactionReference' => Reference::assertValid($reference ?? Reference::generate('REF')),
            'input_ThirdPartyReference'  => Reference::assertValid($tp, 'ThirdPartyReference'),
        ], $tp);
    }

    /** Entre empresas (Business → Business). */
    public function b2b(int|float|string $amount, string $receiverPartyCode, ?string $reference = null, ?string $thirdPartyRef = null): Response
    {
        $tp = $thirdPartyRef ?? Reference::generate('TP');
        return $this->call('b2b', [
            'input_PrimaryPartyCode'     => $this->config->serviceProviderCode(),
            'input_ReceiverPartyCode'    => $receiverPartyCode,
            'input_Amount'               => Amount::format($amount),
            'input_TransactionReference' => Reference::assertValid($reference ?? Reference::generate('REF')),
            'input_ThirdPartyReference'  => Reference::assertValid($tp, 'ThirdPartyReference'),
        ], $tp);
    }

    /** Reversão. $amount null = total. Exige initiator_identifier e security_credential. */
    public function reversal(string $transactionId, int|float|string|null $amount = null, ?string $thirdPartyRef = null): Response
    {
        if ($this->config->initiatorIdentifier() === '' || $this->config->securityCredential() === '') {
            throw new ConfigException('Reversal exige initiator_identifier e security_credential na config.');
        }
        $tp = $thirdPartyRef ?? Reference::generate('TP');
        $body = [
            'input_TransactionID'       => $transactionId,
            'input_SecurityCredential'  => $this->config->securityCredential(),
            'input_InitiatorIdentifier' => $this->config->initiatorIdentifier(),
            'input_ThirdPartyReference' => Reference::assertValid($tp, 'ThirdPartyReference'),
            'input_ServiceProviderCode' => $this->config->serviceProviderCode(),
        ];
        if ($amount !== null) {
            $body['input_ReversalAmount'] = Amount::format($amount);
        }
        return $this->call('reversal', $body, $tp);
    }

    /** Estado de uma transação (TransactionID, ConversationID ou ThirdPartyReference). */
    public function query(string $queryReference, ?string $thirdPartyRef = null): Response
    {
        $tp = $thirdPartyRef ?? Reference::generate('TP');
        return $this->call('query', [
            'input_QueryReference'      => $queryReference,
            'input_ServiceProviderCode' => $this->config->serviceProviderCode(),
            'input_ThirdPartyReference' => $tp,
        ], $tp);
    }

    /** Nome (mascarado) do titular da carteira, útil antes de um B2C. */
    public function queryCustomerName(string $msisdn, ?string $thirdPartyRef = null): Response
    {
        $tp = $thirdPartyRef ?? Reference::generate('TP');
        return $this->call('customerName', [
            'input_CustomerMSISDN'      => Msisdn::normalize($msisdn),
            'input_ThirdPartyReference' => $tp,
            'input_ServiceProviderCode' => $this->config->serviceProviderCode(),
        ], $tp);
    }

    // ---------------------------------------------------------------

    /** @param array<string,mixed> $params */
    private function call(string $endpoint, array $params, string $thirdPartyRef): Response
    {
        [$method, $path] = self::ENDPOINTS[$endpoint];
        $url = 'https://' . $this->config->apiHost() . ':' . $this->config->port($endpoint) . $path;
        $body = null;
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
        } else {
            $body = json_encode($params, JSON_THROW_ON_ERROR);
        }

        $mutating = $method !== 'GET';
        $maxRetries = $mutating ? 0 : $this->config->retries();
        $headers = [
            'Content-Type: application/json',
            'Origin: ' . $this->config->origin(),
            'Authorization: ' . $this->config->bearerToken(),
        ];

        $this->log('info', 'mpesa.request', $this->context($endpoint, $params, $thirdPartyRef));
        $started = microtime(true);

        for ($attempt = 0; ; $attempt++) {
            try {
                $res = $this->http->request($method, $url, $headers, $body, $this->config->timeout(), $this->config->verifySsl());
            } catch (NetworkException $e) {
                if ($mutating) {
                    $this->log('error', 'mpesa.unknown_outcome', ['endpoint' => $endpoint, 'tp' => $thirdPartyRef, 'error' => $e->getMessage()]);
                    throw new UnknownOutcomeException(
                        "Resultado desconhecido em $endpoint ({$e->getMessage()}). Consulte query('$thirdPartyRef') antes de repetir.",
                        $endpoint,
                        $thirdPartyRef,
                        $e
                    );
                }
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                $this->backoff($attempt);
                continue;
            }

            $gatewayDown = in_array($res->status, [502, 503, 504], true);
            if ($gatewayDown && $mutating) {
                $this->log('error', 'mpesa.unknown_outcome', ['endpoint' => $endpoint, 'tp' => $thirdPartyRef, 'http' => $res->status]);
                throw new UnknownOutcomeException(
                    "Gateway devolveu HTTP {$res->status} em $endpoint: resultado desconhecido. Consulte query('$thirdPartyRef') antes de repetir.",
                    $endpoint,
                    $thirdPartyRef
                );
            }
            if ($gatewayDown && $attempt < $maxRetries) {
                $this->backoff($attempt);
                continue;
            }

            $response = new Response($res->body, $res->status);
            $this->log($response->isSuccess() ? 'info' : 'warning', 'mpesa.response', [
                'endpoint' => $endpoint, 'tp' => $thirdPartyRef, 'http' => $res->status, 'code' => $response->code,
                'tx' => $response->transactionId, 'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            return $response;
        }
    }

    /** 0.5s, 1s, 2s... com jitter de ±25%. */
    private function backoff(int $attempt): void
    {
        $base = 500000 * (2 ** $attempt);
        usleep((int) ($base * (0.75 + mt_rand(0, 50) / 100)));
    }

    /** Contexto de log sem segredos e com o MSISDN mascarado. */
    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function context(string $endpoint, array $params, string $tp): array
    {
        $ctx = ['endpoint' => $endpoint, 'tp' => $tp];
        if (isset($params['input_CustomerMSISDN'])) {
            $ctx['msisdn'] = Msisdn::mask((string) $params['input_CustomerMSISDN']);
        }
        if (isset($params['input_Amount'])) {
            $ctx['amount'] = $params['input_Amount'];
        }
        return $ctx;
    }

    /** @param array<string,mixed> $context */
    private function log(string $level, string $message, array $context): void
    {
        $this->logger?->log($level, $message, $context);
    }
}
