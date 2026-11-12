<?php

namespace Database\Seeders;

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceTierDiscount;
use Illuminate\Database\Seeder;

/**
 * Price tiers and the Nigerian catalogue. Services start on the `sandbox`
 * provider (or AFRITOPUP_SEED_PROVIDER) with their VTpass serviceIDs already
 * filled in, so going live is: enter the VTpass keys, switch each service's
 * provider to VTpass and press "Load plans from provider".
 *
 * The plans below are SAMPLES with indicative prices, for the sandbox. Loading
 * plans from the provider replaces them (and switches the samples off).
 * Only adds what is missing; never overwrites edits.
 */
class CatalogueSeeder extends Seeder
{
    public const TIERS = [
        ['customer', 'Customer', 'Everyone who signs up.', true],
        ['agent', 'Agent', 'Shops and individuals who sell to their own customers.', false],
        ['reseller', 'Reseller', 'Businesses buying in volume, usually through the API.', false],
    ];

    /** category => [percent off for agent, reseller] */
    public const DISCOUNTS = [
        'airtime' => [1.5, 2.5], 'data' => [2, 4], 'cable' => [0.5, 1], 'electricity' => [0.5, 1], 'sms' => [5, 10], 'education' => [0, 0.5],
    ];

    public function run(): void
    {
        foreach (self::TIERS as $i => [$code, $name, $desc, $default]) {
            PriceTier::firstOrCreate(['code' => $code], ['name' => $name, 'description' => $desc, 'is_default' => $default, 'position' => $i]);
        }

        $provider = env('AFRITOPUP_SEED_PROVIDER', 'sandbox');
        $smsProvider = $provider === 'sandbox' ? 'sandbox' : 'termii';
        $pos = 0;

        $services = [
            // category, code, name, short, colour, provider code, min, max, options
            ['airtime', 'mtn', 'MTN Airtime', 'MTN', '#FFCC00', 'mtn', 50, 50000, null],
            ['airtime', 'airtel', 'Airtel Airtime', 'Airtel', '#E40000', 'airtel', 50, 50000, null],
            ['airtime', 'glo', 'Glo Airtime', 'Glo', '#00A651', 'glo', 50, 50000, null],
            ['airtime', '9mobile', '9mobile Airtime', '9mobile', '#006E53', 'etisalat', 50, 50000, null],

            ['data', 'mtn-data', 'MTN Data', 'MTN', '#FFCC00', 'mtn-data', null, null, null],
            ['data', 'airtel-data', 'Airtel Data', 'Airtel', '#E40000', 'airtel-data', null, null, null],
            ['data', 'glo-data', 'Glo Data', 'Glo', '#00A651', 'glo-data', null, null, null],
            ['data', '9mobile-data', '9mobile Data', '9mobile', '#006E53', 'etisalat-data', null, null, null],

            ['cable', 'dstv', 'DStv', 'DStv', '#0096D6', 'dstv', null, null, null],
            ['cable', 'gotv', 'GOtv', 'GOtv', '#6CBE45', 'gotv', null, null, null],
            ['cable', 'startimes', 'StarTimes', 'StarTimes', '#F7941D', 'startimes', null, null, null],
            ['cable', 'showmax', 'Showmax', 'Showmax', '#E4007C', 'showmax', null, null, ['verify' => false, 'recipient_label' => 'Phone number']],

            ['electricity', 'ikeja-electric', 'Ikeja Electric (IKEDC)', 'IKEDC', '#E30613', 'ikeja-electric', 1000, 500000, null],
            ['electricity', 'eko-electric', 'Eko Electric (EKEDC)', 'EKEDC', '#004B8D', 'eko-electric', 1000, 500000, null],
            ['electricity', 'abuja-electric', 'Abuja Electric (AEDC)', 'AEDC', '#00843D', 'abuja-electric', 1000, 500000, null],
            ['electricity', 'ibadan-electric', 'Ibadan Electric (IBEDC)', 'IBEDC', '#F58220', 'ibadan-electric', 1000, 500000, null],
            ['electricity', 'enugu-electric', 'Enugu Electric (EEDC)', 'EEDC', '#C8102E', 'enugu-electric', 1000, 500000, null],
            ['electricity', 'portharcourt-electric', 'Port Harcourt Electric (PHED)', 'PHED', '#1B75BB', 'portharcourt-electric', 1000, 500000, null],
            ['electricity', 'kano-electric', 'Kano Electric (KEDCO)', 'KEDCO', '#00A859', 'kano-electric', 1000, 500000, null],
            ['electricity', 'kaduna-electric', 'Kaduna Electric', 'Kaduna', '#0055A4', 'kaduna-electric', 1000, 500000, null],
            ['electricity', 'jos-electric', 'Jos Electric (JED)', 'JED', '#2E3192', 'jos-electric', 1000, 500000, null],
            ['electricity', 'benin-electric', 'Benin Electric (BEDC)', 'BEDC', '#ED1C24', 'benin-electric', 1000, 500000, null],
            ['electricity', 'aba-electric', 'Aba Power', 'Aba', '#00A1E4', 'aba-electric', 1000, 500000, null],
            ['electricity', 'yola-electric', 'Yola Electric (YEDC)', 'YEDC', '#8DC63F', 'yola-electric', 1000, 500000, null],

            ['sms', 'bulk-sms', 'Bulk SMS', 'SMS', '#EC4899', null, null, null, ['price_per_page' => '4.00', 'cost_per_page' => '2.50']],

            ['education', 'waec', 'WAEC Result Checker', 'WAEC', '#1B5E20', 'waec', null, null, ['recipient_label' => 'Phone number to receive the PIN']],
            ['education', 'waec-registration', 'WAEC Registration PIN', 'WAEC Reg', '#2E7D32', 'waec-registration', null, null, ['recipient_label' => 'Phone number to receive the PIN']],
            ['education', 'jamb', 'JAMB ePIN', 'JAMB', '#004D40', 'jamb', null, null, ['profile_required' => true, 'recipient_label' => 'Phone number to receive the PIN']],
            ['education', 'school-fees', 'School Fees', 'School fees', '#0EA5E9', null, 1000, 5000000, ['school' => true, 'product' => false, 'amount' => true, 'recipient_label' => 'Student']],
        ];

        foreach ($services as [$cat, $code, $name, $short, $color, $pcode, $min, $max, $options]) {
            $prov = match (true) {
                $code === 'school-fees' => 'manual',
                $cat === 'sms'          => $smsProvider,
                default                 => $provider,
            };
            Service::firstOrCreate(['country_code' => 'NG', 'code' => $code], [
                'category' => $cat, 'name' => $name, 'short_name' => $short, 'color' => $color, 'provider' => $prov, 'provider_code' => $pcode,
                'min_amount' => $min, 'max_amount' => $max, 'fee' => 0, 'options' => $options, 'is_active' => true, 'position' => $pos++,
            ]);
        }

        $this->samplePlans();
        $this->otherCountries($provider === 'sandbox' ? 'sandbox' : 'reloadly');
        $this->discounts();
    }

