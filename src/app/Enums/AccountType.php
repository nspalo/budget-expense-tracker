<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case BankAccount = 'bank_account';
    case DebitCard = 'debit_card';
    case EWallet = 'e_wallet';
    case CreditCard = 'credit_card';
    case Cash = 'cash';
}
