<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Github\CopilotMetricsClient;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GithubLoginController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('github')
            ->scopes(['read:org'])
            ->redirect();
    }

    public function callback(CopilotMetricsClient $client)
    {
        $socialUser = Socialite::driver('github')->user();

        // Check org membership using the user's own token
        $role = $client->orgMembership($socialUser->token);

        if ($role === null) {
            return redirect()->route('auth.github')
                ->withErrors(['github' => 'You must be an active member of the organisation to access this dashboard.']);
        }

        $adminLogins = config('copilot.admin_logins', []);
        $isAdmin = $role === 'admin' || in_array($socialUser->getNickname(), $adminLogins, true);

        $user = User::updateOrCreate(
            ['github_id' => (string) $socialUser->getId()],
            [
                'name'         => $socialUser->getName() ?? $socialUser->getNickname(),
                'email'        => $socialUser->getEmail() ?? $socialUser->getNickname() . '@github.local',
                'github_login' => $socialUser->getNickname(),
                'avatar_url'   => $socialUser->getAvatar(),
                'is_admin'     => $isAdmin,
            ]
        );

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }

    public function logout()
    {
        Auth::logout();

        return redirect()->route('auth.github');
    }
}
