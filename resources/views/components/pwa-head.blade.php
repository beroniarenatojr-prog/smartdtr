<meta name="theme-color" content="#0D47A1">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="SMART-DTR">
<meta name="application-name" content="SMART-DTR">
<meta name="description" content="Smart Daily Time Record for DepEd Senior High School — INHS.">
{{-- Prefer /images/pwa/* (Hostinger serves that folder); /pwa/* Laravel routes as backup. --}}
<link rel="manifest" href="{{ asset('images/pwa/manifest.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('images/pwa/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/pwa/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/pwa/icon-512.png') }}">
<script>
    (function () {
        window.__smartdtrDeferredInstall = null;

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            window.__smartdtrDeferredInstall = event;
            window.dispatchEvent(new CustomEvent('smartdtr:install-ready'));
        });

        window.addEventListener('appinstalled', function () {
            window.__smartdtrDeferredInstall = null;
            window.dispatchEvent(new CustomEvent('smartdtr:installed'));
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                var swUrl = @json(asset('images/pwa/sw.js'));
                navigator.serviceWorker.register(swUrl, { scope: '/' }).catch(function () {
                    navigator.serviceWorker.register(@json(url('/pwa/sw')), { scope: '/' }).catch(function () {});
                });
            });
        }

        document.addEventListener('alpine:init', function () {
            Alpine.data('installAppButton', function () {
                return {
                    visible: true,
                    busy: false,
                    hint: '',
                    init() {
                        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                            this.visible = false;
                            return;
                        }

                        window.addEventListener('smartdtr:install-ready', () => {
                            this.hint = '';
                            this.visible = true;
                        });
                        window.addEventListener('smartdtr:installed', () => {
                            this.visible = false;
                            this.hint = '';
                        });
                    },
                    async install() {
                        this.hint = '';
                        this.busy = true;

                        try {
                            let event = window.__smartdtrDeferredInstall;
                            if (! event) {
                                await new Promise((resolve) => setTimeout(resolve, 2000));
                                event = window.__smartdtrDeferredInstall;
                            }

                            if (event) {
                                window.__smartdtrDeferredInstall = null;
                                event.prompt();
                                const choice = await event.userChoice;
                                if (choice.outcome === 'accepted') {
                                    this.visible = false;
                                }
                                return;
                            }

                            const ios = /iphone|ipad|ipod/i.test(navigator.userAgent)
                                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

                            if (ios) {
                                this.hint = 'On iPhone/iPad: tap Share, then Add to Home Screen.';
                            } else {
                                this.hint = 'Use the Install icon in the Chrome address bar, or menu ⋮ → Install app.';
                            }
                        } finally {
                            this.busy = false;
                        }
                    },
                };
            });
        });
    })();
</script>
