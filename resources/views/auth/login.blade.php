@extends('layouts.auth')

@section('title')
    Iniciar Sesión
@endsection

@section('auth-contents')

<form method="POST" action="{{ route('login.store') }}" class="mt-14 space-y-5" novalidate>
    @csrf

    <div class="flex flex-col gap-2">
        <label class="font-bold text-2xl" for="email">Email</label>

        <input 
            id="email" 
            type="email" 
            placeholder="Email de Registro"
            class="w-full border border-gray-300 p-3 rounded-lg" 
            name="email" 
            value="{{ old('email') }}"
            tabindex="1" 
            value={{  old('email') }}
        />
    </div>
    @error('email')
        <p class="text-red-600">{{ $message }}</p>
    @enderror

    <div class="flex flex-col gap-2">
        <div class="flex  items-center justify-between">
            <label class="font-bold text-2xl">Password</label>
            <a href="#" class="text-indigo-950" tabindex="3">¿Olvidaste tu Contraseña?</a>
        </div>
        <input 
            type="password" 
            placeholder="Password de Registro" 
            class="w-full border border-gray-300 p-3 rounded-lg"
            name="password" 
            tabindex="2" 
        />
    </div>
    @error('password')
        <p class="text-red-600">{{ $message }}</p>
    @enderror

    <input 
        type="submit" 
        value='Iniciar Sesión'
        class="bg-purple-950 hover:bg-purple-800 w-full p-3 rounded-lg text-white font-bold  text-xl cursor-pointer" 
    />
</form>
@endsection
