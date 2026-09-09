{{--
    File ini MENGGANTIKAN komponen bawaan `mail::header`, jadi isinya
    harus berupa markah header itu sendiri.

    JANGAN memanggil komponen "mail::header" dari dalam file ini — itu
    membuat komponen memanggil DIRINYA SENDIRI tanpa henti (rekursi),
    yang menghabiskan memori server sampai fatal error. Kesalahan itu
    pernah terjadi dan menyebabkan seluruh pendaftaran klien gagal
    dengan HTTP 500, karena setiap pendaftaran memicu pengiriman email.
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@php
    $siteLogo = \App\Models\Setting::get('site_logo');
    $siteName = \App\Models\Setting::get('site_name', config('app.name'));
@endphp
@if ($siteLogo)
<img src="{{ route('branding.file', $siteLogo) }}" class="logo" alt="{{ $siteName }}" style="max-height:64px;max-width:280px;">
@else
{{ $siteName }}
@endif
</a>
</td>
</tr>
