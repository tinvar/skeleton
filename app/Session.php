<?php

declare(strict_types=1);

namespace App;

use Tinvar\Http\CsrfTokens;
use Tinvar\Http\Middleware\VerifyCsrfToken;
use Tinvar\Http\Request;
use Tinvar\Http\Response;
use Tinvar\Support\Signer;

/**
 * The session cookie: which browser this is, and nothing more.
 *
 * The framework has no session layer; an application says what a session
 * is. Here it is a random id under the app key's MAC, issued to every
 * browser that arrives without a valid one, so the tokens the framework
 * binds to a session -- the CSRF token, route and fragment tokens, the
 * history key, the dev toolbar's -- have one to bind to from the first
 * request. Nothing is stored for it: the app has no accounts yet. When it
 * has, the session needs a row behind it, and the id has to be rotated on
 * every privilege change, signing in above all; the example shop
 * (examples/shop, Shop\Auth\Sessions\Sessions) shows how.
 *
 * `__Host-`, so a browser takes it only with Secure, Path=/ and no Domain:
 * neither a sibling subdomain nor a plain-http response can plant one.
 */
final class Session
{
    public const string COOKIE = '__Host-app_session';

    /**
     * How long one id is accepted, in seconds, from when it was issued:
     * fixed, never extended, so a planted or leaked id stops working on
     * schedule.
     */
    public const int LIFETIME = 43_200;

    private const string PURPOSE = 'session-cookie';

    /** A new cookie value: `<id>.<issued>.<expires>.<mac>`. */
    public static function issue(#[\SensitiveParameter] string $key, ?int $now = null): string
    {
        $now ??= time();
        $claims = bin2hex(random_bytes(16)) . '.' . $now . '.' . ($now + self::LIFETIME);

        return $claims . '.' . Signer::encode(new Signer($key, self::PURPOSE)->sign($claims));
    }

    /**
     * The id a cookie carries, or null unless this key signed it and it has
     * not expired.
     */
    public static function id(
        #[\SensitiveParameter]
        ?string $cookie,
        #[\SensitiveParameter]
        string $key,
        ?int $now = null,
    ): ?string {
        // Decimal times without leading zeros and a MAC exactly as long as a
        // SHA-256 one encodes to: one spelling per value, so a cookie cannot
        // be re-spelled into a second one that also verifies.
        $shape = '/^([0-9a-f]{32})\.(0|[1-9][0-9]{0,11})\.(0|[1-9][0-9]{0,11})\.([A-Za-z0-9_-]{43})\z/';
        if ($cookie === null || preg_match($shape, $cookie, $m) !== 1) {
            return null;
        }
        [, $id, $issued, $expires, $mac] = $m;

        // Compared as the string this server writes, not as decoded bytes,
        // which would accept a second spelling of the same MAC.
        if (!hash_equals(Signer::encode(new Signer($key, self::PURPOSE)->sign("{$id}.{$issued}.{$expires}")), $mac)) {
            return null;
        }

        // Expired, issued in the future, or living longer than this code
        // issues for: refused. A clock a minute fast elsewhere is tolerated.
        $now ??= time();
        if ((int) $expires <= $now || (int) $issued > $now + 60 || (int) $expires - (int) $issued > self::LIFETIME) {
            return null;
        }

        return $id;
    }

    /** The id of the session $request carries, or '' for none: what every token is bound through. */
    public static function of(Request $request, #[\SensitiveParameter] string $key): string
    {
        return self::id($request->cookie(self::COOKIE), $key) ?? '';
    }

    /**
     * $response, setting $cookie -- Secure, HttpOnly, SameSite=Lax, Path=/,
     * as Response::withCookie() writes them -- and the new session's CSRF
     * token beside it, so a form on the page the browser lands on posts with
     * the token of the session it now has.
     */
    public static function set(Response $response, #[\SensitiveParameter] string $cookie, ?CsrfTokens $csrf, ?int $now = null): Response
    {
        $expires = (int) (explode('.', $cookie)[2] ?? 0);
        $response = $response->withCookie(self::COOKIE, $cookie, httpOnly: true, maxAge: max(0, $expires - ($now ?? time())));

        $token = $csrf?->for(new Request('GET', '/', cookies: [self::COOKIE => $cookie]));
        if ($token === null) {
            return $response;
        }

        // Readable by script, which copies it into the header, and per
        // visitor: no shared cache may keep it.
        return $response
            ->withCookie(VerifyCsrfToken::COOKIE, $token, httpOnly: false)
            ->withHeader('cache-control', 'private, no-store')
            ->withAddedHeader('vary', 'Cookie');
    }
}
