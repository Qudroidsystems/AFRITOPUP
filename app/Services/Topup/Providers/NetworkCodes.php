<?php

namespace App\Services\Topup\Providers;

use App\Models\Service;

/**
 * What a Nigerian service *is*, independent of any vendor: which network, disco, TV provider or exam.
 * Read from the service's own code (mtn, mtn-data, 9mobile, ikeja-electric, dstv, waec …), so a service
 * can move between vendors without re-typing anything. A service can still pin a vendor's own id
 * in its options: {"vendor_codes": {"clubkonnect": "01"}}.
 */
final class NetworkCodes
{
    public const NETWORKS = ['mtn', 'glo', 'airtel', '9mobile'];

    public const DISCOS = ['eko', 'ikeja', 'abuja', 'kano', 'portharcourt', 'jos', 'ibadan', 'kaduna', 'enugu', 'benin', 'yola', 'aba'];

    public const CABLES = ['dstv', 'gotv', 'startimes', 'showmax'];

    /** "mtn" for MTN airtime and MTN data, "9mobile" for etisalat; null when not a network. */
    public static function network(Service $s): ?string
    {
        foreach ([$s->code, $s->provider_code] as $c) {
            $c = strtolower((string) $c);
            $c = preg_replace('/-(data|airtime|sme|gifting|cg)$/', '', $c);
            if ($c === 'etisalat' || $c === '9mobile' || $c === 't2') return '9mobile';
            if (in_array($c, self::NETWORKS, true)) return $c;
        }

        return null;
    }

    /** "ikeja" for ikeja-electric. */
    public static function disco(Service $s): ?string
    {
        foreach ([$s->code, $s->provider_code] as $c) {
            $c = preg_replace('/-(electric|electricity|power)$/', '', strtolower((string) $c));
            $c = str_replace(['port-harcourt', 'phed'], 'portharcourt', $c);
            if (in_array($c, self::DISCOS, true)) return $c;
        }

        return null;
    }

    public static function cable(Service $s): ?string
    {
        foreach ([$s->code, $s->provider_code] as $c) {
            $c = strtolower((string) $c);
            if (in_array($c, self::CABLES, true)) return $c;
        }

        return null;
    }

    /** A vendor id the admin pinned on the service, if any. */
    public static function pinned(Service $s, string $vendor): ?string
    {
        $v = data_get($s->options, 'vendor_codes.' . $vendor);

        return filled($v) ? (string) $v : null;
    }

    /** "1GB", "500MB" … in megabytes, to recognise the same data plan at another vendor. */
    public static function dataSize(string $name): ?int
    {
        if (!preg_match('/(\d+(?:\.\d+)?)\s*(TB|GB|MB)\b/i', $name, $m)) return null;
        $n = (float) $m[1];

        return (int) round(match (strtoupper($m[2])) { 'TB' => $n * 1024 * 1024, 'GB' => $n * 1024, default => $n });
    }

    /** "30 days", "1 month", "7days" … in days. */
    public static function validityDays(?string $text): ?int
    {
        if (!$text || !preg_match('/(\d+)\s*-?\s*(day|days|week|weeks|month|months|hrs|hours|hr)\b/i', $text, $m)) {
            if ($text && preg_match('/\b(daily|weekly|monthly)\b/i', $text, $w)) return ['daily' => 1, 'weekly' => 7, 'monthly' => 30][strtolower($w[1])];

            return null;
        }
        $n = (int) $m[1];

        return match (strtolower(rtrim($m[2], 's'))) { 'week' => $n * 7, 'month' => $n * 30, 'hr', 'hour' => max(1, intdiv($n, 24)), default => $n };
    }
}
