<?php

/*
| My Account → Products: every product, one page, the same card for each. "self" products
| the customer turns on and off right there; the rest say how to get them (call, chat, or a
| page in My Account). Names match config('corral.products'), so Corral sees the same thing.
|   slug => [product name, short description, price / terms, how: self | call | link route]
*/
return [
    'products' => [
        'autopay' => ['AutoPay', 'Pay your bill automatically on the due date with your default payment method.', 'Free', 'self'],
        'paperless-billing' => ['Paperless Billing', 'Get your bill by email instead of by mail.', 'Free', 'self'],
        'giddy-up' => ['Pick Your Due Date', 'Choose the day of the month your bill is due.', 'Free', 'self'],
        'balanced-billing' => ['Balanced Billing', 'Pay about the same amount every month, averaged over your last 12 months of use.', 'Free', 'self'],
        'combined-billing' => ['Combined Billing', 'One bill for all of your accounts, with one due date.', 'Free, for customers with more than one account', 'call'],
        'rangler-rewards' => ['Rangler Rewards', 'Earn stars for paying on time, AutoPay and referrals, and spend them on bill credits and gift cards.', 'Free', 'myaccount.rewards'],
        'refer-a-friend' => ['Refer-A-Friend', 'Share your link; you both get a bill credit when your friend signs up.', '$50 credit each', 'myaccount.refer'],
        'peak-perks' => ['Peak Perks', 'Earn bill credits for using less electricity during peak demand events.', 'Free; credits each summer', 'self'],
        'peak-perks-plus' => ['Peak Perks+', 'Peak Perks with a smart thermostat that adjusts itself during events, for bigger credits.', 'Free with a qualifying thermostat', 'self'],
        'ecobee' => ['ecobee', 'A smart thermostat, shipped to you and set up for Peak Perks+.', '$0 down, $5.99/mo for 24 months', 'call'],
        'freedom-flex' => ['Freedom Flex', 'Change plans once a year without a cancellation fee.', 'Included on select plans', 'call'],
        'giddyup-guarantee' => ['Giddyup Guarantee', 'Your rate is locked for your whole contract, guaranteed.', 'Included on fixed-rate plans', 'call'],
        'solar-buyback' => ['Solar Buyback', 'Get credit on your bill for the solar power your panels send to the grid.', 'For homes with solar panels', 'call'],
        'solar-lead-referral' => ['Solar Lead Referral', 'Get a free quote from our solar partner.', 'Free quote', 'self'],
        'deposit-alternatives' => ['Deposit Alternatives', 'Lower or skip a deposit with AutoPay and paperless billing, or a letter of credit.', 'Free', 'call'],
        'payment-arrangement' => ['Payment Arrangement', 'Split a past-due balance over your next few bills.', 'Free; must be current on new charges', 'call'],
        'deferred-payment-plan' => ['Deferred Payment Plan', 'Spread a high bill from extreme weather over up to 6 months.', 'Free', 'call'],
        'home-warranty' => ['AIG Home Warranty', 'Repairs for major home systems and appliances.', '$34.99/mo', 'self'],
        'electric-line-protection' => ['Electric System Protection Program', 'Covers repairs to the wiring and electric panel in your home.', '$5.99/mo', 'self'],
        'surge-protection' => ['Surge Protection Program', 'Covers appliances damaged by a power surge.', '$6.99/mo', 'self'],
        'ac-protection' => ['Air Conditioning Protection Program', 'Covers repairs to your central air conditioner and heat pump.', '$14.99/mo', 'self'],
        'ev-charger-protection' => ['Electric Vehicle Charger Protection Program', 'Covers repairs to your home EV charger.', '$7.99/mo', 'self'],
        'adt-security' => ['ADT Security', 'Home security monitoring with a discount for our customers.', 'From $29.99/mo through ADT', 'call'],
    ],
];
