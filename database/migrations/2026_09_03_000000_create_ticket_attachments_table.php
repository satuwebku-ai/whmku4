<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_reply_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        // Pindahkan lampiran tunggal yang sudah ada (kolom lama di
        // ticket_replies) ke tabel baru, supaya riwayat tiket lama tidak
        // kehilangan berkasnya saat kolom lama dihapus di bawah.
        $now = now();
        DB::table('ticket_replies')
            ->whereNotNull('attachment_path')
            ->get(['id', 'attachment_path', 'attachment_name'])
            ->each(function ($reply) use ($now) {
                DB::table('ticket_attachments')->insert([
                    'ticket_reply_id' => $reply->id,
                    'path'            => $reply->attachment_path,
                    'original_name'   => $reply->attachment_name ?: basename($reply->attachment_path),
                    'mime_type'       => null,
                    'size'            => null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            });

        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
        });

        // Rollback best-effort: kembalikan lampiran pertama tiap balasan ke
        // kolom lama (kolom lama cuma menampung satu berkas per balasan).
        DB::table('ticket_attachments')
            ->orderBy('id')
            ->get(['ticket_reply_id', 'path', 'original_name'])
            ->groupBy('ticket_reply_id')
            ->each(function ($rows, $replyId) {
                $first = $rows->first();
                DB::table('ticket_replies')->where('id', $replyId)->update([
                    'attachment_path' => $first->path,
                    'attachment_name' => $first->original_name,
                ]);
            });

        Schema::dropIfExists('ticket_attachments');
    }
};
