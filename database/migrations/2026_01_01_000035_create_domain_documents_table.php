<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TLD Indonesia tertentu (.co.id, .ac.id, dst) mewajibkan dokumen
     * identitas/legalitas diverifikasi PANDI sebelum domain aktif — di
     * luar API Liqu.id sama sekali (dikonfirmasi: tidak ada endpoint
     * upload dokumen di spesifikasi resmi mereka). Klien upload ke sini,
     * admin tinjau, baru diteruskan manual ke Liqu.id.
     */
    public function up(): void
    {
        Schema::create('domain_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        // Ditaruh terpisah dari status approve/reject per file — soalnya
        // persyaratan tiap TLD ada yang bersifat "atau" (mis. .sch.id)
        // dan ada yang opsional (mis. sertifikat merek "kalau ada"),
        // jadi tidak bisa disimpulkan otomatis dari jumlah file yang
        // disetujui. Ini keputusan admin yang eksplisit: "dokumennya
        // sudah cukup lengkap, lanjutkan pendaftaran" — bukan hasil
        // hitungan sistem.
        Schema::table('domains', function (Blueprint $table) {
            $table->timestamp('documents_verified_at')->nullable()->after('eligibility_extra');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('documents_verified_at');
        });

        Schema::dropIfExists('domain_documents');
    }
};
