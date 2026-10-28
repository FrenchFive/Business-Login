<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Request;
use App\Http\Response;

/**
 * Defence-in-depth headers added to every dynamic response.
 */
final class SecurityHeaders
{
    /** Everything is served from our own origin: no inline scripts, no third-party code. */
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        . "font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public static function apply(Response $response, Request $request): Response
    {
        $headers = [
            'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            // Pages carry CSRF tokens and personal data: never cache them.
            'Cache-Control' => 'no-store, max-age=0',
        ];

        if ($request->secure) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
