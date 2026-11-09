<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Tests;

use PHPUnit\Framework\TestCase as Base;
use Woomoza\MobileMoney\Mpesa\Config;

abstract class TestCase extends Base
{
    /** Config de teste com um par RSA gerado na hora (a chave privada permite verificar o token). */
    protected static function config(array $extra = [], &$privateKey = null): Config
    {
        $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        foreach ([getenv('OPENSSL_CONF') ?: '', 'D:/xampp/php/extras/openssl/openssl.cnf', 'D:/xampp/apache/conf/openssl.cnf', '/etc/ssl/openssl.cnf'] as $cnf) {
            if ($cnf !== '' && is_file($cnf)) {
                $opts['config'] = $cnf;
                break;
            }
        }
        $res = openssl_pkey_new($opts);
        if ($res === false) {
            self::markTestSkipped('openssl_pkey_new indisponível: ' . openssl_error_string());
        }
        $details = openssl_pkey_get_details($res);
        $privateKey = $res;
        $pub = preg_replace('/-----[A-Z ]+-----|\s+/', '', $details['key']);

        return new Config($extra + [
            'public_key' => $pub, 'api_key' => 'KEY123', 'service_provider_code' => '171717',
            'api_host' => Config::SANDBOX_HOST, 'retries' => 2,
        ]);
    }

    protected static function ok(string $json): \Woomoza\MobileMoney\Http\HttpResponse
    {
        return new \Woomoza\MobileMoney\Http\HttpResponse(200, $json);
    }
}
