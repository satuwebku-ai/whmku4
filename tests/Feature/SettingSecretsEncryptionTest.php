<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingSecretsEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_many_stores_secret_keys_encrypted_and_reads_them_back_in_plain_text(): void
    {
        Setting::putMany(['wa_token' => 'rahasia-123', 'site_name' => 'Toko'], 'notification');

        $raw = DB::table('settings')->where('key', 'wa_token')->first();
        $this->assertNotSame('rahasia-123', $raw->value);
        $this->assertTrue((bool) $raw->is_encrypted);
        $this->assertSame('rahasia-123', Setting::get('wa_token'));

        // Setting biasa tetap teks biasa.
        $this->assertSame('Toko', DB::table('settings')->where('key', 'site_name')->value('value'));
    }

    public function test_put_encrypts_secret_keys_even_without_the_flag(): void
    {
        Setting::put('vapid_private_key', 'kunci-privat', 'notification');

        $this->assertNotSame('kunci-privat', DB::table('settings')->where('key', 'vapid_private_key')->value('value'));
        $this->assertSame('kunci-privat', Setting::get('vapid_private_key'));
    }

    public function test_overwriting_an_encrypted_key_with_put_many_no_longer_reads_back_null(): void
    {
        Setting::put('site_note', 'lama', 'general', true);
        Setting::putMany(['site_note' => 'baru'], 'general');

        $this->assertSame('baru', Setting::get('site_note'));
    }

    public function test_encrypt_settings_command_converts_legacy_plaintext_rows(): void
    {
        DB::table('settings')->insert(['key' => 'wa_token', 'value' => 'lama-plain', 'group' => 'notification', 'is_encrypted' => false, 'created_at' => now(), 'updated_at' => now()]);
        Setting::flushCache();

        $this->artisan('lumora:encrypt-settings')->assertSuccessful();

        $this->assertNotSame('lama-plain', DB::table('settings')->where('key', 'wa_token')->value('value'));
        $this->assertSame('lama-plain', Setting::get('wa_token'));

        $this->artisan('lumora:encrypt-settings')->expectsOutput('Tidak ada rahasia yang perlu dienkripsi.')->assertSuccessful();
    }
}
