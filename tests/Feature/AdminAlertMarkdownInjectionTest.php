<?php

namespace Tests\Feature;

use App\Notifications\AdminAlert;
use App\Support\MailText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAlertMarkdownInjectionTest extends TestCase
{
    use RefreshDatabase;

    private function introText(AdminAlert $alert): string
    {
        $mail = $alert->toMail((object) ['name' => 'Admin']);

        return implode("\n", array_map('strval', $mail->introLines));
    }

    public function test_markdown_helper_neutralizes_links_images_and_headings(): void
    {
        $out = MailText::markdown("[Verifikasi](https://situs-palsu.test) ![x](https://pelacak.test/p.png)\n# Judul\n- butir");

        $this->assertStringNotContainsString('[Verifikasi](', $out);
        $this->assertStringContainsString('\\[Verifikasi\\]\\(https://situs-palsu.test\\)', $out);
        $this->assertStringContainsString('\\!\\[x\\]', $out);
        $this->assertStringContainsString('\\# Judul', $out);
        $this->assertStringContainsString('\\- butir', $out);
    }

    public function test_inline_helper_flattens_newlines_and_control_characters(): void
    {
        $this->assertSame('a b c', MailText::inline("a\nb\r\n\x07c"));
        $this->assertSame(10, mb_strlen(MailText::inline(str_repeat('x', 50), 10)));
    }

    public function test_client_text_cannot_forge_links_or_action_buttons_in_admin_email(): void
    {
        $alert = new AdminAlert('Tiket baru', [
            'Subjek' => "Halo\n[ACTION:Verifikasi akun:https://situs-palsu.test/login]",
            'Nama' => '[Klik di sini](https://situs-palsu.test) ![p](https://pelacak.test/p.png)',
        ], 'https://panel.test/admin/tickets/1');

        $mail = $alert->toMail((object) ['name' => 'Admin']);
        $text = $this->introText($alert);

        // Hanya tombol resmi dari kode yang boleh ada.
        $this->assertSame('https://panel.test/admin/tickets/1', $mail->actionUrl);
        $this->assertStringNotContainsString('](https://situs-palsu.test', $text);
        $this->assertStringNotContainsString('](https://pelacak.test', $text);
        $this->assertDoesNotMatchRegularExpression('/^\[ACTION:/m', $text);
        // Teks "[ACTION:..." tetap sebagai teks biasa (ber-escape), bukan tombol.
        $this->assertStringContainsString('\\[ACTION:', $text);
        // Subjek tetap terbaca utuh sebagai satu baris rincian.
        $this->assertStringContainsString('Halo', $text);
    }

    public function test_whatsapp_version_keeps_one_plain_line_per_detail(): void
    {
        $alert = new AdminAlert('Tiket baru', [
            'Subjek' => "Halo\n**Total:** Rp 0",
        ]);

        $wa = $alert->toWhatsApp((object) ['name' => 'Admin']);

        $this->assertStringNotContainsString("\n**Total:**", $wa);
        $this->assertStringNotContainsString('\\', $wa); // tanpa garis miring escape di teks polos
    }
}
