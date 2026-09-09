@extends('layouts.admin')

@section('title', 'Backup')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Backup</h1>
      <p class="small text-muted mb-0">Cadangan database + seluruh file upload (bukti bayar, dokumen domain, logo), otomatis tiap hari jam 03:00.</p>
    </div>
    <form method="POST" action="{{ route('admin.backups.run') }}"
          data-confirm="Buat cadangan sekarang? Prosesnya bisa memakan waktu beberapa menit tergantung ukuran data." data-confirm-title="Backup Sekarang" data-confirm-style="info" data-confirm-label="Ya, Mulai">
      @csrf
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-database" style="font-size:12px"></i> Backup Sekarang
      </button>
    </form>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 overflow-hidden">
        <div class="px-4 py-3 border-bottom">
          <h2 class="small fw-bold text-dark mb-0">Daftar Cadangan</h2>
        </div>
        <div>
          @forelse ($backups as $backup)
            <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
              <div class="d-flex align-items-center gap-3 min-w-0">
                <i class="fa-solid fa-file-zipper text-muted"></i>
                <div class="min-w-0">
                  <p class="small text-dark text-truncate mb-0">{{ $backup['name'] }}</p>
                  <p class="text-muted mb-0" style="font-size:12px">{{ $backup['created_at']->format('d M Y H:i') }} — {{ $backup['size'] }} MB</p>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <a href="{{ route('admin.backups.download', $backup['name']) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Unduh">
                  <i class="fa-solid fa-download" style="font-size:12px"></i>
                </a>
                <form method="POST" action="{{ route('admin.backups.destroy', $backup['name']) }}"
                      data-confirm="Hapus cadangan {{ $backup['name'] }}? Tidak bisa dibatalkan." data-confirm-title="Hapus Cadangan" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                    <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                  </button>
                </form>
              </div>
            </div>
          @empty
            <p class="text-center text-muted small py-5 mb-0">Belum ada cadangan. Klik "Backup Sekarang" untuk membuat yang pertama.</p>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-3">Pengaturan</h2>
        <form method="POST" action="{{ route('admin.backups.settings') }}">
          @csrf
          <label class="d-flex align-items-center gap-2 small text-dark mb-3">
            <input type="checkbox" name="backup_enabled" value="1" @checked($enabled) class="form-check-input" style="margin-top:0">
            Backup otomatis tiap hari (03:00)
          </label>
          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">Simpan Berapa Cadangan Terakhir</label>
            <input type="number" name="backup_retention" value="{{ $retention }}" min="1" max="60" class="form-control form-control-sm">
            <p class="text-muted mt-1 mb-0" style="font-size:11px">Cadangan lebih lama dari ini dihapus otomatis, supaya tidak menghabiskan kuota penyimpanan.</p>
          </div>
          <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Simpan Pengaturan</button>
        </form>
      </div>

      <div class="card border rounded-4 p-4 mt-3" style="background:#fffbeb;border-color:#fde68a!important">
        <p class="mb-0" style="font-size:12px;color:#92400e">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Cadangan ini tersimpan di server yang <b>sama</b> dengan aplikasi. Kalau server bermasalah total
          (bukan cuma aplikasinya), cadangan ini bisa ikut hilang. Untuk perlindungan penuh, unduh cadangan
          secara berkala dan simpan di tempat terpisah (komputer sendiri, Google Drive, dll) — atau aktifkan
          unggah otomatis ke Google Drive di bawah.
        </p>
      </div>

      <div class="card border rounded-4 p-4 mt-3">
        <h2 class="small fw-bold text-dark mb-3">
          <i class="fa-brands fa-google-drive"></i> Unggah Otomatis ke Google Drive
        </h2>

        <form method="POST" action="{{ route('admin.backups.gdrive-settings') }}">
          @csrf
          <label class="d-flex align-items-center gap-2 small text-dark mb-3">
            <input type="checkbox" name="backup_gdrive_enabled" value="1" @checked($gdrive['enabled']) class="form-check-input" style="margin-top:0">
            Aktifkan unggah otomatis setelah tiap backup
          </label>
          <div class="mb-2">
            <label class="form-label small fw-medium text-dark">Client ID</label>
            <input type="text" name="backup_gdrive_client_id" value="{{ $gdrive['client_id'] }}" class="form-control form-control-sm" style="font-size:11px" placeholder="xxxxx.apps.googleusercontent.com">
          </div>
          <div class="mb-2">
            <label class="form-label small fw-medium text-dark">Client Secret</label>
            <input type="password" name="backup_gdrive_client_secret" value="{{ $gdrive['client_secret'] }}" class="form-control form-control-sm" style="font-size:11px">
          </div>
          <div class="mb-2">
            <label class="form-label small fw-medium text-dark">Refresh Token</label>
            <input type="password" name="backup_gdrive_refresh_token" value="{{ $gdrive['refresh_token'] }}" class="form-control form-control-sm" style="font-size:11px">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium text-dark">Nama Folder di Drive</label>
            <input type="text" name="backup_gdrive_folder" value="{{ $gdrive['folder'] }}" class="form-control form-control-sm" style="font-size:11px">
          </div>
          <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Simpan</button>
        </form>

        <form method="POST" action="{{ route('admin.backups.gdrive-test') }}" class="mt-2">
          @csrf
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fa-solid fa-plug" style="font-size:11px"></i> Coba Sambungkan
          </button>
        </form>

        <details class="mt-3 small text-muted">
          <summary style="cursor:pointer" class="fw-medium text-dark">Cara mendapatkan Client ID, Secret, & Refresh Token</summary>
          <ol class="mt-2 ps-3" style="font-size:12px">
            <li class="mb-1">Buka <a href="https://console.cloud.google.com/" target="_blank" class="text-accent">Google Cloud Console</a>, buat proyek baru (atau pakai yang sudah ada).</li>
            <li class="mb-1">Aktifkan <b>Google Drive API</b> lewat menu "APIs &amp; Services" → "Enable APIs".</li>
            <li class="mb-1">Buat kredensial: "APIs &amp; Services" → "Credentials" → "Create Credentials" → "OAuth client ID" → pilih tipe <b>"Desktop app"</b>.</li>
            <li class="mb-1">Catat <b>Client ID</b> dan <b>Client Secret</b> yang muncul.</li>
            <li class="mb-1">Buka <a href="https://developers.google.com/oauthplayground/" target="_blank" class="text-accent">Google OAuth Playground</a> → klik ikon gerigi (kanan atas) → centang "Use your own OAuth credentials" → isi Client ID &amp; Secret dari langkah 4.</li>
            <li class="mb-1">Di panel kiri, cari &amp; pilih scope <code>https://www.googleapis.com/auth/drive.file</code> → klik "Authorize APIs" → login dengan akun Google tujuan penyimpanan.</li>
            <li class="mb-1">Klik "Exchange authorization code for tokens" → salin nilai <b>Refresh Token</b> yang muncul.</li>
            <li class="mb-1">Buat folder baru di Google Drive-mu untuk menampung backup, catat namanya, isi di kolom "Nama Folder di Drive" di atas.</li>
          </ol>
        </details>
      </div>
    </div>
  </div>

@endsection
