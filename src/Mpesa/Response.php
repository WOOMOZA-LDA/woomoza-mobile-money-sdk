<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Mpesa;

use Woomoza\MobileMoney\Exception\ApiException;

/** Resposta normalizada (síncrona ou corpo de um callback assíncrono). Imutável. */
final class Response
{
    /** @var array<string,mixed> */
    public readonly array $data;
    public readonly string $code;
    public readonly string $description;
    public readonly string $transactionId;
    public readonly string $conversationId;
    public readonly string $thirdPartyReference;
    /** Query Transaction Status: Completed, Cancelled, Pending... */
    public readonly string $status;
    /** Query Customer Name (mascarado pela Vodacom). */
    public readonly string $customerName;

    public function __construct(public readonly string $raw, public readonly int $httpStatus = 200)
    {
        $d = json_decode($raw, true);
        $this->data = is_array($d) ? $d : [];

        // Assíncrono usa output_ResultCode/ResultDesc; síncrono usa ResponseCode/ResponseDesc.
        $this->code                = (string) ($this->data['output_ResponseCode'] ?? $this->data['output_ResultCode'] ?? '');
        $this->description         = (string) ($this->data['output_ResponseDesc'] ?? $this->data['output_ResultDesc'] ?? '');
        $this->transactionId       = (string) ($this->data['output_TransactionID'] ?? '');
        $this->conversationId      = (string) ($this->data['output_ConversationID'] ?? '');
        $this->thirdPartyReference = (string) ($this->data['output_ThirdPartyReference'] ?? '');
        $this->status              = (string) ($this->data['output_ResponseTransactionStatus'] ?? '');
        $this->customerName        = (string) ($this->data['output_CustomerName'] ?? '');
    }

    /** Sucesso: INS-0 (síncrono) ou ResultCode 0 (assíncrono). */
    public function isSuccess(): bool
    {
        return $this->code === ResponseCode::Success->value || $this->code === '0';
    }

    /** A resposta não é JSON da API (ex.: página "Forbidden" do gateway). */
    public function isGatewayError(): bool
    {
        return $this->code === '';
    }

    public function codeEnum(): ?ResponseCode
    {
        return ResponseCode::tryFrom($this->code);
    }

    public function message(): string
    {
        if ($this->isGatewayError()) {
            $txt = trim(preg_replace('/\s+/', ' ', strip_tags(substr($this->raw, 0, 4000))) ?? '');
            return "HTTP {$this->httpStatus}: " . substr($txt, 0, 80);
        }
        return $this->description !== '' ? $this->description : ($this->codeEnum()?->description() ?? '');
    }

    /** @throws ApiException se não for sucesso */
    public function throwIfFailed(): self
    {
        if (!$this->isSuccess()) {
            throw new ApiException($this);
        }
        return $this;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccess(), 'http' => $this->httpStatus, 'code' => $this->code,
            'description' => $this->message(), 'transaction_id' => $this->transactionId,
            'conversation_id' => $this->conversationId, 'third_party_reference' => $this->thirdPartyReference,
            'status' => $this->status, 'customer_name' => $this->customerName,
        ];
    }

    /** Interpreta o corpo JSON recebido num callback. */
    public static function fromCallback(string $body): self
    {
        return new self($body, 200);
    }
}
