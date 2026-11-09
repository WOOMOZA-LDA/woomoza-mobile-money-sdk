<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Mpesa;

use Woomoza\MobileMoney\Exception\ConfigException;

/**
 * Configuração imutável. Os segredos são privados e mascarados em var_dump/print_r.
 *
 * Chaves do array: public_key, api_key, service_provider_code (obrigatórias);
 * api_host, origin, initiator_identifier, security_credential, timeout, retries,
 * verify_ssl, ports, callback_allowed_ips (opcionais).
 */
final class Config
{
    public const SANDBOX_HOST    = 'api.sandbox.vm.co.mz';
    public const PRODUCTION_HOST = 'api.vm.co.mz';

    /** Valores de exemplo da documentação: proibidos fora do sandbox. */
    private const SAMPLE_CREDENTIALS = ['MPesa2018', 'Mpesa2019'];

    /** Portas por endpoint (documentação do portal). */
    public const DEFAULT_PORTS = [
        'c2b' => 18352, 'b2c' => 18345, 'b2b' => 18349,
        'reversal' => 18354, 'query' => 18353, 'customerName' => 19323,
    ];

    private string $publicKey;
    private string $apiKey;
    private string $apiHost;
    private string $origin;
    private string $serviceProviderCode;
    private string $initiatorIdentifier;
    private string $securityCredential;
    private int $timeout;
    private int $retries;
    private bool $verifySsl;
    /** @var array<string,int> */
    private array $ports;
    /** @var string[] */
    private array $callbackAllowedIps;
    private ?string $bearer = null;

    /** @param array<string,mixed> $c */
    public function __construct(array $c)
    {
        $this->publicKey           = preg_replace('/\s+/', '', (string) ($c['public_key'] ?? '')) ?? '';
        $this->apiKey              = trim((string) ($c['api_key'] ?? ''));
        $this->apiHost             = (string) ($c['api_host'] ?? self::SANDBOX_HOST);
        $this->origin              = (string) ($c['origin'] ?? 'developer.mpesa.vm.co.mz');
        $this->serviceProviderCode = (string) ($c['service_provider_code'] ?? '');
        $this->initiatorIdentifier = (string) ($c['initiator_identifier'] ?? '');
        $this->securityCredential  = (string) ($c['security_credential'] ?? '');
        $this->timeout             = max(1, (int) ($c['timeout'] ?? 60));
        $this->retries             = max(0, (int) ($c['retries'] ?? 2));
        $this->verifySsl           = (bool) ($c['verify_ssl'] ?? true);
        $this->ports               = array_replace(self::DEFAULT_PORTS, array_map('intval', (array) ($c['ports'] ?? [])));
        $this->callbackAllowedIps  = array_values(array_map('strval', (array) ($c['callback_allowed_ips'] ?? [])));

        foreach (['publicKey' => 'public_key', 'apiKey' => 'api_key', 'serviceProviderCode' => 'service_provider_code'] as $prop => $key) {
            if ($this->$prop === '') {
                throw new ConfigException("Config: '$key' é obrigatório.");
            }
        }
        if (!$this->isSandbox()) {
            if (!$this->verifySsl) {
                throw new ConfigException("Config: 'verify_ssl' não pode ser false fora do sandbox.");
            }
            foreach ([$this->initiatorIdentifier, $this->securityCredential] as $v) {
                if (in_array($v, self::SAMPLE_CREDENTIALS, true)) {
                    throw new ConfigException('Config: credenciais de exemplo do sandbox não podem ser usadas em produção.');
                }
            }
        }
    }

    /** Atalho: força o host do sandbox. */
    /** @param array<string,mixed> $c */
    public static function sandbox(array $c): self
    {
        return new self($c + ['api_host' => self::SANDBOX_HOST]);
    }

    /** Atalho: força o host de produção. */
    /** @param array<string,mixed> $c */
    public static function production(array $c): self
    {
        return new self(['api_host' => self::PRODUCTION_HOST] + $c);
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new ConfigException("Ficheiro de configuração não encontrado: $path");
        }
        $arr = require $path;
        if (!is_array($arr)) {
            throw new ConfigException('O ficheiro de configuração deve devolver um array.');
        }
        return new self($arr);
    }

    public function isSandbox(): bool
    {
        return str_contains($this->apiHost, 'sandbox');
    }

    public function apiHost(): string { return $this->apiHost; }
    public function origin(): string { return $this->origin; }
    public function serviceProviderCode(): string { return $this->serviceProviderCode; }
    public function initiatorIdentifier(): string { return $this->initiatorIdentifier; }
    public function securityCredential(): string { return $this->securityCredential; }
    public function timeout(): int { return $this->timeout; }
    public function retries(): int { return $this->retries; }
    public function verifySsl(): bool { return $this->verifySsl; }
    /** @return string[] */
    public function callbackAllowedIps(): array { return $this->callbackAllowedIps; }

    public function port(string $endpoint): int
    {
        return $this->ports[$endpoint] ?? throw new ConfigException("Endpoint desconhecido: $endpoint");
    }

    /** "Bearer <api_key cifrada com a chave pública RSA, em base64>" — calculado uma vez. */
    public function bearerToken(): string
    {
        if ($this->bearer !== null) {
            return $this->bearer;
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($this->publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new ConfigException('Chave pública inválida (public_key).');
        }
        if (!openssl_public_encrypt($this->apiKey, $enc, $key, OPENSSL_PKCS1_PADDING)) {
            throw new ConfigException('Falha ao cifrar a api_key.');
        }
        return $this->bearer = 'Bearer ' . base64_encode($enc);
    }

    /** Nunca expõe segredos em var_dump/print_r/logs de exceção. */
    public function __debugInfo(): array
    {
        return [
            'apiHost' => $this->apiHost, 'serviceProviderCode' => $this->serviceProviderCode,
            'sandbox' => $this->isSandbox(), 'apiKey' => '***', 'publicKey' => '***',
            'securityCredential' => $this->securityCredential !== '' ? '***' : '',
        ];
    }

    public function __serialize(): array
    {
        throw new ConfigException('Config não pode ser serializado (contém segredos).');
    }
}
