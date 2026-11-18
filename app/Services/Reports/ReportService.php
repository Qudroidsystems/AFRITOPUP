<?php

namespace App\Services\Reports;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reports & analytics: sales, cost, profit, transactions, success rates, vendors, channels,
 * customers and wallet fundings for a period, in one currency.
 *
 *   sales        = what customers paid for successful purchases (transactions.total)
 *   cost         = what the vendor charged us (transactions.cost; counted as zero margin when unknown,
 *                  as on the dashboard and the transactions page)
 *   gross profit = sales − cost
 *   net profit   = gross profit − referral rewards paid out in the period
 */
class ReportService
{
    public const PRESETS = [
        'today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days',
        'month' => 'This month', 'last_month' => 'Last month', '90d' => 'Last 90 days', 'year' => 'This year', 'custom' => 'Custom',
    ];

    public CarbonImmutable $from;
    public CarbonImmutable $to;
    public string $preset;
    public string $currency;
    public ?string $category;
    public ?string $vendor;
    public ?string $channel;

    public function __construct(array $f = [])
    {
        $this->preset = array_key_exists($f['range'] ?? '', self::PRESETS) ? $f['range'] : '30d';
        $now = CarbonImmutable::now();
        [$from, $to] = match ($this->preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            '90d' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'year' => [$now->startOfYear(), $now->endOfDay()],
            'custom' => [
                rescue(fn () => CarbonImmutable::parse($f['from'])->startOfDay(), $now->subDays(29)->startOfDay(), false),
                rescue(fn () => CarbonImmutable::parse($f['to'])->endOfDay(), $now->endOfDay(), false),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
        if ($from->gt($to)) [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        if ($from->diffInDays($to) > 731) $from = $to->subDays(731)->startOfDay();   // two years at most
        $this->from = $from;
        $this->to = $to;
        $this->currency = strtoupper((string) ($f['currency'] ?? '')) ?: (string) config('pericharge.default_currency', 'NGN');
        $this->category = ($f['category'] ?? null) ?: null;
        $this->vendor = ($f['vendor'] ?? null) ?: null;
        $this->channel = ($f['channel'] ?? null) ?: null;
    }

    /** Purchases in the period (any status) with the filters applied. */
    public function base(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Builder
    {
        return Transaction::query()
            ->whereBetween('transactions.created_at', [$from ?? $this->from, $to ?? $this->to])
            ->where('transactions.currency', $this->currency)
            ->when($this->category, fn ($q) => $q->where('transactions.category', $this->category))
            ->when($this->vendor, fn ($q) => $q->where('transactions.provider', $this->vendor))
            ->when($this->channel, fn ($q) => $q->where('transactions.channel', $this->channel));
    }

    protected function ok(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Builder
    {
        return $this->base($from, $to)->where('transactions.status', Transaction::SUCCESSFUL);
    }

    /** SQL for the cost of a successful purchase (no margin claimed when the cost is unknown). */
    protected const COST = 'COALESCE(transactions.cost, transactions.total)';

    protected function money(Builder $q): array
    {
        $r = $q->selectRaw('COUNT(*) as n, COALESCE(SUM(transactions.total),0) as sales, COALESCE(SUM(' . self::COST . '),0) as cost, COALESCE(SUM(transactions.discount),0) as discount, COALESCE(SUM(transactions.fee),0) as fees')->first();

        return ['n' => (int) $r->n, 'sales' => (float) $r->sales, 'cost' => (float) $r->cost, 'profit' => (float) $r->sales - (float) $r->cost,
            'discount' => (float) $r->discount, 'fees' => (float) $r->fees];
    }

    public function summary(): array
    {
        $ok = $this->money($this->ok());
        $statuses = $this->base()->selectRaw('transactions.status, COUNT(*) as n, COALESCE(SUM(transactions.total),0) as v')->groupBy('transactions.status')->get()->keyBy('status');
        $all = (int) $statuses->sum('n');
        $settled = (int) (($statuses[Transaction::SUCCESSFUL]->n ?? 0) + ($statuses[Transaction::FAILED]->n ?? 0) + ($statuses[Transaction::REFUNDED]->n ?? 0));

        // The same length of time just before, for the change figures.
        $len = $this->from->diffInSeconds($this->to);
        $prevTo = $this->from->subSecond();
        $prev = $this->money($this->ok($prevTo->subSeconds($len), $prevTo));

        $referrals = Schema::hasTable('referral_rewards')
            ? (float) DB::table('referral_rewards')->where('status', 'credited')->whereBetween('created_at', [$this->from, $this->to])->sum('amount') : 0.0;

        $fund = WalletFunding::where('status', 'successful')->where('currency', $this->currency)
            ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$this->from, $this->to])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as amount, COALESCE(SUM(fee),0) as fee')->first();

        $buyers = (int) $this->ok()->distinct('transactions.user_id')->count('transactions.user_id');

        return [
            'sales' => $ok['sales'], 'cost' => $ok['cost'], 'profit' => $ok['profit'], 'count' => $ok['n'],
            'discount' => $ok['discount'], 'fees' => $ok['fees'],
            'margin' => $ok['sales'] > 0 ? $ok['profit'] / $ok['sales'] * 100 : 0,
            'avg' => $ok['n'] ? $ok['sales'] / $ok['n'] : 0,
            'avgProfit' => $ok['n'] ? $ok['profit'] / $ok['n'] : 0,
            'referrals' => $referrals,
            'net' => $ok['profit'] - $referrals,
            'all' => $all,
            'successRate' => $settled ? ($statuses[Transaction::SUCCESSFUL]->n ?? 0) / $settled * 100 : null,
            'statuses' => collect(Transaction::STATUSES)->map(fn ($meta, $k) => ['label' => $meta[0], 'color' => $meta[1],
                'n' => (int) ($statuses[$k]->n ?? 0), 'value' => (float) ($statuses[$k]->v ?? 0)])->all(),
            'prev' => $prev,
            'change' => [
                'sales' => $this->pct($ok['sales'], $prev['sales']),
                'profit' => $this->pct($ok['profit'], $prev['profit']),
                'count' => $this->pct($ok['n'], $prev['n']),
            ],
            'buyers' => $buyers,
            'newCustomers' => User::buyers()->whereBetween('created_at', [$this->from, $this->to])->count(),
            'fundings' => ['n' => (int) $fund->n, 'amount' => (float) $fund->amount, 'fee' => (float) $fund->fee],
            'float' => (float) Wallet::where('currency', $this->currency)->sum('balance'),
            'stuck' => Transaction::where('status', Transaction::PROCESSING)->where('currency', $this->currency)->where('created_at', '<=', now()->subMinutes(15))->count(),
        ];
    }

    protected function pct(float $now, float $before): ?float
    {
        if ($before == 0.0) return $now == 0.0 ? 0.0 : null;

        return ($now - $before) / abs($before) * 100;
    }

    /** One row per day (every day of the period, zeros included): sales, cost, profit, count, failed. */
    public function daily(): array
    {
        $rows = $this->base()->selectRaw("DATE(transactions.created_at) as d,
                SUM(CASE WHEN transactions.status = 'successful' THEN transactions.total ELSE 0 END) as sales,
                SUM(CASE WHEN transactions.status = 'successful' THEN " . self::COST . " ELSE 0 END) as cost,
                SUM(CASE WHEN transactions.status = 'successful' THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN transactions.status IN ('failed','refunded') THEN 1 ELSE 0 END) as failed,
                COUNT(*) as n")
            ->groupBy(DB::raw('DATE(transactions.created_at)'))->get()->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

        $out = [];
        for ($d = $this->from->startOfDay(); $d->lte($this->to); $d = $d->addDay()) {
            $k = $d->format('Y-m-d');
            $r = $rows[$k] ?? null;
            $sales = (float) ($r->sales ?? 0);
            $cost = (float) ($r->cost ?? 0);
            $out[] = ['date' => $k, 'sales' => $sales, 'cost' => $cost, 'profit' => $sales - $cost,
                'ok' => (int) ($r->ok ?? 0), 'failed' => (int) ($r->failed ?? 0), 'n' => (int) ($r->n ?? 0)];
        }

        return $out;
    }

    /** Successful purchases by hour of the day (0–23). */
    public function hourly(): array
    {
        $expr = match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', transactions.created_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM transactions.created_at)',
            default => 'HOUR(transactions.created_at)',
        };
        $rows = $this->ok()->selectRaw("$expr as h, COUNT(*) as n, COALESCE(SUM(transactions.total),0) as sales")->groupBy(DB::raw($expr))->get()->keyBy(fn ($r) => (int) $r->h);

        return collect(range(0, 23))->map(fn ($h) => ['hour' => $h, 'n' => (int) ($rows[$h]->n ?? 0), 'sales' => (float) ($rows[$h]->sales ?? 0)])->all();
    }

    /**
     * Breakdown by a column: category, provider (vendor) or channel.
     * @return array<int, array{key: string, n: int, ok: int, failed: int, sales: float, cost: float, profit: float, margin: float, rate: ?float}>
     */
    public function by(string $column): array
    {
        abort_unless(in_array($column, ['category', 'provider', 'channel'], true), 400);
        $c = 'transactions.' . $column;

        return $this->base()->selectRaw("$c as k, COUNT(*) as n,
                SUM(CASE WHEN transactions.status = 'successful' THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN transactions.status IN ('failed','refunded') THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN transactions.status = 'successful' THEN transactions.total ELSE 0 END) as sales,
                SUM(CASE WHEN transactions.status = 'successful' THEN " . self::COST . ' ELSE 0 END) as cost')
            ->groupBy($c)->get()
            ->map(fn ($r) => $this->row((string) $r->k, $r))->sortByDesc('sales')->values()->all();
    }

    protected function row(string $key, $r, array $extra = []): array
    {
        $sales = (float) $r->sales;
        $cost = (float) $r->cost;
        $settled = (int) $r->ok + (int) $r->failed;

        return array_merge(['key' => $key, 'n' => (int) $r->n, 'ok' => (int) $r->ok, 'failed' => (int) $r->failed, 'sales' => $sales, 'cost' => $cost,
            'profit' => $sales - $cost, 'margin' => $sales > 0 ? ($sales - $cost) / $sales * 100 : 0, 'rate' => $settled ? (int) $r->ok / $settled * 100 : null], $extra);
    }

    /** Services (billers) ranked by sales. */
    public function services(int $limit = 15): array
    {
        return $this->base()->leftJoin('services', 'services.id', '=', 'transactions.service_id')
            ->selectRaw("transactions.service_id as sid, MAX(services.name) as name, MAX(transactions.category) as category, COUNT(*) as n,
                SUM(CASE WHEN transactions.status = 'successful' THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN transactions.status IN ('failed','refunded') THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN transactions.status = 'successful' THEN transactions.total ELSE 0 END) as sales,
                SUM(CASE WHEN transactions.status = 'successful' THEN " . self::COST . ' ELSE 0 END) as cost')
            ->groupBy('transactions.service_id')->orderByDesc('sales')->limit($limit)->get()
            ->map(fn ($r) => $this->row((string) ($r->name ?? 'Removed service'), $r, ['category' => $r->category]))->all();
    }

    /** Customers ranked by spend. */
    public function customers(int $limit = 10): array
    {
        return $this->ok()->leftJoin('users', 'users.id', '=', 'transactions.user_id')
            ->selectRaw('transactions.user_id as uid, MAX(users.name) as name, MAX(users.email) as email, COUNT(*) as n, SUM(transactions.total) as sales, SUM(' . self::COST . ') as cost')
            ->groupBy('transactions.user_id')->orderByDesc('sales')->limit($limit)->get()
            ->map(fn ($r) => ['id' => (int) $r->uid, 'name' => $r->name ?? 'Deleted account', 'email' => $r->email, 'n' => (int) $r->n,
                'sales' => (float) $r->sales, 'profit' => (float) $r->sales - (float) $r->cost])->all();
    }

    /** Currencies that have purchases, for the currency filter. */
    public static function currencies(): array
    {
        $c = Transaction::query()->distinct()->pluck('currency')->filter()->values()->all();

        return $c ?: [(string) config('pericharge.default_currency', 'NGN')];
    }

    public function filters(): array
    {
        return ['range' => $this->preset, 'from' => $this->from->format('Y-m-d'), 'to' => $this->to->format('Y-m-d'),
            'currency' => $this->currency, 'category' => $this->category, 'vendor' => $this->vendor, 'channel' => $this->channel];
    }
}
