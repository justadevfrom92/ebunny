<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\SiteBlock;
use App\Models\SiteVisit;
use App\Support\SiteTraffic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Lando → Site → Blocked IPs and Blocked Areas. The admin is never blocked. */
class BlockedController extends Controller
{
    private const TYPES = ['ips' => 'ip', 'areas' => 'area'];

    public function index(): View
    {
        $kind = $this->kind();
        $type = self::TYPES[$kind];
        $blocks = SiteBlock::with('user')->where('type', $type)->orderByDesc('active')->orderBy('value')->get();

        return view('backend.lando.site.blocked', [
            'kind' => $kind,
            'type' => $type,
            'blocks' => $blocks,
            // Blocked areas: the pages each one covers
            'covers' => $type === 'area' ? $blocks->mapWithKeys(fn ($b) => [$b->id => Page::where('path', $b->value)->orWhere('path', 'like', $b->value.'/%')->count()]) : collect(),
            'lastSeen' => $type === 'ip' ? SiteVisit::selectRaw('ip, max(created_at) as at, count(*) as n')->whereIn('ip', $blocks->where(fn ($b) => ! str_ends_with($b->value, '*'))->pluck('value'))->groupBy('ip')->get()->keyBy('ip') : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $kind = $this->kind();
        $block = new SiteBlock(['type' => self::TYPES[$kind], 'value' => $request->query('value'), 'active' => true]);

        return view('backend.lando.site.block-form', ['kind' => $kind, 'block' => $block, 'views' => $block->value ? $this->views($block) : null]);
    }

    public function edit(SiteBlock $block): View
    {
        $kind = $this->kind();
        abort_unless($block->type === self::TYPES[$kind], 404);

        return view('backend.lando.site.block-form', ['kind' => $kind, 'block' => $block, 'views' => $this->views($block)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kind = $this->kind();
        $data = $this->validated($request, self::TYPES[$kind]);
        SiteBlock::create($data + ['type' => self::TYPES[$kind], 'user_id' => $request->user()->id]);
        SiteTraffic::forget();

        return redirect()->route('lando.blocked.'.$kind)->with('status', $data['value'].' blocked');
    }

    public function update(Request $request, SiteBlock $block): RedirectResponse
    {
        $kind = $this->kind();
        abort_unless($block->type === self::TYPES[$kind], 404);
        $block->update($this->validated($request, $block->type, $block));
        SiteTraffic::forget();

        return redirect()->route('lando.blocked.'.$kind)->with('status', $block->value.' saved');
    }

    public function toggle(SiteBlock $block): RedirectResponse
    {
        abort_unless($block->type === self::TYPES[$this->kind()], 404);
        $block->update(['active' => ! $block->active]);
        SiteTraffic::forget();

        return back()->with('status', $block->value.($block->active ? ' blocked again' : ' unblocked'));
    }

    public function destroy(SiteBlock $block): RedirectResponse
    {
        $kind = $this->kind();
        abort_unless($block->type === self::TYPES[$kind], 404);
        $block->delete();
        SiteTraffic::forget();

        return redirect()->route('lando.blocked.'.$kind)->with('status', $block->value.' removed');
    }

    /** ips or areas, from the route name (lando.blocked.ips.edit). */
    private function kind(): string
    {
        return explode('.', request()->route()->getName())[2];
    }

    private function validated(Request $request, string $type, ?SiteBlock $block = null): array
    {
        $request->merge(['value' => $type === 'area' ? trim((string) $request->input('value'), ' /') : trim((string) $request->input('value'))]);
        $data = $request->validate([
            'value' => ['required', 'string', 'max:200', Rule::unique('site_blocks')->where('type', $type)->ignore($block)],
            'reason' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:250'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'active' => ['nullable', 'boolean'],
        ], ['value.unique' => 'That is already on the list.', 'expires_at.after' => 'Pick a time in the future, or leave it empty to block until you unblock.']);
        $data['active'] = (bool) ($data['active'] ?? false);

        if ($type === 'ip') {
            $ip = rtrim($data['value'], '*');
            $ok = filter_var($data['value'], FILTER_VALIDATE_IP) || (str_ends_with($data['value'], '*') && preg_match('/^(\d{1,3}\.){1,3}$/', $ip));
            if (! $ok) {
                throw ValidationException::withMessages(['value' => 'Enter an IP address like 203.0.113.7, or a range like 203.0.113.*']);
            }
            if ((new SiteBlock(['type' => 'ip', 'value' => $data['value']]))->matchesIp($request->ip())) {
                throw ValidationException::withMessages(['value' => 'That would block your own address ('.$request->ip().').']);
            }
        } else {
            if ($data['value'] === '' || preg_match('#^(admin|site/session|site/ping)(/|$)#', $data['value'])) {
                throw ValidationException::withMessages(['value' => 'The home page, the admin and the site\'s own endpoints can\'t be blocked.']);
            }
        }

        return $data;
    }

    /** How often this IP or area was visited in the last 30 days. */
    private function views(SiteBlock $block): array
    {
        $q = SiteVisit::where('created_at', '>=', now()->subDays(30));
        $q = $block->type === 'ip'
            ? $q->where('ip', 'like', str_ends_with($block->value, '*') ? rtrim($block->value, '*').'%' : $block->value)
            : $q->where(fn ($w) => $w->where('path', $block->value)->orWhere('path', 'like', $block->value.'/%'));

        return ['n' => (clone $q)->count(), 'people' => (clone $q)->distinct()->count('visitor'), 'last' => (clone $q)->max('created_at')];
    }
}
