<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignupRequest;

class RegisterController extends Controller
{
    public function index()
    {
        return view('auth.register');
    }
    
     public function store(SignupRequest $request)
{
    $data = $request->validated();

   
    dd($data);
}
   
}
