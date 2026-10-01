<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\Client;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Services\Mail\InboundMailProcessor;
use App\Services\Mail\MimeMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MailboxTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'superadmin'): Admin
    {
        return Admin::create([
            'username' => 'mail-' . $role, 'name' => 'Staf Mail', 'email' => $role . '@contoh.test',
            'password' => bcrypt('Rahasia-123'), 'role' => $role, 'is_active' => true,
        ]);
    }

    private function mime(string $from, string $subject, string $body = 'Halo, saya butuh bantuan.', array $headers = []): MimeMessage
    {
        $lines = array_merge([
            'From: ' . $from,
            'To: support@satucloudhosting.com',
            'Subject: ' . $subject,
            'Date: Thu, 01 Oct 2026 10:00:00 +0000',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
        ], $headers);

        return MimeMessage::parse(implode("\r\n", $lines) . "\r\n\r\n" . $body);
    }

    private function process(MimeMessage $mail): string
    {
        return app(InboundMailProcessor::class)->process($mail);
    }

    private function thread(array $overrides = []): MailThread
    {
        return MailThread::create(array_merge([
            'subject' => 'Pertanyaan hosting',
            'contact_email' => 'budi@contoh.test',
            'contact_name' => 'Budi',
            'status' => 'open',
            'last_message_at' => now(),
        ], $overrides));
    }

    // ── Email masuk ─────────────────────────────────────────────

    public function test_new_email_becomes_a_mail_thread_not_a_chat(): void
    {
        $result = $this->process($this->mime('"Budi" <Budi@Contoh.test>', 'Pertanyaan hosting'));

        $this->assertStringStartsWith('mail:', $result);
        $this->assertSame(0, ChatConversation::count());

        $thread = MailThread::firstOrFail();
        $this->assertSame('budi@contoh.test', $thread->contact_email);
        $this->assertSame('Budi', $thread->contact_name);
        $this->assertSame('Pertanyaan hosting', $thread->subject);
        $this->assertSame(1, $thread->unread_count);
        $this->assertSame('in', $thread->messages()->first()->direction);
    }

    public function test_known_client_is_linked_to_the_thread(): void
    {
        $client = Client::factory()->create(['email' => 'klien@contoh.test']);

        $this->process($this->mime('klien@contoh.test', 'Tagihan'));

        $this->assertSame($client->id, MailThread::firstOrFail()->client_id);
    }

    public function test_re_prefixed_email_with_same_subject_joins_the_same_thread(): void
    {
        $this->process($this->mime('budi@contoh.test', 'Pertanyaan hosting'));
        $this->process($this->mime('budi@contoh.test', 'Re: Pertanyaan hosting', 'Terima kasih.'));

        $this->assertSame(1, MailThread::count());
        $this->assertSame(2, MailMessage::count());
        $this->assertSame(2, MailThread::first()->unread_count);
    }

    public function test_in_reply_to_header_joins_thread_even_when_subject_changes(): void
    {
        $thread = $this->thread();
        $thread->messages()->create([
            'direction' => 'out', 'from_email' => 'noreply@satucloudhosting.com', 'to_email' => 'budi@contoh.test',
            'subject' => 'Pertanyaan hosting', 'body' => 'Silakan dicek.', 'message_id' => 'abc123@satucloudhosting.com',
        ]);

        $result = $this->process($this->mime(
            'budi@contoh.test', 'Subjek sudah diganti pelanggan', 'Sudah saya cek.',
            ['In-Reply-To: <abc123@satucloudhosting.com>'],
        ));

        $this->assertSame('mail:' . $thread->id, $result);
        $this->assertSame(1, MailThread::count());
    }

    public function test_token_in_subject_joins_thread_and_reopens_a_closed_one(): void
    {
        $thread = $this->thread(['status' => 'closed']);

        $result = $this->process($this->mime('budi@contoh.test', 'Re: Beda subjek ' . $thread->token()));

        $this->assertSame('mail:' . $thread->id, $result);
        $this->assertSame('open', $thread->fresh()->status);
    }

    public function test_token_from_a_different_sender_does_not_join_the_thread(): void
    {
        $thread = $this->thread();

        $result = $this->process($this->mime('orang.lain@contoh.test', 'Re: Pertanyaan ' . $thread->token()));

        $this->assertNotSame('mail:' . $thread->id, $result);
        $this->assertSame(0, $thread->messages()->count());
        $this->assertSame(2, MailThread::count());
    }

    public function test_automated_cpanel_mail_is_ignored(): void
    {
        $result = $this->process($this->mime('cPanel <cpanel@satucloudhosting.com>', '[satucloudhosting.com] Client configuration settings'));

        $this->assertStringStartsWith('ignored:', $result);
        $this->assertSame(0, MailThread::count());
        $this->assertSame(0, ChatConversation::count());
    }

    public function test_reply_to_a_live_chat_email_still_goes_to_that_chat(): void
    {
        $chat = ChatConversation::create([
            'name' => 'Tamu', 'email' => 'tamu@contoh.test', 'channel' => 'email',
            'status' => 'open', 'last_message_at' => now(),
        ]);

        $result = $this->process($this->mime('tamu@contoh.test', 'Re: Balasan dari Situs [CHAT-' . $chat->id . ']'));

        $this->assertSame('chat:' . $chat->id, $result);
        $this->assertSame(0, MailThread::count());
    }

    // ── Halaman admin ───────────────────────────────────────────

    public function test_pages_render_and_opening_a_thread_marks_it_read(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->process($this->mime('"Budi" <budi@contoh.test>', 'Pertanyaan hosting', "Baris satu\nBaris dua"));
        $thread = MailThread::firstOrFail();

        $this->get(route('admin.mail'))->assertOk()->assertSee('Pertanyaan hosting')->assertSee('budi@contoh.test');
        $this->get(route('admin.mail', ['filter' => 'unread']))->assertOk()->assertSee('Pertanyaan hosting');
        $this->get(route('admin.mail', ['search' => 'Baris dua']))->assertOk()->assertSee('Pertanyaan hosting');
        // Subjek juga muncul di notifikasi aktivitas pada header, jadi yang dicek
        // adalah state kosong dan tidak adanya baris thread (alamat pengirim).
        $this->get(route('admin.mail', ['search' => 'tidak-ada-ini']))
            ->assertOk()->assertSee('Tidak ada email yang cocok.')->assertDontSee('budi@contoh.test');
        $this->get(route('admin.mail.compose'))->assertOk()->assertSee('Tulis Email');

        $this->assertSame(1, $thread->unread_count);
        $this->get(route('admin.mail.show', $thread))->assertOk()->assertSee('Baris dua')->assertSee('Kirim Balasan');
        $this->assertSame(0, $thread->fresh()->unread_count);
    }

    public function test_email_body_is_escaped(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->process($this->mime('budi@contoh.test', 'XSS', '<script>alert(1)</script>'));

        $this->get(route('admin.mail.show', MailThread::firstOrFail()))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_admin_without_support_module_is_forbidden(): void
    {
        $this->actingAs($this->admin('finance'), 'admin');

        $this->get(route('admin.mail'))->assertForbidden();
    }

    public function test_close_reopen_and_delete(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $thread = $this->thread();

        $this->post(route('admin.mail.close', $thread))->assertRedirect(route('admin.mail'));
        $this->assertSame('closed', $thread->fresh()->status);

        $this->post(route('admin.mail.reopen', $thread))->assertRedirect();
        $this->assertSame('open', $thread->fresh()->status);

        $this->delete(route('admin.mail.delete', $thread))->assertRedirect(route('admin.mail'));
        $this->assertSame(0, MailThread::count());
    }

    // ── Kirim email ─────────────────────────────────────────────

    public function test_reply_sends_a_threaded_email_and_records_it(): void
    {
        $this->actingAs($admin = $this->admin(), 'admin');
        $thread = $this->thread();
        $thread->messages()->create([
            'direction' => 'in', 'from_email' => 'budi@contoh.test', 'to_email' => 'support@satucloudhosting.com',
            'subject' => 'Pertanyaan hosting', 'body' => 'Tolong dibantu.', 'message_id' => 'abc123@mail.contoh.test',
        ]);

        $this->post(route('admin.mail.reply', $thread), [
            'subject' => 'Re: Pertanyaan hosting',
            'body' => 'Sudah kami proses.',
        ])->assertRedirect()->assertSessionHas('success');

        $out = $thread->messages()->where('direction', 'out')->firstOrFail();
        $this->assertSame($admin->id, $out->admin_id);
        $this->assertSame('Sudah kami proses.', $out->body);
        $this->assertSame('Re: Pertanyaan hosting', $out->subject);   // token tidak ikut tersimpan

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);

        $message = $sent->first()->getOriginalMessage();
        $this->assertSame('Re: Pertanyaan hosting ' . $thread->token(), $message->getSubject());
        $this->assertSame('budi@contoh.test', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('abc123@mail.contoh.test', $message->getHeaders()->get('In-Reply-To')->getBodyAsString());
        $this->assertStringContainsString($out->message_id, $message->getHeaders()->get('Message-ID')->getBodyAsString());
    }

    public function test_reply_with_attachment_stores_and_sends_the_file(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin(), 'admin');
        $thread = $this->thread();

        $this->post(route('admin.mail.reply', $thread), [
            'subject' => 'Re: Pertanyaan hosting',
            'body' => 'Terlampir panduannya.',
            'attachments' => [UploadedFile::fake()->create('panduan.pdf', 20, 'application/pdf')],
        ])->assertRedirect()->assertSessionHas('success');

        $file = $thread->messages()->firstOrFail()->attachments[0];
        $this->assertSame('panduan.pdf', $file['name']);
        Storage::disk('local')->assertExists($file['path']);
    }

    public function test_failed_send_saves_nothing_and_keeps_the_text(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $thread = $this->thread();

        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->from(route('admin.mail.show', $thread))
            ->post(route('admin.mail.reply', $thread), ['subject' => 'Re: X', 'body' => 'Jangan hilang.'])
            ->assertRedirect(route('admin.mail.show', $thread))
            ->assertSessionHas('error')
            ->assertSessionHasInput('body', 'Jangan hilang.');

        $this->assertSame(0, $thread->messages()->count());
    }

    public function test_reply_requires_subject_and_body(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.mail.reply', $this->thread()), ['subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'body']);
    }

    public function test_compose_creates_a_thread_and_sends_the_email(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $client = Client::factory()->create(['email' => 'klien@contoh.test']);

        $response = $this->post(route('admin.mail.store'), [
            'to_email' => 'Klien@Contoh.test',
            'subject' => 'Info perpanjangan',
            'body' => 'Layanan Anda akan jatuh tempo.',
        ]);

        $thread = MailThread::firstOrFail();
        $response->assertRedirect(route('admin.mail.show', $thread));
        $this->assertSame('klien@contoh.test', $thread->contact_email);
        $this->assertSame($client->id, $thread->client_id);
        $this->assertSame('out', $thread->messages()->firstOrFail()->direction);
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
    }

    public function test_compose_failure_leaves_no_thread_behind(): void
    {
        $this->actingAs($this->admin(), 'admin');
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->post(route('admin.mail.store'), ['to_email' => 'a@contoh.test', 'subject' => 'Hai', 'body' => 'Halo'])
            ->assertSessionHas('error');

        $this->assertSame(0, MailThread::count());
    }

    // ── Lampiran ────────────────────────────────────────────────

    public function test_attachment_is_always_served_as_a_download(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin(), 'admin');

        Storage::disk('local')->put('mail/x.txt', 'isi berkas');
        $message = $this->thread()->messages()->create([
            'direction' => 'in', 'from_email' => 'budi@contoh.test', 'to_email' => 'support@satucloudhosting.com',
            'body' => 'lihat lampiran',
            'attachments' => [['path' => 'mail/x.txt', 'name' => 'catatan.txt', 'mime' => 'text/plain', 'size' => 10]],
        ]);

        $response = $this->get(route('admin.mail.attachment', [$message, 0]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->get(route('admin.mail.attachment', [$message, 5]))->assertNotFound();
    }

    public function test_deleting_a_thread_removes_its_attachment_files(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin(), 'admin');

        Storage::disk('local')->put('mail/y.txt', 'isi');
        $thread = $this->thread();
        $thread->messages()->create([
            'direction' => 'in', 'from_email' => 'budi@contoh.test', 'to_email' => 'support@satucloudhosting.com',
            'body' => 'x', 'attachments' => [['path' => 'mail/y.txt', 'name' => 'y.txt', 'mime' => 'text/plain', 'size' => 3]],
        ]);

        $this->delete(route('admin.mail.delete', $thread));

        Storage::disk('local')->assertMissing('mail/y.txt');
    }
}
