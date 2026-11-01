# A Tinvar app

A PHP 8.5 core with a React Server Components renderer: PHP owns every
request -- routing, middleware, authorization, validation, the ORM, queries
and actions -- and a small pool of Node processes turns a React tree into
HTML and an RSC stream.

## Start

You need PHP 8.5 with `pdo_sqlite`, `sockets`, `pcntl` and `posix`;
Composer 2; Node 22.13 or later; and FrankenPHP's static binary
(<https://github.com/php/frankenphp/releases>) at `tools/frankenphp`, in
`TINVAR_FRANKENPHP`, or on your `PATH`.

```sh
composer create-project tinvar/skeleton my-app
cd my-app
bin/dev
```

`composer create-project` refuses a directory that is not empty. Once the
app is installed it runs `bin/tinvar setup`, once: it generates the app key
and the probe token into your state directory (`~/.local/state/tinvar/`,
never the project),
writes the TypeScript the views use from the PHP, makes the SQLite database,
and runs `npm install --ignore-scripts`. It runs nothing else it fetched.

`bin/dev` is dev mode: Vite's dev server renders the app from source, an
edit shows in the open page, codegen runs again as the PHP changes, and the
dev toolbar sits over each page. Loopback only, never in production. Started
with `TINVAR_ENV=local`, it also draws the pages and components in
`resources/workbench.tsx` on their own, from the samples there, at
`/_vite/__workbench` and `/_vite/__playground`. `bin/tinvar render --dump
PATH` prints a page's HTML and RSC payload, and `bin/tinvar mcp` is an MCP
server for an assistant; both read a build made with `bin/tinvar build
--profile=test --out=dist-test`, on a copy of the database. To serve the
build as production does:

```sh
npm run build
bin/serve
```

## What is here

```
app/Kernel.php            the composition root, and the only file at app/'s root
app/Http/Pages/           #[Page] and #[Layout] classes; Home is the home page
app/Http/Queries/         #[Query] classes, which server components call
app/Http/Actions/         #[Action] methods, which forms post to
app/Http/Requests/        form requests: what an action is posted
app/Http/Data/            the Data classes that cross to the renderer
app/Database/Models/      #[Table] models
app/Database/Migrations.php  where the migrations are
app/Policies/             what #[Authorize] asks
app/Auth/                 the users the accounts kit reads, and the flashes
app/Config/               the app's #[Config] settings
database/migrations/      one migration per file
resources/pages/          the views, one .tsx per page or layout
resources/components/     server and 'use client' components
resources/entries/        the renderer's rsc, ssr and browser entries
resources/types/          what bin/tinvar codegen writes
tests/Feature/            Pest: pages, queries and actions through PHP's request path
tests/components/         Vitest: client components against codegen's mocks
public/worker.php         the FrankenPHP worker
Caddyfile                 how FrankenPHP serves the app
```

The home page lists notes with a query (`RecentNotes`) and adds one with an
action (`NoteActions::add`, posted by `<NoteForm>`). Both say
`#[PublicAccess]` and `NotePolicy` lets anyone in, signed in or not: decide
there who may.

## Accounts

The app has accounts from the start, from the framework's accounts kit
(`Tinvar\Accounts`, ADR 0150): signing up by a link to the address, signing
in by password or passkey, two-factor codes from an authenticator app with
recovery codes, a forgotten and a changed password, a change of address by
a link to the new one, and signing out everywhere. The pages are under
`app/Http/Pages/Account/` and `resources/pages/account/`, the actions under
`app/Http/Actions/Account/`, the emails' words in `app/Mail/Account/` and
`resources/emails/`, and the users in `app/Database/Models/Account/User.php`
through `app/Auth/Account/Users.php` -- change those two to keep your
people in a table of your own. A page for signed-in users says
`#[Middleware(RequireSignIn::class)]`.

Its email goes through the queue, which `bin/dev` and `bin/serve` run
beside the server. In development it lands in
`database/app.sqlite-mail.log`, its links on `http://localhost:PORT`; open
the app on `localhost` rather than 127.0.0.1, which passkeys refuse. In
production set `APP_URL` (the https origin every link is built on and
passkeys are made for), `APP_MAIL_FROM` and `TINVAR_MAIL_PROVIDER`: the app
refuses to start without them.

`bin/tinvar make:page`, `make:query`, `make:action`, `make:model` and
`make:migration` write the next ones into this layout, each registered by
being there, and a page's view in `resources/entries/rsc.tsx`. Give a name
a domain once the app has more than one: `make:page Orders/OrderDetail`
writes `app/Http/Pages/Orders/OrderDetail.php` and its Data beside the
domain's in `app/Http/Data/Orders/`. `bin/tinvar help` lists every command.

## Check it

```sh
vendor/bin/pest
npx vitest run
npm run lint
bin/tinvar codegen --check
bin/tinvar check
```

`bin/tinvar check` holds every page, query and action to the security
model's build rules -- every model input is authorized, or says why not --
and the lockfiles to `Kernel::packages()`.

## Settings and secrets

The app reads its settings from the environment, through a typed
`#[Config]` class, `app/Config/AppConfig.php`: each constructor parameter
is one variable, parsed to its type at boot, and a key missing or
malformed stops the server with the variable named, never its value. A
secret is typed `Tinvar\Config\Secret`, which no dump, log or render shows.
`bin/tinvar config` lists every key and where its value came from, with
secrets hidden; `.env.example` lists them with nothing secret filled in.
The app key is `TINVAR_APP_KEY`, or the file
`bin/tinvar setup` generated. `/_health`, `/_ready` and `/_metrics` let in
the probe token alone, as `Authorization: Bearer <token>`: it is
`TINVAR_PROBE_TOKEN`, or the `.probe-token` file setup generated beside the
key. No caller gets in for being on loopback -- a TLS terminator on the
same machine is on loopback too -- unless `TINVAR_PROBE_TRUST_LOOPBACK=1`.

```sh
curl -H "Authorization: Bearer $(cat ~/.local/state/tinvar/my-app-*.probe-token)" http://127.0.0.1:6123/_health
```
 Never commit a key: `.gitignore` and
`.dockerignore` keep `.env`, `*.key` and the database out.

## Production

`Dockerfile` and `compose.yaml` build and run one container: FrankenPHP and
the renderers as two users, nothing root's once both are started, a
read-only root, every base pinned by digest and every binary by its sha256.
The image holds no secret; it refuses to start without an app key and a
probe token.

```sh
cp .env.example .env    # fill in APP_KEY and APP_PROBE_TOKEN
docker compose up -d --build
```

Put a TLS terminator in front: the cookies are `__Host-`, which browsers
send over https alone.

## Licence

The skeleton is Tinvar's, under the MIT licence: that is what `LICENSE` and
`"license": "MIT"` in `composer.json` say. Your app is yours. Replace both
with your own licence -- or delete `LICENSE` and set
`"license": "proprietary"` -- before you publish or ship it. The
framework's packages in `vendor/` and `node_modules/` keep their own MIT
licences either way.
