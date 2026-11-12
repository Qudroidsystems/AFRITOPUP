<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Customer area pages that aren't part of buying: profile & security. */
class AccountController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        if ($user->isStaff()) return redirect()->route('profile.settings', $user->id);

        return view('account.profile', [
            'pagetitle' => 'Profile & security',
            'user'      => $user,
            'hasPin'    => $user->hasTransactionPin(),
        ]);
    }
}
