<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persyaratan dokumen yang bisa DIATUR ADMIN, menggantikan daftar
     * hardcoded di DomainDocument::requirements().
     *
     * Sebelumnya daftar dokumen tiap TLD ditulis langsung di kode, jadi
     * setiap kali PANDI/registrar mengubah aturan (atau admin mau minta
     * berkas tambahan), harus ganti kode dan deploy ulang. Sekarang
     * cukup diatur lewat Pengaturan -> Persyaratan.
     *
     * Tiga tabel:
     *  - document_requirements       : daftar jenis berkas (KTP, NIB, dst)
     *  - document_requirement_tld    : TLD mana butuh berkas apa
     *  - domain_documents.requirement_id : tiap file yang diupload klien
     *                                      menempel ke satu persyaratan,
     *                                      supaya bisa diverifikasi/
     *                                      ditolak SATU PER SATU.
     */
    public function up(): void
    {
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // mis. "KTP Penanggung Jawab"
            $table->text('description')->nullable();      // petunjuk untuk klien
            // Persyaratan opsional tetap ditampilkan ke klien, tapi tidak
            // menghalangi domain diproses kalau tidak diunggah -- mis.
            // "Sertifikat merek (kalau ada)".
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_requirement_tld', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_requirement_id')->constrained()->cascadeOnDelete();
            // Disimpan sebagai TEKS ekstensi, bukan foreign key ke tlds --
            // satu ekstensi sekarang bisa punya beberapa baris tld (satu
            // per registrar), sedangkan persyaratan dokumen ditentukan
            // REGISTRY, bukan registrar. Jadi ".co.id" cukup sekali,
            // berlaku untuk semua registrar yang menjualnya.
            $table->string('extension');                  // mis. ".co.id"
            $table->timestamps();

            $table->unique(['document_requirement_id', 'extension'], 'doc_req_tld_unique');
            $table->index('extension');
        });

        Schema::table('domain_documents', function (Blueprint $table) {
            // Nullable: file lama (sebelum sistem ini ada) tidak menempel
            // ke persyaratan mana pun, dan itu tidak apa-apa -- tetap
            // tampil sebagai berkas tanpa kategori.
            $table->foreignId('document_requirement_id')->nullable()->after('domain_id')
                ->constrained()->nullOnDelete();
        });

        // Isi awal dari daftar yang selama ini hardcoded, supaya begitu
        // migration jalan, aturan yang berlaku sekarang TIDAK hilang.
        $now = now();

        $seed = [
            'KTP / Identitas Penanggung Jawab' => [
                'desc' => 'KTP, Paspor, SIM, KITAS, atau KITAP milik penanggung jawab domain.',
                'required' => true,
                'tlds' => ['.ac.id', '.co.id', '.net.id', '.sch.id', '.or.id'],
            ],
            'SK Pendirian Lembaga' => [
                'desc' => 'Surat Keputusan pendirian dari instansi berwenang.',
                'required' => true,
                'tlds' => ['.ac.id'],
            ],
            'Surat Keterangan Pimpinan' => [
                'desc' => 'Surat keterangan dari rektor / kepala sekolah / pimpinan lembaga.',
                'required' => true,
                'tlds' => ['.ac.id', '.sch.id'],
            ],
            'Nomor Induk Berusaha (NIB)' => [
                'desc' => 'NIB atau dokumen legalitas badan usaha lain.',
                'required' => true,
                'tlds' => ['.co.id'],
            ],
            'Surat Izin Penyelenggaraan Telekomunikasi' => [
                'desc' => 'Izin penyelenggaraan usaha telekomunikasi.',
                'required' => true,
                'tlds' => ['.net.id'],
            ],
            'Surat Keterangan Laik Operasi' => [
                'desc' => 'Dokumen laik operasi dari instansi berwenang.',
                'required' => true,
                'tlds' => ['.net.id'],
            ],
            'Akta Pendirian Organisasi' => [
                'desc' => 'Akta atau surat keterangan pendirian organisasi.',
                'required' => true,
                'tlds' => ['.or.id'],
            ],
            'Sertifikat Merek' => [
                'desc' => 'Opsional — lampirkan kalau nama domain memakai merek terdaftar.',
                'required' => false,
                'tlds' => ['.co.id', '.net.id'],
            ],
        ];

        $order = 0;

        foreach ($seed as $name => $row) {
            $id = DB::table('document_requirements')->insertGetId([
                'name' => $name,
                'description' => $row['desc'],
                'is_required' => $row['required'],
                'is_active' => true,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($row['tlds'] as $ext) {
                DB::table('document_requirement_tld')->insert([
                    'document_requirement_id' => $id,
                    'extension' => $ext,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('domain_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_requirement_id');
        });

        Schema::dropIfExists('document_requirement_tld');
        Schema::dropIfExists('document_requirements');
    }
};
