<meta name="theme-color" content="#0D47A1">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="SMART-DTR">
<meta name="application-name" content="SMART-DTR">
<meta name="description" content="Smart Daily Time Record for DepEd Senior High School — INHS. Install on phone, tablet, or computer.">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('images/pwa/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/pwa/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/pwa/icon-512.png') }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(@json(asset('sw.js')), { scope: @json(url('/')) }).catch(function () {});
        });
    }
    window.__smartdtrDeferredInstall = null;
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.__smartdtrDeferredInstall = event;
        document.querySelectorAll('[data-pwa-install]').forEach(function (el) {
            el.hidden = false;
        });
    });
    window.addEventListener('appinstalled', function () {
        window.__smartdtrDeferredInstall = null;
        document.querySelectorAll('[data-pwa-install]').forEach(function (el) {
            el.hidden = true;
        });
    });
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-pwa-install]');
        if (!btn || !window.__smartdtrDeferredInstall) return;
        event.preventDefault();
        window.__smartdtrDeferredInstall.prompt();
        window.__smartdtrDeferredInstall.userChoice.finally(function () {
            window.__smartdtrDeferredInstall = null;
            document.querySelectorAll('[data-pwa-install]').forEach(function (el) {
                el.hidden = true;
            });
        });
    });
</script>
