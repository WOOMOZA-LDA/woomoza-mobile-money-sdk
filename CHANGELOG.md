# Changelog

## 1.0.0
- SDK independente (sem `abdulmueid/mpesa`): `Client`, `Config`, `Response`, `ResponseCode`.
- Endpoints: C2B, B2C, B2B, Reversal (com `input_ReversalAmount`), Query Transaction Status, Query Customer Name.
- Hierarquia de exceções; `UnknownOutcomeException` em pagamentos de resultado incerto (sem retry automático).
- Transporte injetável (`HttpClient`), retry com backoff + jitter só em consultas, logger opcional com MSISDN mascarado.
- `Config` com segredos privados e mascarados, portas configuráveis, validações para produção.
- `CallbackHandler` com validação, filtro de IP, idempotência e log sem headers.
- CLI `bin/mpesa`; testes PHPUnit (49) e PHPStan nível 6.
