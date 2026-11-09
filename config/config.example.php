<?php
// Credenciais M-Pesa Moçambique (Vodacom). Preencha antes de rodar c2b.php.
// Sandbox: api.sandbox.vm.co.mz | Produção: api.vm.co.mz
return [
    'public_key'            => '',
    'api_host'              => 'api.sandbox.vm.co.mz',
    'api_key'               => '',
    'origin'                => 'developer.mpesa.vm.co.mz',
    'service_provider_code' => '171717', // exemplo da doc (sandbox); troque pelo seu shortcode real em produção
    'initiator_identifier'  => '',
    'security_credential'   => '',
    // Opcionais:
    // 'timeout'              => 60,
    // 'retries'              => 2,          // só consultas (GET)
    // 'verify_ssl'           => true,       // false só no sandbox
    // 'ports'                => ['c2b' => 18352],  // sobrepõe a porta de um endpoint
    // 'callback_allowed_ips' => [],         // IPs da Vodacom autorizados no callback.php
];
