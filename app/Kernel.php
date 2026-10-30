<?php

declare(strict_types=1);

namespace App;

use App\Actions\NoteActions;
use App\Config\AppConfig;
use App\Models\Note;
use App\Pages\ErrorPage;
use App\Pages\Home;
use App\Pages\Layout;
use App\Policies\NotePolicy;
use App\Queries\RecentNotes;
use Tinvar\Actions\ActionRegistry;
use Tinvar\Auth\Authenticatable;
use Tinvar\Auth\AuthContext;
use Tinvar\Auth\PanelContext;
use Tinvar\Auth\PolicyGate;
use Tinvar\Auth\PolicyRegistry;
use Tinvar\Cache\ApcuStore;
use Tinvar\Cache\ArrayStore;
use Tinvar\Cache\QueryCache;
use Tinvar\Cache\Store;
use Tinvar\Codec\FrameCodec;
use Tinvar\Codec\MessagePackCodec;
use Tinvar\Config\FrameworkConfig;
use Tinvar\Container\Container;
use Tinvar\Data\Encoder;
use Tinvar\Data\SharedReads;
use Tinvar\Dev\DevToolbar;
use Tinvar\Http\ApcuThrottleStore;
use Tinvar\Http\AssetLinks;
use Tinvar\Http\BuildRegistry;
use Tinvar\Http\BuiltAssets;
use Tinvar\Http\BuildStore;
use Tinvar\Http\CsrfTokens;
use Tinvar\Http\ErrorShell;
use Tinvar\Http\FileBuildStore;
use Tinvar\Http\FragmentSupport;
use Tinvar\Http\HistoryKeys;
use Tinvar\Http\Kernel as HttpKernel;
use Tinvar\Http\Request;
use Tinvar\Http\Response;
use Tinvar\Http\Sapi;
use Tinvar\Http\SqliteThrottleStore;
use Tinvar\Http\Terminable;
use Tinvar\Http\TestableApplication;
use Tinvar\Http\Throttle;
use Tinvar\Http\ThrottleStore;
use Tinvar\Config\Configs;
use Tinvar\Observability\ApcuMetricsStore;
use Tinvar\Observability\ArrayMetricsStore;
use Tinvar\Observability\DatabaseCheck;
use Tinvar\Observability\Logger;
use Tinvar\Observability\Metrics;
use Tinvar\Observability\ProbeAccess;
use Tinvar\Observability\ProblemReports;
use Tinvar\Observability\Probes;
use Tinvar\Observability\RendererCheck;
use Tinvar\Observability\RendererLines;
use Tinvar\Observability\RequestId;
use Tinvar\Observability\SqliteMetricsStore;
use Tinvar\Observability\StreamSink;
use Tinvar\Observability\Telemetry;
use Tinvar\Orm\IdentityMap;
use Tinvar\Orm\Orm;
use Tinvar\Orm\PdoConnection;
use Tinvar\Packages\PackagePolicy;
use Tinvar\Protocol\Frame;
use Tinvar\Protocol\PageMetadata;
use Tinvar\Query\QueryRegistry;
use Tinvar\Query\QueryRunner;
use Tinvar\Render\FragmentManifest;
use Tinvar\Render\FragmentManifestProducer;
use Tinvar\Render\FragmentMinter;
use Tinvar\Render\FragmentToken;
use Tinvar\Protocol\HeadTag;
use Tinvar\Render\ModulePreload;
use Tinvar\Render\RenderChannel;
use Tinvar\Render\RenderRetry;
use Tinvar\Render\RendererHealth;
use Tinvar\Render\RendererUser;
use Tinvar\Render\RenderResult;
use Tinvar\Render\RouteToken;
use Tinvar\Render\SocketTrust;
use Tinvar\Routing\LoadedRoute;
use Tinvar\Routing\RouteRegistry;
use Tinvar\Support\AppKey;
use Tinvar\Support\BuildProfile;
use Tinvar\Support\DevMode;
use Tinvar\Support\Signer;
use Tinvar\Support\StandIns;

/**
 * Wires the app together: its composition root.
 *
 * Composition is explicit. One container builds the app's pages, queries,
 * actions and policies by their constructor types, and this class wires the
 * rest by hand; there are no service providers. Every request goes through
 * the framework's Kernel -- middleware, the throttle, CSRF, authorization,
 * the reset contract, exception classification and the error pages -- and
 * this class adds what is the app's own: the session cookie, and turning a
 * loaded route into a render.
 *
 * Register what you add in the lists below: a page or layout in ROUTES, a
 * query in QUERIES, an action class in ACTIONS, a model's policy in
 * POLICIES. `bin/tinvar check` holds every one of them to the security
 * model's build rules. The example shop (examples/shop in the framework's
 * repository) wires the rest -- sign-in and sessions with rows, panels,
 * jobs and mail, live updates, uploads, static pages -- the same way.
 *
 * Its settings come from the environment, never a file in the project,
 * through a typed #[Config] class in CONFIGS -- APP_DATABASE, APP_LOG and
 * the TINVAR_* it reads are App\Config\AppConfig's -- beside the app key,
 * TINVAR_APP_KEY or TINVAR_APP_KEY_FILE (.env.example lists them).
 */
