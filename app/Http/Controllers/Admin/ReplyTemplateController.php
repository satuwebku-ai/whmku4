<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailTemplate;
use App\Services\Chat\AiReplyDrafter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Menu "Template Balasan": teks support/helpdesk yang dipilih admin saat
 * membalas Email dan Live Chat, sekaligus bahan panduan bot AI.
 */
class ReplyTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $cat = trim((string) $request->query('category', ''));

        $templates = MailTemplate::query()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%");
            }))
            ->when($cat !== '', fn ($query) => $query->where('category', $cat))
            ->ordered()
            ->get();

        return view('admin.templates.index', [
            'templates' => $templates,
            'categories' => MailTemplate::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'q' => $q,
            'category' => $cat,
            'aiReady' => app(AiReplyDrafter::class)->available(),
            'aiTotal' => MailTemplate::active()->where('use_for_ai', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $data['sort'] = (int) MailTemplate::max('sort') + 1;

        MailTemplate::create($data);

        return redirect()->route('admin.templates.index')->with('success', 'Template ditambahkan.');
    }

    public function update(Request $request, MailTemplate $template): RedirectResponse
    {
        $template->update($this->data($request));

        return back()->with('success', 'Template diperbarui.');
    }

    public function toggle(Request $request, MailTemplate $template): RedirectResponse
    {
        $field = $request->validate(['field' => ['required', 'in:is_active,use_for_ai']])['field'];
        $template->update([$field => ! $template->{$field}]);

        return back()->with('success', 'Template diperbarui.');
    }

    public function duplicate(MailTemplate $template): RedirectResponse
    {
        MailTemplate::create([
            'title' => mb_substr($template->title . ' (salinan)', 0, 120),
            'category' => $template->category,
            'subject' => $template->subject,
            'body' => $template->body,
            'is_active' => false,
            'use_for_ai' => false,
            'sort' => (int) MailTemplate::max('sort') + 1,
        ]);

        return back()->with('success', 'Template diduplikasi (nonaktif sampai Anda aktifkan).');
    }

    public function destroy(MailTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['use_for_ai'] = $request->boolean('use_for_ai');

        return $data;
    }
}