    /** SAMPLE plans so the sandbox works end to end. Replace with "Load plans from provider". */
    protected function samplePlans(): void
    {
        $plans = [
            'mtn-data' => [['mtn-500mb-7d', 'MTN 500MB – 7 days', 500, '7 days'], ['mtn-1gb-30d', 'MTN 1GB – 30 days', 1000, '30 days'], ['mtn-2gb-30d', 'MTN 2GB – 30 days', 1500, '30 days'], ['mtn-5gb-30d', 'MTN 5GB – 30 days', 3500, '30 days'], ['mtn-10gb-30d', 'MTN 10GB – 30 days', 5500, '30 days']],
            'airtel-data' => [['airtel-1gb-30d', 'Airtel 1GB – 30 days', 1000, '30 days'], ['airtel-3gb-30d', 'Airtel 3GB – 30 days', 2000, '30 days'], ['airtel-10gb-30d', 'Airtel 10GB – 30 days', 5000, '30 days']],
            'glo-data' => [['glo-1gb-30d', 'Glo 1GB – 30 days', 500, '30 days'], ['glo-5gb-30d', 'Glo 5GB – 30 days', 2000, '30 days'], ['glo-10gb-30d', 'Glo 10GB – 30 days', 4000, '30 days']],
            '9mobile-data' => [['9mobile-1gb-30d', '9mobile 1GB – 30 days', 1000, '30 days'], ['9mobile-4.5gb-30d', '9mobile 4.5GB – 30 days', 4000, '30 days']],
            'dstv' => [['dstv-padi', 'DStv Padi', 4400, '1 month'], ['dstv-yanga', 'DStv Yanga', 6000, '1 month'], ['dstv-confam', 'DStv Confam', 11000, '1 month'], ['dstv-compact', 'DStv Compact', 19000, '1 month'], ['dstv-compact-plus', 'DStv Compact Plus', 30000, '1 month'], ['dstv-premium', 'DStv Premium', 44500, '1 month']],
            'gotv' => [['gotv-smallie', 'GOtv Smallie', 1900, '1 month'], ['gotv-jinja', 'GOtv Jinja', 3900, '1 month'], ['gotv-jolli', 'GOtv Jolli', 5800, '1 month'], ['gotv-max', 'GOtv Max', 8500, '1 month'], ['gotv-supa', 'GOtv Supa', 11400, '1 month']],
            'startimes' => [['startimes-nova', 'StarTimes Nova', 1900, '1 month'], ['startimes-basic', 'StarTimes Basic', 3700, '1 month'], ['startimes-classic', 'StarTimes Classic', 5500, '1 month']],
            'showmax' => [['showmax-mobile', 'Showmax Mobile', 1600, '1 month'], ['showmax-pro', 'Showmax Pro', 6300, '1 month']],
            'waec' => [['waecdirect', 'WAEC Result Checker PIN', 3900, null]],
            'waec-registration' => [['waec-reg', 'WAEC Registration PIN', 18000, null]],
            'jamb' => [['utme', 'JAMB UTME ePIN', 6200, null], ['de', 'JAMB Direct Entry ePIN', 6200, null]],
        ];

        foreach ($plans as $serviceCode => $rows) {
            $service = Service::where('country_code', 'NG')->where('code', $serviceCode)->first();
            if (!$service || $service->products()->exists()) continue;
            foreach ($rows as $i => [$code, $name, $price, $validity]) {
                Product::create(['service_id' => $service->id, 'code' => $code, 'name' => $name, 'cost' => $price, 'price' => $price,
                    'validity' => $validity, 'position' => $i, 'is_active' => true, 'meta' => ['sample' => true]]);
            }
        }
    }

