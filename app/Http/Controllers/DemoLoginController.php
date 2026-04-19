<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('demo.enabled'), 404);

        $user = User::where('email', config('demo.email'))->first();

        abort_if(is_null($user), 404);

        Auth::login($user, remember: false);

        $request->session()->regenerate();

        return redirect()->intended(route('books.shelf'));
    }
}
