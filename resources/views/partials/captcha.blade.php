{{--
  Blok verifikasi "saya bukan robot" -- versi Bootstrap.

  Dipakai bersama di form login admin, login klien, dan pendaftaran
  yang sudah dimigrasikan ke Bootstrap. Tidak menampilkan apa-apa kalau
  CAPTCHA sedang tidak diperlukan.

  Variabel $captcha berasal dari controller (CaptchaService).
--}}

@if (!empty($captcha) && $captcha['required'])
  <div class="mb-3">
    @if ($captcha['recaptcha'])
      <div class="g-recaptcha" data-sitekey="{{ $captcha['site_key'] }}"></div>
      <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @else
      <label class="form-label small fw-medium text-dark">{{ $captcha['question'] }}</label>
      <input type="number" name="captcha_answer" required autocomplete="off"
             class="form-control form-control-sm" placeholder="Jawaban">
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        Pertanyaan sederhana ini muncul karena ada beberapa percobaan masuk yang gagal.
      </p>
    @endif

    @error('captcha') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
  </div>
@endif
