<?php

declare(strict_types=1);

namespace App\Pages;

use App\Data\ErrorPageData;
use Tinvar\Attributes\ErrorPage as ErrorPageAttribute;
use Tinvar\Http\HttpError;
use Tinvar\Http\Request;

/**
 * What a document request that fails is answered with: the status, what
 * happened, one way forward, and the reference its failure is logged
 * under. A page for 500 answers every 5xx. The cause is never shown: it is
 * in the log, under the reference.
 *
 * A system page (resources/pages/app/error.tsx): the mark and the message,
 * no header, since the navigation might be what failed.
 */
#[ErrorPageAttribute([403, 404, 419, 429, 500], view: 'app/error', within: '/')]
final class ErrorPage
{
    public function __invoke(HttpError $error, Request $request): ErrorPageData
    {
        $here = $request->rawQuery === null || $request->rawQuery === '' ? $request->path : "{$request->path}?{$request->rawQuery}";

        return match ($error->status) {
            403 => new ErrorPageData(403, '403 · Forbidden', 'This page is not open to you', 'The page exists, and its policy does not let this visitor see it.', $error->digest, 'Go to the home page', '/'),
            404 => new ErrorPageData(404, '404 · Not found', 'Nothing lives at this address', 'There is no page here. The link may be mistyped, or out of date.', $error->digest, 'Go to the home page', '/'),
            419 => new ErrorPageData(419, '419 · Page expired', 'This page was open too long', 'Its form token ran out with the session it belonged to. Load the page again and repeat what you were doing.', $error->digest, 'Load the page again', $here),
            429 => new ErrorPageData(429, '429 · Too many requests', 'Too many requests from this address', 'This address went over the number of requests allowed in a minute. Wait a minute, then reload.', $error->digest, 'Reload the page', $here),
            default => new ErrorPageData($error->status, "{$error->status} · Server error", 'Something failed on our side', 'Nothing you did caused this. Quote the reference below if you report it; it points to the failure in the log.', $error->digest, 'Go to the home page', '/'),
        };
    }
}
