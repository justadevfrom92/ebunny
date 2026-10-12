@php $stage = $e->stage(); @endphp
@include('backend.partials.pill', ['text' => \App\Http\Controllers\Backend\Rodeo\EmailController::STAGES[$stage] ?? ucfirst($stage), 'tone' => ['clicked' => 'ok', 'opened' => 'ok', 'sent' => 'info', 'dropped' => 'bad', 'not sent' => 'warn', 'suppressed' => 'bad'][$stage] ?? ''])
