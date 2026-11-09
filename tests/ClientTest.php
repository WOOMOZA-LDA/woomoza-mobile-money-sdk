<?php

declare(strict_types=1);

namespace Woomoza\Mpesa\Tests;

use Woomoza\Mpesa\Client;
use Woomoza\Mpesa\Exception\ApiException;
use Woomoza\Mpesa\Exception\ConfigException;
use Woomoza\Mpesa\Exception\NetworkException;
use Woomoza\Mpesa\Exception\UnknownOutcomeException;
use Woomoza\Mpesa\Exception\ValidationException;
use Woomoza\Mpesa\Http\HttpResponse;
use Woomoza\Mpesa\ResponseCode;

final class ClientTest extends TestCase
{
    private const OK = '{"output_ResponseCode":"INS-0","output_ResponseDesc":"Request processed successfully","output_TransactionID":"TX1","output_ConversationID":"","output_ThirdPartyReference":"TPX"}';

    private function client(FakeHttpClient $http, array $cfg = [], ?object $logger = null): Client
    {
        return new Client(self::config($cfg), $http, $logger);
    }

    public function testC2bBuildsCorrectRequest(): void
    {
        $http = (new FakeHttpClient())->push(self::ok(self::OK));
        $r = $this->client($http)->c2b('10', '841234567', 'REF1', 'TP1');

        $req = $http->requests[0];
        self::assertSame('POST', $req['method']);
        self::assertSame('https://api.sandbox.vm.co.mz:18352/ipg/v1x/c2bPayment/singleStage/', $req['url']);
        self::assertContains('Origin: developer.mpesa.vm.co.mz', $req['headers']);
        $body = json_decode($req['body'], true);
        self::assertSame('258841234567', $body['input_CustomerMSISDN']);
        self::assertSame('10', $body['input_Amount']);
        self::assertSame('171717', $body['input_ServiceProviderCode']);
        self::assertTrue($r->isSuccess());
        self::assertSame('TX1', $r->transactionId);
    }

    public function testBearerTokenIsRsaEncryptedApiKey(): void
    {
        $http = (new FakeHttpClient())->push(self::ok(self::OK));
        $client = new Client($cfg = self::config([], $priv), $http);
        $client->c2b(10, '841234567');

        $auth = current(array_filter($http->requests[0]['headers'], fn($h) => str_starts_with($h, 'Authorization: Bearer ')));
        $enc = base64_decode(substr($auth, strlen('Authorization: Bearer ')));
        openssl_private_decrypt($enc, $plain, $priv, OPENSSL_PKCS1_PADDING);
        self::assertSame('KEY123', $plain);
    }

    public function testReversalSendsReversalAmountAndPut(): void
    {
        $http = (new FakeHttpClient())->push(self::ok(self::OK));
        $this->client($http, ['initiator_identifier' => 'init', 'security_credential' => 'cred'])->reversal('TXID', 5);

        self::assertSame('PUT', $http->requests[0]['method']);
        self::assertStringContainsString(':18354/ipg/v1x/reversal/', $http->requests[0]['url']);
        $body = json_decode($http->requests[0]['body'], true);
        self::assertSame('5', $body['input_ReversalAmount']);
        self::assertArrayNotHasKey('input_Amount', $body);
    }

    public function testFullReversalOmitsAmount(): void
    {
        $http = (new FakeHttpClient())->push(self::ok(self::OK));
        $this->client($http, ['initiator_identifier' => 'i', 'security_credential' => 'c'])->reversal('TXID');
        self::assertArrayNotHasKey('input_ReversalAmount', json_decode($http->requests[0]['body'], true));
    }

    public function testReversalRequiresInitiator(): void
    {
        $this->expectException(ConfigException::class);
        $this->client(new FakeHttpClient())->reversal('TXID');
    }

    public function testQueryIsGetWithQueryString(): void
    {
        $http = (new FakeHttpClient())->push(self::ok('{"output_ResponseCode":"INS-0","output_ResponseTransactionStatus":"Completed"}'));
        $r = $this->client($http)->query('TXID');
        self::assertSame('GET', $http->requests[0]['method']);
        self::assertStringContainsString(':18353/ipg/v1x/queryTransactionStatus/?input_QueryReference=TXID', $http->requests[0]['url']);
        self::assertNull($http->requests[0]['body']);
        self::assertSame('Completed', $r->status);
    }

