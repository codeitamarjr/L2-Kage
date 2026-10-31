<?php

namespace App\Http\Controllers;

use App\Models\Game\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Player sign-in / sign-out with the game account (player guard).
 */
class AccountSessionController extends Controller
{
    private const MAX_FAILURES_PER_ACCOUNT = 5;

    private const MAX_FAILURES_PER_IP = 10;

    private const LOCKOUT_SECONDS = 15 * 60;

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:45', 'regex:/^[A-Za-z0-9_-]+$/'],
            'password' => ['required', 'string', 'max:64'],
        ], [
            'login.*' => __('Enter your game account name.'),
            'password.*' => __('Enter your password.'),
        ]);

        $accountKey = 'player-login:account:'.Str::lower($credentials['login']);
        $ipKey = 'player-login:ip:'.$request->ip();

        foreach ([[$accountKey, self::MAX_FAILURES_PER_ACCOUNT], [$ipKey, self::MAX_FAILURES_PER_IP]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

                throw ValidationException::withMessages([
                    'login' => trans_choice('Too many failed sign-ins. Try again in :count minute.|Too many failed sign-ins. Try again in :count minutes.', $minutes),
                ]);
            }
        }

        if (! Auth::guard('player')->attempt($credentials)) {
            RateLimiter::hit($accountKey, self::LOCKOUT_SECONDS);
            RateLimiter::hit($ipKey, self::LOCKOUT_SECONDS);

            // Same message for unknown accounts and wrong passwords, so account names can't be probed.
            throw ValidationException::withMessages(['login' => __('Wrong account name or password.')]);
        }

        /** @var Account $account */
        $account = Auth::guard('player')->user();

        if ($account->isBanned()) {
            Auth::guard('player')->logout();

            throw ValidationException::withMessages(['login' => __('This account is banned.')]);
        }

        RateLimiter::clear($accountKey);
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('player')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('account');
    }
}
