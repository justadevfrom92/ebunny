<?php

namespace Database\Seeders;

use App\Models\HistoryItem;
use App\Models\Page;
use App\Models\PageComponent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample edit history for the website pages (Lando → List Pages → Activity → History):
 * who created each page and the later edits to its titles, meta and content blocks.
 */
class PageHistorySeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(7311);
        $editors = User::whereIn('email', ['admin@example.com', 'marketing@example.com', 'pricing@example.com'])->pluck('id')->values();
        $edits = [
            ['html_title', fn ($p) => $p->html_title, fn ($p) => $p->title.' | Energy Texas'],
            ['meta_description', fn ($p) => $p->meta_description, fn ($p) => 'Learn about '.strtolower($p->title).' from Energy Texas, a Texas retail electricity provider.'],
            ['title', fn ($p) => $p->title.' Page', fn ($p) => $p->title],
            ['status', fn () => 'Draft', fn ($p) => $p->status],
            ['no_index', fn () => '1', fn ($p) => (string) (int) $p->no_index],
            ['phone', fn () => '', fn () => '(800) 555-0142'],
        ];
        $rows = [];
        foreach (Page::all() as $p) {
            $at = now()->subDays(mt_rand(60, 120))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $who = $editors[mt_rand(0, $editors->count() - 1)];
            $rows[] = $this->row($p->id, 'Page_model', 'created', 'Page created: '.$p->title, ['path' => $p->path, 'title' => $p->title], null, $who, $at);
            foreach (array_rand($edits, mt_rand(2, 4)) as $i) {
                [$field, $old, $new] = $edits[$i];
                $at = $at->copy()->addDays(mt_rand(2, 18))->setTime(mt_rand(8, 17), mt_rand(0, 59));
                if ($at->isFuture()) {
                    break;
                }
                $rows[] = $this->row($p->id, 'Page_model', 'updated', $p->title.' — '.str_replace('_', ' ', $field).' changed', ['path' => $p->path],
                    [$field => [(string) $old($p), (string) $new($p)]], $editors[mt_rand(0, $editors->count() - 1)], $at);
            }
        }
        foreach (PageComponent::with('block', 'page')->get() as $c) {
            $at = now()->subDays(mt_rand(5, 40))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $rows[] = $this->row($c->id, 'PageComponent_model', 'created', 'Content block added to '.$c->page?->title.': '.$c->block?->name,
                ['page_id' => $c->page_id, 'zone' => $c->zone], null, $editors[mt_rand(0, $editors->count() - 1)], $at);
            if (mt_rand(0, 2) === 0) {
                $rows[] = $this->row($c->id, 'PageComponent_model', 'updated', 'Content block on '.$c->page?->title.' — position changed',
                    ['page_id' => $c->page_id, 'zone' => $c->zone], ['position' => [(string) ($c->position + 1), (string) $c->position]], $editors[0], $at->copy()->addDays(mt_rand(1, 4)));
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            HistoryItem::insert($chunk);
        }
    }

    private function row(int $id, string $model, string $action, string $summary, array $data, ?array $changes, ?int $user, $at): array
    {
        return ['customer_id' => null, 'user_id' => $user, 'model' => $model, 'group' => 'Admin Changes', 'record_id' => $id, 'action' => $action,
            'summary' => $summary, 'data' => json_encode($data), 'changes' => $changes ? json_encode($changes) : null,
            'ip' => '10.0.0.'.mt_rand(10, 99), 'created_at' => $at->format('Y-m-d H:i:s')];
    }
}
