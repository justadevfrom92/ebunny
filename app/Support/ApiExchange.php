<?php

namespace App\Support;

use App\Models\ApiLog;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * What an API call sent and what came back, for its page in Sheriff → APIs and Corral → API Log.
 * Real calls are captured from Laravel's HTTP client; secrets (keys, tokens, passwords, card numbers)
 * are blanked before anything is saved. Sample calls in the demo data get a sample exchange.
 */
class ApiExchange
{
    private const SECRET = '/(authorization|secret|token|password|api[-_]?key|card|cvc|cvv|account_number|routing|ssn)/i';

    private static ?ResponseReceived $last = null;

    private static bool $listening = false;

    /** Run $call and return its result plus the last HTTP request/response it made: [result, method, endpoint, request, response]. */
    public static function capture(callable $call): array
    {
        if (! self::$listening) {
            Event::listen(ResponseReceived::class, fn (ResponseReceived $e) => self::$last = $e);
            self::$listening = true;
        }
        self::$last = null;
        $result = $call();
        $e = self::$last;
        if (! $e) {
            return [$result, null, null, null, null];
        }
        $body = $e->request->body();
        $json = json_decode($body, true);
        if ($json === null && $body !== '') {
            parse_str($body, $json);
        }

        return [$result, $e->request->method(), self::cleanUrl($e->request->url()),
            ['headers' => self::clean(array_map(fn ($v) => implode(', ', $v), $e->request->headers())), 'body' => self::clean($json ?: ($body !== '' ? Str::limit($body, 4000) : null))],
            ['status' => $e->response->status(), 'headers' => array_map(fn ($v) => implode(', ', $v), array_intersect_key($e->response->headers(), array_flip(['Content-Type', 'content-type', 'Date', 'date', 'Request-Id', 'request-id', 'X-Request-Id', 'x-request-id']))),
                'body' => self::clean($e->response->json() ?? Str::limit($e->response->body(), 4000))]];
    }

    /** Blank anything that looks like a secret, at any depth. */
    public static function clean(mixed $v, ?string $key = null): mixed
    {
        if ($key !== null && preg_match(self::SECRET, $key) && ! is_array($v)) {
            return $v === null || $v === '' ? $v : '•••• hidden';
        }
        if (is_array($v)) {
            foreach ($v as $k => $x) {
                $v[$k] = self::clean($x, is_string($k) ? $k : $key);
            }
        }

        return $v;
    }

    private static function cleanUrl(string $url): string
    {
        $parts = parse_url($url);
        if (empty($parts['query'])) {
            return $url;
        }
        parse_str($parts['query'], $q);

        return Str::before($url, '?').'?'.http_build_query(self::clean($q));
    }

