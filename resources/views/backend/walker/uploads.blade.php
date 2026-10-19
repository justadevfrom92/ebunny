@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => $category ?? 'All Files', 'sub' => [
            'ETL' => 'Files from the scheduled feeds: what came in and what went out. Each feed\'s run history is under ETL Feeds.',
            'Legal Documents' => 'Terms of service, required disclosures and policies. Keep the current version here.',
            'Customer Templates' => 'Letters and notices sent to customers, like the monthly payment reminder. {{merge.fields}} are filled in for each customer.',
        ][$category] ?? 'Documents and report files: ETL files, legal documents, customer templates, finance workbooks, utility and vendor reports.',
        'actions' => '<a class="btn cyan" href="'.route('walker.uploads.create', $category ? ['category' => $category] : []).'">Upload a File</a>'])

    <div class="chips small" style="margin-bottom:14px"><a class="chip-link {{ $category ? '' : 'on' }}" href="{{ route('walker.uploads.index') }}">All · {{ $counts->sum() }}</a>@foreach (config('walker.upload_categories') as $c)@if ($counts[$c] ?? 0)@php $slug = array_search($c, config('walker.folders'), true); @endphp<a class="chip-link {{ $category === $c ? 'on' : '' }}" href="{{ $slug ? route('walker.folder', $slug) : route('walker.uploads.index', ['category' => $c]) }}">{{ $c }} · {{ $counts[$c] }}</a>@endif @endforeach</div>
    <div>
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>File</th><th>Folder</th><th>Name</th><th>Added</th><th></th></tr></thead>
            <tbody>@forelse ($uploads as $u)
                <tr><td><b>{{ $u->title }}</b>@if ($u->description)<br><span class="help">{{ $u->description }}</span>@endif</td><td>{{ $u->category }}</td>
                    <td class="mono help">{{ $u->original_name }}<br>{{ number_format($u->size / 1024, 1) }} KB</td>
                    <td>{{ $u->created_at->format('n/j/Y') }}<br><span class="help">{{ $u->user?->name }}</span></td>
                    <td class="actions">@include('backend.partials.report-actions', ['size' => 'row', 'download' => route('walker.uploads.download', $u), 'downloadLabel' => 'Download '.$u->original_name, 'rerun' => null])
                        @can('delete')<form method="post" action="{{ route('walker.uploads.destroy', $u) }}" class="inline" data-confirm="Delete {{ $u->title }}?|The file is removed for everyone.|Delete">@csrf @method('delete')<button class="btn sm ghost">Delete</button></form>@endcan</td></tr>
            @empty <tr><td colspan="5" class="empty">No files{{ $category ? ' in '.$category : '' }}.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $uploads])</div>
    </div>
@endsection
