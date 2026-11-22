@extends('layouts.app')

@section('body_class', '')
@section('main_class', '')
@section('hide_footer')
@section('title', 'Sign In - SMARTDTR')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-logo-wrap">
            <div class="auth-logo-badge"><x-logo size="xl" class="auth-logo-img" /></div>
            <div class="auth-logo">SMART-DTR</div>
            <p class="auth-subtitle">Sign in with your account email or admin username</p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf
            <div class="form-group">
                <label class="form-label">Email or username</label>
                <input type="text" name="username" value="{{ old('username') }}" required autofocus
                    autocomplete="username" class="form-input @error('username') form-input-error @enderror"
                    placeholder="you@gmail.com or admin username">
                @error('username')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" required autocomplete="current-password" class="form-input" placeholder="Your password">
            </div>
            <button type="submit" class="btn-primary w-full py-3">Sign In</button>
        </form>

        <div class="auth-divider">new here?</div>

        <div class="text-center text-sm text-slate-500 space-y-2">
            <p><a href="{{ route('staff.register') }}" class="auth-link">Sign Up →</a></p>
            <p><a href="{{ route('home') }}" class="auth-link">← Back to home</a></p>
        </div>
    </div>
</div>
@endsection
