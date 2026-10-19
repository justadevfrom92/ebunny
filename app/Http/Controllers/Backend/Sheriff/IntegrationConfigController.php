<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\IntegrationTest;
use App\Support\EnvFile;
use App\Support\History;
use App\Support\IntegrationTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sheriff → APIs → Configure: upload a .env file to set one company's settings,
 * and test the connection. Values go only into the server's .env; the page shows
 * which are set, never what they are.
 */
class IntegrationConfigController extends Controller
{
    private static function find(string $key): array
    {
        abort_unless(config()->has('admin.integrations.'.$key), 404);
        $i = config('admin.integrations.'.$key);

        return $i + ['key' => $key, 'set' => collect($i['env'])->map(fn ($v) => filled($v))->all(), 'configured' => collect($i['env'])->every(fn ($v) => filled($v))];
    }

    public function show(string $integration): View
    {
        return view('backend.sheriff.integration-configure', [
            'i' => self::find($integration),
            'tests' => IntegrationTest::with('user')->where('integration', $integration)->latest('id')->limit(15)->get(),
            'cached' => app()->configurationIsCached(),
        ]);
    }

    /** Upload a .env file: only this company's keys are taken; the rest of the file is ignored. */
    public function upload(Request $request, string $integration): RedirectResponse
    {
        $i = self::find($integration);
        abort_unless($request->user()->can('configure'), 403, 'Changing API settings needs the "Change API settings" right.');
        $request->validate(['env_file' => ['required', 'file', 'max:64']], ['env_file.required' => 'Choose a .env file to upload.']);
        $file = $request->file('env_file');
        $text = (string) file_get_contents($file->getRealPath());
        if (str_contains($text, "\0")) {
            throw ValidationException::withMessages(['env_file' => 'That is not a text .env file.']);
        }
        $pairs = EnvFile::parse($text);
        $mine = array_intersect_key($pairs, $i['env']);
        if (! $mine) {
            throw ValidationException::withMessages(['env_file' => 'The file has none of '.$i['name'].'\'s settings ('.implode(', ', array_keys($i['env'])).').']);
        }
        EnvFile::write($mine);
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');   // so the new values are read on the next request
        }
        $ignored = array_diff(array_keys($pairs), array_keys($mine));
        History::record(['model' => 'Integration_model', 'group' => 'Admin Changes', 'action' => 'updated',
            'summary' => $i['name'].' settings uploaded: '.implode(', ', array_keys($mine)), 'data' => ['integration' => $integration, 'keys' => array_keys($mine)]]);

        return redirect()->route('sheriff.integrations.configure', $integration)->with('status', 'Saved '.count($mine).' '.str('setting')->plural(count($mine)).' to .env: '.implode(', ', array_keys($mine)).'.'
            .($ignored ? ' Ignored '.count($ignored).' other '.str('line')->plural(count($ignored)).' in the file (not '.$i['name'].' settings).' : '').' Run Test Connection to check them.');
    }

    public function test(Request $request, string $integration): RedirectResponse
    {
        $i = self::find($integration);
        $result = IntegrationTester::test($integration);
        IntegrationTest::create(['integration' => $integration, 'ok' => $result['ok'], 'message' => $result['message'], 'response_ms' => $result['ms'], 'user_id' => $request->user()->id, 'created_at' => now()]);
        ApiLog::create(['api' => $i['name'], 'action' => 'connection-test', 'status' => $result['ok'] ? '200' : 'failed', 'response_ms' => $result['ms'], 'created_at' => now()]);

        // From the All APIs page: back to the list with the result for that card
        if ($request->input('back') === 'list') {
            return redirect()->route('sheriff.integrations.index')->with('status', $i['name'].': '.($result['ok'] ? 'connected' : 'failed').' — '.$result['message']);
        }

        return redirect()->route('sheriff.integrations.configure', $integration)->with('test_result', $result);
    }

    /** All APIs → Test All: tests every integration and lists the results. */
    public function testAll(Request $request): RedirectResponse
    {
        $ok = 0;
        foreach (config('admin.integrations') as $key => $i) {
            $result = IntegrationTester::test($key);
            IntegrationTest::create(['integration' => $key, 'ok' => $result['ok'], 'message' => $result['message'], 'response_ms' => $result['ms'], 'user_id' => $request->user()->id, 'created_at' => now()]);
            ApiLog::create(['api' => $i['name'], 'action' => 'connection-test', 'status' => $result['ok'] ? '200' : 'failed', 'response_ms' => $result['ms'], 'created_at' => now()]);
            $ok += $result['ok'] ? 1 : 0;
        }

        return redirect()->route('sheriff.integrations.index')->with('status', 'Tested '.count(config('admin.integrations')).' APIs: '.$ok.' connected.');
    }
}
