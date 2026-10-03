@props(['url'])
@php
    $mailLogo = \App\Models\Setting::get('site_logo');
    $mailShowLogo = \App\Models\Setting::get('email_show_logo', '1') === '1';
    $mailLogoH = max(16, min(200, (int) \App\Models\Setting::get('email_logo_height', 40)));
    $mailSite = \App\Models\Setting::get('site_name', config('app.name'));
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($mailLogo && $mailShowLogo)
<img src="{{ route('branding.file', $mailLogo) }}" alt="{{ $mailSite }}" height="{{ $mailLogoH }}" style="height: {{ $mailLogoH }}px; width: auto; max-width: 100%; border: 0; display: block; margin: 0 auto;">
@else
{{ $mailSite }}
@endif
</a>
</td>
</tr>