    /**
     * A sample exchange for a demo API call (fictional values): [method, endpoint, request, response].
     *
     * @return array{0: string, 1: string, 2: array, 3: array}
     */
    public static function sample(ApiLog $l): array
    {
        $c = $l->customer;
        $status = is_numeric($l->status) ? (int) $l->status : 0;
        $ok = $status >= 200 && $status < 300;
        $id = strtoupper(substr(md5($l->id.$l->action), 0, 14));
        $acct = $c?->account;
        $name = $c ? ['first_name' => $c->first_name, 'last_name' => $c->last_name] : [];
        $action = Str::before($l->action, ' ');
        $hostPath = fn (string $host, string $path) => 'https://'.$host.$path;
        [$method, $endpoint, $body, $answer] = match ($l->api) {
            'Stripe' => ['POST', $hostPath('api.stripe.com', '/v1/charges'), ['amount' => 8423, 'currency' => 'usd', 'customer' => 'cus_'.$id, 'description' => 'Electricity bill '.$acct, 'source' => 'card_•••• hidden'],
                ['id' => 'ch_'.$id, 'object' => 'charge', 'amount' => 8423, 'currency' => 'usd', 'paid' => $ok, 'status' => $ok ? 'succeeded' : 'failed', 'failure_code' => $ok ? null : 'card_declined']],
            'Experian' => ['POST', $hostPath('sandbox.example-credit.test', '/consumerservices/credit-profile/v2/credit-report'), ['consumerPii' => ['primaryApplicant' => ['name' => $name, 'ssn' => '•••• hidden', 'currentAddress' => ['zipCode' => $c?->zip]]], 'permissiblePurpose' => ['type' => '3F']],
                ['creditProfile' => [['riskModel' => [['modelIndicator' => 'V4', 'score' => '0'.(620 + $l->id % 160)]], 'deposit' => ['required' => $l->id % 3 === 0, 'amount' => $l->id % 3 === 0 ? 150 : 0]]], 'requestId' => $id]],
            'Utilibill' => ['GET', $hostPath('api.example-billing.test', '/v2/bills/'.Str::after($l->action, 'bill.import ')), [],
                ['bill' => ['reference' => Str::after($l->action, 'bill.import '), 'account' => $acct, 'kwh' => 900 + $l->id % 900, 'amount' => round(80 + $l->id % 120 + 0.42, 2), 'status' => 'issued']]],
            'Twilio' => ['POST', $hostPath('api.twilio.com', '/2010-04-01/Accounts/AC•••• hidden/Messages.json'), ['To' => $c?->phone ?? '+15555550100', 'From' => '+15555550199', 'Body' => 'Your bill is ready. Reply STOP to opt out.'],
                ['sid' => 'SM'.$id, 'status' => $ok ? 'queued' : 'failed', 'error_code' => $ok ? null : 21610]],
            'SalesForce' => ['POST', $hostPath('example.rest.marketingcloudapis.test', '/messaging/v1/email/messages/'), ['definitionKey' => 'monthly-bill', 'recipients' => [['contactKey' => $acct, 'to' => $c?->email ?? 'someone@example.com']]],
                ['requestId' => $id, 'responses' => [['messageKey' => strtolower($id), 'hasErrors' => ! $ok]]]],
            'Plaid' => ['POST', $hostPath('sandbox.plaid.com', '/'.str_replace('.', '/', $action)), ['client_id' => '•••• hidden', 'secret' => '•••• hidden', 'user' => ['client_user_id' => $acct]],
                ['request_id' => $id, 'link_token' => $ok ? 'link-sandbox-'.strtolower($id) : null]],
            'Open Models API', 'Local Models' => ['POST', $l->api === 'Local Models' ? 'http://localhost:11434/api/'.$action : $hostPath('api.example-models.test', '/v1/chat/completions'),
                ['model' => 'qwen2.5-7b-instruct', 'messages' => [['role' => 'system', 'content' => 'You answer customer texts for an electricity company.'], ['role' => 'user', 'content' => 'When is my bill due?']]],
                ['id' => 'chatcmpl-'.strtolower($id), 'choices' => [['message' => ['role' => 'assistant', 'content' => 'Your next bill is due on the 16th. You can pay it in My Account.']]], 'usage' => ['prompt_tokens' => 48, 'completion_tokens' => 19]]],
            default => [str_contains($action, 'get') || str_contains($action, 'query') || str_contains($action, 'status') || str_contains($action, 'forecast') ? 'GET' : 'POST',
                $hostPath('api.example-'.Str::slug($l->api).'.test', '/'.str_replace(['.', ' '], '/', $l->action)), $acct ? ['account' => $acct] : ['date' => $l->created_at?->toDateString()],
                ['id' => $id, 'result' => $ok ? 'ok' : 'error']],
        };
        if (! $ok) {
            $answer = ['error' => ['code' => $status ?: $l->status, 'message' => match ($status) {
                400 => 'Bad request: a required field is missing.', 401 => 'Unauthorized: check the API key.', 402 => 'Payment declined by the card issuer.',
                429 => 'Too many requests: try again in a minute.', 500, 503 => 'The service is having a problem. Try again later.', default => 'Not sent: '.$l->status.'.',
            }]] + ($l->api === 'Stripe' ? ['id' => 'ch_'.$id] : []);
        }

        return [$method, $endpoint,
            ['headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => '•••• hidden', 'User-Agent' => config('brand.name').' Admin'], 'body' => $body ?: null],
            ['status' => $status ?: $l->status, 'headers' => ['Content-Type' => 'application/json', 'Request-Id' => 'req_'.strtolower($id)], 'body' => $answer]];
    }
}
