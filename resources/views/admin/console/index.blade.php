@extends('layouts.admin')

@section('title', 'Konsol Web')

@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Konsol Web</h1>
    <p class="small text-muted mb-0">
      Jalankan perintah pemeliharaan tanpa perlu Terminal/SSH — berguna kalau hosting-mu tidak menyediakan akses itu.
      Cuma perintah yang aman & sudah ditentukan yang bisa dijalankan dari sini.
    </p>
  </div>

  @if (session('output'))
    <div class="card border rounded-4 p-3 mb-4" style="background:#0f172a;color:#e2e8f0;font-family:monospace;font-size:12px;white-space:pre-wrap;overflow-x:auto">{{ session('output') ?: '(tidak ada keluaran)' }}</div>
  @endif

  <div class="card border rounded-4 p-4" style="max-width:36rem">
    <form method="POST" action="{{ route('admin.console.run') }}" id="consoleForm">
      @csrf
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Pilih Perintah</label>
        <select name="command" id="commandSelect" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem" required>
          <option value="">— Pilih —</option>
          @foreach ($commands as $cmd => $desc)
            <option value="{{ $cmd }}" @selected(old('command') === $cmd)>{{ $cmd }}</option>
          @endforeach
        </select>
        <p id="commandDesc" class="text-muted mt-1 mb-0" style="font-size:11px"></p>
      </div>

      <div id="argumentField" class="d-none mb-3">
        <label class="form-label small fw-medium text-dark">Alamat Email Tujuan</label>
        <input type="email" name="argument" value="{{ old('argument') }}" class="form-control form-control-sm" placeholder="kamu@email.com">
      </div>

      <div id="dryField" class="d-none mb-3">
        <label class="d-flex align-items-center gap-2 small text-dark mb-1">
          <input type="checkbox" name="dry" value="1" checked class="form-check-input" style="margin-top:0">
          Mode aman (simulasi) — tidak benar-benar mengubah apa pun, cuma menunjukkan yang akan terjadi
        </label>
        <p class="text-warning mb-0" style="font-size:11px">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Hilangkan centang untuk benar-benar menjalankan (perpanjangan/suspend/pengingat sungguhan).
        </p>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-terminal" style="font-size:11px"></i> Jalankan
      </button>
    </form>
  </div>

  <script>
    const descriptions = @json($commands);
    const dryRunCommands = @json($dryRunCommands);

    const select = document.getElementById('commandSelect');
    const descEl = document.getElementById('commandDesc');
    const argField = document.getElementById('argumentField');
    const dryField = document.getElementById('dryField');

    function sync() {
      const cmd = select.value;
      descEl.textContent = descriptions[cmd] || '';
      argField.classList.toggle('d-none', cmd !== 'lumora:test-mail');
      dryField.classList.toggle('d-none', !dryRunCommands.includes(cmd));
    }

    select.addEventListener('change', sync);
    sync();
  </script>

@endsection
