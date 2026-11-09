# Changelog

## 1.0.0
- Pacote `woomoza/mobile-money-sdk`, namespace `Woomoza\MobileMoney`. Partes comuns a qualquer carteira em
  `Exception`, `Http` e `Support`; driver M-Pesa (Vodacom) em `Woomoza\MobileMoney\Mpesa`.
- Driver M-Pesa independente (sem bibliotecas de terceiros): C2B, B2C, B2B, Reversal (com `input_ReversalAmount`),
  Query Transaction Status e Query Customer Name.
- Hierarquia de exceções (`MobileMoneyException` e derivadas); `UnknownOutcomeException` em pagamentos de resultado
  incerto, sem retry automático.
- Transporte injetável (`HttpClient`), retry com backoff + jitter só em consultas, logger opcional com MSISDN mascarado.
- `Config` com segredos privados e mascarados, portas configuráveis e validações para produção.
- `CallbackHandler` com validação, filtro de IP, idempotência e log sem headers.
- CLI `bin/mpesa` com opções (`--config`, `--ref`, `--tp`, `--timeout`, `--json`, `-v`, `--yes`), comando `config`,
  proteção em produção e códigos de saída.
- Licença MIT, `SECURITY.md`, `CONTRIBUTING.md` e aviso de não afiliação.
- Testes PHPUnit (49) e PHPStan nível 6.
