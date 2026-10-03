<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MailMessage;
use App\Models\Setting;
use App\Models\MailTemplate;
use App\Models\MailThread;
use App\Services\Mail\ChatMailMirror;
use App\Services\Mail\MailAutomation;
use App\Services\Mail\MailboxMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Inbox Email: surat masuk dari mailbox support (IMAP) ditampilkan sebagai
 * thread surat-menyurat, lengkap dengan balasan dan email baru dari admin.
 */
class MailboxController extends Controller
{
    private const ATTACHMENT_RULES = ['nullable', 'array', 'max:5'];

    public function index(Request $request): View
    {
        $closed = $request->query('status') === 'closed';
        $filter = $request->query('filter');
        $unreadOnly = ! $closed && $filter === 'unread';
        $sentOnly = ! $closed && $filter === 'sent';

        $folder = $closed ? 'closed' : ($unreadOnly ? 'unread' : ($sentOnly ? 'sent' : 'inbox'));

        $threads = MailThread::query()
            ->with(['client', 'latestMessage'])
            ->withCount('messages')
            ->when($closed, fn ($q) => $q->where('status', 'closed'))
            ->when(! $closed && ! $sentOnly, fn ($q) => $q->where('status', 'open'))
            ->when($unreadOnly, fn ($q) => $q->where('unread_count', '>', 0))
            ->when($sentOnly, fn ($q) => $q->whereHas('messages', fn ($m) => $m->where('direction', 'out')->where('is_auto', false)))
            ->when($request->query('search'), function ($q, $search) {
                $term = '%' . $search . '%';

                $q->where(function ($w) use ($term) {
                    $w->where('subject', 'like', $term)
                        ->orWhere('contact_email', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', $term));
                });
            })
            ->orderByRaw('(unread_count > 0) desc')
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'open' => MailThread::where('status', 'open')->count(),
            'unread' => MailThread::where('status', 'open')->where('unread_count', '>', 0)->count(),
            'closed' => MailThread::where('status', 'closed')->count(),
        ];

        return view('admin.mail.index', compact('threads', 'counts', 'folder'));
    }

