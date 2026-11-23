<meta name="theme-color" content="#0D47A1">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="SMART-DTR">
<meta name="application-name" content="SMART-DTR">
<meta name="description" content="Smart Daily Time Record for DepEd Senior High School — INHS.">
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

    document.addEventListener('alpine:init', function () {
        Alpine.data('installAppButton', function () {
            return {
                visible: false,
                hint: '',
                deferred: null,
                init() {
                    const installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                    if (installed) {
                        return;
                    }

                    this.visible = true;
                    window.addEventListener('beforeinstallprompt', (event) => {
                        event.preventDefault();
                        this.deferred = event;
                        this.visible = true;
                    });
                    window.addEventListener('appinstalled', () => {
                        this.visible = false;
                        this.hint = '';
                        this.deferred = null;
                    });
                },
                async install() {
                    this.hint = '';
                    if (this.deferred) {
                        this.deferred.prompt();
                        const choice = await this.deferred.userChoice;
                        this.deferred = null;
                        if (choice.outcome === 'accepted') {
                            this.visible = false;
                        }
                        return;
                    }

                    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
                    this.hint = ios
                        ? 'Tap Share, then Add to Home Screen.'
                        : 'Open this site in Chrome on that device, then tap download again.';
                },
            };
        });
    });
</script>
