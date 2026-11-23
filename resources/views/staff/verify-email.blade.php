@extends('layouts.app')

@section('body_class', '')
@section('main_class', '')
@section('hide_footer')
@section('title', 'Verify Email - SMARTDTR')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-logo-wrap">
            <div class="auth-logo-badge"><x-logo size="xl" class="auth-logo-img" /></div>
            <div class="auth-logo">SMART-DTR</div>
            <p class="auth-subtitle">Enter the 6-digit code we sent to your email. HR only reviews accounts after verification.</p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('staff.verify-email') }}" class="space-y-5">
            @csrf
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus
                    autocomplete="email" class="form-input @error('email') form-input-error @enderror"
                    placeholder="you@gmail.com">
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Verification code</label>
                <input type="text" name="code" value="{{ old('code') }}" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                    autocomplete="one-time-code" class="form-input tracking-widest text-center text-lg @error('code') form-input-error @enderror"
                    placeholder="000000">
                @error('code')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full py-3">Verify Email</button>
        </form>

        <form method="POST" action="{{ route('staff.verify-email.resend') }}" class="mt-4">
            @csrf
            <input type="hidden" name="email" value="{{ old('email', $email) }}">
            <button type="submit" class="btn-secondary w-full py-2.5 text-sm">Resend code</button>
        </form>

        <div class="text-center text-sm text-slate-500 mt-6 space-y-2">
            <p><a href="{{ route('login') }}" class="auth-link">← Back to Sign In</a></p>
        </div>
    </div>
</div>
@endsection
