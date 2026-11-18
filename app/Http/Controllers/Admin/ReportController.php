<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Reports\ReportService;
use App\Services\Topup\Providers\ProviderManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reports & analytics: sales, profit, transactions, vendors, channels and customers for a period. */
class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View reports');
    }

    public function index(Request $request, ProviderManager $providers)
    {
        $r = new ReportService($request->query());

        return view('admin.reports.index', [
            'pagetitle'  => 'Reports & analytics',
            'r'          => $r,
            'f'          => $r->filters(),
            's'          => $r->summary(),
            'daily'      => $r->daily(),
            'hourly'     => $r->hourly(),
            'byCategory' => $r->by('category'),
            'byVendor'   => $r->by('provider'),
            'byChannel'  => $r->by('channel'),
            'services'   => $r->services(),
            'customers'  => $r->customers(),
            'currencies' => ReportService::currencies(),
            'vendors'    => $this->vendorNames($providers),
            'categories' => collect(config('afritopup.categories'))->map(fn ($c) => $c['name'])->all(),
        ]);
    }

    /** CSV: the day-by-day summary, a breakdown, or every purchase in the period. */
    public function export(Request $request, ProviderManager $providers, string $what): StreamedResponse
    {
        abort_unless(in_array($what, ['daily', 'categories', 'vendors', 'channels', 'services', 'transactions'], true), 404);
        $r = new ReportService($request->query());
        $names = $this->vendorNames($providers);
        $cats = collect(config('afritopup.categories'))->map(fn ($c) => $c['name'])->all();
        $file = 'pericharge-' . $what . '-' . $r->from->format('Ymd') . '-' . $r->to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($r, $what, $names, $cats) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // Excel: UTF-8
            $breakdown = function (array $rows, string $label, callable $name) use ($out) {
                fputcsv($out, [$label, 'Purchases', 'Successful', 'Failed', 'Success rate %', 'Sales', 'Cost', 'Profit', 'Margin %']);
                foreach ($rows as $x) fputcsv($out, [$name($x), $x['n'], $x['ok'], $x['failed'], $x['rate'] === null ? '' : round($x['rate'], 1),
                    round($x['sales'], 2), round($x['cost'], 2), round($x['profit'], 2), round($x['margin'], 1)]);
            };
            match ($what) {
                'daily' => (function () use ($r, $out) {
                    fputcsv($out, ['Date', 'Purchases', 'Successful', 'Failed', 'Sales', 'Cost', 'Profit']);
                    foreach ($r->daily() as $d) fputcsv($out, [$d['date'], $d['n'], $d['ok'], $d['failed'], round($d['sales'], 2), round($d['cost'], 2), round($d['profit'], 2)]);
                })(),
                'categories' => $breakdown($r->by('category'), 'Category', fn ($x) => $cats[$x['key']] ?? $x['key']),
                'vendors' => $breakdown($r->by('provider'), 'Vendor', fn ($x) => $names[$x['key']] ?? $x['key']),
                'channels' => $breakdown($r->by('channel'), 'Channel', fn ($x) => strtoupper($x['key'])),
                'services' => $breakdown($r->services(1000), 'Service', fn ($x) => $x['key']),
                'transactions' => (function () use ($r, $out, $names) {
                    fputcsv($out, ['Reference', 'Date', 'Customer', 'Email', 'Category', 'Service', 'Plan', 'Recipient', 'Amount', 'Discount', 'Fee', 'Total', 'Cost', 'Profit', 'Currency', 'Status', 'Vendor', 'Vendor reference', 'Channel']);
                    $r->base()->with('user:id,name,email', 'service:id,name', 'product:id,name')->orderBy('transactions.id')
                        ->chunk(1000, function ($rows) use ($out, $names) {
                            foreach ($rows as $t) {
                                $cost = $t->cost ?? $t->total;
                                fputcsv($out, [$t->reference, $t->created_at?->format('Y-m-d H:i:s'), $t->user?->name, $t->user?->email, $t->category, $t->service?->name, $t->product?->name,
                                    $t->recipient, $t->amount, $t->discount, $t->fee, $t->total, $t->cost,
                                    $t->status === Transaction::SUCCESSFUL ? round((float) $t->total - (float) $cost, 2) : '', $t->currency, $t->status,
                                    $names[$t->provider] ?? $t->provider, $t->provider_reference, $t->channel]);
                            }
                        });
                })(),
            };
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Vendor key => name, including vendors that were removed (shown by their key). */
    protected function vendorNames(ProviderManager $providers): array
    {
        $names = $providers->options();
        foreach (Transaction::query()->distinct()->pluck('provider') as $k) $names[$k] ??= $k;

        return $names;
    }
}
