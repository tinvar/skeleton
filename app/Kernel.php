<?php

declare(strict_types=1);

namespace App;

use App\Auth\Account\Users;
use App\Auth\Admin\Admins;
use App\Auth\Admin\RequireAdmin;
use App\Config\AppConfig;
use App\Http\Pages\Account\ApiTokenSettings;
use App\Mail\Account\AccountMails;
use Tinvar\Accounts\AccountLinks;
use Tinvar\Accounts\AccountsKit;
use Tinvar\Accounts\SessionCookie;
use Tinvar\Accounts\StartSession;
use Tinvar\Actions\ActionRegistry;
use Tinvar\Api\ApiAccess;
use Tinvar\Api\DatabaseApiTokens;
use Tinvar\Attributes\OutboundPolicy;
use Tinvar\Attributes\Queue as QueueOptions;
use Tinvar\Auth\Authenticatable;
use Tinvar\Auth\AuthContext;
use Tinvar\Auth\PanelContext;
use Tinvar\Auth\PanelDefinition;
use Tinvar\Auth\PanelRegistry;
use Tinvar\Auth\PolicyGate;
use Tinvar\Auth\PolicyRegistry;
use Tinvar\Cache\ApcuStore;
use Tinvar\Cache\ArrayStore;
use Tinvar\Cache\QueryCache;
use Tinvar\Cache\Store;
use Tinvar\Codec\FrameCodec;
use Tinvar\Codec\MessagePackCodec;
use Tinvar\Config\FrameworkConfig;
use Tinvar\Config\Secret;
use Tinvar\Console\Console;
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
use Tinvar\Http\TrustedProxies;
use Tinvar\Http\ThrottleStore;
use Tinvar\Config\Configs;
use Tinvar\Discovery\DiscoveredClasses;
use Tinvar\Discovery\Discovery;
use Tinvar\Jobs\Dispatcher;
use Tinvar\Jobs\Envelope;
use Tinvar\Jobs\JobRunner;
use Tinvar\Mail\Address;
use Tinvar\Mail\ArrayTransport;
use Tinvar\Mail\DkimConfig;
use Tinvar\Mail\Mailer;
use Tinvar\Mail\MailRenderer;
use Tinvar\Mail\MailTransports;
use Tinvar\Mail\RendererTemplates;
use Tinvar\Mail\SendMail;
use Tinvar\Mail\Transport;
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
use Tinvar\Orm\DatabaseDriver;
use Tinvar\Orm\IdentityMap;
use Tinvar\Orm\Orm;
use Tinvar\Orm\PdoConnection;
use Tinvar\Outbound\PinnedHttp;
use Tinvar\Packages\PackagePolicy;
use Tinvar\Protocol\Frame;
use Tinvar\Protocol\PageMetadata;
use Tinvar\Query\QueryRegistry;
use Tinvar\Query\QueryRunner;
use Tinvar\Queue\DatabaseQueue;
use Tinvar\Queue\Queue;
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
use Tinvar\Endpoints\EndpointRegistry;
use Tinvar\Support\AppKey;
use Tinvar\Support\StateFile;
use Tinvar\Support\BuildProfile;
use Tinvar\Support\DevMode;
use Tinvar\Support\Signer;
use Tinvar\Support\StandIns;
use App\Auth\Flashes;

