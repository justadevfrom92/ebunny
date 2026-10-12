@extends('backend.layouts.app')

@section('crumb', $model->exists ? $model->name : 'Add a Model')

@section('content')
    @include('backend.partials.page-head', ['title' => $model->exists ? 'Edit '.$model->name : 'Add a Model',
        'sub' => 'Any open-weight model: Qwen, Kimi, DeepSeek, Llama, Mistral, gpt-oss and so on. For a downloaded model, use its name on the local server (what <span class="mono">ollama list</span> shows, like <span class="mono">qwen3:8b</span>). For a hosted one, use the id the open models API lists (like <span class="mono">moonshotai/Kimi-K2-Instruct</span>).'])
    <form method="post" action="{{ $model->exists ? route('deputy.models.update', $model) : route('deputy.models.store') }}" class="panel simple-form">@csrf @if ($model->exists) @method('put') @endif
        <div class="panel-body form-grid">
            <label for="name">Name</label><input id="name" name="name" required maxlength="80" value="{{ old('name', $model->name) }}" placeholder="Qwen3 8B">
            <label for="provider">Provider</label><select id="provider" name="provider">@foreach (config('deputy.providers') as $v => $l)<option value="{{ $v }}" @selected(old('provider', $model->provider) === $v)>{{ $l }}</option>@endforeach</select>
            <label for="model_id">Model id</label><input id="model_id" name="model_id" required maxlength="120" class="mono" value="{{ old('model_id', $model->model_id) }}" placeholder="qwen3:8b">
            <label for="source">Downloaded from</label><input id="source" name="source" maxlength="250" value="{{ old('source', $model->source) }}" placeholder="ollama pull qwen3:8b, a Hugging Face repo, or a file like /models/billing-q4.gguf">
            <label for="size_gb">Size (GB)</label><input id="size_gb" name="size_gb" type="number" step="0.1" min="0" value="{{ old('size_gb', $model->size_gb) }}" style="max-width:12ch">
            <label for="quantization">Quantization</label><input id="quantization" name="quantization" maxlength="20" value="{{ old('quantization', $model->quantization) }}" placeholder="Q4_K_M" style="max-width:16ch">
            <label for="context_window">Context window</label><input id="context_window" name="context_window" type="number" min="512" value="{{ old('context_window', $model->context_window) }}" placeholder="tokens" style="max-width:16ch">
            <label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="1000" style="min-height:80px;font-family:inherit">{{ old('notes', $model->notes) }}</textarea>
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $model->exists ? 'Save Model' : 'Add Model' }}</button><a class="btn ghost" href="{{ route('deputy.models') }}">Cancel</a></div>
    </form>
@endsection