    public function testQueryCustomerNameUsesPort19323(): void
    {
        $http = (new FakeHttpClient())->push(self::ok('{"output_ResponseCode":"INS-0","output_CustomerName":"Jo*n Sm**h"}'));
        $r = $this->client($http)->queryCustomerName('841234567');
        self::assertStringContainsString(':19323/ipg/v1x/queryCustomerName/', $http->requests[0]['url']);
        self::assertSame('Jo*n Sm**h', $r->customerName);
    }

    public function testPortsAreConfigurable(): void
    {
        $http = (new FakeHttpClient())->push(self::ok(self::OK));
        $this->client($http, ['ports' => ['c2b' => 19999]])->c2b(10, '841234567');
        self::assertStringContainsString(':19999/', $http->requests[0]['url']);
    }

    public function testPaymentsAreNeverRetriedOnNetworkError(): void
    {
        $http = (new FakeHttpClient())->push(new NetworkException('timeout'));
        try {
            $this->client($http)->c2b(10, '841234567', 'REF1', 'TPK');
            self::fail('esperava UnknownOutcomeException');
        } catch (UnknownOutcomeException $e) {
            self::assertSame('TPK', $e->thirdPartyReference);
            self::assertSame('c2b', $e->endpoint);
        }
        self::assertCount(1, $http->requests);
    }

    public function testPaymentGatewayTimeoutIsUnknownOutcome(): void
    {
        $http = (new FakeHttpClient())->push(new HttpResponse(504, '<html>Gateway Time-out</html>'));
        $this->expectException(UnknownOutcomeException::class);
        $this->client($http)->b2c(10, '841234567');
    }

    public function testQueriesAreRetriedOnGateway502(): void
    {
        $http = (new FakeHttpClient())
            ->push(new HttpResponse(502, '<html>Forbidden</html>'))
            ->push(self::ok('{"output_ResponseCode":"INS-0"}'));
        $r = $this->client($http, ['retries' => 2])->query('TX');
        self::assertTrue($r->isSuccess());
        self::assertCount(2, $http->requests);
    }

    public function testQueryGivesUpAfterRetries(): void
    {
        $http = (new FakeHttpClient());
        for ($i = 0; $i < 3; $i++) {
            $http->push(new HttpResponse(502, '<html>Forbidden</html>'));
        }
        $r = $this->client($http, ['retries' => 2])->queryCustomerName('841234567');
        self::assertTrue($r->isGatewayError());
        self::assertSame(502, $r->httpStatus);
        self::assertCount(3, $http->requests);
    }

    public function testApiErrorAndThrowIfFailed(): void
    {
        $http = (new FakeHttpClient())->push(new HttpResponse(409, '{"output_ResponseCode":"INS-10","output_ResponseDesc":"Duplicate Transaction"}'));
        $r = $this->client($http)->c2b(10, '841234567');
        self::assertFalse($r->isSuccess());
        self::assertSame(ResponseCode::DuplicateTransaction, $r->codeEnum());
        $this->expectException(ApiException::class);
        $r->throwIfFailed();
    }

    public function testValidationHappensBeforeAnyRequest(): void
    {
        $http = new FakeHttpClient();
        $client = $this->client($http);
        foreach ([fn() => $client->c2b(10, '821234567'), fn() => $client->c2b(0, '841234567'), fn() => $client->c2b(10, '841234567', str_repeat('A', 21))] as $bad) {
            try {
                $bad();
                self::fail('esperava ValidationException');
            } catch (ValidationException) {
            }
        }
        self::assertSame([], $http->requests);
    }

    public function testLoggerNeverSeesSecretsAndMasksMsisdn(): void
    {
        $logger = new class {
            public array $lines = [];
            public function log(string $level, string $message, array $context = []): void { $this->lines[] = json_encode([$level, $message, $context]); }
        };
        $http = (new FakeHttpClient())->push(self::ok(self::OK));
        $this->client($http, [], $logger)->c2b(10, '841234567');

        $all = implode("\n", $logger->lines);
        self::assertStringContainsString('mpesa.request', $all);
        self::assertStringContainsString('25884****567', $all);
        self::assertStringNotContainsString('841234567', str_replace('25884****567', '', $all));
        self::assertStringNotContainsString('KEY123', $all);
        self::assertStringNotContainsString('Bearer', $all);
    }
}
