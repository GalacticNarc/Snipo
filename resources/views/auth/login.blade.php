@extends('layouts.app')

@section('title', 'Log in · '.config('app.name'))

@include('auth._styles')

@section('content')
<div class="auth card">
    <h1>Log in</h1>
    <p class="muted">Welcome back to {{ config('app.name') }}.</p>

    <form method="post" action="{{ url('/login') }}">
        @csrf
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="check"><input type="checkbox" name="remember" value="1" checked> Keep me logged in</label>
        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif
        <button class="btn btn-primary btn-lg">Log in</button>
    </form>

    <p class="muted switch">No account yet? <a href="{{ route('register') }}">Sign up</a></p>
</div>
@endsection
