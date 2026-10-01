<?php

namespace App\Services\Chat;

use App\Models\AiChatUsage;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Draf balasan AI untuk ADMIN (bukan bot otomatis ke pengunjung): admin
 * menekan tombol, AI menulis draf di kolom balasan, admin mengedit lalu
 * mengirim sendiri. Memakai provider, model, dan konteks bisnis yang sama
 * dengan bot chat, ditambah template berstatus "dipakai AI".
 */
class AiReplyDrafter
{
    /**
     * Rapikan riwayat supaya valid untuk provider AI: dimulai dari pelanggan,
     * peran yang berurutan digabung, dan diakhiri pesan pelanggan.
     *
     * @param  array<int, array{role: string, content: string}>  $rows
     */
    public static function normalize(array $rows): array
    {
        $out = [];

        foreach ($rows as $r) {
            $text = trim((string) $r['content']);
            if ($text === '') {
                continue;
            }
            if ($out === [] && $r['role'] !== 'user') {
                continue;
            }
            if ($out !== [] && $out[count($out) - 1]['role'] === $r['role']) {
                $out[count($out) - 1]['content'] .= "\n\n" . $text;
            } else {
                $out[] = ['role' => $r['role'], 'content' => $text];
            }
        }

        if ($out !== [] && $out[count($out) - 1]['role'] !== 'user') {
            $out[] = ['role' => 'user', 'content' => '(Staf meminta draf balasan lanjutan untuk pelanggan ini.)'];
        }

        return $out;
    }

    public function available(): bool
    {
        $provider = Setting::get('ai_chat_provider', 'anthropic');
        $key = $provider === 'openai' ? 'ai_chat_openai_api_key' : 'ai_chat_api_key';

        return filled(Setting::get($key));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  riwayat, pesan terakhir dari pelanggan
     * @return array{ok: bool, text: ?string, message: string}
     */
    public function draft(array $messages, ?int $conversationId, string $customerName, string $channel): array
    {
        if (! $this->available()) {
            return ['ok' => false, 'text' => null, 'message' => 'Kunci API AI belum diisi di Pengaturan → Live Chat.'];
        }

        if ($messages === []) {
            return ['ok' => false, 'text' => null, 'message' => 'Belum ada pesan untuk dibalas.'];
        }

        $provider = AiProviderFactory::make();
        $providerKey = Setting::get('ai_chat_provider', 'anthropic');
        $model = Setting::get("ai_chat_model_{$providerKey}") ?: $provider->defaultModel();

        $site = Setting::get('site_name', 'layanan hosting ini');
        $system = "Anda membantu staf support {$site} menulis DRAF balasan untuk pelanggan bernama {$customerName} (kanal: {$channel}).\n\n"
            . "Aturan:\n"
            . "- Tulis langsung isi balasan dalam Bahasa Indonesia, sopan, jelas, dan ringkas. Tanpa pembuka seperti \"Berikut draf\".\n"
            . "- Staf akan membaca dan mengedit sebelum mengirim. Kalau ada informasi yang belum Anda ketahui (status akun, nominal, hasil pengecekan), tulis penanda [ISI: ...] supaya staf melengkapinya, jangan mengarang.\n"
            . "- Jangan menjanjikan harga, diskon, refund, atau tenggat yang tidak disebut di informasi bisnis.\n"
            . "- Jangan meminta password, nomor kartu, atau OTP.";

        $system .= AiChatService::businessKnowledge();

        try {
            $result = $provider->chat($messages, $system, $model);
        } catch (Throwable $e) {
            Log::warning('AI draf balasan: provider melempar exception — ' . $e->getMessage());

            return ['ok' => false, 'text' => null, 'message' => 'AI tidak bisa dihubungi saat ini.'];
        }

        if (! $result['success'] || blank($result['text'])) {
            return ['ok' => false, 'text' => null, 'message' => 'AI belum bisa membuat draf: ' . ($result['message'] ?? 'tanpa jawaban')];
        }

        try {
            AiChatUsage::create([
                'chat_conversation_id' => $conversationId,
                'model' => $model,
                'kind' => 'draft',
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
            ]);
        } catch (Throwable $e) {
            Log::warning('AI draf balasan: gagal mencatat pemakaian token — ' . $e->getMessage());
        }

        return ['ok' => true, 'text' => trim($result['text']), 'message' => 'OK'];
    }
}
