<?php

namespace App\Exceptions\Payment;

use App\Exceptions\Billing\BillingException;

class PaymentNotAllowedException extends BillingException
{
    public function __construct(
        string $message,
        public readonly ?int $invoiceId = null,
        public readonly ?int $blockingDomainId = null,
    ) {
        parent::__construct($message);
    }
}