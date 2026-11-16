<?php

namespace App\Enums;

enum TransactionType: string
{
    case StudentPayment = 'student_payment';
    case PaymentReversal = 'payment_reversal';
    case LessonCharge = 'lesson_charge';
    case ChargeReversal = 'charge_reversal';
    case TeacherEarning = 'teacher_earning';
    case EarningReversal = 'earning_reversal';
    case TeacherPayout = 'teacher_payout';
    case Expense = 'expense';
    case OwnerWithdrawal = 'owner_withdrawal';
    case CashboxTransfer = 'cashbox_transfer';
    case BalanceTransfer = 'balance_transfer';
    case Refund = 'refund';
    case DebtWriteoff = 'debt_writeoff';
    case OpeningBalance = 'opening_balance';
}
