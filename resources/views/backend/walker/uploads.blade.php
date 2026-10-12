@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Uploaded Reports', 'sub' => 'Report files shared by the team: utility and ERCOT files, finance workbooks, vendor reports.',
        'actions' => '<a class="btn cyan" href="'.route('walker.uploads.create').'">Upload a Report</a>'])

    <div class="actions" style="margin-bottom:14px"><a class="btn sm {{ $category ? 'ghost' : '' }}" href="{{ route('walker.uploads.index') }}">All</a>@foreach (config('walker.upload_categories') as $c)<a class="btn sm {{ $category === $c ? '' : 'ghost' }}" href="{{ route('walker.uploads.index', ['category' => $c]) }}">{{ $c }} <span class="help">{{ $counts[$c] ?? 0 }}</span></a>@endforeach</div>
    <div>
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>Report</th><th>Category</th><th>File</th><th>Uploaded</th><th></th></tr></thead>
            <tbody>@forelse ($uploads as $u)
                <tr><td><b>{{ $u->title }}</b>@if ($u->description)<br><span class="help">{{ $u->description }}</span>@endif</td><td>{{ $u->category }}</td>
                    <td class="mono help">{{ $u->original_name }}<br>{{ number_format($u->size / 1024, 1) }} KB</td>
                    <td>{{ $u->created_at->format('n/j/Y') }}<br><span class="help">{{ $u->user?->name }}</span></td>
                    <td class="actions"><a class="btn sm ghost" href="{{ route('walker.uploads.download', $u) }}">Download</a>
                        @can('delete')<form method="post" action="{{ route('walker.uploads.destroy', $u) }}" class="inline" data-confirm="Delete {{ $u->title }}?|The file is removed for everyone.|Delete">@csrf @method('delete')<button class="btn sm ghost">Delete</button></form>@endcan</td></tr>
            @empty <tr><td colspan="5" class="empty">No uploaded reports{{ $category ? ' in '.$category : '' }}.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $uploads])</div>
    </div>
@endsection
