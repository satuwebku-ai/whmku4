<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}