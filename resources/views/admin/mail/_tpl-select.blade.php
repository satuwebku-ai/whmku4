@php $tplOptions = \App\Models\MailTemplate::orderBy('sort')->orderBy('id')->get(['id', 'title']); @endphp
@if ($tplOptions->isNotEmpty())
  <select id="tplPick" class="ix-tpl" title="Sisipkan template balasan">
    <option value="">⚡ Template…</option>
    @foreach ($tplOptions as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach
  </select>
@endif
