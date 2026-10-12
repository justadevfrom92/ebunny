<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Reads the site's stylesheets for Lando → Style Guide: every CSS variable
 * (where it's defined, its value, what uses it) and the colors still typed
 * straight into rules instead of coming from a variable.
 */
class StyleSheets
{
    /** path under public/ => who uses it */
    public const FILES = [
        'shared/css/tokens.css' => 'Shared (website + every admin app)',
        'backend/admin.css' => 'Admin apps',
        'frontend/css/style.css' => 'Website',
    ];

    private const COLOR = '/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|hsla?\([^)]*\)/';

    /** @return array<string, list<array{selector: string, body: string}>> rules per file (comments removed, @media flattened) */
    public static function rules(): array
    {
        return once(function () {
            $out = [];
            foreach (array_keys(self::FILES) as $file) {
                $css = preg_replace(['#/\*.*?\*/#s', '/@import\s+url\([^)]*\)[^;]*;|@import\s+([\'"])[^\'"]*\1[^;]*;|@charset[^;]*;/'], '', (string) @file_get_contents(public_path($file)));
                preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);
                $out[$file] = array_map(fn ($r) => ['selector' => trim(preg_replace('/\s+/', ' ', $r[1])), 'body' => trim($r[2])], $m);
            }

            return $out;
        });
    }

    /**
     * Every variable defined in a :root block.
     *
     * @return Collection<string, array{name: string, value: string, resolved: string, file: string, group: string, kind: string, uses: array<string, list<string>>, count: int}>
     */
    public static function variables(): Collection
    {
        $vars = [];
        foreach (self::rules() as $file => $rules) {
            foreach ($rules as $r) {
                if ($r['selector'] !== ':root') {
                    continue;
                }
                preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $r['body'], $m, PREG_SET_ORDER);
                foreach ($m as [, $name, $value]) {
                    $vars[$name] = ['name' => $name, 'value' => trim($value), 'file' => $file];
                }
            }
        }
        $resolve = function (string $v, int $depth = 0) use (&$resolve, $vars): string {
            return $depth > 5 ? $v : preg_replace_callback('/var\((--[\w-]+)(?:\s*,\s*([^)]+))?\)/', fn ($m) => isset($vars[$m[1]]) ? $resolve($vars[$m[1]]['value'], $depth + 1) : ($m[2] ?? $m[0]), $v);
        };

        return collect($vars)->map(function ($v) use ($resolve) {
            $v['resolved'] = $resolve($v['value']);
            $v['kind'] = match (true) {
                str_contains($v['name'], 'font') => 'font',
                (bool) preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\()/', $v['resolved']) => 'color',
                default => 'other',
            };
            $v['group'] = match (true) {
                $v['kind'] === 'font' => 'Fonts',
                $v['kind'] === 'other' => 'Sizes & effects',
                (bool) preg_match('/^--(ok|warn|bad|info)/', $v['name']) => 'Status colors',
                $v['file'] === 'shared/css/tokens.css' => 'Brand colors',
                default => 'App colors',
            };
            $v['uses'] = self::usesOf($v['name']);
            $v['count'] = array_sum(array_map('count', $v['uses']));

            return $v;
        });
    }

    /** @return array<string, list<string>> file => selectors whose rules use var(--name) */
    public static function usesOf(string $name): array
    {
        $out = [];
        foreach (self::rules() as $file => $rules) {
            foreach ($rules as $r) {
                if ($r['selector'] !== ':root' && preg_match('/var\(\s*'.preg_quote($name, '/').'\s*[,)]/', $r['body'])) {
                    $out[$file][] = $r['selector'];
                }
            }
        }

        return $out;
    }

    /** @return Collection<int, array{value: string, count: int, uses: list<string>}> colors typed into rules rather than taken from a variable */
    public static function literals(): Collection
    {
        $found = [];
        foreach (self::rules() as $file => $rules) {
            foreach ($rules as $r) {
                if ($r['selector'] === ':root') {
                    continue;
                }
                preg_match_all(self::COLOR, $r['body'], $m);
                foreach ($m[0] as $c) {
                    $key = strtolower(preg_replace('/\s+/', '', $c));
                    $found[$key]['value'] = $c;
                    $found[$key]['uses'][] = self::FILES[$file].': '.$r['selector'];
                }
            }
        }

        return collect($found)->map(fn ($f) => $f + ['count' => count($f['uses'])])->sortByDesc('count')->values();
    }

    /** Variables used by the rules for these selectors (".btn" matches ".btn", ".btn:hover", ".btn.cyan" but not ".btn-outline"). */
    public static function varsFor(array $selectors, ?string $onlyFile = null): array
    {
        $names = [];
        foreach (self::rules() as $file => $rules) {
            if ($onlyFile && $file !== $onlyFile) {
                continue;
            }
            foreach ($rules as $r) {
                foreach (explode(',', $r['selector']) as $sel) {
                    $sel = trim($sel);
                    foreach ($selectors as $want) {
                        if (str_starts_with($sel, $want) && ! preg_match('/^[\w-]/', substr($sel, strlen($want)))) {
                            preg_match_all('/var\(\s*(--[\w-]+)/', $r['body'], $m);
                            array_push($names, ...$m[1]);
                        }
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }
}
