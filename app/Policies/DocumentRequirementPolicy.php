<?php

namespace App\Policies;

use App\Models\DocumentRequirement;
use App\Models\Admin;

class DocumentRequirementPolicy
{
    public function viewAny(?Admin $user): bool
    {
        return (int) auth('admin')->id() > 0;
    }

    public function manage(?Admin $user, DocumentRequirement $requirement): bool
    {
        return (int) auth('admin')->id() > 0;
    }
}