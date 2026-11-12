<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Service;
use App\Models\User;
use App\Services\Site\SiteCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The public website: home, a page per service, live pricing, business,
 * developers, about, FAQ, contact and the legal pages.
 *
 * The quick-pay box on these pages hands the order to the signed-in buy
 * form. A visitor without an account is sent to sign up first and comes
 * straight back to that order afterwards.
 */
class SiteController extends Controller
{
    /** Short descriptions used on cards and service pages. */
    public const BLURBS = [
        'airtime'     => ['Top up any line in seconds', 'Recharge MTN, Airtel, Glo and 9mobile instantly, with agent and reseller discounts on every top-up.'],
        'data'        => ['Data bundles for every network', 'Daily, weekly and monthly data plans on every network, delivered the moment you pay.'],
        'cable'       => ['Renew DStv, GOtv & more', 'Check your smartcard name, pick a bouquet and stay connected — no queues, no scratch cards.'],
        'electricity' => ['Prepaid tokens & postpaid bills', 'Verify your meter, pay and get your token on screen, by SMS and by e-mail.'],
        'education'   => ['Exam PINs & school fees', 'WAEC result checkers, registration PINs, JAMB ePINs and school fees in one place.'],
        'sms'         => ['Bulk & business SMS', 'Reach thousands of customers with your own sender ID at a low price per page.'],
    ];

    public const CTA = ['airtime' => 'Buy airtime', 'data' => 'Buy data', 'cable' => 'Renew your TV', 'electricity' => 'Buy power', 'education' => 'Pay exams & fees', 'sms' => 'Send bulk SMS'];

    public function __construct(protected SiteCatalogue $catalogue) {}

    public function home(Request $request)
    {
        $country = $this->catalogue->country($request->query('country', $request->user()?->country_code));

        return view('site.home', [
            'catalogue' => $this->catalogue->catalogue($country),
            'countries' => $this->catalogue->countries(),
            'billers'   => $this->catalogue->billers($country),
            'stats'     => $this->catalogue->stats(),
            'faqs'      => array_slice(self::faqs(), 0, 6),
            'blurbs'    => self::BLURBS,
        ]);
    }

    public function service(Request $request, string $category)
    {
        $cat = config('afritopup.categories.' . $category);
        abort_unless($cat, 404);
        $country = $this->catalogue->country($request->query('country', $request->user()?->country_code));
        $catalogue = $this->catalogue->catalogue($country);
        $entry = collect($catalogue['categories'])->firstWhere('key', $category);

        // Plans for the price list (data, cable, exam PINs).
        $plans = [];
        if ($entry && $cat['product']) {
            foreach (Service::whereIn('id', collect($entry['services'])->pluck('id'))->get() as $s) {
                $plans[] = ['service' => $s, 'plans' => $this->catalogue->plans($s)];
            }
        }

        return view('site.service', [
            'key'       => $category,
            'cat'       => $cat,
            'entry'     => $entry,
            'catalogue' => $catalogue,
            'countries' => $this->catalogue->countries(),
            'plans'     => $plans,
            'blurb'     => self::BLURBS[$category] ?? [$cat['name'], ''],
            'faqs'      => self::faqs($category),
        ]);
    }

    public function pricing(Request $request)
    {
        $country = $this->catalogue->country($request->query('country', $request->user()?->country_code));
        $services = Service::active()->in($country->code)->ordered()->get()->filter->needsProduct()->groupBy('category');

        return view('site.pricing', [
            'country'   => $country,
            'countries' => $this->catalogue->countries(),
            'groups'    => $services->map(fn ($list) => $list->map(fn ($s) => ['service' => $s, 'plans' => $this->catalogue->plans($s)])),
            'airtime'   => Service::active()->in($country->code)->where('category', 'airtime')->ordered()->get(),
        ]);
    }

    public function page(string $page)
    {
        $pages = ['about' => 'About us', 'business' => 'Agents & resellers', 'developers' => 'Developers', 'faq' => 'Help centre', 'terms' => 'Terms of service', 'privacy' => 'Privacy policy'];
        abort_unless(isset($pages[$page]), 404);

        return view('site.pages.' . $page, [
            'title' => $pages[$page],
            'faqs'  => self::faqs(),
            'stats' => $this->catalogue->stats(),
            'countries' => $this->catalogue->countries(),
        ]);
    }

    public function contact()
    {
        return view('site.pages.contact', ['title' => 'Contact us', 'topics' => ContactMessage::TOPICS]);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        // Bots fill the hidden "website" field; humans never see it.
        if ($request->filled('website')) return back()->with('contact_sent', true);

        $data = $request->validate([
            'name'    => 'required|string|max:120',
            'email'   => 'required|email|max:190',
            'phone'   => 'nullable|string|max:30',
            'topic'   => 'required|in:' . implode(',', array_keys(ContactMessage::TOPICS)),
            'message' => 'required|string|min:10|max:5000',
        ]);

        $msg = ContactMessage::create($data + ['user_id' => $request->user()?->id, 'ip' => $request->ip()]);

        rescue(function () use ($msg) {
            $to = config('afritopup.support_email');
            if ($to) {
                Mail::raw("New message from the website\n\nFrom: {$msg->name} <{$msg->email}> {$msg->phone}\nTopic: " . (ContactMessage::TOPICS[$msg->topic] ?? $msg->topic) . "\n\n{$msg->message}",
                    fn ($m) => $m->to($to)->replyTo($msg->email, $msg->name)->subject('[Website] ' . (ContactMessage::TOPICS[$msg->topic] ?? 'Message') . ' — ' . $msg->name));
            }
        }, fn ($e) => Log::warning('Contact e-mail not sent: ' . $e->getMessage()), false);

        return back()->with('contact_sent', true);
    }

