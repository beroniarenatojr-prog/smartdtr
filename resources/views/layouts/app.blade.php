<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SMART-DTR')</title>
    <x-pwa-head />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <x-styles />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body
    class="@yield('body_class', 'app-body')"
    x-data="{
        toast: {{ \Illuminate\Support\Js::from(session('success') ?? session('error') ?? $errors->first() ?? '') }},
        toastType: {{ \Illuminate\Support\Js::from(session('success') ? 'success' : ((session('error') || $errors->any()) ? 'error' : '')) }},
        toastExtra: {{ \Illuminate\Support\Js::from($errors->count() > 1 ? array_slice($errors->all(), 1) : []) }},
        confirmOpen: false,
        confirmMessage: '',
        confirmType: 'primary',
        confirmButton: 'Confirm',
        pendingForm: null,
        sidebarOpen: false,
        cancelConfirm() {
            this.confirmOpen = false;
            this.pendingForm = null;
        },
        submitConfirmed() {
            const form = this.pendingForm;
            this.confirmOpen = false;
            this.pendingForm = null;
            if (form) {
                form.dataset.confirmed = 'true';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        }
    }"
    x-init="
        if (toast && toastType === 'success') setTimeout(() => toast = '', 4000);
        document.addEventListener('submit', (e) => {
            const form = e.target.closest('form[data-confirm]');
            if (!form) return;
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            pendingForm = form;
            confirmMessage = form.dataset.confirm || 'Are you sure you want to continue?';
            confirmType = form.dataset.confirmType || 'primary';
            confirmButton = form.dataset.confirmButton || 'Confirm';
            confirmOpen = true;
        }, true);
    "
    @keydown.escape.window="sidebarOpen = false"
>
    @hasSection('sidebar')
        <div class="app-shell">
            @yield('sidebar')

            <div class="app-main">
                @hasSection('topbar')
                    @yield('topbar')
                @endif

                <main class="@yield('main_class', 'page-shell')">
                    @if ($errors->any() || session('error'))
                        <div class="alert alert-error mb-6">
                            <span class="text-lg shrink-0">⚠️</span>
                            <ul class="list-disc list-inside space-y-0.5">
                                @if (session('error'))
                                    <li>{{ session('error') }}</li>
                                @endif
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>

                @if(!View::hasSection('hide_footer'))
                    <footer class="app-footer">
                        <div class="app-footer-inner">
                            &copy; {{ date('Y') }} Isabela National High School. All rights reserved.
                        </div>
                    </footer>
                @endif
            </div>

            <div class="sidebar-overlay" :class="{ 'sidebar-overlay-visible': sidebarOpen }" @click="sidebarOpen = false" x-cloak></div>
        </div>
    @else
        <main class="@yield('main_class', 'page-shell')">
            @if ($errors->any() || session('error'))
                <div class="alert alert-error mb-6">
                    <span class="text-lg shrink-0">⚠️</span>
                    <ul class="list-disc list-inside space-y-0.5">
                        @if (session('error'))
                            <li>{{ session('error') }}</li>
                        @endif
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        @hasSection('footer')
            @yield('footer')
        @elseif(!View::hasSection('hide_footer'))
            <footer class="app-footer">
                <div class="app-footer-inner">
                    &copy; {{ date('Y') }} Isabela National High School. All rights reserved.
                </div>
            </footer>
        @endif
    @endif

    <x-confirm-modal />

    <x-system-alert />

    @stack('scripts')
</body>
</html>
