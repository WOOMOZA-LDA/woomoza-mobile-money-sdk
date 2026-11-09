# Contribuir

Obrigado pelo interesse! Este é um SDK open source, não oficial, para carteiras móveis de Moçambique.

## Preparar o ambiente

```bash
composer install
composer test    # PHPUnit (não chama as APIs dos operadores)
composer stan    # PHPStan nível 6
```

Requisitos: PHP ≥ 8.1 com `ext-curl`, `ext-openssl` e `ext-json`.

## Antes de abrir um pull request

1. Abra primeiro uma issue para mudanças grandes (por exemplo, um driver novo).
2. Mantenha `composer test` e `composer stan` verdes e acrescente testes para o que mudar.
   Os testes usam `FakeHttpClient`: nunca chamam a Vodacom nem outro operador.
3. **Sem segredos nem dados reais** em código, testes, exemplos ou mensagens de commit: use números de exemplo
   (`841234567`) e credenciais falsas.
4. Siga o estilo existente (`declare(strict_types=1)`, tipos em tudo, PSR-12).
5. Atualize o `CHANGELOG.md` e o `README.md` se o comportamento público mudar.

## Estrutura

```
src/Exception, src/Http, src/Support   partes comuns a qualquer carteira
src/Mpesa                               driver M-Pesa (Vodacom)
```

Um driver novo (por exemplo, e-Mola) vai para `src/<Operador>/`, reutiliza as partes comuns e traz os seus testes.

## Pagamentos

Pagamentos **nunca** são repetidos automaticamente: resultado incerto lança `UnknownOutcomeException`.
Qualquer alteração a esta regra tem de ser discutida numa issue.