    /**
     * One link for the app (QR code, shares): Android phones go to Google Play,
     * iPhones and iPads to the App Store, everyone else to the app section.
     */
    public function app(Request $request): RedirectResponse
    {
        $ua = strtolower((string) $request->userAgent());
        $links = config('afritopup.app_links', []);
        $ios = str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod');
        $android = str_contains($ua, 'android');

        if ($ios && !empty($links['ios'])) return redirect()->away($links['ios']);
        if ($android && !empty($links['android'])) return redirect()->away($links['android']);

        return redirect()->to(route('home') . '#app');
    }

    /** Plans for one service (quick-pay widget). */
    public function plans(Service $service): JsonResponse
    {
        abort_unless($service->is_active, 404);

        return response()->json(['data' => $this->catalogue->plans($service)])->header('Cache-Control', 'public, max-age=120');
    }

    /**
     * The quick-pay box: pass the order to the buy form. Visitors sign up
     * (or log in) first and land back on the same order.
     */
    public function quickPay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category'   => 'required|string|in:' . implode(',', array_keys(config('afritopup.categories'))),
            'service'    => 'nullable|string|max:60',
            'recipient'  => 'nullable|string|max:30',
            'amount'     => 'nullable|numeric|min:0|max:10000000',
            'product'    => 'nullable|integer',
            'meter_type' => 'nullable|in:prepaid,postpaid',
        ]);

        $url = route('topup.buy.form', array_filter([
            'category'   => $data['category'],
            'service'    => $data['service'] ?? null,
            'recipient'  => isset($data['recipient']) ? preg_replace('/[^0-9A-Za-z+]/', '', $data['recipient']) : null,
            'amount'     => $data['amount'] ?? null,
            'product'    => $data['product'] ?? null,
            'meter_type' => $data['meter_type'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        if ($request->user()) return redirect()->to($url);

        $request->session()->put('url.intended', $url);

        return redirect()->route(config('afritopup.allow_registration') && !$request->boolean('login') ? 'register' : 'login')
            ->with('status', 'Create a free account (or log in) to finish your payment — your order is saved.');
    }

    /** FAQ entries; a category adds its own questions first. */
    public static function faqs(?string $category = null): array
    {
        $name = config('afritopup.name', 'AfriTopup');
        $general = [
            ['How fast is delivery?', 'Most airtime, data, cable and electricity payments are delivered within seconds. If a network is slow, your purchase shows as “processing” and we keep checking until it completes — or refund your wallet automatically if it fails.'],
            ['How do I fund my wallet?', 'Pay with your debit card, or send a bank transfer to your personal account number shown on the Wallet page — it credits your wallet automatically, day or night.'],
            ['Is my money safe?', 'Every purchase needs your 4-digit transaction PIN, card payments are processed by Paystack, and your wallet keeps a full statement of every credit and debit.'],
            ['What if a payment fails?', 'Failed purchases are refunded to your wallet straight away. You can see the refund on the receipt and in your wallet statement.'],
            ['Can I earn money with ' . $name . '?', 'Yes. Agents and resellers buy at a discount and sell at the regular price. You also earn when friends you refer start using ' . $name . '.'],
            ['Do you have an API?', 'Yes — resellers can connect their own website or app to our REST API with an API key, and get webhooks when purchases complete.'],
            ['Which countries do you support?', 'Nigeria today, with Ghana, Kenya and more African countries rolling out. Switch country from your account to buy local airtime and data.'],
            ['Can I use the website and the app together?', 'Yes. It is the same account and the same wallet — sign in on either and everything is in sync.'],
        ];

        $specific = [
            'airtime'     => [['Can I buy airtime for another number?', 'Yes — type any number on any network. We detect the network from the number so you do not pick the wrong one.'],
                              ['What is the minimum top-up?', 'From as little as ₦50, up to ₦50,000 per top-up.']],
            'data'        => [['Will the data roll over?', 'That depends on the network’s plan. Each plan shows its validity before you pay.']],
            'cable'       => [['Do I need my smartcard name?', 'We check the smartcard / IUC number and show the name on the account before you pay, so you can be sure it is right.'],
                              ['Renew or change bouquet?', 'Choose “renew” to keep your current bouquet, or pick a new one to upgrade or downgrade.']],
            'electricity' => [['Where do I see my token?', 'On the receipt straight away, and by SMS and e-mail. You can always find it again in your transaction history.'],
                              ['Prepaid and postpaid?', 'Both. Choose your meter type, we verify the meter and show the customer name before you pay.']],
            'education'   => [['Which exam PINs can I buy?', 'WAEC result checker and registration PINs and JAMB ePINs. The PIN shows on your receipt immediately.']],
            'sms'         => [['Can I use my own sender ID?', 'Yes — request a sender ID from your account. Once approved by the networks, your messages go out under your name.']],
        ];

        return array_merge($specific[$category] ?? [], $general);
    }
}
