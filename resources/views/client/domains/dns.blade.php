@extends('client.layout')
@section('title', 'DNS — ' . $domain->domain_name)

@section('content')
  <a href="{{ route('client.domains.show', $domain) }}" class="text-decoration-none text-muted" style="font-size:12px">
    &larr; Kembali ke {{ $domain->domain_name }}
  </a>

  <div class="mt-2 mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Kelola DNS — {{ $domain->domain_name }}</h1>
    <p class="text-muted mb-0">
      Hanya berlaku kalau domain memakai nameserver bawaan registrar. Kalau nameserver sudah
      diarahkan ke penyedia lain (mis. server hosting Anda), kelola DNS di sana, bukan di sini.
    </p>
  </div>

  @if ($warning)
    <div class="card-public p-4 mb-4" style="border-color:#fde68a!important;background:#fffbeb">
      <p class="mb-0" style="font-size:14px;color:#92400e">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Sebagian data tidak bisa diambil: {{ $warning }}
      </p>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="card-public overflow-hidden">
        <div class="px-4 py-3 border-bottom">
          <h2 class="small fw-bold text-dark mb-0">Record Saat Ini</h2>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                <th class="px-4 py-3">Tipe</th>
                <th class="py-3">Host</th>
                <th class="py-3">Nilai</th>
                <th class="text-end px-4 py-3">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($records as $record)
                <tr>
                  <td class="px-4 py-3"><span class="badge badge-soft-secondary">{{ $record['type'] }}</span></td>
                  <td class="py-3" style="font-family:monospace;font-size:12px">{{ $record['hostname'] }}</td>
                  <td class="py-3" style="font-family:monospace;font-size:12px;word-break:break-all">
                    {{ $record['value'] }}
                    @if ($record['priority'])
                      <span class="text-muted">(prioritas {{ $record['priority'] }})</span>
                    @endif
                  </td>
                  <td class="text-end px-4 py-3">
                    <form method="POST" action="{{ route('client.domains.dns.delete', $domain) }}"
                          data-confirm="Hapus record {{ $record['type'] }} {{ $record['hostname'] }}?"
                          data-confirm-title="Hapus Record DNS" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                      @csrf @method('DELETE')
                      <input type="hidden" name="type" value="{{ $record['type'] }}">
                      <input type="hidden" name="hostname" value="{{ $record['hostname'] }}">
                      <input type="hidden" name="value" value="{{ $record['value'] }}">
                      <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;padding:0">
                        <i class="fa-regular fa-trash-can" style="font-size:11px"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted py-5">Belum ada record DNS kustom.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Tambah Record</h2>

        <form method="POST" action="{{ route('client.domains.dns.add', $domain) }}" class="d-flex flex-column gap-3" id="dnsForm">
          @csrf

          <div>
            <label class="form-label">Tipe</label>
            <select name="type" id="dnsType" class="form-select">
              @foreach ($types as $t)
                <option value="{{ $t }}">{{ $t }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="form-label">Host</label>
            <input type="text" name="hostname" required placeholder="@ atau www" class="form-control">
            <p class="text-muted mt-1 mb-0" style="font-size:11px">Isi "@" untuk domain utama.</p>
          </div>

          <div>
            <label class="form-label">Nilai</label>
            <input type="text" name="value" required placeholder="192.0.2.1" class="form-control">
          </div>

          <div id="priorityField" class="d-none">
            <label class="form-label">Prioritas (khusus MX)</label>
            <input type="number" name="priority" min="0" value="10" class="form-control">
          </div>

          @error('type') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          @error('hostname') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror
          @error('value') <p class="text-danger mb-0" style="font-size:12px">{{ $message }}</p> @enderror

          <button type="submit" class="btn btn-theme w-100">
            <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Record
          </button>
        </form>
      </div>

      <div class="card-public p-4 mt-4 text-muted" style="font-size:12px;line-height:1.7">
        <p class="fw-bold text-dark mb-1">Contoh nilai per tipe</p>
        <p class="mb-1"><b>A</b> — alamat IP server, mis. <code>203.0.113.10</code></p>
        <p class="mb-1"><b>AAAA</b> — alamat IPv6 server, mis. <code>2001:db8::1</code></p>
        <p class="mb-1"><b>CNAME</b> — nama domain lain, mis. <code>contoh.com</code></p>
        <p class="mb-1"><b>MX</b> — server email, mis. <code>mail.contoh.com</code></p>
        <p class="mb-0"><b>TXT</b> — teks verifikasi, mis. <code>v=spf1 include:_spf.google.com ~all</code></p>
      </div>
    </div>
  </div>

  <script>
    // Kolom prioritas hanya relevan untuk MX.
    (function () {
      const type = document.getElementById('dnsType');
      const field = document.getElementById('priorityField');

      function sync() { field.classList.toggle('d-none', type.value !== 'MX'); }

      type.addEventListener('change', sync);
      sync();
    })();
  </script>
@endsection