/**
 * Wires the app together: its composition root.
 *
 * Composition is explicit. One container builds the app's pages, queries,
 * actions and policies by their constructor types, and this class wires the
 * rest by hand; there are no service providers. Every request goes through
 * the framework's Kernel -- middleware, the throttle, CSRF, authorization,
 * the reset contract, exception classification and the error pages -- and
 * this class adds what is the app's own: its accounts -- the session, sign-up
 * and sign-in, passkeys and two-factor, from the framework's accounts kit
 * (Tinvar\Accounts, ADR 0153) -- its queue and mail, and turning a loaded
 * route into a render.
 *
 * What you add registers itself: a #[Page] or #[Layout], a #[Query], a
 * class with #[Action] methods, a model, a #[Policy(Model::class)], a
 * #[Config] class -- each is discovered under App\ by what it declares,
 * whatever folder it is in (classes(), ADR 0146). `php tinvar check` holds
 * every one of them to the security model's build rules. One panel, the
 * admin panel, is defined in panels(): an #[AdminResource] in it is a list,
 * a record and its forms under /admin, for the accounts APP_ADMINS names.
 * The example shop (examples/shop in the framework's repository) wires the
 * rest -- live updates, uploads, static pages -- the same way.
 *
 * Its settings come from the environment, never a file in the project,
 * through a typed #[Config] class -- APP_DATABASE, APP_LOG and
 * the TINVAR_* it reads are App\Config\AppConfig's -- beside the app key,
 * TINVAR_APP_KEY or TINVAR_APP_KEY_FILE (.env.example lists them).
 */
final class Kernel implements TestableApplication, Terminable
{
    /** The app's name, in the title of every page and on the error shell. */
    public const string NAME = 'Tinvar app';

    /**
     * Every class the app registers: its pages and layouts, queries,
     * actions, policies, models, #[Config] classes and the rest, discovered
     * under App\ by what each declares (ADR 0146) -- from the build's
     * manifest in production, which `php tinvar build` writes and which
     * must be there, and from a scan kept while the files stay as they were
     * anywhere else. The framework's own config is added by hand.
     */
    public static function classes(): DiscoveredClasses
    {
        return Discovery::of(self::class, self::buildDirectory())->classes()->with(configs: [FrameworkConfig::class, DkimConfig::class, ...MailTransports::configs()]);
    }

    public readonly Orm $orm;
    public readonly QueryRegistry $queries;
    public readonly RouteRegistry $routes;
    public readonly EndpointRegistry $endpoints;
    public readonly ActionRegistry $actions;

    /** The accounts kit: the session, sign-in and every account flow (ADR 0153). */
    public readonly AccountsKit $accounts;

    /** Where jobs wait: the jobs table, beside the rows they are about. */
    public readonly Queue $queue;

    /** Sends the app's email, through the renderer, now or on the queue worker. */
    public readonly Mailer $mailer;

    /** queue:work and the rest of the framework's commands, over this app (tinvar). */
    public readonly Console $console;

    /** The session cookie's name: `__Host-`, so nothing outside this origin can plant it. */
    public const string SESSION_COOKIE = '__Host-app_session';

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
        // The proxies in front, through which the throttles read the
        // client's address (TINVAR_TRUSTED_PROXIES); none, and the peer is
        // the client.
        ?TrustedProxies $proxies = null,
        // Where sign-in guesses are counted: never APCu, which a flood of
        // other keys clears (SignInThrottle, ADR 0023). A file beside the
        // database unless given.
        ?ThrottleStore $signInStore = null,
        // Where the account emails' links are built and passkeys are for:
        // AccountLinks::origin() of APP_URL unless given.
        ?string $origin = null,
        // Where jobs wait, how mail is drawn and where it goes: the jobs
        // table, the renderer and TINVAR_MAIL_PROVIDER's transport unless
        // given -- a test's MailFake, say.
        ?Queue $queue = null,
        ?MailRenderer $mailRenderer = null,
        ?Transport $mailTransport = null,
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

        // The connecting peer, unless it is one of the proxies the
        // deployment pinned (TINVAR_TRUSTED_PROXIES): then the right-most
        // X-Forwarded-For hop that is not one of them, so a visitor behind
        // the TLS terminator is not everyone behind it. Never the header
        // from anyone else: it is whatever the client sent, and keyed on it
        // a client could pick its own bucket (ADR 0134).
        $throttle = new Throttle($throttleStore, $auth, ($proxies ?? TrustedProxies::none())->clientAddress(...));

