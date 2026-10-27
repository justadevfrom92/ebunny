<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\PdfGeneration;
use App\Models\PdfTemplate;
use App\Models\User;
use App\Support\PdfTemplateRenderer;
use Illuminate\Database\Seeder;

/** Sample PDF templates (fictional wording) and PDFs made from them for sample accounts. */
class PdfTemplateSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(77);
        $footer = '{{brand}} · Questions? Call {{phone}} or visit My Account. Sample document for demonstration.';
        $templates = [
            ['Payment Reminder Letter', 'Collections', 'Payment Reminder', <<<'TXT'
Hi {{first_name}},

Our records show a balance of {{balance}} on account {{account}}. If you have already paid, thank you, and please disregard this letter.

| Balance due | {{balance}} |
| Latest bill | {{last_bill}} |
| Due date | {{due_date}} |

## Ways to pay
- Online in My Account, any time
- By phone at {{phone}}
- Set up AutoPay so you never miss a due date

If you're having trouble paying, call us. We can set up a payment arrangement.
TXT],
            ['Disconnection Notice', 'Collections', 'Disconnection Notice', <<<'TXT'
**This notice is about electric service at {{address}}, {{city}}.**

Dear {{name}},

Your account {{account}} is past due. To avoid disconnection, please pay {{balance}} by the date below.

| Amount needed to avoid disconnection | {{balance}} |
| Pay by | {{due_date}} |

## What happens next
- If payment is received by the date above, your service continues as normal.
- If not, your service may be disconnected on or after that date.
- A reconnection fee may apply after a disconnection.

## Help paying your bill
You may qualify for a deferred payment plan or energy assistance programs. Call {{phone}} to talk about your options before the date above.

---

## Your rights
You can ask for a copy of your bill history at any time. If you think this notice is wrong, call us before the date above so we can review your account. You may also contact the Public Utility Commission of Texas.

**Medical needs:** if someone at this address depends on electric-powered medical equipment, call us right away. You may qualify for a critical care designation.
TXT],
            ['Welcome Letter', 'Welcome', 'Welcome to {{brand}}', <<<'TXT'
Welcome, {{first_name}}!

Thank you for choosing {{brand}}. Your service at {{address}} is set up on the {{plan}} plan.

| Account number | {{account}} |
| Plan | {{plan}} |
| ESIID | {{esiid}} |

## Get started
- Sign in to My Account to see your usage and bills
- Turn on AutoPay and paperless billing
- Earn Rangler Rewards stars for paying on time

We're glad you're here.
TXT],
            ['Deposit Request', 'Billing', 'Deposit Request', <<<'TXT'
Hi {{first_name}},

Based on the credit review for account {{account}}, a deposit is needed to start or keep service. The deposit is credited back after 12 months of on-time payments.

| Deposit amount | $150.00 |
| Account | {{account}} |

You can pay the deposit in My Account or by calling {{phone}}.
TXT],
            ['Final Bill Notice', 'Billing', 'Your Final Bill', <<<'TXT'
Hi {{first_name}},

Your service at {{address}} has ended. This is your final bill for account {{account}}.

| Final amount due | {{balance}} |
| Due date | {{due_date}} |

Any deposit on file has been applied to this balance. If you have a credit, we'll mail a refund check to the address we have on file.

Thank you for being a {{brand}} customer.
TXT],
            ['Contract Renewal Notice', 'Service', 'Your Contract Is Ending Soon', <<<'TXT'
Hi {{first_name}},

Your {{plan}} contract for {{address}} ends soon. Pick a new plan to keep a fixed rate.

## Your options
- Renew in My Account in a few minutes
- Call {{phone}} and we'll help you pick a plan
- Do nothing: you'll move to a month-to-month variable rate

| Account | {{account}} |
| Current plan | {{plan}} |
TXT],
            ['Terms of Service Summary', 'Legal', 'Terms of Service Summary', <<<'TXT'
# About this summary
This is a short summary of the terms for account {{account}}. The full Terms of Service, Your Rights as a Customer and the Electricity Facts Label for {{plan}} are in My Account.

# Billing
Bills are issued monthly and are due 16 days after they are issued. Late payments may carry a fee. You can pay online, by phone, by mail or with AutoPay.

# Deposits
A deposit may be required based on your credit history or payment history with us. Deposits are credited back after 12 months of on-time payments, or when service ends.

# Your contract
Fixed-rate plans keep the same energy rate for the contract term, except for changes in delivery charges or taxes. Ending a fixed-rate contract early may carry a cancellation fee, which is waived if you move and give us a forwarding address.

# Ending service
You can end service at any time. Your final bill includes usage up to the end date. Any credit is refunded within 10 business days.

# Disconnection
Service may be disconnected for non-payment after written notice. You will always get at least 10 days' notice. Disconnection will not happen on a weekend or holiday, or during extreme weather.

# Your privacy
We do not sell your personal information. We share it only to provide your service, as required by law, or with your permission.

# Complaints
If you have a complaint, call {{phone}} first. If we can't resolve it, you may contact the Public Utility Commission of Texas.

---

**Questions?** Call {{phone}} or visit My Account.
TXT],
            ['Rewards Statement', 'Rewards', 'Your Rangler Rewards', <<<'TXT'
Hi {{first_name}},

Here's your Rangler Rewards summary for account {{account}} as of {{today}}.

## How to earn stars
- Pay your bill on time
- Turn on AutoPay and paperless billing
- Refer a friend

Redeem stars in My Account for gift cards and bill credits.
TXT],
        ];
        $customers = Customer::with('plan')->inRandomOrder()->limit(30)->get();
        $staff = User::whereIn('email', ['admin@example.com', 'marketing@example.com', 'csr@example.com'])->pluck('id')->all() ?: [null];
        foreach ($templates as $i => [$name, $category, $title, $body]) {
            $t = PdfTemplate::create(['name' => $name, 'category' => $category, 'title' => $title, 'body' => $body, 'footer' => $footer,
                'show_address' => $category !== 'Legal', 'status' => $i === 7 ? 'Draft' : 'Active']);
            $t->forceFill(['created_at' => now()->subDays(60 - $i * 3), 'updated_at' => now()->subDays(20 - $i)])->saveQuietly();
            foreach ($customers->random(min($customers->count(), $i === 7 ? 0 : mt_rand(2, 6))) as $c) {
                $doc = $t->only(['title', 'body', 'footer', 'show_address']);
                $pdf = PdfTemplateRenderer::build($doc, $c);
                PdfGeneration::create($doc + ['pdf_template_id' => $t->id, 'customer_id' => $c->id, 'user_id' => $staff[array_rand($staff)],
                    'file_name' => $t->fileName($c), 'pages' => $pdf->pageCount(), 'size' => strlen($pdf->output($t->title)), 'created_at' => now()->subMinutes(mt_rand(30, 60 * 24 * 30))]);
            }
        }
    }
}
