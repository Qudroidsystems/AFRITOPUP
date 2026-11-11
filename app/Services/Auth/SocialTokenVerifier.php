<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Checks a "Sign in with Google" / "Sign in with Apple" ID token (a JWT
 * signed RS256) against the provider's published keys: signature, issuer,
 * audience (one of our client IDs) and expiry. No third-party JWT library.
 *
 * @return array the token's claims: sub, email, email_verified, name …
 */
class SocialTokenVerifier
{
    public const PROVIDERS = [
        'google' => [
            'jwks'   => 'https://www.googleapis.com/oauth2/v3/certs',
            'issuer' => ['https://accounts.google.com', 'accounts.google.com'],
        ],
        'apple' => [
            'jwks'   => 'https://appleid.apple.com/auth/keys',
            'issuer' => ['https://appleid.apple.com'],
        ],
    ];

    /** Our client IDs for the provider (config/afritopup.php → social). */
    public static function audiences(string $provider): array
    {
        return array_values(array_filter(array_map('trim', (array) config("afritopup.social.$provider.client_ids", []))));
    }

    public static function enabled(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider]) && self::audiences($provider) !== [];
    }

    public function verify(string $provider, string $jwt): array
    {
        $p = self::PROVIDERS[$provider] ?? throw new RuntimeException('Unknown sign-in provider.');
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new RuntimeException('That sign-in token is not valid.');
        [$h64, $c64, $s64] = $parts;

        $header = json_decode(self::b64($h64), true);
        $claims = json_decode(self::b64($c64), true);
        if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            throw new RuntimeException('That sign-in token is not valid.');
        }

        $pem = $this->key($provider, $p['jwks'], (string) $header['kid']);
        if (openssl_verify("$h64.$c64", self::b64($s64), $pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('That sign-in token could not be verified.');
        }

        $now = time();
        $aud = (array) ($claims['aud'] ?? []);
        if (!in_array($claims['iss'] ?? '', $p['issuer'], true)) throw new RuntimeException('That sign-in token was not issued by ' . ucfirst($provider) . '.');
        if (!array_intersect($aud, self::audiences($provider))) throw new RuntimeException('That sign-in token is for a different app.');
        if (($claims['exp'] ?? 0) < $now - 60) throw new RuntimeException('That sign-in has expired. Please try again.');
        if (($claims['iat'] ?? $now) > $now + 300) throw new RuntimeException('That sign-in token is not valid yet.');
        if (empty($claims['sub'])) throw new RuntimeException('That sign-in token has no account ID.');

        return $claims;
    }

    /** PEM public key for a key ID, from the provider's JWKS (cached; refreshed once when a new kid appears). */
    protected function key(string $provider, string $url, string $kid): string
    {
        $cacheKey = "social-jwks:$provider";
        $find = fn (array $keys) => collect($keys)->firstWhere('kid', $kid);

        $keys = Cache::get($cacheKey);
        $jwk = is_array($keys) ? $find($keys) : null;
        if (!$jwk) {
            $keys = (array) Http::acceptJson()->timeout(10)->get($url)->throw()->json('keys');
            Cache::put($cacheKey, $keys, now()->addHours(6));
            $jwk = $find($keys);
        }
        if (!$jwk || ($jwk['kty'] ?? '') !== 'RSA') throw new RuntimeException('That sign-in token could not be verified.');

        return self::pem(self::b64($jwk['n']), self::b64($jwk['e']));
    }

    protected static function b64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    /** RSA modulus + exponent → SubjectPublicKeyInfo PEM. */
    public static function pem(string $n, string $e): string
    {
        $int = fn (string $b) => "\x02" . self::len(strlen($b = (ord($b[0]) > 0x7f ? "\x00" . $b : $b))) . $b;
        $rsa = $int($n) . $int($e);
        $rsa = "\x30" . self::len(strlen($rsa)) . $rsa;
        $bit = "\x03" . self::len(strlen($rsa) + 1) . "\x00" . $rsa;
        $algo = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $spki = "\x30" . self::len(strlen($algo . $bit)) . $algo . $bit;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    protected static function len(int $l): string
    {
        if ($l < 0x80) return chr($l);
        $b = ltrim(pack('N', $l), "\x00");

        return chr(0x80 | strlen($b)) . $b;
    }
}
