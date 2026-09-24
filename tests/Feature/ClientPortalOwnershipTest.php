<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_read_or_mutate_another_clients_core_resources(): void
    {
        $owner = Client::factory()->create();
        $attacker = Client::factory()->create();

        $invoice = Invoice::factory()->create(['client_id' => $owner->id]);
        $hosting = HostingAccount::factory()->create(['client_id' => $owner->id]);
        $domain = Domain::create([
            'client_id' => $owner->id,
            'domain_name' => 'owned-by-owner.test',
            'status' => 'active',
            'provision_status' => 'manual',
        ]);
        $ticket = Ticket::create([
            'client_id' => $owner->id,
            'subject' => 'Private ticket',
            'department' => 'support',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $this->actingAs($attacker, 'client');

        $this->get(route('client.invoices.show', $invoice))
            ->assertForbidden();
        $this->get(route('client.services.show', $hosting))
            ->assertForbidden();
        $this->get(route('client.domains.show', $domain))
            ->assertForbidden();
        $this->get(route('client.tickets.show', $ticket))
            ->assertForbidden();

        $this->post(route('client.tickets.close', $ticket))
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'open',
        ]);
    }

    public function test_ticket_attachment_requires_ticket_ownership_and_hides_internal_notes(): void
    {
        Storage::fake('local');

        $owner = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $ticket = Ticket::create([
            'client_id' => $owner->id,
            'subject' => 'Attachment access test',
            'department' => 'support',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $publicReply = $ticket->replies()->create([
            'client_id' => $owner->id,
            'message' => 'Public reply',
            'is_internal_note' => false,
        ]);
        $publicAttachment = $publicReply->attachments()->create([
            'path' => 'ticket-attachments/public.txt',
            'original_name' => 'public.txt',
            'mime_type' => 'text/plain',
            'size' => 6,
        ]);
        Storage::disk('local')->put($publicAttachment->path, 'public');

        $internalReply = $ticket->replies()->create([
            'message' => 'Internal note',
            'is_internal_note' => true,
        ]);
        $internalAttachment = $internalReply->attachments()->create([
            'path' => 'ticket-attachments/internal.txt',
            'original_name' => 'internal.txt',
            'mime_type' => 'text/plain',
            'size' => 8,
        ]);
        Storage::disk('local')->put($internalAttachment->path, 'internal');

        $this->actingAs($otherClient, 'client')
            ->get(route('client.ticket-attachments.file', $publicAttachment))
            ->assertForbidden();

        $this->actingAs($owner, 'client')
            ->get(route('client.ticket-attachments.file', $publicAttachment))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=public.txt');

        $this->get(route('client.ticket-attachments.file', $internalAttachment))
            ->assertNotFound();
    }
}