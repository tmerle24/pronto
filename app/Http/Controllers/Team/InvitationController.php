<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Joining the team with a personal link from WhatsApp. */
class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = Invitation::findOpen($token);

        return Inertia::render('Team/Invitation', [
            'token' => $token,
            'name' => $invitation?->name,
            'valid' => $invitation !== null,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::findOpen($token);
        if ($invitation === null) {
            return back()->withErrors(['email' => 'Der Link ist abgelaufen oder schon benutzt. Bitte frag nach einem neuen.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], ['email.unique' => 'Mit dieser E-Mail gibt es schon einen Zugang. Melde dich einfach an.']);

        $user = DB::transaction(function () use ($invitation, $data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $invitation->role,
            ]);
            $invitation->update(['accepted_at' => now(), 'accepted_user_id' => $user->id]);

            return $user;
        });

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('team.shifts');
    }
}
