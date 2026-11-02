<?php

declare(strict_types=1);

namespace App\Auth\Admin;

use Tinvar\Accounts\AccountPaths;
use Tinvar\Auth\AuthContext;
use Tinvar\Http\ForbiddenException;
use Tinvar\Http\Middleware;
use Tinvar\Http\Request;
use Tinvar\Http\Response;

/**
 * The admin panel's guard: the first thing every page, action and query in
 * the panel runs (Kernel::panels()). A visitor is sent to sign in and
 * brought back; a signed-in account that is not an admin is refused with a
 * 403 that says why. Each screen's policy is asked after this, as well.
 */
final readonly class RequireAdmin implements Middleware
{
    public function __construct(private AuthContext $auth, private AccountPaths $paths, private Admins $admins) {}

    public function handle(Request $request, \Closure $next): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect($this->paths->signInFor($request->path, $request->rawQuery));
        }

        if (!$this->admins->allows($this->auth->user())) {
            throw new ForbiddenException($this->admins->unnamed()
                ? 'The admin panel is shut: APP_ADMINS names nobody. Set it to the admins\' addresses, comma-separated, and restart the app.'
                : 'Only the app\'s admins may open the admin panel; APP_ADMINS names them.');
        }

        return $next($request);
    }
}
