@extends('layouts.app')

@section('body_class', '')
@section('main_class', '')
@section('hide_footer')
@section('title', 'Sign Up - SMARTDTR')

@section('content')
<div class="auth-shell py-8">
    <img src="{{ asset('images/inhs-logo.png') }}" alt="" class="auth-bg-logo" aria-hidden="true">
    <div class="auth-card max-w-lg">
        <div class="auth-logo-wrap">
            <div class="auth-logo-badge"><x-logo size="xl" class="auth-logo-img" /></div>
            <div class="auth-logo">SMART-DTR</div>
            <p class="auth-subtitle">Create your employee account and register your face. HR will approve the registration before you can sign in.</p>
        </div>

        <form method="POST" action="{{ route('staff.register') }}" enctype="multipart/form-data" class="space-y-4" data-face-form data-face-redirect="{{ route('login') }}">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                        autocomplete="family-name" inputmode="text" maxlength="100"
                        pattern="[A-ZÑ][A-Za-zÑñ]*(?:[ .'\-][A-ZÑ][A-Za-zÑñ]*)*"
                        title="Must start with a capital letter. Letters only."
                        data-filter="name"
                        class="form-input @error('last_name') form-input-error @enderror" placeholder="Dela Cruz">
                    @error('last_name')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required
                        autocomplete="given-name" inputmode="text" maxlength="100"
                        pattern="[A-ZÑ][A-Za-zÑñ]*(?:[ .'\-][A-ZÑ][A-Za-zÑñ]*)*"
                        title="Must start with a capital letter. Letters only."
                        data-filter="name"
                        class="form-input @error('first_name') form-input-error @enderror" placeholder="Juan">
                    @error('first_name')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name') }}" required
                        autocomplete="additional-name" inputmode="text" maxlength="100"
                        pattern="[A-ZÑ][A-Za-zÑñ]*(?:[ .'\-][A-ZÑ][A-Za-zÑñ]*)*"
                        title="Must start with a capital letter. Letters only."
                        data-filter="name"
                        class="form-input @error('middle_name') form-input-error @enderror" placeholder="Perez">
                    @error('middle_name')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Suffix</label>
                    <select name="suffix" class="form-select">
                        <option value="">None</option>
                        @foreach (['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'] as $suffix)
                            <option value="{{ $suffix }}" @selected(old('suffix') === $suffix)>{{ $suffix }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email / Gmail Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    autocomplete="username" maxlength="255" inputmode="email"
                    class="form-input @error('email') form-input-error @enderror" placeholder="you@gmail.com">
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @else
                    <p class="text-xs text-slate-400 mt-1">You will use this email to sign in and receive approval notifications.</p>
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Mobile Number</label>
                <input type="tel" name="phone" value="{{ old('phone') }}"
                    autocomplete="tel" inputmode="numeric" maxlength="11"
                    pattern="09[0-9]{9}"
                    title="11 digits starting with 09. Numbers only."
                    data-filter="phone"
                    class="form-input @error('phone') form-input-error @enderror" placeholder="09XXXXXXXXX">
                @error('phone')
                    <p class="form-error">{{ $message }}</p>
                @else
                    <p class="text-xs text-slate-400 mt-1">Optional — 11 digits starting with 09. Numbers only.</p>
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Department</label>
                <input type="text" name="department" value="{{ old('department') }}"
                    maxlength="100"
                    pattern="([A-Za-zÑñ]+(?:[ .&\/\-][A-Za-zÑñ]+)*)?"
                    title="Optional. Letters only if filled."
                    data-filter="department"
                    placeholder="e.g. Science" class="form-input @error('department') form-input-error @enderror">
                @error('department')
                    <p class="form-error">{{ $message }}</p>
                @else
                    <p class="text-xs text-slate-400 mt-1">Optional — leave blank if not applicable.</p>
                @enderror
            </div>
            <x-password-fields />

            <div class="pt-2 border-t border-slate-200">
                <h2 class="text-sm font-semibold text-slate-800">Facial recognition</h2>
                <p class="text-xs text-slate-500 mt-1 mb-3">Blink three times. These photos are saved with your registration and used by the attendance camera after HR approves your account.</p>
                <x-face-capture employee-name="your face" />
            </div>

            <button type="submit" data-face-save class="btn-primary w-full py-3 mt-2 disabled:opacity-60 disabled:cursor-not-allowed" disabled
                data-waiting-label="Capture your face to register"
                data-ready-label="Register Account"
                data-saving-label="Creating account…">
                Capture your face to register
            </button>
        </form>

        <p class="mt-5 text-center text-sm text-slate-500">
            Already registered? <a href="{{ route('login') }}" class="auth-link">Sign In</a>
        </p>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const filters = {
        name: /[^\p{L} .'\-]/gu,
        phone: /\D/g,
        department: /[^\p{L} .&/\-]/gu,
    };

    document.querySelectorAll('[data-filter]').forEach((input) => {
        const type = input.getAttribute('data-filter');
        const strip = filters[type];
        if (!strip) {
            return;
        }
        input.addEventListener('input', () => {
            let value = input.value.replace(strip, '');
            if (type === 'name') {
                value = value.replace(/(^|[ .'\-])(\p{L})(\p{L}*)/gu, (_, sep, first, rest) => sep + first.toUpperCase() + rest.toLowerCase());
            }
            if (type === 'phone') {
                value = value.slice(0, 11);
            }
            input.value = value;
        });
        input.addEventListener('paste', (event) => {
            event.preventDefault();
            const text = (event.clipboardData || window.clipboardData).getData('text');
            input.value = text;
            input.dispatchEvent(new Event('input'));
        });
    });
})();
</script>
@endpush
@endsection