        $container = (new Container())
            ->instance(Orm::class, $this->orm)
            ->instance(Throttle::class, $throttle)
            ->instance(AuthContext::class, $auth)
            // Who may open the admin panel: APP_ADMINS, or under `php tinvar serve --dev`
            // with it unset, every signed-in account (App\Auth\Admin\Admins).
            ->instance(Admins::class, Admins::fromList(self::config()->admins, openInDev: $dev));

        // Every class the app registers, read once (ADR 0146).
        $classes = self::classes();

        $this->queries = new QueryRegistry($container->resolver(), $classes->models);
        $this->queries->registerAll($classes->queries);

        $this->actions = new ActionRegistry($container->resolver(), $classes->models);
        $this->actions->registerAll($classes->actions);

        $this->routes = new RouteRegistry($classes->models);
        $this->routes->registerAll($classes->routes);

        // The routes that are not pages -- an API, a download, a webhook's
        // callback: #[Get], #[Post] and #[Endpoint] methods (ADR 0067).
        $this->endpoints = new EndpointRegistry($classes->models);
        $this->endpoints->registerAll($classes->endpoints);

        $policies = new PolicyRegistry($container->resolver());
        $policies->registerAll($classes->policies);

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

        // Background work, in the app's own database (its tables are a
        // migration): a job dispatched while a row is written is a row
        // written in the same transaction, so it exists exactly when the
        // row does. `php tinvar queue:work` runs it, and `php tinvar serve --dev` and
        // `php tinvar serve` run that.
        $this->queue = $queue ?? new DatabaseQueue($connection);
        $jobSigner = new Signer($key, Envelope::PURPOSE);
        $jobs = new Dispatcher($this->queue, $this->orm, $jobSigner, $auth, $panels, self::panels());
        $this->mailer = new Mailer(
            // fromEnvironment() hands in the environment's; built by hand,
            // the mail is kept in memory and goes nowhere.
            $mailTransport ?? new ArrayTransport(),
            // Drawn by the renderer, as pages are: resources/emails/, named in
            // resources/entries/mail.tsx.
            $mailRenderer ?? new RendererTemplates($this->channel, telemetry: $telemetry),
            self::mailFrom(),
            $jobs,
            $this->encoder,
            $telemetry,
        );

