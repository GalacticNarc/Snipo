@extends('layouts.app')

@section('title', 'Sign up · '.config('app.name'))

@include('auth._styles')

@section('content')
<div class="auth card">
    <h1>Create your account</h1>
    <p class="muted">Record your screen and share it with a link.</p>

    <form method="post" action="{{ url('/register') }}">
        @csrf
        <label>Name
            <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="100">
        </label>
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </label>
        <label>Password <span class="muted">(at least 8 characters)</span>
            <input type="password" name="password" required autocomplete="new-password" minlength="8">
        </label>
        <label>Confirm password
            <input type="password" name="password_confirmation" required autocomplete="new-password" minlength="8">
        </label>
        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif
        <button class="btn btn-primary btn-lg">Sign up</button>
    </form>

    <p class="muted switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
</div>
@endsection
