<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailCodes;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

/** Web: confirm your e-mail with the 6-digit code or the link from the e-mail. */
class EmailVerificationController extends Controller
{
    public function __construct(protected EmailCodes $codes) {}

    public function notice(Request $request)
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) return redirect()->intended(route('dashboard'));

        return view('auth.verify', ['email' => $user->email, 'wait' => $this->codes->wait($user, EmailCodes::VERIFY)]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate(['code' => 'required|string|max:12']);
        $user = $request->user();
        if ($user->hasVerifiedEmail()) return redirect()->intended(route('dashboard'));

        if (!$this->codes->check($user, EmailCodes::VERIFY, $request->input('code'), $error)) {
            return back()->withErrors(['code' => EmailCodes::message($error, EmailCodes::VERIFY)]);
        }

        $this->markVerified($user);

        return redirect()->intended(route('dashboard'))->with('success', 'Your e-mail is confirmed. Welcome aboard!');
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) return redirect()->intended(route('dashboard'));

        if ($wait = $this->codes->wait($user, EmailCodes::VERIFY)) {
            return back()->withErrors(['code' => "Please wait {$wait} seconds before asking for another code."]);
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'A new code is on its way to ' . $user->email . '.');
    }

    /** The button in the e-mail (signed, expires after a day). */
    public function verifyLink(Request $request, int $id, string $hash)
    {
        $user = User::find($id);
        if (!$user || !hash_equals(sha1($user->getEmailForVerification()), $hash)) abort(403, 'This link is not valid.');

        if (!$user->hasVerifiedEmail()) $this->markVerified($user);

        if ($request->user()?->is($user)) {
            return redirect()->intended(route('dashboard'))->with('success', 'Your e-mail is confirmed. Welcome aboard!');
        }

        return redirect()->route('login')->with('status', 'Your e-mail is confirmed — please log in.');
    }

    protected function markVerified(User $user): void
    {
        if ($user->markEmailAsVerified()) event(new Verified($user));
    }
}
