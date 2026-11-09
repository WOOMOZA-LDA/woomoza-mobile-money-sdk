<?php

declare(strict_types=1);

// Endpoint do "Asynchronous Response URL" registado no portal M-Pesa.
require __DIR__ . '/../bootstrap.php';

use Woomoza\MobileMoney\Mpesa\Callback\CallbackHandler;
use Woomoza\MobileMoney\Mpesa\Config;

$allowed = [];
try {
    $allowed = Config::fromFile(MPESA_CONFIG)->callbackAllowedIps();
} catch (Throwable) {
    // sem config legível: segue sem filtro de IP, o corpo continua a ser validado
}

$result = (new CallbackHandler($allowed, 65536, MPESA_STORAGE))->handle(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REMOTE_ADDR'] ?? '-',
    (string) file_get_contents('php://input', false, null, 0, 65537)
);

// Aqui a sua aplicação atualiza o pedido/pagamento usando $result->response
// (ignore se $result->duplicate for true).

http_response_code($result->httpStatus);
header('Content-Type: application/json');
echo json_encode($result->body);
