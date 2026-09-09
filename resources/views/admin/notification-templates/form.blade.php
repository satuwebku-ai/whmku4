@extends('layouts.admin')

@section('title', 'Edit Template — ' . $meta['label'])

@section('content')

  <a href="{{ route('admin.notification-templates.index') }}" class="text-decoration-none text-muted" style="font-size:12px">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Template Notifikasi
  </a>

  <div class="d-flex align-items-center justify-content-between mt-2 mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h4 fw-bold text-dark mb-0">{{ $meta['label'] }}</h1>
      @if (! empty($meta['note']))
        <p class="mt-2 mb-0 rounded-3 px-3 py-2" style="font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;max-width:36rem">
          <i class="fa-solid fa-circle-info"></i> {{ $meta['note'] }}
        </p>
      @endif
    </div>
    <div class="d-flex align-items-center gap-2">
      @if ($isCustomized)
        <form method="POST" action="{{ route('admin.notification-templates.reset', $key) }}"
              data-confirm="Kembalikan ke kata-kata bawaan? Perubahan yang sudah kamu buat akan hilang." data-confirm-title="Reset Template" data-confirm-style="danger" data-confirm-label="Ya, Reset">
          @csrf
          <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="fa-solid fa-rotate-left" style="font-size:11px"></i> Reset ke Bawaan
          </button>
        </form>
      @endif
      <form method="POST" action="{{ route('admin.notification-templates.preview.draft', $key) }}" target="_blank" id="previewForm">
        @csrf
        <input type="hidden" name="subject" id="previewSubject">
        <input type="hidden" name="body_mail" id="previewBodyMail">
        <input type="hidden" name="body_whatsapp" id="previewBodyWhatsapp">
        <input type="hidden" name="body_sms" id="previewBodySms">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-eye" style="font-size:11px"></i> Lihat Pratinjau
        </button>
      </form>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <form method="POST" action="{{ route('admin.notification-templates.update', $key) }}" class="d-flex flex-column gap-3" id="editForm">
        @csrf

        @if (! is_null($meta['subject']))
          <div class="card border rounded-4 p-4">
            <label class="form-label small fw-medium text-dark">Subjek Email</label>
            <input type="text" name="subject" value="{{ old('subject', $effective['subject']) }}" class="form-control form-control-sm">
            @error('subject') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          </div>
        @endif

        <div class="card border rounded-4 p-4">
          <label class="form-label small fw-medium text-dark">Isi Email</label>
          <textarea name="body_mail" rows="12" class="form-control form-control-sm" style="font-family:monospace;font-size:12px;line-height:1.6">{{ old('body_mail', $effective['body_mail']) }}</textarea>
          @error('body_mail') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          <p class="text-muted mt-2 mb-0" style="font-size:11px">
            Satu baris = satu paragraf. Baris kosong dilewati (tidak jadi paragraf kosong).
            Untuk tombol aksi, tulis di baris tersendiri: <code class="bg-light px-1 rounded">[ACTION:Teks Tombol:{invoice_url}]</code>
          </p>
        </div>

        @if (! is_null($meta['body_whatsapp']))
          <div class="card border rounded-4 p-4">
            <label class="form-label small fw-medium text-dark">Isi Pesan WhatsApp</label>
            <textarea name="body_whatsapp" rows="6" class="form-control form-control-sm" style="font-family:monospace;font-size:12px;line-height:1.6">{{ old('body_whatsapp', $effective['body_whatsapp']) }}</textarea>
            @error('body_whatsapp') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            <p class="text-muted mt-2 mb-0" style="font-size:11px">
              Cuma dikirim kalau WhatsApp aktif di Pengaturan → Notifikasi. Pakai *bintang* untuk teks tebal (format asli WhatsApp).
            </p>
          </div>
        @endif

        @if (array_key_exists('body_sms', $meta) && ! is_null($meta['body_sms']))
          <div class="card border rounded-4 p-4">
            <label class="form-label small fw-medium text-dark">Isi Pesan SMS</label>
            <textarea name="body_sms" rows="3" class="form-control form-control-sm" style="font-family:monospace;font-size:12px;line-height:1.6" maxlength="1000">{{ old('body_sms', $effective['body_sms']) }}</textarea>
            @error('body_sms') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
            <p class="text-muted mt-2 mb-0" style="font-size:11px">
              Cuma dikirim kalau gateway SMS aktif di Pengaturan → Notifikasi. Tulis singkat & polos (tanpa *bintang*/format) —
              SMS dikenakan biaya per segmen 160 karakter, tiap segmen tambahan menambah biaya kirim.
            </p>
          </div>
        @endif

        <button type="submit" class="btn btn-primary" style="width:fit-content">
          <i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Template
        </button>
      </form>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4">
        <h2 class="small fw-bold text-dark mb-2">Variabel Tersedia</h2>
        <p class="text-muted mb-3" style="font-size:12px">Klik untuk menyalin, lalu tempel di isi email/WhatsApp.</p>
        <div class="d-flex flex-wrap gap-2">
          @foreach ($meta['variables'] as $v)
            <button type="button" data-copy-var="{{ $v }}"
                    class="copy-var-btn btn btn-outline-secondary" style="font-size:11px;font-family:monospace;padding:.25rem .5rem">
              {{ '{' . $v . '}' }}
            </button>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <script>
    document.querySelectorAll('.copy-var-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const text = '{' + btn.dataset.copyVar + '}';
        navigator.clipboard.writeText(text);

        const original = btn.textContent;
        btn.textContent = 'Disalin!';
        setTimeout(() => { btn.textContent = original; }, 1000);
      });
    });

    document.getElementById('previewForm').addEventListener('submit', function () {
      const mainForm = document.getElementById('editForm');
      document.getElementById('previewSubject').value = mainForm.querySelector('[name="subject"]')?.value || '';
      document.getElementById('previewBodyMail').value = mainForm.querySelector('[name="body_mail"]')?.value || '';
      document.getElementById('previewBodyWhatsapp').value = mainForm.querySelector('[name="body_whatsapp"]')?.value || '';
      document.getElementById('previewBodySms').value = mainForm.querySelector('[name="body_sms"]')?.value || '';
    });
  </script>

@endsection