final class Kernel implements TestableApplication, Terminable
{
    /** The app's name, in the title of every page and on the error shell. */
    public const string NAME = 'Tinvar app';

    /** Every page and layout, and the error page shown when a document request fails. */
    public const array ROUTES = [Layout::class, Home::class, ErrorPage::class];

    public const array QUERIES = [RecentNotes::class];

    public const array ACTIONS = [NoteActions::class];

    /** @var array<class-string, class-string> */
    public const array POLICIES = [Note::class => NotePolicy::class];

    /**
     * The models, for the migrations and the generators, and handed to each
     * registry, which holds every entry point it registers to
     * tenant-scoped-ids-must-be-typed. Add one here when you add a model.
     */
    public const array MODELS = [Note::class];

    /**
     * The #[Config] classes: loaded at boot, listed by `bin/tinvar config`,
     * and held by `bin/tinvar check`. Add one here when you add a class.
     */
    public const array CONFIGS = [AppConfig::class, FrameworkConfig::class];

    public readonly Orm $orm;
    public readonly QueryRegistry $queries;
    public readonly RouteRegistry $routes;
    public readonly ActionRegistry $actions;

    /** The framework's composition root, which every request goes through. */
    private readonly HttpKernel $http;

    /** The last failure the Kernel classified, for a test that wants the cause. */
    private ?\Throwable $lastFailure = null;

    private readonly Encoder $encoder;
    private readonly RenderChannel $channel;

    /** Which routes may mint fragment tokens, and what each route's renders may query. */
    private readonly FragmentManifest $manifest;

