<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\LinkClick;
use App\Support\EmailHeatmap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/** Rodeo → click heat maps: where people click in an email template, or in one campaign's emails. */
class HeatmapController extends Controller
{
    public function template(EmailTemplate $template): View
    {
        return $this->page($template, LinkClick::where('email_template_id', $template->id), $template->name,
            ContactLog::where('channel', 'Email')->where('template', $template->name), route('rodeo.templates.edit', $template));
    }

    public function campaign(Campaign $campaign): View
    {
        $template = $campaign->email_template_id ? EmailTemplate::find($campaign->email_template_id) : null;

        return $this->page($template, LinkClick::where('campaign_id', $campaign->id), $campaign->name,
            ContactLog::where('campaign_id', $campaign->id), route('rodeo.campaigns.edit', $campaign), $campaign->channel === 'SMS' ? $campaign->message : null);
    }

    private function page(?EmailTemplate $t, Builder $clicks, string $name, Builder $sent, string $back, ?string $text = null): View
    {
        $counts = (clone $clicks)->selectRaw('link, count(*) as n, count(distinct customer_id) as people')->groupBy('link')->get()->keyBy('link');
        $links = $text !== null ? ['body-1' => ['label' => 'Link in the text', 'url' => '']] : EmailHeatmap::links($t);
        $total = $counts->sum('n');

        return view('backend.rodeo.heatmap', ['name' => $name, 'back' => $back, 'links' => $links, 'counts' => $counts, 'total' => $total,
            'max' => max(1, (int) $counts->max('n')), 'sent' => (clone $sent)->count(), 'opened' => (clone $sent)->whereNotNull('opened_at')->count(),
            'clickers' => (clone $clicks)->distinct('customer_id')->count('customer_id'),
            'devices' => (clone $clicks)->selectRaw("coalesce(device, 'Unknown') as d, count(*) as n")->groupBy('d')->pluck('n', 'd'),
            'html' => $text !== null ? '<div class="hm-email hm-sms"><div class="hm-body"><p>'.preg_replace('#(https?://\S+)#', '<a data-link="body-1" href="$1">$1</a>', e($text)).'</p></div></div>'
                : EmailHeatmap::html($t, Customer::whereNotNull('email')->first())]);
    }
}