        // The accounts (ADR 0153): the users in App\Database\Models\Account\User
        // through App\Auth\Account\Users, their email in App\Mail\Account,
        // every link built on APP_URL, and sign-in guesses counted where a
        // flood cannot clear them.
        $this->accounts = new AccountsKit(
            connection: $connection,
            key: $key,
            users: new Users($this->orm),
            mails: new AccountMails(),
            jobs: $jobs,
            mailer: $this->mailer,
            throttles: $signInStore ?? self::signInStore(':memory:'),
            throttle: $throttle,
            origin: $origin ?? AccountLinks::origin(self::config()->url, self::config()->servedAt),
            name: self::NAME,
            auth: $auth,
            csrf: $this->csrf,
            cookieName: self::SESSION_COOKIE,
            limits: $limits,
            orm: $this->orm,
        );
        $this->accounts->register($container)
            ->instance(Dispatcher::class, $jobs)
            ->instance(Mailer::class, $this->mailer);

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
            // Every request's session, opened before anything reads who is
            // signed in: the accounts kit's (ADR 0153).
            globalMiddleware: [StartSession::class],
            // A form posted without script that fails is sent back to its
            // page with its errors, which wait for it here, by session.
            flashes: new Flashes($this->orm, $this->accounts->cookie),
            historyKeys: $historyKeys,
            panels: $panels,
            // The admin panel: its guard runs before every page, action and
            // query in it, then each one's own checks.
            panelRegistry: self::panels(),
            // API tokens (ADR 0159): /_api for every #[RequiresScope], held to
            // each account's credential epoch, which signing in elsewhere
            // leaves alone and signing out everywhere moves.
            apiAccess: new ApiAccess(new DatabaseApiTokens($connection), $this->accounts->tokenHolders, maxPerUser: ApiTokenSettings::MAX_TOKENS),
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
            endpoints: $this->endpoints,
            standIns: $standIns,
        );

        // queue:work and the queue's other commands. A job that runs as the
        // user who dispatched it is found again at their epoch now.
        $this->console = new Console(
            commands: $classes->commands,
            orm: $this->orm,
            resolver: $container->resolver(),
            scope: $this->http->scope,
            panels: $panels,
            queue: $this->queue,
            runner: new JobRunner($this->orm, $jobSigner, $container->resolver(), $auth, $panels, self::panels(), users: $this->accounts->lookup),
            report: static function (\Throwable $e) use ($report): void {
                $report($e, null);
            },
            // Mail first: an account email is what someone is waiting on.
            queues: [SendMail::QUEUE, QueueOptions::DEFAULT],
            telemetry: $telemetry,
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
        // In place of the database the config names: a test's SQLite file or
        // ':memory:', or the copy `render --dump` and `mcp` make -- an SQLite
        // file, or a copy on the app's server named by its DSN, which carries
        // its password (copyConnection(), ADR 0173).
        #[\SensitiveParameter]
        ?string $database = null,
        ?bool $dev = null,
        // In place of the log file and metrics store the environment gives: a test's.
        ?Telemetry $telemetry = null,
        // What answers in place of a query's or an action's body: a test's fakes.
        ?StandIns $standIns = null,
        ?FragmentManifest $manifest = null,
        // A test's: how mail is drawn and where it goes (a MailFake), where
        // sign-in guesses are counted, and the origin links are built on.
        ?MailRenderer $mailRenderer = null,
        ?Transport $mailTransport = null,
        ?ThrottleStore $signInStore = null,
        ?string $origin = null,
        // What the files kept beside the database -- the log, the mail log,
        // the metrics, the counts, the build history -- are named from, in
        // place of the database's path: required with a copy on a server,
        // whose DSN names no place to keep them -- the dev tools name one in
        // the copy's private directory (ADR 0173).
        ?string $files = null,
    ): self {
        // First of all: a key missing or malformed stops the process here,
        // every one named, before anything is opened on a guess.
        Configs::loadAll(self::classes()->configs);
        $dev ??= FrameworkConfig::load()->devMode();
        $key ??= self::appKey();
        $socketPath ??= self::config()->socket;
        // First, so a renderer user that is no one, root or this process's
        // own stops the process here rather than leaving renders to go anywhere.
        $rendererUsers = RendererUser::socketOwners();

        // One resolver for every binding: fragment and route tokens, the CSRF
        // token, the history key and the toolbar's token, all the session's.
        $cookie = new SessionCookie($key, self::SESSION_COOKIE);
        $sessionId = static fn (Request $r): string => $cookie->of($r);

        // Before anything opens: a toolbar asked for in production stops here.
        $toolbar = DevToolbar::fromEnvironment($sessionId, dev: $dev, build: self::build());

        $named = $database !== null;
        $copy = $named && (str_starts_with($database, 'pgsql:') || str_starts_with($database, 'mysql:'));
        if ($copy && $files === null) {
            throw new \InvalidArgumentException('A database on a server, named by its DSN, has nowhere beside it to keep the files kept beside a database: name a place with files:.');
        }
        $database ??= self::databasePath();
        $beside = $files ?? $database;
        $manifest ??= $dev ? self::devFragmentManifest() : self::fragmentManifest();

        /** @var self|null $kernel */
        $kernel = null;
        $fragments = new FragmentSupport(
            signer: new Signer($key, FragmentToken::PURPOSE),
            builds: self::builds($beside),
            render: static function (FragmentToken $token, Request $request, array $models, array $allowed, ?SharedReads $shared = null) use (&$kernel): Response {
                \assert($kernel instanceof self);

                return $kernel->renderFragment($token, $request, $allowed);
            },
            sessionId: $sessionId,
            allowedQueries: $manifest->queriesFor(...),
            routeSigner: new Signer($key, RouteToken::PURPOSE),
        );

        // A database a caller names -- a test's, `render --dump`'s copy -- is
        // a SQLite file, or a copy on a server and nothing else there;
        // otherwise the one the config says.
        $connection = match (true) {
            $copy => self::copyConnection($database),
            $named => PdoConnection::sqlite($database, 'app'),
            default => self::connection(),
        };
        $telemetry ??= self::telemetry($beside);

        $kernel = new self(
            $connection,
            $socketPath,
            $key,
            $fragments,
            new MessagePackCodec(),
            false,
            new CsrfTokens(new Signer($key, CsrfTokens::PURPOSE), $sessionId),
            self::throttleStore($beside),
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
            FrameworkConfig::load()->proxies(),
            $signInStore ?? self::signInStore($beside),
            $origin,
            mailRenderer: $mailRenderer,
            mailTransport: $mailTransport ?? self::mailTransport($beside),
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

        $cookie = $this->accounts->cookie;
        $issued = null;
        if ($cookie->id($request->cookie($cookie->name)) === null) {
            $issued = $cookie->issue();
            $request = self::withRequestCookie($request, $cookie->name, $issued);
        }

        $response = $this->http->handle($request, $respond, $rawBody);

        return $issued === null || isset($response->cookies()[$cookie->name])
            ? $response
            : $cookie->set($response, $issued, $this->csrf);
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
     * The cookies a browser signed in as $user sends with $request: a
     * session row made as signing in makes one, with no password asked.
     * For tests (TestableApplication); refused where the environment says
     * production. No route, action or endpoint reaches it.
     */
    public function signedIn(Authenticatable $user, Request $request): array
    {
        return $this->accounts->signedIn($user, $request);
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
            'x-frame-options' => 'DENY',
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
     *
     * And no page framed, by another origin or by the app itself: a page in
     * someone else's iframe is a click taken under a disguise. An app that
     * frames its own pages says `frame-ancestors 'self'` here instead, and
     * `tinvar check` fails a policy that says nothing.
     * X-Frame-Options says the same to a browser that reads no
     * `frame-ancestors`.
     */
    public static function contentSecurityPolicy(string $nonce, bool $dev = false, bool $reports = false): string
    {
        return "script-src 'nonce-{$nonce}' 'strict-dynamic'" . ($dev ? " 'unsafe-eval'" : '') . "; object-src 'none'; base-uri 'none'; frame-ancestors 'none'"
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
                // What its vitals and errors are filed under, with no route
                // token to read it from -- a static page, an unnamed one
                // (ADR 0129): its name, or its view.
                route: $page->name ?? $page->view,
            ),
            segments: $loaded->segments,
            // For the page's forms: the token their hidden field posts
            // without script, and what such a post left for this page.
            csrfToken: self::stringOrNull($request->attribute(HttpKernel::CSRF_TOKEN)),
            flash: self::arrayOrEmpty($request->attribute(HttpKernel::FLASH)),
            head: [HeadTag::title(self::NAME)],
            // What route() writes a link to a page on another host with
            // (ADR 0149, 0185): the hosts this page may link to, absolute
            // there. Null where the app routes on no host.
            hosts: $this->http->hostsFor($request, $loaded),
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
     * What the app lets into its dependency tree, which `php tinvar check`
     * holds composer.lock and package-lock.json to: npm's registry, and
     * Meridian's own host for the design system. No package with framework
     * metadata is enabled; one that arrived would fail the check until it
     * is named here and locked (`php tinvar packages:lock`).
     *
     * @tinvar/mail, @tinvar/react and @tinvar/renderer come from npm's
     * registry, where the project holds the scope, at the sha512
     * package-lock.json pins (ADR 0138, 0174). An app that should take
     * them from inside itself instead -- `npm pack` tarballs, depended on
     * as `file:` -- names `@tinvar/*` in the policy's `unpublished`, and
     * the check then refuses them from any registry.
     */
    /**
     * The app's panels: the admin panel, behind RequireAdmin. An
     * #[AdminResource(panel: Admins::PANEL)] -- `php tinvar make:resource
     * --panel=admin` writes one -- is drawn by the three views under
     * resources/pages/admin/resources, and a page joins the panel with
     * #[Panel(Admins::PANEL)].
     */
    public static function panels(): PanelRegistry
    {
        return new PanelRegistry(new PanelDefinition(Admins::PANEL, guard: RequireAdmin::class));
    }

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
     * one is needed. `php tinvar setup` makes it, and says where it is.
     * Bound to the profile as the app key is (AppKey::secret(), ADR 0102):
     * under staging a probe sends the token `php tinvar probe:token` prints.
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

    /**
     * A file of this app's in this user's state directory, by extension;
     * null with none. One file per app on this machine, named for where it
     * is (StateFile): two apps made from this skeleton never sign with one
     * key.
     */
    private static function stateFile(string $extension): ?string
    {
        return StateFile::of(\dirname(__DIR__), $extension);
    }

    /**
     * The app's settings (app/Config/AppConfig.php), loaded once per worker
     * and again only when the environment changes -- a test's.
     */
    public static function config(): AppConfig
    {
        return Configs::load(AppConfig::class);
    }

    /**
     * The app's database, as APP_DATABASE_DRIVER says: the SQLite file at
     * databasePath(), or a Postgres or MySQL server by APP_DATABASE_HOST,
     * APP_DATABASE_PORT and APP_DATABASE_NAME, as APP_DATABASE_USER with
     * APP_DATABASE_PASSWORD (or the file APP_DATABASE_PASSWORD_FILE names).
     * The migrations run against it (`php tinvar migrate`) as the server does.
     *
     * @param bool $create make the SQLite file where it is missing, as migrating does
     */
    public static function connection(bool $create = false): PdoConnection
    {
        $config = self::config();
        if (!$config->databaseDriver->isServer()) {
            return PdoConnection::sqlite(self::databasePath(), 'app', create: $create);
        }

        return PdoConnection::server(
            $config->databaseDriver,
            (string) $config->databaseHost,
            $config->databasePort,
            (string) $config->databaseName,
            $config->databaseUser,
            $config->databasePassword?->reveal(),
            'app',
        );
    }

    /**
     * What `php tinvar render --dump` and `mcp` copy, and render on the copy
     * of (tinvar/testing's DatabaseSnapshot, ADR 0173): the SQLite file at
     * databasePath(), or the Postgres or MySQL database the config names,
     * as the DSN the server's own dump tools are handed it by (ADR 0113) --
     * its user and password in it, so a Secret. Never the path the files
     * kept beside a server's database are named from, which holds none of
     * its rows.
     *
     * A user or password the DSN cannot carry is refused, never cut short:
     * a semicolon ends a DSN's value -- `secret;dbname=other` would name
     * another database -- and the DSN is read with each value's ends trimmed.
     *
     * On MySQL the copy is opened by the copies' user's DSN,
     * TINVAR_DUMP_MYSQL_DSN, so one this app could not open -- no host, a
     * socket -- is refused here, before a copy is made to no end.
     *
     * @throws \RuntimeException for a user or password a DSN cannot carry, a copies' user's DSN this
     *         app could not open, or an SQLite path that reads as a DSN
     * @throws \InvalidArgumentException for a host or database name a DSN cannot carry (DatabaseDriver::dsn())
     */
    public static function devToolsDatabase(): string|Secret
    {
        $config = self::config();
        if (!$config->databaseDriver->isServer()) {
            // A path that reads as a server's DSN would be copied as one.
            $path = self::databasePath();
            if (str_starts_with($path, 'pgsql:') || str_starts_with($path, 'mysql:')) {
                throw new \RuntimeException('APP_DATABASE reads as a server\'s DSN, and APP_DATABASE_DRIVER says SQLite: name the SQLite file there, or the server by APP_DATABASE_DRIVER and the rest; nothing was copied.');
            }

            return $path;
        }
        // On MySQL the copy is opened by the copies' user's DSN: one this app
        // could not open is refused now, before anything is copied.
        $copies = $config->databaseDriver === DatabaseDriver::Mysql ? FrameworkConfig::load()->dumpMysqlDsn : null;
        if ($copies !== null) {
            $why = self::unopenable(self::dsnParts($copies->reveal())[1], database: false);
            if ($why !== null) {
                throw new \RuntimeException("TINVAR_DUMP_MYSQL_DSN {$why}, and the copy is opened by it as this app opens its database; nothing was copied.");
            }
        }

        $dsn = $config->databaseDriver->dsn((string) $config->databaseHost, $config->databasePort, (string) $config->databaseName);
        $credentials = [
            'user' => ['APP_DATABASE_USER', $config->databaseUser],
            'password' => ['APP_DATABASE_PASSWORD', $config->databasePassword?->reveal()],
        ];
        foreach ($credentials as $part => [$variable, $value]) {
            if ($value === null) {
                continue;
            }
            if (str_contains($value, ';') || trim($value) !== $value || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
                throw new \RuntimeException("{$variable} holds a semicolon, a control character or a space at either end, which the DSN the dev tools copy the database by cannot carry; nothing was copied.");
            }
            $dsn .= ";{$part}={$value}";
        }

        return new Secret($dsn);
    }

    /**
     * A copy `render --dump` or `mcp` made of the app's database on its
     * server (tinvar/testing's ServerCopy, ADR 0113), by the DSN the dev
     * tools hand the app, opened as the app's own database is --
     * PdoConnection::server(), so a MySQL session reads names in double
     * quotes. A copy and nothing else: a database whose name is not one
     * ServerCopy makes -- `tinvar_dump_`, the host's hash, the pid that made
     * it and a random part -- is refused, so nothing opens the app's own
     * database, or any other, this way (ADR 0173).
     *
     * @throws \RuntimeException for a DSN that names no copy, or names one by what this app opens no database by
     */
    private static function copyConnection(#[\SensitiveParameter] string $dsn): PdoConnection
    {
        [$scheme, $parts] = self::dsnParts($dsn);
        $name = $parts['dbname'] ?? '';
        if (preg_match('/^tinvar_dump_[0-9a-f]{8}_[1-9][0-9]{0,9}_[0-9a-f]{8}\z/', $name) !== 1) {
            throw new \RuntimeException("A database on a server is named to the Kernel by its DSN only as a copy `render --dump` made (tinvar_dump_...), and {$name} is not a copy: the app's own database is opened from its config alone.");
        }
        $why = self::unopenable($parts);
        if ($why !== null) {
            throw new \RuntimeException("The copy's {$scheme} DSN {$why}; it was not opened.");
        }
        $port = $parts['port'] ?? null;

        return PdoConnection::server(
            $scheme === 'pgsql' ? DatabaseDriver::Postgres : DatabaseDriver::Mysql,
            $parts['host'] ?? '',
            $port === null ? null : (int) $port,
            $name,
            $parts['user'] ?? null,
            $parts['password'] ?? null,
            'app',
        );
    }

    /**
     * A DSN's scheme and its parts, read as tinvar/testing's ServerCopy reads
     * one: split at each semicolon and at a part's first equals sign, each
     * key lower-cased and each value trimmed.
     *
     * @return array{string, array<string, string>}
     * @throws \RuntimeException for a part that is not key=value
     */
    private static function dsnParts(#[\SensitiveParameter] string $dsn): array
    {
        [$scheme, $rest] = array_pad(explode(':', $dsn, 2), 2, '');
        $parts = [];
        foreach (explode(';', $rest) as $pair) {
            if (trim($pair) === '') {
                continue;
            }
            $at = strpos($pair, '=');
            if ($at === false) {
                throw new \RuntimeException("A {$scheme} DSN the dev tools were handed has a part that is not key=value; nothing was opened.");
            }
            $parts[strtolower(trim(substr($pair, 0, $at)))] = trim(substr($pair, $at + 1));
        }

        return [$scheme, $parts];
    }

    /**
     * Why PdoConnection::server() could not open a database by $parts as
     * this app opens its own -- by host, port and name -- or null when it
     * can. charset is taken and not read: a MySQL connection is utf8mb4, as
     * the app's own is (DatabaseDriver::dsn()).
     *
     * @param array<string, string> $parts
     * @param bool $database whether $parts must name the database: a copy's do, the copies' user's need not
     */
    private static function unopenable(#[\SensitiveParameter] array $parts, bool $database = true): ?string
    {
        $unknown = array_diff(array_keys($parts), ['host', 'port', 'dbname', 'user', 'password', 'charset']);

        return match (true) {
            $unknown !== [] => 'names ' . implode(', ', $unknown) . ', which this app opens no database by',
            ($parts['host'] ?? '') === '' || preg_match('/[;=\s]/', $parts['host']) === 1 => 'names no host this app reaches a database by',
            isset($parts['port']) && preg_match('/^[1-9][0-9]{0,4}\z/', $parts['port']) !== 1 => 'names a port that is not one',
            $database && (($parts['dbname'] ?? '') === '' || preg_match('/[;=\s]/', $parts['dbname']) === 1) => 'names no database this app opens',
            default => null,
        };
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
     * Where sign-in guesses, two-factor codes and requests for account email
     * are counted: a SQLite file beside the database, which nothing else
     * fills -- never APCu, where anyone can fill the store until it clears
     * every count, sign-in's with it (ADR 0023). One server's: behind a load
     * balancer, name a store every server reaches.
     */
    public static function signInStore(?string $database = null): ThrottleStore
    {
        $database ??= self::databasePath();

        return new SqliteThrottleStore($database === ':memory:' ? ':memory:' : "{$database}-sign-in");
    }

    /**
     * Where the app's email goes: TINVAR_MAIL_PROVIDER and the named
     * provider's own variables -- `smtp` with TINVAR_SMTP_HOST and the rest,
     * or `ses`, `postmark`, `mailgun`, `resend` or `sendgrid` (ADR 0147).
     * Unset, in development, a log file -- APP_MAIL_LOG, or one beside the
     * database -- so nothing leaves the machine; where the environment says
     * production, a provider that sends must be named, and the boot stops
     * without.
     */
    public static function mailTransport(?string $database = null): Transport
    {
        $database ??= self::databasePath();
        $log = self::config()->mailLog
            ?? ($database === ':memory:' ? (self::stateFile('mail.log') ?? throw new \RuntimeException('No place for the mail log: set APP_MAIL_LOG.')) : "{$database}-mail.log");

        return new MailTransports(new PinnedHttp(OutboundPolicy::of(self::class)), DkimConfig::load()->signer(), $log)->transport();
    }

    /**
     * Who the app's email is from: APP_MAIL_FROM. In development -- TINVAR_ENV
     * local, development or testing -- a stand-in on localhost; anywhere
     * else, an environment that says nothing included, it must be set,
     * since mail from an address nobody owns is mail nobody can answer, and
     * the boot stops without.
     */
    public static function mailFrom(): Address
    {
        $from = self::config()->mailFrom;
        if ($from !== null && $from !== '') {
            return new Address($from, self::NAME);
        }
        // A stand-in only where the environment says this is a developer's
        // machine; one that says nothing may be production's (ADR 0213).
        $why = DevMode::notDevelopment();
        if ($why !== null) {
            throw new \RuntimeException("APP_MAIL_FROM is not set, and {$why}: set it to the address the app's email comes from, as hello@app.example.");
        }

        return new Address('no-reply@app.localhost', self::NAME);
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
     * The build this code is: TINVAR_BUILD, which `tinvar serve` sets to the hash
     * of the client manifest it built, or `dev`.
     */
    public static function build(): string
    {
        return self::config()->build;
    }

    /**
     * Where `php tinvar build:current`, `build:revoke` and the rest keep what
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
     * The fragment manifest `php tinvar build:fragments` wrote beside the
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
            $routes->registerAll(self::classes()->routes);
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