    /**
     * @param array<string, int> $limits requests per window by throttle bucket, over the
     *        framework's defaults; 0 turns a bucket off
     * @param list<int> $rendererUsers the renderers' own user, where they run as one
     *        (TINVAR_RENDERER_USER): every render, and every probe of a renderer, is
     *        then refused unless the process answering is that user's
     */
    public function __construct(
        PdoConnection $connection,
        string $socketPath,
        #[\SensitiveParameter]
        private readonly string $key,
        private readonly ?FragmentSupport $fragments = null,
        FrameCodec $codec = new MessagePackCodec(),
        bool $debug = false,
        private readonly ?CsrfTokens $csrf = null,
        ThrottleStore $throttleStore = new \Tinvar\Http\ArrayThrottleStore(),
        array $limits = [],
        ?HistoryKeys $historyKeys = null,
        ?FragmentManifest $manifest = null,
        private readonly bool $dev = false,
        ?DevToolbar $toolbar = null,
        ?Telemetry $telemetry = null,
        ?ProbeAccess $probeAccess = null,
        ?StandIns $standIns = null,
        ?Store $cacheStore = null,
        array $rendererUsers = [],
    ) {
        // The overlay shows what the renderer said, so dev keeps it.
        $debug = $debug || $dev;
        $this->orm = new Orm($connection, new IdentityMap($connection->name()));
        $report = $this->report(...);
        $this->encoder = new Encoder($report);
        $auth = new AuthContext();
        $panels = new PanelContext();

        // Keyed by this build as well: a shared store outlives a deploy.
        $cache = new QueryCache($cacheStore ?? new ArrayStore(), $auth, $panels, build: $fragments?->builds->current ?? self::build());

        // The connecting peer, never X-Forwarded-For, which is whatever the
        // client sent: keyed on it, a client could pick its own bucket.
        // Behind a proxy you control, resolve the client address there.
        $throttle = new Throttle($throttleStore, $auth, static fn (Request $r): string => $r->remoteAddress ?? '');

        $container = (new Container())
            ->instance(Orm::class, $this->orm)
            ->instance(Throttle::class, $throttle);

        $this->queries = new QueryRegistry($container->resolver(), self::MODELS);
        $this->queries->registerAll(self::QUERIES);

        $this->actions = new ActionRegistry($container->resolver(), self::MODELS);
        $this->actions->registerAll(self::ACTIONS);

        $this->routes = new RouteRegistry(self::MODELS);
        $this->routes->registerAll(self::ROUTES);

        $policies = new PolicyRegistry($container->resolver());
        $policies->registerAll(self::POLICIES);

        $runner = new QueryRunner(
            registry: $this->queries,
            orm: $this->orm,
            gate: new PolicyGate($policies, $auth),
            encoder: $this->encoder,
            debug: $debug,
            cache: $cache,
            report: $report,
            panels: $this->queryPanelRefusal(...),
            standIn: $standIns === null ? null : $standIns->query(...),
        );

        $this->channel = new RenderChannel(
            $socketPath,
            $runner,
            $codec,
            debug: $debug,
            trustedSocketOwners: $rendererUsers,
            report: $report,
            tap: $toolbar,
            telemetry: $telemetry,
            // A render of a GET whose renderer could not be reached, or hung
            // up before it was done -- a pool restarting under it -- is tried
            // once more, after 50 ms. Never an action's (ADR 0093).
            retry: new RenderRetry(),
        );

        // The renderers, asked as `renderer:check` asks: for /_ready, and
        // each one in the pool for /_metrics, a second at most each.
        $renderers = new RendererCheck($socketPath, new RendererHealth($codec, timeoutSeconds: 1.0, trust: new SocketTrust($rendererUsers)));

        $this->manifest = $manifest ?? ($dev ? self::devFragmentManifest($this->routes) : self::fragmentManifest());
        $logged = $telemetry?->logger->forExceptions();

        $this->http = new HttpKernel(
            orm: $this->orm,
            routes: $this->routes,
            queries: $this->queries,
            actions: $this->actions,
            auth: $auth,
            policies: $policies,
            resolver: $container->resolver(),
            // Nothing is callable over /_query: every query is called by a
            // server component over the render's own socket.
            allowedQueries: static fn (Request $r): array => [],
            cache: $cache,
            fragments: $this->fragments,
            throttle: $throttle,
            limits: $limits,
            log: function (\Throwable $e, string $digest) use ($logged): void {
                $this->lastFailure = $e;
                if ($logged !== null) {
                    $logged($e, $digest);
                }
            },
            container: $container,
            csrf: $this->csrf,
            manifest: $this->manifest,
            partialReloads: true,
            // A form posted without script that fails is sent back to its
            // page with its errors, which wait for it here, by session.
            flashes: new Flashes($this->orm, $key),
            historyKeys: $historyKeys,
            panels: $panels,
            errorShell: new ErrorShell(name: self::NAME, logo: self::mark(), homeHref: '/', homeLabel: 'Go to the home page'),
            dev: $dev,
            toolbar: $toolbar,
            telemetry: $telemetry,
            clientTelemetry: $telemetry?->metrics !== null,
            clientAssets: self::buildDirectory() . '/client/assets',
            // /_reports: the CSP violations and deprecations the browser
            // reports of its own accord, which every document's policy and
            // Reporting-Endpoints name (ADR 0093). Sessionless; counted and
            // logged where there are metrics.
            problemReports: $telemetry?->metrics !== null,
            probes: $telemetry === null ? null : new Probes(
                $probeAccess ?? new ProbeAccess(),
                [new DatabaseCheck($connection), $renderers],
                $telemetry->metrics,
                $telemetry->logger,
            ),
            standIns: $standIns,
        );
    }

