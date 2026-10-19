<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * More FICTIONAL admin users for Sheriff → Users: employees across departments, and
 * 3rd-party company users (outsourced call center, collections, agencies, vendors).
 * Their addresses are on reserved example domains, so they don't appear in the
 * one-click test sign-in list (that stays @example.com only).
 */
class MoreUsersSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(5150);
        $password = env('ADMIN_DEMO_PASSWORD', 'password');
        $role = fn ($n) => Role::where('name', $n)->value('id');
        $mgr = fn ($e) => User::where('email', $e)->value('id');

        // [name, title, department, role, manager email, location, ext, hired, denied, extra, note]
        $staff = [
            ['Morgan Placeholder', 'Customer Service Rep', 'Customer Service', 'Customer Service', 'lead@example.com', 'Houston Call Center', '2241', '2024-02-05', [], [], null],
            ['Jordan Sampleton', 'Customer Service Rep', 'Customer Service', 'Customer Service', 'lead@example.com', 'Houston Call Center', '2242', '2024-08-19', [], [], null],
            ['Riley Testwood', 'Senior Customer Service Rep', 'Customer Service', 'Customer Service', 'lead@example.com', 'Houston Call Center', '2243', '2021-11-01', [], ['rodeo'], 'Covers text replies for the lead'],
            ['Quinn Mockridge', 'Bilingual Customer Service Rep', 'Customer Service', 'Customer Service', 'lead@example.com', 'Houston Call Center', '2244', '2025-01-13', [], [], null],
            ['Taylor Demoson', 'Retention Specialist', 'Customer Service', 'Customer Service Lead', 'lead@example.com', 'Austin HQ', '2250', '2022-04-25', [], [], null],
            ['Parker Fictive', 'Pricing Analyst', 'Pricing', 'Pricing', 'pricing@example.com', 'Austin HQ', '2311', '2023-09-11', [], [], null],
            ['Casey Notreal', 'Marketing Coordinator', 'Marketing', 'Content Editor', 'marketing@example.com', 'Austin HQ', '2412', '2024-05-06', [], [], null],
            ['Drew Specimen', 'Web Content Specialist', 'Marketing', 'Content Editor', 'marketing@example.com', 'Remote', '2413', '2025-03-17', [], [], null],
            ['Avery Ledgerly', 'Accounts Receivable Specialist', 'Finance', 'Finance', 'finance@example.com', 'Austin HQ', '2511', '2022-07-18', ['refunds'], [], 'Refunds are approved by the Controller'],
            ['Hayden Sumwell', 'Staff Accountant', 'Finance', 'Finance', 'finance@example.com', 'Austin HQ', '2512', '2023-01-09', [], [], null],
            ['Emerson Botley', 'AI Operations Analyst', 'AI Operations', 'AI Operations', 'ai@example.com', 'Remote', '2611', '2025-06-02', [], [], null],
            ['Reese Kernel', 'Systems Administrator', 'IT', 'Administrator', 'admin@example.com', 'Austin HQ', '2111', '2020-10-12', [], [], null],
            ['Sawyer Stackman', 'Developer', 'IT', 'Administrator', 'admin@example.com', 'Remote', '2112', '2024-03-04', [], [], null],
            ['Dakota Chiefly', 'VP of Operations', 'Management', 'Customer Service Lead', null, 'Austin HQ', '2001', '2018-01-15', [], ['caboose', 'walker'], 'Reads finance and reports'],
        ];
        foreach ($staff as $i => [$name, $title, $dept, $r, $manager, $loc, $ext, $hired, $denied, $extra, $note]) {
            $this->make([
                'name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@staff.example.com', 'employee_id' => 'E-'.(1100 + $i * 7),
                'title' => $title, 'department' => $dept, 'role_id' => $role($r), 'manager_id' => $manager ? $mgr($manager) : null, 'location' => $loc,
                'phone_ext' => $ext, 'hired_on' => $hired, 'denied_perms' => $denied, 'extra_perms' => $extra, 'access_note' => $note,
            ], $password);
        }

        // 3rd-party companies: [company, domain, contact (manager) email, contract end, [[name, title, role, denied, extra, note]]]
        $companies = [
            ['Brightline Contact Solutions', 'brightline.example.net', 'lead@example.com', '+8 months', [
                ['Alex Overflow', 'Contract Agent — Overflow Queue', 'Customer Service', ['bounty'], [], 'Outsourced overflow calls; no rewards changes'],
                ['Blair Afterhours', 'Contract Agent — After Hours', 'Customer Service', ['bounty'], [], 'Outsourced overflow calls; no rewards changes'],
                ['Cameron Weekender', 'Contract Agent — Weekends', 'Customer Service', ['bounty'], [], null],
                ['Devon Supervisor', 'Contract Team Supervisor', 'Customer Service Lead', ['deputy'], [], 'Supervises the outsourced agents'],
            ]],
            ['Lone Prairie Recovery Services', 'loneprairie.example.net', 'finance@example.com', '+5 months', [
                ['Ellis Collector', 'Collections Agent', 'Finance', ['refunds', 'walker'], [], 'Collections only: past-due accounts'],
                ['Finley Dunning', 'Collections Manager', 'Finance', ['refunds'], [], null],
            ]],
            ['Mesquite Creative Agency', 'mesquite.example.org', 'marketing@example.com', '+3 months', [
                ['Gray Copywright', 'Agency Copywriter', 'Content Editor', ['rodeo'], [], 'Website copy only, no campaigns'],
                ['Harper Pixelton', 'Agency Web Designer', 'Content Editor', ['rodeo'], [], 'Website copy only, no campaigns'],
            ]],
            ['Caliche Energy Consulting', 'caliche.example.org', 'pricing@example.com', '+2 months', [
                ['Indy Ratewell', 'Pricing Consultant', 'Pricing', ['lando'], ['walker'], 'Rates and reports, not the website'],
            ]],
            ['Trailhead IT Partners', 'trailhead.example.net', 'admin@example.com', '+11 months', [
                ['Jessie Patchwell', 'Managed Services Engineer', 'Administrator', ['refunds'], [], 'No refunds'],
            ]],
        ];
        foreach ($companies as [$company, $domain, $contact, $ends, $people]) {
            foreach ($people as [$name, $title, $r, $denied, $extra, $note]) {
                $this->make([
                    'name' => $name, 'email' => strtolower(explode(' ', $name)[0]).'.'.strtolower(explode(' ', $name)[1])[0].'@'.$domain,
                    'company' => $company, 'access_ends_on' => Carbon::parse($ends)->endOfMonth()->toDateString(), 'title' => $title,
                    'department' => null, 'role_id' => $role($r), 'manager_id' => $mgr($contact), 'location' => 'Off-site ('.$company.')',
                    'denied_perms' => $denied, 'extra_perms' => $extra, 'access_note' => $note, 'hired_on' => now()->subMonths(mt_rand(2, 20))->toDateString(),
                ], $password);
            }
        }
        // One contractor whose access already ended
        $this->make(['name' => 'Kai Formerton', 'email' => 'kai.f@brightline.example.net', 'company' => 'Brightline Contact Solutions', 'title' => 'Contract Agent',
            'role_id' => $role('Customer Service'), 'access_ends_on' => now()->subMonths(2)->endOfMonth()->toDateString(), 'active' => false, 'location' => 'Off-site (Brightline Contact Solutions)'], $password);
    }

    private function make(array $attrs, string $password): void
    {
        $u = User::updateOrCreate(['email' => $attrs['email']], $attrs + ['password' => $password, 'active' => true]);
        $u->forceFill(['last_login_at' => ($u->active ? now()->subMinutes(mt_rand(20, 60 * 24 * 9)) : now()->subMonths(2))])->save();
    }
}
