<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Mpesa;

/** Códigos de resposta da documentação M-Pesa (tabela "Response Codes & Descriptions"). */
enum ResponseCode: string
{
    case Success = 'INS-0';
    case InternalError = 'INS-1';
    case InvalidApiKey = 'INS-2';
    case UserNotActive = 'INS-4';
    case CancelledByCustomer = 'INS-5';
    case TransactionFailed = 'INS-6';
    case RequestTimeout = 'INS-9';
    case DuplicateTransaction = 'INS-10';
    case InvalidShortcode = 'INS-13';
    case InvalidReference = 'INS-14';
    case InvalidAmount = 'INS-15';
    case TemporaryOverload = 'INS-16';
    case InvalidTransactionReference = 'INS-17';
    case InvalidTransactionId = 'INS-18';
    case InvalidThirdPartyReference = 'INS-19';
    case MissingParameters = 'INS-20';
    case ParameterValidationFailed = 'INS-21';
    case InvalidOperationType = 'INS-22';
    case UnknownStatus = 'INS-23';
    case InvalidInitiator = 'INS-24';
    case InvalidSecurityCredential = 'INS-25';
    case NotAuthorized = 'INS-26';
    case DirectDebitMissing = 'INS-993';
    case DirectDebitExists = 'INS-994';
    case CustomerProfileProblem = 'INS-995';
    case CustomerAccountInactive = 'INS-996';
    case LinkingTransactionNotFound = 'INS-997';
    case InvalidMarket = 'INS-998';
    case InitiatorAuthError = 'INS-2001';
    case InvalidReceiver = 'INS-2002';
    case InsufficientBalance = 'INS-2006';
    case InvalidMsisdn = 'INS-2051';
    case InvalidLanguage = 'INS-2057';

    public function description(): string
    {
        return match ($this) {
            self::Success => 'Request processed successfully',
            self::InternalError => 'Internal Error',
            self::InvalidApiKey => 'Invalid API Key',
            self::UserNotActive => 'User is not active',
            self::CancelledByCustomer => 'Transaction cancelled by customer',
            self::TransactionFailed => 'Transaction Failed',
            self::RequestTimeout => 'Request timeout',
            self::DuplicateTransaction => 'Duplicate Transaction',
            self::InvalidShortcode => 'Invalid Shortcode Used',
            self::InvalidReference => 'Invalid Reference Used',
            self::InvalidAmount => 'Invalid Amount Used',
            self::TemporaryOverload => 'Unable to handle the request due to a temporary overloading',
            self::InvalidTransactionReference => 'Invalid Transaction Reference. Length Should Be Between 1 and 20',
            self::InvalidTransactionId => 'Invalid TransactionID Used',
            self::InvalidThirdPartyReference => 'Invalid ThirdPartyReference Used',
            self::MissingParameters => 'Not All Parameters Provided',
            self::ParameterValidationFailed => 'Parameter validations failed',
            self::InvalidOperationType => 'Invalid Operation Type',
            self::UnknownStatus => 'Unknown Status. Contact M-Pesa Support',
            self::InvalidInitiator => 'Invalid InitiatorIdentifier Used',
            self::InvalidSecurityCredential => 'Invalid SecurityCredential Used',
            self::NotAuthorized => 'Not authorized',
            self::DirectDebitMissing => 'Direct Debit Missing',
            self::DirectDebitExists => 'Direct Debit Already Exists',
            self::CustomerProfileProblem => "Customer's Profile Has Problems",
            self::CustomerAccountInactive => 'Customer Account Status Not Active',
            self::LinkingTransactionNotFound => 'Linking Transaction Not Found',
            self::InvalidMarket => 'Invalid Market',
            self::InitiatorAuthError => 'Initiator authentication error',
            self::InvalidReceiver => 'Receiver invalid',
            self::InsufficientBalance => 'Insufficient balance',
            self::InvalidMsisdn => 'MSISDN invalid',
            self::InvalidLanguage => 'Language code invalid',
        };
    }

    /** Erro temporário: faz sentido tentar mais tarde (a mesma referência pode ser reutilizada só se o pedido não foi processado). */
    public function isRetryable(): bool
    {
        return in_array($this, [self::InternalError, self::RequestTimeout, self::TemporaryOverload], true);
    }
}