    /**
     * Built from the environment, as the front controller, the worker and
     * the CLI build it. TINVAR_SOCKET names the renderer, and
     * TINVAR_RENDERER_USER the user it runs as where that is not this
     * process's own; APP_DATABASE the SQLite file; TINVAR_APP_KEY or the key
     * file the signing key; TINVAR_THROTTLE adjusts the limits, as
     * `actions=600`; TINVAR_DEV=1, which `tinvar serve --dev` sets, is dev
     * mode, refused beside any sign of production (DevMode).
     */
    public static function fromEnvironment(
        ?string $socketPath = null,
        #[\SensitiveParameter]
        ?string $key = null,
        ?string $database = null,
        ?bool $dev = null,
        // In place of the log file and metrics store the environment gives: a test's.
        ?Telemetry $telemetry = null,
        // What answers in place of a query's or an action's body: a test's fakes.
        ?StandIns $standIns = null,
        ?FragmentManifest $manifest = null,
    ): self {
        // First of all: a key missing or malformed stops the process here,
        // every one named, before anything is opened on a guess.
        Configs::loadAll(self::CONFIGS);
        $dev ??= FrameworkConfig::load()->devMode();
        $key ??= self::appKey();
        $socketPath ??= self::config()->socket;
        // First, so a renderer user that is no one, root or this process's
        // own stops the process here rather than leaving renders to go anywhere.
        $rendererUsers = RendererUser::socketOwners();

        // One resolver for every binding: fragment and route tokens, the CSRF
        // token, the history key and the toolbar's token, all the session's.
        $sessionId = static fn (Request $r): string => Session::of($r, $key);

        // Before anything opens: a toolbar asked for in production stops here.
        $toolbar = DevToolbar::fromEnvironment($sessionId, dev: $dev, build: self::build());

        $database ??= self::databasePath();
        $manifest ??= $dev ? self::devFragmentManifest() : self::fragmentManifest();

        /** @var self|null $kernel */
        $kernel = null;
        $fragments = new FragmentSupport(
            signer: new Signer($key, FragmentToken::PURPOSE),
            builds: self::builds($database),
            render: static function (FragmentToken $token, Request $request, array $models, array $allowed, ?SharedReads $shared = null) use (&$kernel): Response {
                \assert($kernel instanceof self);

                return $kernel->renderFragment($token, $request, $allowed);
            },
            sessionId: $sessionId,
            allowedQueries: $manifest->queriesFor(...),
            routeSigner: new Signer($key, RouteToken::PURPOSE),
        );

        $connection = PdoConnection::sqlite($database, 'app');
        $telemetry ??= self::telemetry($database);

        $kernel = new self(
            $connection,
            $socketPath,
            $key,
            $fragments,
            new MessagePackCodec(),
            false,
            new CsrfTokens(new Signer($key, CsrfTokens::PURPOSE), $sessionId),
            self::throttleStore($database),
            self::limitsFrom((string) self::config()->throttle),
            new HistoryKeys(new Signer($key, HistoryKeys::PURPOSE), $sessionId),
            $manifest,
            $dev,
            $toolbar,
            $telemetry,
            self::probeAccess(),
            $standIns,
            self::cacheStore(),
            $rendererUsers,
        );

        return $kernel;
    }

    /**
     * The app as the front controller and the worker serve it, under the
     * production profile. The build must have been made for it, checked
     * before anything opens. This app has no browser test endpoint, so the
     * test profile is refused rather than served without one.
     */
    public static function serving(): self
    {
        $framework = FrameworkConfig::load();
        $profile = $framework->buildProfile();
        if ($profile === BuildProfile::TEST) {
            throw new \RuntimeException(BuildProfile::ENV . '=test, and this app serves no browser test endpoint.');
        }
        if (!$framework->devMode()) {
            BuildProfile::assertBuild($profile, self::buildDirectory());
        }

        return self::fromEnvironment();
    }

    // ------------------------------------------------------------ requests

    /**
     * One HTTP request: the document, or -- for a client navigation,
     * `RSC: 1` -- the payload alone.
     *
     * A request with no valid session cookie is given one here, before
     * anything reads it: the CSRF token and every other token bind to the
     * session, so the first request a browser makes needs one as much as
     * every later one does. Issuing one writes nothing.
     */
    public function handle(Request $request, string $rawBody = ''): Response
    {
        return $this->handleWith($request, $this->respond(...), $rawBody);
    }

    public function handleWith(Request $request, \Closure $respond, string $rawBody = ''): Response
    {
        $this->lastFailure = null;

        // A probe every few seconds is not a visitor: no session for it.
        if ($this->http->opensNoSession($request)) {
            return $this->http->handle($request, $respond, $rawBody);
        }

        $issued = null;
        if (Session::id($request->cookie(Session::COOKIE), $this->key) === null) {
            $issued = Session::issue($this->key);
            $request = self::withRequestCookie($request, Session::COOKIE, $issued);
        }

        $response = $this->http->handle($request, $respond, $rawBody);

        return $issued === null || isset($response->cookies()[Session::COOKIE])
            ? $response
            : Session::set($response, $issued, $this->csrf);
    }

    /** The framework's composition root, for the testing layer's direct calls. */
    public function kernel(): HttpKernel
    {
        return $this->http;
    }

    public function lastFailure(): ?\Throwable
    {
        return $this->lastFailure;
    }

    /**
     * The app has no accounts to sign anyone in as. Add them, and this is
     * where a test's session for one is made, with no password asked; see
     * the example shop's Kernel::signedIn().
     */
    public function signedIn(Authenticatable $user, Request $request): array
    {
        throw new \InvalidArgumentException('This app has no accounts to sign ' . get_debug_type($user) . ' in as.');
    }

    /**
     * The reset contract, for a worker to run after every request. The
     * framework's Kernel runs it at the end of handle() already; a worker
     * runs it again regardless, since a failure before handle() returned
     * must not leave state for the next visitor.
     */
    public function reset(): void
    {
        $this->http->scope->reset();
        $this->lastFailure = null;
    }

    /** Metrics to their store once the response has gone. */
    public function terminate(): void
    {
        $this->http->terminate();
    }