    /**
     * Aksi massal dari daftar: arsipkan (tutup), buka kembali, atau hapus.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:close,reopen,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $threads = MailThread::whereIn('id', $data['ids'])->get();

        foreach ($threads as $thread) {
            match ($data['action']) {
                'close' => $thread->update(['status' => 'closed']),
                'reopen' => $thread->update(['status' => 'open']),
                'delete' => $this->destroyThread($thread),
            };
        }

        $label = ['close' => 'diarsipkan', 'reopen' => 'dibuka kembali', 'delete' => 'dihapus'][$data['action']];

        return back()->with('success', $threads->count() . ' email ' . $label . '.');
    }


    // ── Otomatisasi & template balasan ──────────────────────────

    public function settings(): View
    {
        $values = [];

        foreach (array_keys(MailAutomation::DEFAULTS) as $key) {
            $values[$key] = MailAutomation::get($key);
        }

        return view('admin.mail.settings', [
            'v' => $values,
            'templates' => MailTemplate::orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_autoreply_body' => ['required', 'string', 'max:3000'],
            'mail_autoclose_hours' => ['required', 'integer', 'between:1,720'],
            'mail_autoclose_body' => ['required', 'string', 'max:3000'],
            'mail_idle_grace_hours' => ['nullable', 'integer', 'between:1,336'],
            'mail_idle_prompt_body' => ['nullable', 'string', 'max:3000'],
        ]);

        // Kolom baru boleh kosong (form lama): pakai nilai bawaan.
        $data['mail_idle_grace_hours'] = $data['mail_idle_grace_hours'] ?? MailAutomation::DEFAULTS['mail_idle_grace_hours'];
        $data['mail_idle_prompt_body'] = filled($data['mail_idle_prompt_body'] ?? null)
            ? $data['mail_idle_prompt_body']
            : MailAutomation::DEFAULTS['mail_idle_prompt_body'];
        $data['mail_idle_prompt_enabled'] = $request->boolean('mail_idle_prompt_enabled') ? '1' : '0';

        $data['mail_autoreply_enabled'] = $request->boolean('mail_autoreply_enabled') ? '1' : '0';
        $data['mail_autoclose_enabled'] = $request->boolean('mail_autoclose_enabled') ? '1' : '0';
        $data['mail_autoclose_notice'] = $request->boolean('mail_autoclose_notice') ? '1' : '0';

        Setting::putMany(array_map('strval', $data), 'email');

        return back()->with('success', 'Pengaturan otomatisasi email disimpan.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $this->templateData($request);
        $data['sort'] = (int) MailTemplate::max('sort') + 1;

        MailTemplate::create($data);

        return back()->with('success', 'Template ditambahkan.');
    }

    public function updateTemplate(Request $request, MailTemplate $template): RedirectResponse
    {
        $template->update($this->templateData($request));

        return back()->with('success', 'Template diperbarui.');
    }

    public function destroyTemplate(MailTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }

    private function templateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
    }

    public function show(MailThread $thread): View
    {
        $thread->load(['client', 'messages.admin']);

        // Dibuka admin = surat sudah dibaca.
        if ($thread->unread_count > 0) {
            $thread->update(['unread_count' => 0]);
        }

        return view('admin.mail.show', ['thread' => $thread]);
    }

    public function reply(Request $request, MailThread $thread): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'attachments' => self::ATTACHMENT_RULES,
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,txt,zip'],
        ]);

        $lastInbound = $thread->messages()->where('direction', 'in')->latest('id')->first();

        try {
            MailboxMailer::send(
                $thread,
                $data['subject'],
                $data['body'],
                $request->file('attachments', []),
                Auth::guard('admin')->user(),
                $lastInbound?->message_id,
            );

            // Thread berasal dari widget chat: balasan juga tampil di widget pengunjung.
            ChatMailMirror::toWidget($thread, 'admin', $data['body'], Auth::guard('admin')->id());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Email gagal terkirim dan balasan belum disimpan. Periksa Pengaturan → Email (SMTP), lalu coba lagi.');
        }

        return back()->with('success', 'Balasan terkirim ke ' . $thread->contact_email . '.');
    }

    public function create(Request $request): View
    {
        return view('admin.mail.compose', ['to' => $request->query('to')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to_email' => ['required', 'email', 'max:255'],
            'to_name' => ['nullable', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'attachments' => self::ATTACHMENT_RULES,
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,txt,zip'],
        ]);

        $email = strtolower(trim($data['to_email']));
        $client = Client::where('email', $email)->first();

        $thread = MailThread::create([
            'subject' => MailThread::normalizeSubject($data['subject']),
            'contact_email' => $email,
            'contact_name' => ($data['to_name'] ?? null) ?: $client?->name,
            'client_id' => $client?->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        try {
            MailboxMailer::send(
                $thread,
                $data['subject'],
                $data['body'],
                $request->file('attachments', []),
                Auth::guard('admin')->user(),
            );
        } catch (Throwable $e) {
            report($e);
            $thread->delete();

            return back()->withInput()->with('error', 'Email gagal terkirim. Periksa Pengaturan → Email (SMTP), lalu coba lagi.');
        }

        return redirect()->route('admin.mail.show', $thread)->with('success', 'Email terkirim ke ' . $email . '.');
    }

    public function close(MailThread $thread): RedirectResponse
    {
        $thread->update(['status' => 'closed']);

        return redirect()->route('admin.mail')->with('success', 'Email dipindahkan ke Ditutup.');
    }

    public function reopen(MailThread $thread): RedirectResponse
    {
        $thread->update(['status' => 'open']);

        return back()->with('success', 'Email dibuka kembali.');
    }

    public function destroy(MailThread $thread): RedirectResponse
    {
        $this->destroyThread($thread);

        return redirect()->route('admin.mail')->with('success', 'Email dihapus.');
    }

    private function destroyThread(MailThread $thread): void
    {
        foreach ($thread->messages as $message) {
            foreach ($message->attachments ?? [] as $file) {
                Storage::disk('local')->delete($file['path'] ?? '');
            }
        }

        $thread->delete();
    }

    public function attachment(MailMessage $mailMessage, int $index): StreamedResponse
    {
        $file = ($mailMessage->attachments ?? [])[$index] ?? null;

        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        // download() memaksa Content-Disposition: attachment, jadi lampiran
        // dari pengirim luar tidak pernah dirender di domain admin.
        return Storage::disk('local')->download($file['path'], $file['name']);
    }
}
