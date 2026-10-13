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
            CatalogSeeder::class,
            CmsSeeder::class,
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
        ]);
    }

    /** Read one of the JSON data files. */
    public static function data(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/data/'.$name.'.json'), true);
    }
}
