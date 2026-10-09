<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Team/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // tablets stay logged in for the whole market day and beyond
        if (! Auth::attempt($credentials, remember: true)) {
            return back()->withErrors(['email' => 'E-Mail oder Passwort stimmt nicht.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('team.queue'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('team.login');
    }
}
