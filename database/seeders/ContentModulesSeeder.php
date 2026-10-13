<?php

namespace Database\Seeders;

use App\Models\BlockCategory;
use App\Models\ContentBlock;
use Illuminate\Database\Seeder;

/**
 * Modules: content blocks with {{variables}} that each page fills in. The location pages,
 * career posts and the rewards promo band use them instead of repeating the same HTML.
 */
class ContentModulesSeeder extends Seeder
{
    public function run(): void
    {
        $cat = BlockCategory::firstOrCreate(['name' => 'Modules']);
        $module = fn (string $slug, string $name, string $html) => ContentBlock::updateOrCreate(['slug' => $slug], ['name' => $name, 'html' => $html, 'category_id' => $cat->id]);

        $module('location-intro', 'Module - Location Intro',
            '<section class="loc-intro"><h2>Electricity plans in {{city|your city}}</h2><p>We serve {{city|your city}} and the {{utility|local utility}} service area. {{note|Compare fixed-rate plans and start service in minutes.}}</p>[[zip_form]]</section>');
        $module('promo-band', 'Module - Promo Band',
            '<div class="rewards-band"><b>{{heading|Earn Rangler Rewards}}</b> {{text|Earn stars on every on-time payment.}} <a href="{{link|/refer-a-friend}}">{{button|Learn more}}</a></div>');
        $module('job-post', 'Module - Job Post',
            '<article class="job-post"><p>{{summary}}</p><ul><li><b>Location:</b> {{location|Houston, TX}}</li><li><b>Type:</b> {{type|Full time}}</li><li><b>Team:</b> {{team}}</li></ul><p>Send your resume through the <a href="/contact-us.html">contact page</a>.</p></article>');

        // The pages pass their own text into the modules
        $uses = [
            'page-locationshouston-primary' => '[[block|location-intro|city=Houston|utility=CenterPoint]]',
            'page-locationsdallas-primary' => '[[block|location-intro|city=Dallas|utility=Oncor]]',
            'page-locationsfort-worth-primary' => '[[block|location-intro|city=Fort Worth|utility=Oncor]]',
            'page-locationscorpus-christi-primary' => '[[block|location-intro|city=Corpus Christi|utility=AEP Texas Central|note=Coastal homes: ask about our storm-season plans.]]',
            'rewards-promo-band' => '[[block|promo-band|heading=Rangler Rewards|text=Earn stars on every on-time payment and spend them on bill credits.|link=/myaccount/rewards|button=See your stars]]',
            'page-careerslead-developer-primary' => '[[block|job-post|summary=Lead our web and customer-account development. Experience with PHP/Laravel and plain JavaScript preferred.|team=Technology]]',
            'page-careersstaff-accountant-primary' => '[[block|job-post|summary=Help close the books each month and support billing reconciliation. CPA or CPA track preferred.|team=Finance|location=Austin, TX]]',
        ];
        foreach ($uses as $slug => $html) {
            ContentBlock::where('slug', $slug)->update(['html' => $html]);
        }
    }
}
