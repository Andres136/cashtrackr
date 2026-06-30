<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignInRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function index(): View
    {
        return view('auth.login');
    }

    public function store(SignInRequest $request)
    {
        $data = $request->validated();

        if (! Auth::attempt($data,true)) {
             return back()->with('error', 'Credenciales incorrectas.');
        }

        return redirect()->route('dashboard');
    }
  
}
