<?php

declare(strict_types=1);

namespace Woomoza\Mpesa\Callback;

use Woomoza\Mpesa\Response;

/**
 * Valida e regista o callback assíncrono da Vodacom.
 *  - só POST (405); lista de IPs opcional (403); corpo ≤ $maxBytes (413); JSON objeto (400);
 *  - idempotente: o mesmo corpo (sha256) só é processado uma vez (duplicate = true);
 *  - grava uma linha JSON em <storage>/callback.log SEM headers/cookies.
 */
final class CallbackHandler
{
    /** @param string[] $allowedIps vazio = aceita qualquer IP (defina os IPs da Vodacom em produção) */
    public function __construct(
        private array $allowedIps = [],
        private int $maxBytes = 65536,
        private ?string $storageDir = null
    ) {
    }

    public function handle(string $method, string $ip, string $body): CallbackResult
    {
        if (strtoupper($method) !== 'POST') {
            return new CallbackResult(405, ['status' => 'method_not_allowed']);
        }
        if ($this->allowedIps !== [] && !in_array($ip, $this->allowedIps, true)) {
            $this->log(['event' => 'rejected', 'reason' => 'ip', 'ip' => $ip]);
            return new CallbackResult(403, ['status' => 'forbidden']);
        }
        if (strlen($body) > $this->maxBytes) {
            return new CallbackResult(413, ['status' => 'too_large']);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || $decoded === []) {
            $this->log(['event' => 'rejected', 'reason' => 'invalid_json', 'ip' => $ip]);
            return new CallbackResult(400, ['status' => 'invalid_json']);
        }

        $response  = Response::fromCallback($body);
        $duplicate = !$this->claim(hash('sha256', $body));
        $this->log([
            'event' => $duplicate ? 'duplicate' : 'received', 'ip' => $ip,
            'code' => $response->code, 'tx' => $response->transactionId,
            'conversation' => $response->conversationId, 'tp' => $response->thirdPartyReference,
            'body' => $decoded,
        ]);

        return new CallbackResult(200, ['status' => 'received'], $response, $duplicate);
    }

    /** Marca o corpo como visto; false se já existia (atómico via fopen 'x'). */
    private function claim(string $hash): bool
    {
        if ($this->storageDir === null) {
            return true;
        }
        $dir = $this->storageDir . '/callbacks';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return true;
        }
        $fh = @fopen($dir . '/' . $hash, 'x');
        if ($fh === false) {
            return false;
        }
        fclose($fh);
        return true;
    }

    /** @param array<string,mixed> $entry */
    private function log(array $entry): void
    {
        if ($this->storageDir === null) {
            return;
        }
        $line = json_encode(['at' => date('c')] + $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents($this->storageDir . '/callback.log', $line, FILE_APPEND | LOCK_EX);
    }
}
