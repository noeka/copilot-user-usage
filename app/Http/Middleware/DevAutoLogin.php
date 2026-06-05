<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DevAutoLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')
            && ! Auth::check()
            && ! $request->session()->get('logged_out')
            && ($login = config('copilot.dev_login'))) {
            $user = User::where('github_login', $login)->first();

            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
