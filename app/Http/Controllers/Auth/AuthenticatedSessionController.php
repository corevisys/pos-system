<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Activity trail: record the successful sign-in. Written inline (rather
        // than via an event listener) to match this codebase's convention of
        // populating audit stamps ad hoc in the controller that owns the action.
        $this->recordActivity($request, 'login');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Capture the actor BEFORE logout() (the user is still resolvable here;
        // afterwards auth()->id() is null and the row would lose its actor).
        $this->recordActivity($request, 'logout');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Persist a single activity_logs row for the current authenticated actor.
     *
     * store_id is taken from the acting user's fixed store_id (this app has no
     * store-switching feature). It is nullable so a future store-less Super
     * Admin login still records successfully.
     */
    private function recordActivity(Request $request, string $action): void
    {
        $user = $request->user();

        // Audit logging must never be the reason authentication fails: if the
        // trail cannot be written the user should still log in/out normally.
        try {
            ActivityLog::create([
                'store_id' => $user?->store_id,
                'user_id' => $user?->id,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
