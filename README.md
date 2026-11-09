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

`$valor` aceita `int`, `float` ou `string` (`"10"`, `"10,50"`), máximo 2 decimais.

### Endpoints e portas

| Método do SDK | HTTP | Porta | Caminho |
|---|---|---|---|
| `c2b` | POST | 18352 | `/ipg/v1x/c2bPayment/singleStage/` |
| `b2c` | POST | 18345 | `/ipg/v1x/b2cPayment/` |
| `b2b` | POST | 18349 | `/ipg/v1x/b2bPayment/` |
| `reversal` | PUT | 18354 | `/ipg/v1x/reversal/` |
| `query` | GET | 18353 | `/ipg/v1x/queryTransactionStatus/` |
| `queryCustomerName` | GET | 19323 | `/ipg/v1x/queryCustomerName/` |

As portas podem ser sobrepostas no config (`'ports' => ['c2b' => 18352, ...]`).

### Referências

- **`TransactionReference`** (`$ref`): 1 a 20 caracteres. Use de preferência **o ID do seu pedido**, por exemplo
  `PED12345`. **Só letras e números:** nos testes no sandbox a API recusou `PED-1001` e `PED.1001` com `INS-17`.
- **`ThirdPartyReference`** (`$tpRef`): identifica a tentativa, deve ser **única** (repetir uma dá `INS-10`
  Duplicate) e é a chave para consultar com `query()`.
- Se não as indicar, o SDK gera ambas: curtas e únicas (`REF261109K3F9A`, 14 caracteres; `TP261109K3F9A`, 13).

### Resposta (`Response`)

| Propriedade / método | Conteúdo |
|---|---|
| `isSuccess()` | `true` com `INS-0` (síncrono) ou `ResultCode 0` (callback) |
| `code`, `description`, `message()` | código INS e descrição legível |
| `codeEnum()` | `ResponseCode` (enum) ou `null` |
| `transactionId`, `conversationId`, `thirdPartyReference` | identificadores da transação |
| `status` | estado devolvido por `query()`: `Completed`, `Cancelled`, `Pending`... |
| `customerName` | nome mascarado, de `queryCustomerName()` |
| `httpStatus`, `raw`, `data` | resposta HTTP bruta e JSON decodificado |
| `isGatewayError()` | a resposta não é JSON da API (ex.: página "Forbidden" do gateway) |
| `throwIfFailed()` | lança `ApiException` se não for sucesso |
| `toArray()` | resumo em array |

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

`php bin/mpesa <comando> [args] [opções]` — só corre em linha de comandos.

### Comandos

| Comando | Descrição |
|---|---|
| `c2b <msisdn> <valor>` | cobra ao cliente (Customer → Business) |
| `b2c <msisdn> <valor>` | paga ao cliente (Business → Customer) |
| `b2b <shortcode> <valor>` | paga a outra empresa (Business → Business) |
| `reversal <transactionId> [valor]` | reverte; sem valor, reversão total |
| `query <referencia>` | estado (TransactionID, ConversationID ou ThirdPartyReference) |
| `name <msisdn>` | nome (mascarado) do titular da carteira |
| `all [msisdn] [valor]` | testa todos os endpoints (**só sandbox**) |
| `config` | mostra a configuração ativa (sem segredos), as portas e se a `api_key`/chave pública geram o token |
| `help` | ajuda |

### Opções

| Opção | Descrição |
|---|---|
| `--config=FICHEIRO` | ficheiro de configuração (padrão: `config/config.php`) — útil para várias contas |
| `--ref=TEXTO` | `TransactionReference` (c2b, b2c, b2b); 1–20, só letras e números; padrão: gerada |
| `--tp=TEXTO` | `ThirdPartyReference` (todos os comandos); padrão: gerada |
| `--timeout=SEG` | timeout por pedido (sobrepõe a config) |
| `--json` | resultado em JSON, uma linha por pedido (para scripts) |
| `-v`, `--verbose` | mostra pedidos e respostas em STDERR (MSISDN mascarado, sem segredos) |
| `--yes` | confirma operações que movem dinheiro **fora do sandbox** |

### Exemplos

```bash
php bin/mpesa config                                         # confirma host, portas e credenciais
php bin/mpesa c2b 841234567 10 --ref=PED12345                # cobra 10 MZN
php bin/mpesa c2b 841234567 10 --json -v                     # JSON + log dos pedidos
php bin/mpesa query PED12345TP1                              # estado por ThirdPartyReference
php bin/mpesa reversal <transactionId>                       # reversão total
php bin/mpesa reversal <transactionId> 5                     # reversão parcial de 5 MZN
php bin/mpesa name 841234567                                 # nome mascarado do titular
php bin/mpesa all --config=config/outra-conta.php            # bateria completa noutra conta
php bin/mpesa c2b 841234567 10 --yes                         # em PRODUÇÃO exige --yes
```

### Códigos de saída

| Código | Significado |
|---|---|
| `0` | sucesso |
| `1` | erro da API ou de validação |
| `2` | resultado **incerto** (timeout/502/503/504 num pagamento): consulte com `query` antes de repetir |
| `3` | uso inválido (comando ou argumentos em falta, config não encontrada, falta `--yes` em produção) |

Com `--json`, a saída de cada pedido é `{"success":..., "http":..., "code":..., "description":..., "transaction_id":..., "conversation_id":..., "third_party_reference":..., "status":..., "customer_name":...}`.

## Lista de verificação para produção

1. `api_host` = `api.vm.co.mz`, com o `service_provider_code`, o `initiator_identifier` e o `security_credential`
   **reais** (fornecidos pela Vodacom). O `Config` recusa os valores de exemplo do sandbox.
2. `verify_ssl` ativo (o `Config` recusa desativá-lo fora do sandbox).
3. `config/config.php` fora do webroot e fora do git; renove a API key no portal se alguma vez foi partilhada.
4. Se usar callback: URL https com porta 11000–19000 registado no portal, `root` do servidor web em `public/`
   e `callback_allowed_ips` com os IPs da Vodacom.
5. Guarde cada transação na sua base de dados (referências, TransactionID, estado) e trate `UnknownOutcomeException`
   consultando `query()` antes de repetir qualquer pagamento.
6. Faça o "Go Live" no portal com transações bem-sucedidas no relatório.

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
