<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Record pages for rows without a page of their own (config/records.php), one per admin app. */
class Records
{
    /** Fields never shown on a record page. */
    public const HIDDEN = ['password', 'remember_token', 'token', 'secret', 'api_key', 'card_number', 'cvv', 'routing', 'account_number', 'ssn'];

    /** The record page for this row, in the app the page is in. */
    public static function url(string $type, int|string $id, ?string $app = null): string
    {
        return route(($app ?? AdminMenu::currentApp() ?? 'sheriff').'.records.show', [$type, $id]);
    }

    /** @return array{0: string, 1: array<string, mixed>, 2: ?object} label, fields, model */
    public static function find(string $type, int $id): array
    {
        [$source, $label] = config('records.'.$type) ?? abort(404);
        if (str_starts_with($source, 'table:')) {
            $row = DB::table(substr($source, 6))->find($id) ?? abort(404);

            return [$label, (array) $row, null];
        }
        $model = $source::findOrFail($id);

        return [$label, Arr::except($model->getAttributes(), array_merge($model->getHidden(), ['id'])), $model];
    }

    public static function hidden(string $field): bool
    {
        return collect(self::HIDDEN)->contains(fn ($h) => str_contains(strtolower($field), $h));
    }
}
