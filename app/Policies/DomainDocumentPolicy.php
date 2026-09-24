<?php

namespace App\Policies;

use App\Models\DomainDocument;
use App\Models\Client;

class DomainDocumentPolicy
{
    public function view(?Client $user, DomainDocument $document): bool
    {
        return (int) auth('client')->id() === (int) $document->domain?->client_id
            || (int) auth('admin')->id() > 0;
    }

    public function delete(?Client $user, DomainDocument $document): bool
    {
        return $this->view($user, $document) && $document->status !== 'approved';
    }
}