    /**
     * Renders a matched route: what handle() hands the framework's Kernel.
     *
     * @param (\Closure(RenderResult, string): void)|null $rendered
     */
    public function respond(LoadedRoute $loaded, Request $request, ?\Closure $rendered = null): Response
    {
        $payloadOnly = $request->wantsRscPayload();
        $body = '';
        $sink = static function (string $bytes) use (&$body): void {
            $body .= $bytes;
        };

        // One nonce for the render and the policy: the renderer writes it on
        // every script, and the header refuses a script without it.
        $nonce = base64_encode(random_bytes(16));

        // A document's first loads -- the stylesheets every page links, the
        // client entry -- hinted before it renders, with their digests where
        // the build has them: a 103 under FrankenPHP, nothing elsewhere
        // (ADR 0108).
        if (!$payloadOnly && !$this->dev) {
            $hints = self::assetLinks()->earlyHints();
            if ($hints !== null) {
                Sapi::earlyHints($hints);
            }
        }

        $this->orm->beginRender();
        try {
            // A retried render starts again from nothing: the body is this
            // response's alone until it is returned (ADR 0093).
            $result = $this->renderRoute($loaded, $request, $sink, $payloadOnly, $nonce, static function () use (&$body): void {
                $body = '';
            });
        } finally {
            $this->orm->endRender();
        }

        if ($rendered !== null) {
            $rendered($result, $body);
        }

        if ($payloadOnly) {
            return Response::make(200, $body, ['content-type' => 'text/x-component']);
        }

        // Where problem reports are taken, the policy names the endpoint its
        // violations go to, and so does Reporting-Endpoints, for those and
        // for the browser's other reports (ADR 0093).
        $reports = $this->http->problemReports_ !== null;
        $response = Response::make(200, $body, [
            'content-type' => 'text/html; charset=utf-8',
            'content-security-policy' => self::contentSecurityPolicy($nonce, $this->dev, $reports),
            ...($reports ? ['reporting-endpoints' => ProblemReports::reportingEndpoints()] : []),
        ]);
        $preload = ModulePreload::header($result, assets: self::assetLinks());

        return $preload === null ? $response : $response->withHeader('link', $preload);
    }

    /**
     * The Content-Security-Policy for a document rendered with this nonce:
     * `strict-dynamic` lets a nonced script load the client chunks it
     * imports, and nothing else runs; no plugins, no <base>. In dev, and only
     * there, eval as well, which React's development build needs. With
     * $reports, its violations are reported to /_reports (ADR 0093).
     */
    public static function contentSecurityPolicy(string $nonce, bool $dev = false, bool $reports = false): string
    {
        return "script-src 'nonce-{$nonce}' 'strict-dynamic'" . ($dev ? " 'unsafe-eval'" : '') . "; object-src 'none'; base-uri 'none'"
            . ($reports ? '; ' . ProblemReports::cspDirectives() : '');
    }

    /**
     * @param \Closure(string): void $sink
     * @param \Closure(): void $rewind takes back what $sink was given, for a
     *        render tried again after its first byte (RenderChannel::render())
     */
    private function renderRoute(LoadedRoute $loaded, Request $request, \Closure $sink, bool $payloadOnly, string $nonce, \Closure $rewind): RenderResult
    {
        $page = $loaded->chain->page;

        // Read-only, as the loaders were: encoding resolves their Lazy fields.
        $loaderData = $this->orm->readOnly(fn (): array => $loaded->encode($this->encoder, $request));

        // What the build found this route's views can call, and nothing else.
        $allowed = $this->manifest->queriesFor($page->name ?? '');

        $frame = Frame::render(
            routeId: $page->view,
            params: $loaded->chain->parameters,
            loaderData: $loaderData,
            requestId: RequestId::of($request),
            cspNonce: $nonce,
            allowedQueries: $allowed,
            page: $payloadOnly ? null : new PageMetadata(
                url: $request->rawQuery === null || $request->rawQuery === '' ? $request->path : "{$request->path}?{$request->rawQuery}",
                version: $this->fragments?->builds->current ?? 'dev',
                routeToken: self::stringOrNull($request->attribute(HttpKernel::ROUTE_TOKEN)),
                view: $page->view,
                historyKey: self::stringOrNull($request->attribute(HttpKernel::HISTORY_KEY)),
            ),
            segments: $loaded->segments,
            // For the page's forms: the token their hidden field posts
            // without script, and what such a post left for this page.
            csrfToken: self::stringOrNull($request->attribute(HttpKernel::CSRF_TOKEN)),
            flash: self::arrayOrEmpty($request->attribute(HttpKernel::FLASH)),
            head: [HeadTag::title(self::NAME)],
        );
        if ($payloadOnly) {
            $frame['rsc'] = true;
        }

        $minter = $request->attribute(HttpKernel::FRAGMENT_MINTER);

        return $this->channel->render(
            $frame,
            $allowed,
            $sink,
            $minter instanceof FragmentMinter ? $minter->closure() : null,
            $this->http->shared($loaded, $request),
            // A GET's render may be tried again; an action's re-render, or
            // anything else, may not (ADR 0093).
            repeatable: RenderRetry::permits($request),
            rewind: $rewind,
        );
    }

