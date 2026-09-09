<?php

namespace App\Services\Chat\Contracts;

/**
 * Kontrak bersama untuk semua provider AI chat -- pola sama persis
 * dengan HostingPanelInterface (Server) & DomainRegistrarInterface
 * (Registrar): satu antarmuka, banyak implementasi, dipilih lewat
 * Factory. Menambah provider baru tidak perlu menyentuh AiChatService
 * atau ChatController sama sekali.
 */
interface AiProviderInterface
{
    /**
     * Kirim riwayat percakapan, terima balasan.
     *
     * @param  array  $messages  [['role' => 'user'|'assistant', 'content' => string], ...]
     * @return array{success: bool, text: ?string, input_tokens: int, output_tokens: int, message: string}
     *               message berisi teks error kalau success=false, atau "OK" kalau berhasil.
     */
    public function chat(array $messages, string $systemPrompt, string $model): array;

    /**
     * Model default yang dipakai kalau admin belum memilih model
     * spesifik -- tiap provider punya penamaan model sendiri, jadi
     * default-nya juga beda per provider.
     */
    public function defaultModel(): string;
}
