<?php

namespace Junges\Watchdog\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Junges\Watchdog\Exceptions\InvalidInviteCodeException;
use Junges\Watchdog\Exceptions\InviteWithRestrictedUsageException;
use Junges\Watchdog\Exceptions\RouteProtectedByInviteCodeException;
use Junges\Watchdog\Exceptions\UserLoggedOutException;
use Junges\Watchdog\Facades\Watchdog;
use Junges\Watchdog\Http\Models\Invite;

class ProtectedByInviteCodeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     * @throws RouteProtectedByInviteCodeException
     * @throws InvalidInviteCodeException
     * @throws UserLoggedOutException
     * @throws InviteWithRestrictedUsageException
     */
    public function handle($request, Closure $next)
    {
        if ($request->has('invite_code')) {
            $invite_code = $request->input('invite_code');
            $invite_model = app(config('watchdog.models.invite_model'));

            try{
                /*** @var Invite $invite */
                $invite = $invite_model->where('code', $invite_code)->firstOrFail();
            } catch (ModelNotFoundException $exception) {
                throw new InvalidInviteCodeException('Your invite code is invalid');
            }

            if ($invite->hasRestrictedUsage()) {
                if (! Auth::check()) {
                    throw new UserLoggedOutException('You must be logged in to use this invite code');
                }
                if ($invite->usageRestrictedToEmail(Auth::user()->{config('watchdog.user.email_column')})) {
                    Watchdog::redeem($invite_code);
                    return $next($request);
                } else {
                    throw new InviteWithRestrictedUsageException('This invite code is not for you.');
                }
            }
        } else {
            throw new RouteProtectedByInviteCodeException('This route is accessible only by using invite codes');
        }
        return $next($request);
    }
}