    /**
     * Ghana and Kenya, so the markets can be tried end to end before going live
     * (the countries stay hidden until opened under Countries). For live
     * operators, use "Import networks from Reloadly" instead.
     */
    protected function otherCountries(string $provider): void
    {
        $markets = [
            'GH' => [
                ['airtime', 'mtn-gh', 'MTN Ghana Airtime', 'MTN', '#FFCC00', 1, 500],
                ['airtime', 'telecel-gh', 'Telecel Ghana Airtime', 'Telecel', '#E60000', 1, 500],
                ['airtime', 'airteltigo-gh', 'AirtelTigo Airtime', 'AirtelTigo', '#0033A0', 1, 500],
                ['data', 'mtn-gh-data', 'MTN Ghana Data', 'MTN', '#FFCC00', null, null],
            ],
            'KE' => [
                ['airtime', 'safaricom-ke', 'Safaricom Airtime', 'Safaricom', '#3DB54A', 10, 10000],
                ['airtime', 'airtel-ke', 'Airtel Kenya Airtime', 'Airtel', '#E40000', 10, 10000],
                ['data', 'safaricom-ke-data', 'Safaricom Data', 'Safaricom', '#3DB54A', null, null],
            ],
        ];
        $plans = [
            'mtn-gh-data' => [['5', 'MTN 1GB', 5], ['10', 'MTN 2.5GB', 10], ['20', 'MTN 6GB', 20]],
            'safaricom-ke-data' => [['50', 'Safaricom 1GB – 24 hours', 50], ['100', 'Safaricom 1.5GB – 3 days', 100], ['250', 'Safaricom 2GB – 7 days', 250]],
        ];
        foreach ($markets as $country => $rows) {
            foreach ($rows as $i => [$cat, $code, $name, $short, $color, $min, $max]) {
                $s = Service::firstOrCreate(['country_code' => $country, 'code' => $code], [
                    'category' => $cat, 'name' => $name, 'short_name' => $short, 'color' => $color, 'provider' => $provider,
                    'min_amount' => $min, 'max_amount' => $max, 'is_active' => true, 'position' => $i,
                ]);
                if (isset($plans[$code]) && !$s->products()->exists()) {
                    foreach ($plans[$code] as $j => [$pc, $pn, $price]) {
                        Product::create(['service_id' => $s->id, 'code' => $pc, 'name' => $pn, 'cost' => $price, 'price' => $price, 'position' => $j, 'is_active' => true, 'meta' => ['sample' => true]]);
                    }
                }
            }
        }
    }

    protected function discounts(): void
    {
        $agent = PriceTier::where('code', 'agent')->first();
        $reseller = PriceTier::where('code', 'reseller')->first();
        if (!$agent || !$reseller) return;

        foreach (Service::all() as $s) {
            [$a, $r] = self::DISCOUNTS[$s->category] ?? [0, 0];
            ServiceTierDiscount::firstOrCreate(['service_id' => $s->id, 'price_tier_id' => $agent->id], ['type' => 'percent', 'value' => $a]);
            ServiceTierDiscount::firstOrCreate(['service_id' => $s->id, 'price_tier_id' => $reseller->id], ['type' => 'percent', 'value' => $r]);
        }
    }
}
