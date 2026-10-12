@php [$label, $tone] = \App\Models\ReportRun::stateLabel($run->state()); @endphp
@include('backend.partials.pill', ['text' => $label, 'tone' => $tone])
