<?php

namespace Database\Seeders;

use App\Models\ApiLog;
use App\Support\ApiExchange;
use Illuminate\Database\Seeder;

/** Sample request/response for every demo API call (fictional), so each call's page shows what was sent and what came back. */
class ApiExchangeSeeder extends Seeder
{
    public function run(): void
    {
        ApiLog::with('customer')->whereNull('request')->chunkById(300, function ($logs) {
            foreach ($logs as $l) {
                [$method, $endpoint, $request, $response] = ApiExchange::sample($l);
                $l->forceFill(compact('method', 'endpoint', 'request', 'response'))->saveQuietly();
            }
        });
    }
}