    /**
     * One deferred subtree, for the fragment endpoint, which has already
     * verified the token and run its route's #[Authorize] chain again.
     *
     * @param list<string> $allowed
     */
    private function renderFragment(FragmentToken $token, Request $request, array $allowed): Response
    {
        $body = '';
        $this->orm->beginRender();
        try {
            $this->channel->render(Frame::fragment(
                token: ['component' => $token->component, 'props' => $token->props],
                requestId: RequestId::of($request),
                cspNonce: base64_encode(random_bytes(12)),
                allowedQueries: $allowed,
            ), $allowed, static function (string $bytes) use (&$body): void {
                $body .= $bytes;
            }, repeatable: RenderRetry::permits($request), rewind: static function () use (&$body): void {
                $body = '';
            });
        } finally {
            $this->orm->endRender();
        }

        return Response::make(200, $body);
    }

    /** Logs a failure that did not fail its response, under the digest the browser is shown. */
    private function report(\Throwable $e, ?string $digest): string
    {
        return $this->http->report($e, $digest);
    }

    private function queryPanelRefusal(string $query): ?string
    {
        return $this->http->queryPanelRefusal($query);
    }

    // ------------------------------------------------------- configuration

    /**
     * What the app lets into its dependency tree, which `bin/tinvar check`
     * holds composer.lock and package-lock.json to: npm's registry, and
     * Meridian's own host for the design system. No package with framework
     * metadata is enabled; one that arrived would fail the check until it
     * is named here and locked (`bin/tinvar packages:lock`).
     */
    public static function packages(): PackagePolicy
    {
        return new PackagePolicy(
            packages: [],
            installScripts: [
                'fsevents' => "Vite's file watcher on macOS: npm builds its binding.gyp. Optional, and skipped elsewhere.",
            ],
            registries: [PackagePolicy::NPM_REGISTRY, 'https://design.chopper.dev/packages/'],
        );
    }

    /**
     * The application key: TINVAR_APP_KEY, or the key generated into
     * TINVAR_APP_KEY_FILE -- or, by default, into a file of this user's
     * state directory named for this app's directory -- the first time one
     * is needed. Bound to the profile this server serves
     * (AppKey::application()): a staging server given production's key still
     * signs with a key of its own.
     *
     * Never in the project. The renderer is sandboxed to what rendering
     * needs, but a key under the project is one a mistake in that sandbox,
     * a copied directory or an image build could carry away.
     */
    public static function appKey(): string
    {
        return AppKey::application('TINVAR_APP_KEY', self::keyFile());
    }

    /** Where the key is generated when TINVAR_APP_KEY gives none. */
    public static function keyFile(): string
    {
        // Absolute or refused: a relative path resolves against whatever
        // directory the process started in, so the server and the CLI would
        // each generate and sign with a key of their own.
        $configured = getenv('TINVAR_APP_KEY_FILE');
        if (\is_string($configured) && $configured !== '') {
            if (!str_starts_with($configured, '/')) {
                throw new \RuntimeException("TINVAR_APP_KEY_FILE must be an absolute path; got {$configured}.");
            }

            return $configured;
        }

        return self::stateFile('key')
            ?? throw new \RuntimeException('No place to keep a generated app key: set TINVAR_APP_KEY, or TINVAR_APP_KEY_FILE (absolute, outside the project, readable by this user alone).');
    }

    /**
     * The token /_health, /_ready and /_metrics let in: TINVAR_PROBE_TOKEN,
     * or the one generated into TINVAR_PROBE_TOKEN_FILE -- or, by default,
     * into this user's state directory beside the app key -- the first time
     * one is needed. `bin/tinvar setup` makes it, and says where it is.
     * Bound to the profile as the app key is (AppKey::secret(), ADR 0102):
     * under staging a probe sends the token `bin/tinvar probe:token` prints.
     */
    public static function probeToken(): string
    {
        return AppKey::secret('TINVAR_PROBE_TOKEN', self::probeTokenFile());
    }

