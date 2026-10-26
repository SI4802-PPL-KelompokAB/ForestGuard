<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterCitizen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.guest', ['mode' => 'register']);
    }

    public function store(RegisterRequest $request, RegisterCitizen $registerCitizen): RedirectResponse
    {
        $registerCitizen->handle($request);

        return redirect()->route('citizen.home');
    }
}
