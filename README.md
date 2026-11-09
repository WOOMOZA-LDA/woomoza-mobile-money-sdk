# woomoza-mpesa-sdk

Pacote Composer: `woomoza/mpesa` · Namespace: `Woomoza\Mpesa`

SDK PHP independente para a API M-Pesa de Moçambique (Vodacom). Sem dependências de runtime
(só `ext-curl`, `ext-openssl`, `ext-json`), PHP ≥ 8.1.

Endpoints: **C2B**, **B2C**, **B2B**, **Reversal**, **Query Transaction Status**, **Query Customer Name** e
tratamento seguro do **callback assíncrono**.

## Estrutura

```
src/            o SDK (Client, Config, Response, ResponseCode, Exception/, Http/, Support/, Callback/)
public/         só callback.php (o único ficheiro que deve estar exposto na web)
bin/mpesa       CLI de testes e operação
config/         config.php (segredos, fora do git) e config.example.php
storage/        callback.log e callbacks/ (idempotência)
tests/          PHPUnit com transporte falso (não chama a Vodacom)
```

## Instalação e configuração

```bash
composer install
cp config/config.example.php config/config.php   # e preencha as chaves do portal
```

`config/config.php` devolve um array: `public_key`, `api_key`, `service_provider_code` (obrigatórios);
`api_host`, `origin`, `initiator_identifier`, `security_credential`, `timeout` (60), `retries` (2),
`verify_ssl` (true), `ports` (sobrepõe as portas por endpoint) e `callback_allowed_ips`.

Em **produção** o `Config` recusa: `verify_ssl=false` e as credenciais de exemplo do sandbox (`MPesa2018`/`Mpesa2019`).

## Uso

```php
use Woomoza\Mpesa\Client;
use Woomoza\Mpesa\Exception\UnknownOutcomeException;

$mpesa = Client::fromFile(__DIR__ . '/config/config.php');

try {
    $r = $mpesa->c2b('10', '841234567');          // aceita 84x, 258x, +258x, 00258x
    if ($r->isSuccess()) { /* $r->transactionId, $r->thirdPartyReference */ }
    else                 { /* $r->code, $r->message(), $r->codeEnum() */ }
} catch (UnknownOutcomeException $e) {
    // timeout / 502 / 503 / 504: NÃO se sabe se o cliente foi cobrado. NÃO repita.
    $estado = $mpesa->query($e->thirdPartyReference);   // Completed | Cancelled | ...
}
```

| Método | Descrição |
|---|---|
| `c2b($valor, $msisdn, $ref = null, $tpRef = null)` | cobra ao cliente |
| `b2c($valor, $msisdn, ...)` | paga ao cliente |
| `b2b($valor, $shortcodeDestino, ...)` | paga a outra empresa |
| `reversal($transactionId, $valor = null)` | reverte (total sem valor) |
| `query($referencia)` | estado (TransactionID, ConversationID ou ThirdPartyReference) |
| `queryCustomerName($msisdn)` | nome mascarado do titular |

`$valor` aceita `int`, `float` ou `string` (`"10"`, `"10,50"`), máximo 2 decimais. As referências são geradas
automaticamente se não as indicar (curtas e únicas: `REF261109K3F9A`, 14 caracteres; `TP261109K3F9A`, 13). Para o
`TransactionReference` use de preferência **o ID do seu pedido** (ex.: `PED-12345`, máx. 20 caracteres). `Response::throwIfFailed()` lança `ApiException`.

## Regras de segurança do SDK

- **Pagamentos nunca são repetidos automaticamente** (c2b, b2c, b2b, reversal). Em resultado incerto lança
  `UnknownOutcomeException` com a `thirdPartyReference` para consultar com `query()`.
- **Consultas** (GET) são repetidas com backoff exponencial + jitter.
- Segredos nunca aparecem em `var_dump`/`print_r`/logs; o MSISDN é mascarado nos logs (`25884****567`).
- Logger opcional: qualquer objeto com `log($level, $message, $context)` (por exemplo, PSR-3).

## Exceções

`MpesaException` (base) → `ConfigException`, `ValidationException` (nada foi enviado), `NetworkException` →
`UnknownOutcomeException`, e `ApiException` (de `throwIfFailed()`).

## Callback assíncrono

Registe no portal (Account → Profile → *Asynchronous Response URL*) um URL **https** com **porta entre 11000 e 19000**,
por exemplo `https://seu-dominio:11443/callback.php`. Com URL registado, **todas** as chamadas da conta passam a ser assíncronas.

`public/callback.php` usa `CallbackHandler`: só POST, limite de tamanho, JSON válido, filtro de IPs opcional
(`callback_allowed_ips`), regista uma linha JSON **sem headers/cookies** e é idempotente (`$result->duplicate`).
O `root` do servidor web deve apontar para `public/`.

## CLI

```bash
php bin/mpesa c2b 841234567 10
php bin/mpesa query <referencia>
php bin/mpesa reversal <transactionId> [valor]
php bin/mpesa name 841234567
php bin/mpesa all            # todos os endpoints (recusa fora do sandbox)
```

## Qualidade

```bash
composer test    # PHPUnit
composer stan    # PHPStan nível 6
```

## Notas sobre a API

- O C2B no sandbox pode demorar 10–60 s e o gateway responder 504 ocasionalmente.
- `Query Customer Name` pode devolver `502 Forbidden` se o endpoint não estiver ativado na conta.
- O relatório do portal não guarda a `ThirdPartyReference` dos C2B: guarde as suas transações na sua base de dados.
- Para as credenciais de produção (shortcode, `initiator_identifier`, `security_credential`) contacte a Vodacom.
