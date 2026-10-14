<?php

namespace App\Support;

use App\Models\AdminApp;

/**
 * Every admin app: the built-in ones from config/admin.php plus the ones created
 * from the launcher (admin_apps table). Keys double as permission names on roles.
 */
class AdminApps
{
    /** Keys a new app can't use (they're taken by admin URLs). */
    public const RESERVED = ['login', 'logout', 'apps', 'history', 'admin'];

    public static function icons(): array
    {
        return ['folder' => 'Folder', 'users' => 'People', 'layout' => 'Pages', 'chart' => 'Chart', 'star' => 'Star', 'bolt' => 'Lightning', 'file' => 'Document', 'gear' => 'Settings', 'link' => 'Link', 'safe' => 'Safe', 'train' => 'Caboose', 'gift' => 'Gift', 'megaphone' => 'Megaphone'];
    }

    /** @return array<string, array{name: string, desc: string, icon: string, url: string, custom: bool, menu: mixed, model?: AdminApp}> */
    public static function all(): array
    {
        $apps = [];
        foreach (config('admin.apps') as $key => $a) {
            $apps[$key] = $a + ['custom' => false, 'url' => route($a['home'])];
        }
        foreach (AdminApp::orderBy('position')->orderBy('id')->get() as $c) {
            $apps[$c->key] = [
                'name' => $c->name, 'desc' => $c->description, 'icon' => $c->icon,
                'url' => route('custom.show', $c->key), 'custom' => true, 'menu' => $c->menuSections(), 'model' => $c,
            ];
        }

        return $apps;
    }

    public static function get(?string $key): ?array
    {
        return $key ? (self::all()[$key] ?? null) : null;
    }

    /** Every built-in admin screen a custom app can link to: route name => "App → Screen". */
    public static function screens(): array
    {
        $screens = [];
        foreach (config('admin.apps') as $app) {
            foreach ($app['menu'] as $heading => $items) {
                $items = match ($items) {
                    'queues' => [['Exception Queues', 'corral.queues.index']],
                    'integrations' => [['APIs', 'sheriff.integrations.index']],
                    default => $items,
                };
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    $screens[$item[1]] ??= $app['name'].' → '.$item[0];
                }
            }
        }

        return $screens;
    }
}
