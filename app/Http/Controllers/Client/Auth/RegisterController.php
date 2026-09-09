<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Setting;
use App\Notifications\VerifyEmailCode;
use App\Services\Security\CaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request, CaptchaService $captcha): View
    {
        return view('client.auth.register', [
            'captcha' => LoginController::buildCaptchaData($request, $captcha),
        ]);
    }

    public function createBootstrap(Request $request, CaptchaService $captcha): View
    {
        return view('client.auth.register', [
            'captcha' => LoginController::buildCaptchaData($request, $captcha),
        ]);
    }

    public function store(Request $request, CaptchaService $captcha): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:clients,email'],
            'phone'    => ['required', 'string', 'max:30'],
            'company'  => ['nullable', 'string', 'max:255'],
            'address'  => ['nullable', 'string', 'max:500'],
            'city'     => ['nullable', 'string', 'max:120'],
            'state'    => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country'  => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms'    => ['accepted'],
        ], [
            'terms.accepted' => 'Anda harus menyetujui syarat & ketentuan untuk mendaftar.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.',
        ]);

        if ($pesan = $captcha->verify($request)) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['captcha' => $pesan]);
        }

        $client = Client::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'],
            'company'  => $data['company'] ?? null,
            'address'  => $data['address'] ?? null,
            'city'     => $data['city'] ?? null,
            'state'    => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country'  => $data['country'] ?? 'Indonesia',
            'password' => $data['password'],
            'status'   => 'active',
        ]);

        // Pemberitahuan ke admin tetap dikirim sekarang; email sambutan
        // ditunda sampai alamatnya terbukti valid (lihat VerifyEmailController).
        try {
            app(\App\Services\Notification\NotificationService::class)->clientRegistered($client);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notifikasi pendaftaran gagal: ' . $e->getMessage());
        }

        // Verifikasi email wajib: akun dibuat, tapi belum bisa dipakai
        // sampai pemiliknya membuktikan alamat email itu benar miliknya.
        if (Setting::get('require_email_verification', '1') === '1') {
            try {
                $client->notify(new VerifyEmailCode($client->generateResetCode()));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Kode verifikasi gagal dikirim: ' . $e->getMessage());
            }

            $request->session()->put('verify.email', $client->email);

            return redirect()->route('client.verify.notice')
                ->with('success', 'Akun dibuat. Kami mengirim kode verifikasi ke ' . $client->email . '.');
        }

        Auth::guard('client')->login($client);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard')
            ->with('success', 'Akun berhasil dibuat. Selamat datang!');
    }
}