    /** Where the probe token is generated when TINVAR_PROBE_TOKEN gives none. */
    public static function probeTokenFile(): string
    {
        $configured = getenv('TINVAR_PROBE_TOKEN_FILE');
        if (\is_string($configured) && $configured !== '') {
            if (!str_starts_with($configured, '/')) {
                throw new \RuntimeException("TINVAR_PROBE_TOKEN_FILE must be an absolute path; got {$configured}.");
            }

            return $configured;
        }

        return self::stateFile('probe-token')
            ?? throw new \RuntimeException('No place to keep a generated probe token: set TINVAR_PROBE_TOKEN, or TINVAR_PROBE_TOKEN_FILE (absolute, outside the project, readable by this user alone).');
    }

    /** A file of this app's in this user's state directory, by extension; null with none. */
    private static function stateFile(string $extension): ?string
    {
        // One file per app on this machine, named for where it is: two apps
        // made from this skeleton never sign with one key.
        $root = realpath(\dirname(__DIR__)) ?: \dirname(__DIR__);
        $name = (preg_replace('/[^a-z0-9-]+/', '-', strtolower(basename($root))) ?? 'app') . '-' . substr(hash('sha256', $root), 0, 12) . '.' . $extension;

        // The XDG spec says to ignore a relative XDG_STATE_HOME; so does this.
        $state = getenv('XDG_STATE_HOME');
        if (\is_string($state) && str_starts_with($state, '/')) {
            return "{$state}/tinvar/{$name}";
        }
        $home = getenv('HOME');
        if (\is_string($home) && str_starts_with($home, '/')) {
            return "{$home}/.local/state/tinvar/{$name}";
        }

        return null;
    }

    /**
     * The app's settings (app/Config/AppConfig.php), loaded once per worker
     * and again only when the environment changes -- a test's.
     */
    public static function config(): AppConfig
    {
        return Configs::load(AppConfig::class);
    }

    /** APP_DATABASE, or database/app.sqlite. */
    public static function databasePath(): string
    {
        return self::config()->database ?? \dirname(__DIR__) . '/database/app.sqlite';
    }

    /** Where the app's log lines go: APP_LOG, or a file beside the database. */
    private static function logSink(string $database): StreamSink
    {
        $log = self::config()->log;

        return match (true) {
            $log !== null => StreamSink::file($log),
            $database === ':memory:' => StreamSink::stderr(),
            default => StreamSink::file("{$database}-app.log"),
        };
    }

    /** The app's log, which `serve` writes the renderers' lines to as well. */
    public static function rendererLog(): RendererLines
    {
        return new RendererLines(self::logSink(self::databasePath()));
    }

    /**
     * The app's telemetry: JSON log lines in APP_LOG or a file beside the
     * database, metrics in APCu where the server has it loaded and otherwise
     * a SQLite file beside the database, which the CLI's processes always
     * count into. None of it reaches the renderer.
     */
    public static function telemetry(?string $database = null): Telemetry
    {
        $database ??= self::databasePath();
        $memory = $database === ':memory:';
        $logger = new Logger(self::logSink($database));

        // The queue worker and the scheduler count into the file, whatever
        // APCu they have, and the server reads it beside its own.
        $shared = $memory ? null : new SqliteMetricsStore("{$database}-metrics");
        $metrics = match (true) {
            PHP_SAPI !== 'cli' && ApcuMetricsStore::available() => new Metrics(new ApcuMetricsStore('app:metrics:'), readAlso: $shared === null ? [] : [$shared]),
            $shared === null => new Metrics(new ArrayMetricsStore()),
            default => new Metrics($shared, flushEvery: 1.0),
        };

        return new Telemetry($logger, $metrics);
    }

    /**
     * Who may read /_health, /_ready and /_metrics: a caller with the probe
     * token (probeToken(), 32 characters or more), and no one for where
     * they connect from. Loopback is no proof of being a probe: a TLS
     * terminator or a proxy on the same machine, which may add no header to
     * say so, and the renderers beside PHP all connect from it. So it is
     * trusted only when TINVAR_PROBE_TRUST_LOOPBACK is 1; anything but 1 or
     * 0 is refused rather than read as either.
     */
    public static function probeAccess(): ProbeAccess
    {
        $token = self::probeToken();

        return self::config()->probeTrustLoopback ? new ProbeAccess(token: $token) : new ProbeAccess([], $token);
    }

    /**
     * Where the throttles count: APCu where it is loaded, shared by every
     * worker thread; otherwise a SQLite file beside the database. Both are
     * one server's: behind a load balancer, name a store every server
     * reaches (the shop's SHOP_THROTTLE_STORE shows how).
     */
    public static function throttleStore(?string $database = null): ThrottleStore
    {
        if (ApcuThrottleStore::available()) {
            return new ApcuThrottleStore('app:throttle:');
        }
        $database ??= self::databasePath();

        return new SqliteThrottleStore($database === ':memory:' ? ':memory:' : "{$database}-throttle");
    }

