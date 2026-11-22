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
    (function () {
        var deferred = null;
        var waitingResolvers = [];

        function isStandalone() {
            return window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
        }

        function isIos() {
            return /iphone|ipad|ipod/i.test(navigator.userAgent)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        }

        function openInstallModal() {
            var modal = document.getElementById('pwa-install-modal');
            if (! modal) return;
            var title = document.getElementById('pwa-install-title');
            var body = document.getElementById('pwa-install-body');
            if (isStandalone()) {
                title.textContent = 'Already installed';
                body.innerHTML = '<p>SMART-DTR is already running as an installed app on this device.</p>';
            } else if (isIos()) {
                title.textContent = 'Install on iPhone / iPad';
                body.innerHTML = '<ol class="pwa-install-steps"><li>Tap the <strong>Share</strong> button in Safari.</li><li>Scroll and tap <strong>Add to Home Screen</strong>.</li><li>Tap <strong>Add</strong>.</li></ol>';
            } else {
                title.textContent = 'Install SMART-DTR';
                body.innerHTML = '<ol class="pwa-install-steps"><li>Look at the <strong>address bar</strong> (top of Chrome).</li><li>Click the <strong>Install</strong> icon — a computer screen with a down arrow.</li><li>Or open the browser menu (⋮) → <strong>Install app</strong> / <strong>Add to Home screen</strong>.</li></ol>';
            }
            modal.hidden = false;
            document.body.classList.add('overflow-hidden');
        }

        function closeInstallModal() {
            var modal = document.getElementById('pwa-install-modal');
            if (! modal) return;
            modal.hidden = true;
            document.body.classList.remove('overflow-hidden');
        }

        function promptInstall() {
            if (! deferred) return Promise.resolve(false);
            var event = deferred;
            deferred = null;
            event.prompt();
            return event.userChoice.then(function () {
                return true;
            }).catch(function () {
                return false;
            });
        }

        function waitForPrompt(ms) {
            if (deferred) return Promise.resolve(true);
            return new Promise(function (resolve) {
                var settled = false;
                var timer = setTimeout(function () {
                    if (settled) return;
                    settled = true;
                    resolve(false);
                }, ms);
                waitingResolvers.push(function () {
                    if (settled) return;
                    settled = true;
                    clearTimeout(timer);
                    resolve(true);
                });
            });
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register(@json(asset('sw.js')), { scope: @json(url('/')) }).catch(function () {});
            });
        }

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferred = event;
            waitingResolvers.splice(0).forEach(function (fn) { fn(); });
        });

        window.addEventListener('appinstalled', function () {
            deferred = null;
            closeInstallModal();
        });

        document.addEventListener('click', function (event) {
            if (event.target.closest('[data-pwa-install-close]')) {
                event.preventDefault();
                closeInstallModal();
                return;
            }

            var btn = event.target.closest('[data-pwa-install]');
            if (! btn) return;
            event.preventDefault();

            if (isStandalone()) {
                openInstallModal();
                return;
            }

            if (deferred) {
                promptInstall();
                return;
            }

            // Wait briefly — Chrome often fires the install event right after the SW registers.
            btn.disabled = true;
            var original = btn.textContent;
            if (btn.tagName === 'BUTTON' && btn.classList.contains('btn-landing-primary')) {
                btn.textContent = 'Preparing…';
            }

            waitForPrompt(2500).then(function (ready) {
                btn.disabled = false;
                if (btn.tagName === 'BUTTON' && btn.classList.contains('btn-landing-primary')) {
                    btn.textContent = original || 'Install App';
                }
                if (ready && deferred) {
                    return promptInstall();
                }
                openInstallModal();
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeInstallModal();
        });
    })();
</script>
