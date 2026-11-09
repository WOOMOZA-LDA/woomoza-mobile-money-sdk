<?php

declare(strict_types=1);

namespace Woomoza\Mpesa\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Woomoza\Mpesa\Config;
use Woomoza\Mpesa\Exception\ConfigException;
use Woomoza\Mpesa\Exception\ValidationException;
use Woomoza\Mpesa\Response;
use Woomoza\Mpesa\Support\Amount;
use Woomoza\Mpesa\Support\Msisdn;
use Woomoza\Mpesa\Support\Reference;

final class SupportTest extends TestCase
{
    #[DataProvider('validMsisdns')]
    public function testMsisdnNormalizes(string $in): void
    {
        self::assertSame('258841234567', Msisdn::normalize($in));
    }

    public static function validMsisdns(): array
    {
        return [['841234567'], ['258841234567'], ['+258841234567'], ['00258841234567'], ['84 123 4567'], ['258-841234567']];
    }

    #[DataProvider('invalidMsisdns')]
    public function testMsisdnRejects(string $in): void
    {
        $this->expectException(ValidationException::class);
        Msisdn::normalize($in);
    }

    public static function invalidMsisdns(): array
    {
        return [[''], ['821234567'], ['84123'], ['25884588819'], ['abc']];
    }

    public function testMsisdnMask(): void
    {
        self::assertSame('25884****567', Msisdn::mask('258841234567'));
    }

    #[DataProvider('amounts')]
    public function testAmountFormat(int|float|string $in, string $out): void
    {
        self::assertSame($out, Amount::format($in));
    }

    public static function amounts(): array
    {
        return [[10, '10'], [10.0, '10'], [10.5, '10.5'], ['10,50', '10.5'], ['0.1', '0.1'], [0.1 + 0.2, '0.3'] ?? null];
    }

    #[DataProvider('badAmounts')]
    public function testAmountRejects(int|float|string $in): void
    {
        $this->expectException(ValidationException::class);
        Amount::format($in);
    }

    public static function badAmounts(): array
    {
        return [[0], [-5], ['abc'], ['10.999'], ['']];
    }

    public function testReferencesAreUniqueAndWithinLimit(): void
    {
        $seen = [];
        for ($i = 0; $i < 500; $i++) {
            $r = Reference::generate('TP');
            self::assertLessThanOrEqual(14, strlen($r));
            self::assertMatchesRegularExpression('/^TP\d{6}[0-9A-Z]{5}$/', $r);
            $seen[$r] = true;
        }
        self::assertCount(500, $seen);
    }

    public function testConfigHidesSecrets(): void
    {
        $cfg = self::config(['security_credential' => 'topsecret']);
        $dump = print_r($cfg, true) . var_export((array) $cfg, true);
        self::assertStringNotContainsString('KEY123', print_r($cfg, true));
        self::assertStringNotContainsString('topsecret', print_r($cfg, true));
        self::assertStringContainsString('***', $dump);
    }

    public function testConfigCannotBeSerialized(): void
    {
        $this->expectException(ConfigException::class);
        serialize(self::config());
    }

    public function testConfigRequiresMandatoryKeys(): void
    {
        $this->expectException(ConfigException::class);
        new Config(['api_key' => 'x']);
    }

    public function testProductionRejectsSampleCredentialsAndInsecureTls(): void
    {
        $base = ['public_key' => 'AAAA', 'api_key' => 'k', 'service_provider_code' => '1', 'api_host' => Config::PRODUCTION_HOST];
        foreach ([['initiator_identifier' => 'MPesa2018'], ['security_credential' => 'Mpesa2019'], ['verify_ssl' => false]] as $bad) {
            try {
                new Config($bad + $base);
                self::fail('esperava ConfigException');
            } catch (ConfigException) {
            }
        }
        self::assertFalse((new Config($base))->isSandbox());
    }

    public function testResponseParsesSyncAndAsync(): void
    {
        $sync = new Response('{"output_ResponseCode":"INS-0","output_TransactionID":"A"}', 200);
        $async = Response::fromCallback('{"output_ResultCode":"0","output_ResultDesc":"Success","output_ThirdPartyReference":"T9"}');
        $gw = new Response('<html><h1>Forbidden</h1></html>', 502);

        self::assertTrue($sync->isSuccess());
        self::assertTrue($async->isSuccess());
        self::assertSame('T9', $async->thirdPartyReference);
        self::assertTrue($gw->isGatewayError());
        self::assertStringContainsString('502', $gw->message());
    }
}
