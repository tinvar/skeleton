<?php

declare(strict_types=1);

/**
 * The browser's own reports -- CSP violations, deprecations -- taken at
 * /_reports with no session and no CSRF token, where there are metrics to
 * count them in, and named by every document's policy (ADR 0093).
 */

namespace Tests\Feature;

use App\Database\Migrations;
use App\Kernel;
use Tinvar\Http\Request;
use Tinvar\Observability\Logger;
use Tinvar\Observability\MemorySink;
use Tinvar\Observability\Metrics;
use Tinvar\Observability\Telemetry;

function reportTo(Kernel $kernel): \Tinvar\Http\Response
{
    return $kernel->handle(new Request(
        'POST',
        '/_reports',
        ['content-type' => 'application/reports+json'],
        remoteAddress: '127.0.0.1',
    ), json_encode([[
        'type' => 'csp-violation',
        'url' => 'https://example.test/',
        'body' => ['effectiveDirective' => 'script-src-elem', 'disposition' => 'enforce', 'blockedURL' => 'inline'],
    ]], JSON_THROW_ON_ERROR));
}

it('takes a CSP violation at /_reports with no session, and counts it', function () {
    $metrics = new Metrics();
    $kernel = Kernel::fromEnvironment(database: ':memory:', telemetry: new Telemetry(new Logger(new MemorySink()), $metrics));
    Migrations::migrate($kernel->orm->connection());

    $report = reportTo($kernel);

    expect($report->status)->toBe(204)
        ->and($report->body())->toBe('')
        ->and($report->cookies())->toBe([])
        ->and($metrics->exposition())->toContain('tinvar_problem_reports_total{type="csp-violation",directive="script-src-elem",disposition="enforce"} 1');
});

it('takes none where there are no metrics to count them in', function () {
    $kernel = Kernel::fromEnvironment(database: ':memory:', telemetry: new Telemetry(new Logger(new MemorySink())));
    Migrations::migrate($kernel->orm->connection());

    expect(reportTo($kernel)->status)->not->toBe(204);
});

it('names /_reports in a document\'s policy only where it takes them', function () {
    expect(Kernel::contentSecurityPolicy('bm9uY2U=', reports: true))->toContain('report-uri /_reports; report-to default')
        ->and(Kernel::contentSecurityPolicy('bm9uY2U='))->not->toContain('report');
});
