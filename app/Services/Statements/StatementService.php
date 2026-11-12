<?php

namespace App\Services\Statements;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A customer's wallet statement for a date range: opening balance, every credit
 * and debit with the running balance, closing balance, and a spending summary.
 * Available as a preview, a PDF, a CSV (Excel) file, or by e-mail.
 */
class StatementService
{
    public const MAX_DAYS = 366;
    public const MAX_ROWS = 5000;

    /** Validated range from request input (defaults: this month so far). */
    public static function range(?string $from, ?string $to): array
    {
        $tz = config('app.timezone');
        $start = $from ? Carbon::parse($from, $tz)->startOfDay() : now($tz)->startOfMonth();
        $end = $to ? Carbon::parse($to, $tz)->endOfDay() : now($tz)->endOfDay();
        if ($end->isFuture()) $end = now($tz)->endOfDay();
        if ($start->gt($end)) [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        if ($start->diffInDays($end) > self::MAX_DAYS) $start = $end->copy()->subDays(self::MAX_DAYS)->startOfDay();

        return [$start, $end];
    }

    /** Quick ranges for the pickers. */
    public static function presets(): array
    {
        $tz = config('app.timezone');
        $now = now($tz);

        return [
            'this_month' => ['This month', $now->copy()->startOfMonth(), $now->copy()],
            'last_month' => ['Last month', $now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_3'     => ['Last 3 months', $now->copy()->subMonthsNoOverflow(3)->startOfDay(), $now->copy()],
            'last_6'     => ['Last 6 months', $now->copy()->subMonthsNoOverflow(6)->startOfDay(), $now->copy()],
            'this_year'  => ['This year', $now->copy()->startOfYear(), $now->copy()],
        ];
    }

    public function build(User $user, CarbonInterface $from, CarbonInterface $to, ?string $currency = null): array
    {
        $wallet = $user->wallet($currency);
        $before = WalletEntry::where('wallet_id', $wallet->id)->where('created_at', '<', $from)->orderByDesc('id')->first();
        $entries = WalletEntry::where('wallet_id', $wallet->id)->whereBetween('created_at', [$from, $to])->orderBy('id')->limit(self::MAX_ROWS + 1)->get();
        $truncated = $entries->count() > self::MAX_ROWS;
        if ($truncated) $entries = $entries->take(self::MAX_ROWS);

        $opening = $before ? (float) $before->balance_after : ($entries->first() ? (float) $entries->first()->balance_before : (float) $wallet->balance);
        $closing = $entries->last() ? (float) $entries->last()->balance_after : $opening;

        $spend = Transaction::where('user_id', $user->id)->where('currency', $wallet->currency)->where('status', Transaction::SUCCESSFUL)
            ->whereBetween('created_at', [$from, $to])->selectRaw('category, count(*) as n, sum(total) as total')->groupBy('category')->get()
            ->map(fn ($r) => ['category' => $r->category, 'label' => config('afritopup.categories.' . $r->category . '.name', ucfirst($r->category)), 'count' => (int) $r->n, 'total' => (float) $r->total])
            ->sortByDesc('total')->values();

        return [
            'user'      => $user,
            'wallet'    => $wallet,
            'currency'  => $wallet->currency,
            'from'      => $from,
            'to'        => $to,
            'opening'   => $opening,
            'credits'   => (float) $entries->where('type', 'credit')->sum('amount'),
            'debits'    => (float) $entries->where('type', 'debit')->sum('amount'),
            'closing'   => $closing,
            'entries'   => $entries,
            'spend'     => $spend,
            'truncated' => $truncated,
            'number'    => 'ST-' . $user->id . '-' . $from->format('Ymd') . '-' . $to->format('Ymd'),
        ];
    }

    public function pdf(array $s): string
    {
        return Pdf::loadView('pdf.statement', ['s' => $s])->setPaper('a4')->output();
    }

    public function filename(array $s, string $ext): string
    {
        return 'Statement-' . $s['from']->format('Y-m-d') . '-to-' . $s['to']->format('Y-m-d') . '.' . $ext;
    }

    public function csv(array $s): StreamedResponse
    {
        return response()->streamDownload(function () use ($s) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows ₦ correctly
            fputcsv($out, [config('afritopup.name') . ' wallet statement', $s['user']->name, $s['user']->email]);
            fputcsv($out, ['Period', $s['from']->format('j M Y') . ' – ' . $s['to']->format('j M Y'), 'Currency', $s['currency']]);
            fputcsv($out, ['Opening balance', number_format($s['opening'], 2, '.', ''), 'Closing balance', number_format($s['closing'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['Date', 'Description', 'Reference', 'Money in', 'Money out', 'Balance']);
            foreach ($s['entries'] as $e) {
                fputcsv($out, [
                    $e->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    $e->description,
                    $e->reference,
                    $e->type === 'credit' ? number_format((float) $e->amount, 2, '.', '') : '',
                    $e->type === 'debit' ? number_format((float) $e->amount, 2, '.', '') : '',
                    number_format((float) $e->balance_after, 2, '.', ''),
                ]);
            }
            fputcsv($out, ['', 'Totals', '', number_format($s['credits'], 2, '.', ''), number_format($s['debits'], 2, '.', ''), number_format($s['closing'], 2, '.', '')]);
            fclose($out);
        }, $this->filename($s, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
