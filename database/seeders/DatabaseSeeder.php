<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. The data files live in database/seeders/data.
     * Every customer and user is fictional sample data.
     */
    public function run(): void
    {
        $this->call([
            AccessSeeder::class,
            MoreUsersSeeder::class,
            CatalogSeeder::class,
            CmsSeeder::class,
            ContentModulesSeeder::class,
            PageHistorySeeder::class,
            CustomerSeeder::class,
            MarketingRewardsSeeder::class,
            AccountSeeder::class,
            PortalLoginSeeder::class,
            ItemSeeder::class,
            HistorySeeder::class,
            SystemSeeder::class,
            WalkerSeeder::class,
            SiteTrafficSeeder::class,
            CookieConsentSeeder::class,
            ContactMessageSeeder::class,
            DeputySeeder::class,
            CrmSeeder::class,
            RangerSeeder::class,
            FillTablesSeeder::class, // rows for every admin table that would otherwise be empty
            CampaignSeeder::class,   // after the account data (phone types, AutoPay) it targets
            HeartbeatSeeder::class,
            AdminNotificationSeeder::class,   // last: points at tickets, reports, files, emails and campaigns
        ]);
    }

    /** Read one of the JSON data files. */
    public static function data(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/data/'.$name.'.json'), true);
    }
}
