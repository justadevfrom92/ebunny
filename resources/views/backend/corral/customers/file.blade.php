@extends('backend.layouts.app')

@section('crumb', $file->name)

{{-- One file on the account: its PDF (view, download, regenerate) in English or Spanish, print it, or email it to the customer. --}}
@section('content')
    @php $langs = \App\Support\CustomerDocument::LANGUAGES; @endphp
    @include('backend.partials.page-head', ['title' => $file->name, 'sub' => e($c->name).' · Account <a class="mono" href="'.route('corral.customers.show', $c).'">'.e($c->account).'</a> · added '.$file->created_at->format('n/j/Y'),
        'actions' => '<a class="btn ghost btn-back" href="'.route('corral.customers.show', $c).'#files">Back to Account</a>'
            .($pdf64 ? '<a class="btn cyan" href="'.route('corral.files.pdf', [$file, 'lang' => $lang]).'" target="_blank" rel="noopener" data-pdf-open>View PDF</a><a class="btn ghost" href="'.route('corral.files.pdf', [$file, 'lang' => $lang, 'download' => 1]).'" data-pdf-download>Download PDF</a>' : '')
            .($doc ? '<a class="btn ghost" href="'.route('corral.files.print', [$file, 'lang' => $lang]).'" target="_blank" rel="noopener">Print</a>' : '')
            .($file->path ? '<a class="btn ghost" href="'.route('corral.files.download', $file).'">Download</a>' : '')])

    <div class="grid-2" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
        <div class="stack" style="display:flex;flex-direction:column;gap:20px">
            <div class="panel"><div class="panel-head"><h2>{{ $doc ? 'PDF' : 'File' }}</h2>
                @if ($doc)<div class="chips small">@foreach ($langs as $k => $l)<a class="chip-link {{ $lang === $k ? 'on' : '' }}" href="{{ route('corral.files.show', [$file, 'lang' => $k]) }}">{{ $l }}</a>@endforeach</div>@endif</div>
                @if ($pdf64)
                    {{-- The page as drawn in the PDF; View PDF and Download PDF are built in the browser from the base64 PDF --}}
                    <div class="pdf-viewer" data-pdf64="{{ $pdf64 }}" data-pdf-name="{{ \App\Http\Controllers\Backend\Corral\CustomerFileController::pdfName($file, $lang) }}">
                        <div class="pdf-page">{!! $page !!}</div>
                    </div>
                @else<div class="panel-body"><p>Uploaded file <b class="mono">{{ $file->name }}</b>. Download it to open it; emailing sends it as an attachment.</p></div>@endif
            </div>
            @if ($pdf64)
            <div class="panel"><div class="panel-head"><h2>File Details</h2>
                <form method="post" action="{{ route('corral.files.regenerate', [$file, 'lang' => $lang]) }}" class="inline" data-confirm="Regenerate {{ $file->name }}?|The PDF is built again from the account as it is now: name, address, plan and latest bill.|Regenerate">@csrf<button class="btn sm ghost">Regenerate</button></form></div>
                <div class="panel-body"><dl class="kv"><dt>File Name</dt><dd class="mono">{{ $file->name }}</dd>
                    <dt>Type</dt><dd>{{ ['efl' => 'Electricity Facts Label', 'yrac' => 'Your Rights as a Customer', 'tos' => 'Terms of Service', 'welcome' => 'Welcome Packet', 'invoice' => 'Invoice'][$file->kind] ?? ucfirst($file->kind) }} · PDF</dd>
                    <dt>Size</dt><dd>{{ number_format(strlen(base64_decode($pdf64)) / 1024, 1) }} KB</dd>
                    <dt>Created</dt><dd>{{ $file->created_at->format('n/j/Y g:i A') }}</dd>
                    <dt>Last Generated</dt><dd>{{ ($file->generated_at ?? $file->created_at)->format('n/j/Y g:i A') }}@if ($file->regenerated) <span class="help">· regenerated {{ $file->regenerated }} {{ \Illuminate\Support\Str::plural('time', $file->regenerated) }}</span>@endif</dd>
                    <dt>Language</dt><dd>{{ $langs[$lang] }}</dd></dl></div>
            </div>
            @endif
        </div>

        <div class="stack" style="display:flex;flex-direction:column;gap:20px">
            <form method="post" action="{{ route('corral.files.send', $file) }}" class="panel">@csrf
                <div class="panel-head"><h2>Email to Customer</h2></div>
                <div class="panel-body" style="display:flex;flex-direction:column;gap:12px">
                    <div class="field"><label for="fs-email">To</label><input id="fs-email" name="email" type="email" required value="{{ old('email', $c->email) }}"></div>
                    <div class="field"><label>Language</label><div class="chips small">@foreach ($langs as $k => $l)<label><input type="radio" name="lang" value="{{ $k }}" @checked(old('lang', $lang) === $k)><span>{{ $l }}</span></label>@endforeach</div>
                        <div class="help">Their preferred language: {{ $c->language ?? 'English' }}.</div></div>
                    <div class="field"><label for="fs-note">Note (optional)</label><textarea id="fs-note" name="note" maxlength="1000" style="min-height:70px" placeholder="Added above the document"></textarea></div>
                    <div><button class="btn cyan">Send Email</button></div>
                </div>
            </form>
            <div class="panel"><div class="panel-head"><h2>Sent</h2></div><div class="table-wrap"><table><tbody>
                @forelse ($sent as $s)<tr><td class="nowrap">{{ $s->created_at->format('n/j/Y g:i A') }}</td><td>{{ \Illuminate\Support\Str::between($s->template, '(', ')') }}</td><td>{{ $s->user?->name ?? '—' }}</td></tr>
                @empty <tr><td class="empty">Not emailed yet.</td></tr> @endforelse
            </tbody></table></div></div>
        </div>
    </div>
@endsection
