<?php
// Autoload próprio (PSR-4) do SDK — não precisa de Composer nem vendor/.
spl_autoload_register(function (string $class): void {
    $prefix = 'Woomoza\\MobileMoney\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

const MPESA_CONFIG  = __DIR__ . '/config/config.php';
const MPESA_STORAGE = __DIR__ . '/storage';
