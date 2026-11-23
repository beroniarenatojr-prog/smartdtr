@extends('layouts.app')

@section('body_class', '')
@section('main_class', '')
@section('hide_footer', true)
@section('title', 'Forgot Password - SMARTDTR')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-logo-wrap">
            <div class="auth-logo-badge"><x-logo size="xl" class="auth-logo-img" /></div>
            <div class="auth-logo">Reset Password</div>
            <p class="auth-subtitle">{{ $guard === 'admin' ? 'Admin / HR account' : 'Staff account' }}</p>
        </div>

        <form method="POST" action="{{ route('password.email', $guard) }}" class="space-y-4">
            @csrf
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="form-input" placeholder="you@inhs.edu.ph">
            </div>
            <button type="submit" class="btn-primary w-full py-3">Send Reset Link</button>
        </form>

        <p class="mt-5 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="auth-link">← Back to Sign In</a>
        </p>
    </div>
</div>
@endsection
