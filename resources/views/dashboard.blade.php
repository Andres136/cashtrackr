@extends('layouts.auth')

@section('title')
    Administra tus Presupuestos
@endsection

@section('auth-contents')
    @if (session('success'))
       <p class="my-10 text-center border border-green-400 bg-green-100 text-green-700 py-3">{{ session('success') }}</p>
    @endif
@endsection
