{{-- Permintaan kode OTP untuk tindakan sensitif (email / WhatsApp). --}}
<div class="rounded-3 border p-3 mb-3" style="background:rgba(79,70,229,.03)">
  @if ($pending)
    <p class="mb-2" style="font-size:12px">
      <i class="fa-solid fa-circle-check text-success"></i>
      Kode dikirim lewat <b>{{ $pending['channel'] === 'whatsapp' ? 'WhatsApp' : 'email' }}</b>,
      berlaku sampai {{ \Carbon\Carbon::createFromTimestamp($pending['expires_at'])->format('H:i') }}.
    </p>
  @endif

  <form method="POST" action="{{ route('client.profile.otp') }}" class="d-flex flex-column gap-2">
    @csrf
    <input type="hidden" name="purpose" value="{{ $purpose }}">
    <p class="fw-medium text-dark mb-0" style="font-size:12px">{{ $pending ? 'Kirim ulang kode ke:' : 'Kirim kode verifikasi ke:' }}</p>
    @foreach ($channels as $channelKey => $channelLabel)
      <label class="d-flex align-items-center gap-2 mb-0" style="font-size:13px;cursor:pointer">
        <input type="radio" name="channel" value="{{ $channelKey }}" class="form-check-input mt-0" @checked($loop->first) required>
        {{ $channelLabel }}
      </label>
    @endforeach
    @if (count($channels) === 1)
      <p class="text-muted mb-0" style="font-size:11px">Ingin menerima lewat WhatsApp? Isi nomor WhatsApp di Data Akun (butuh gateway WhatsApp aktif).</p>
    @endif
    <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">
      <i class="fa-regular fa-paper-plane" style="font-size:11px"></i> {{ $pending ? 'Kirim Ulang' : 'Kirim Kode' }}
    </button>
  </form>
</div>