    /** Where cached queries are kept: APCu where it is loaded, else this worker's memory. */
    public static function cacheStore(): Store
    {
        return ApcuStore::available() ? new ApcuStore('app:cache:') : new ArrayStore();
    }

    /**
     * The build this code is: TINVAR_BUILD, which bin/serve sets to the hash
     * of the client manifest it built, or `dev`.
     */
    public static function build(): string
    {
        return self::config()->build;
    }

    /**
     * Where `bin/tinvar build:current`, `build:revoke` and the rest keep what
     * they record: TINVAR_BUILDS_FILE, or a file beside the database. Never
     * in dist/, which every build replaces, revocations with it.
     */
    public static function buildStore(?string $database = null): BuildStore
    {
        $configured = self::config()->buildsFile;
        if ($configured !== null) {
            return new FileBuildStore($configured);
        }
        $database ??= self::databasePath();
        if ($database === ':memory:') {
            throw new \RuntimeException('An in-memory database has nowhere beside it to keep builds; set TINVAR_BUILDS_FILE.');
        }

        return new FileBuildStore("{$database}-builds.json");
    }

    /** The builds this server answers for: its own, and what the store says of the rest. */
    private static function builds(string $database): BuildRegistry
    {
        return $database === ':memory:' && self::config()->buildsFile === null
            ? new BuildRegistry(self::build())
            : BuildRegistry::persisted(self::build(), self::buildStore($database));
    }

    /**
     * What the build lets a Link header name, and each asset's digest where
     * the build has them: read once a process (ADR 0108).
     */
    public static function assetLinks(): AssetLinks
    {
        static $links = null;

        return $links ??= AssetLinks::fromBuild(new BuiltAssets(self::buildDirectory()));
    }

    /** The build: dist/, or the directory TINVAR_BUILD_DIR names. */
    public static function buildDirectory(): string
    {
        return BuildProfile::buildDirectory(\dirname(__DIR__));
    }

    /**
     * The fragment manifest `bin/tinvar build:fragments` wrote beside the
     * build: which routes may mint fragment tokens, and what each route's
     * renders may query. Missing until the app is built, when no render may
     * mint or query.
     */
    private static function fragmentManifest(): FragmentManifest
    {
        return FragmentManifest::fromFile(self::buildDirectory() . '/fragments.json');
    }

    /**
     * In dev there is no build: the dev server writes what each view may
     * query and defer, read from its module graph, where
     * TINVAR_DEFERRED_MANIFEST says, and it is joined with the routes here.
     */
    private static function devFragmentManifest(?RouteRegistry $routes = null): FragmentManifest
    {
        $deferred = self::config()->deferredManifest;
        if ($deferred === null || !is_file($deferred)) {
            return self::fragmentManifest();
        }
        if ($routes === null) {
            $routes = new RouteRegistry();
            $routes->registerAll(self::ROUTES);
        }

        return FragmentManifestProducer::produce($routes, $deferred);
    }

    /**
     * `actions=600` as a limits map. Malformed entries are refused rather
     * than skipped: a typo that silently left a limit where it was would
     * read as configured.
     *
     * @return array<string, int>
     */
    public static function limitsFrom(string $spec): array
    {
        $limits = [];
        foreach (array_filter(array_map(trim(...), explode(',', $spec))) as $entry) {
            if (preg_match('/^([a-z][a-z.-]*)=(0|[1-9][0-9]{0,6})\z/', $entry, $m) !== 1) {
                throw new \InvalidArgumentException("TINVAR_THROTTLE: cannot read '{$entry}'; write bucket=limit, as actions=600.");
            }
            $limits[$m[1]] = (int) $m[2];
        }

        return $limits;
    }

    /** The mark, as the favicon draws it, for the error shell PHP draws alone. */
    private static function mark(): string
    {
        $svg = @file_get_contents(\dirname(__DIR__) . '/resources/brand/favicon.svg');

        return \is_string($svg) ? trim($svg) : '';
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function arrayOrEmpty(mixed $value): array
    {
        /** @var array<string, mixed> */
        return \is_array($value) ? $value : [];
    }

    /** The request, carrying one more cookie. A Request is immutable, so it is rebuilt. */
    private static function withRequestCookie(Request $request, string $name, #[\SensitiveParameter] string $value): Request
    {
        $rebuilt = new Request(
            $request->method,
            $request->path,
            $request->headers(),
            $request->query,
            $request->body,
            [...$request->cookies, $name => $value],
            $request->rawQuery,
            $request->remoteAddress,
            $request->files,
        );
        foreach ($request->attributes() as $attribute => $held) {
            $rebuilt = $rebuilt->withAttribute($attribute, $held);
        }

        return $rebuilt;
    }
}
