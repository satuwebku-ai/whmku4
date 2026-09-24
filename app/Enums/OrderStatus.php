<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case RequirementsPending = 'requirements_pending';
    case RequirementsReview = 'requirements_review';
    case RequirementsRejected = 'requirements_rejected';
    case RequirementsApproved = 'requirements_approved';
    // Nilai lama masih dapat muncul dari order yang dibuat sebelum lifecycle
    // canonical diperkenalkan. Ia hanya boleh ditransisikan sekali ke
    // pending_payment, bukan dipakai oleh kode checkout baru.
    case LegacyPending = 'pending';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Provisioning = 'provisioning';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}