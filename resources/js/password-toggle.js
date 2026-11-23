// Adds a show/hide eye button inside every password field.

const EYE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
const EYE_OFF = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';

function enhance(input) {
    if (input.dataset.passwordToggle) {
        return;
    }
    input.dataset.passwordToggle = '1';

    const wrap = document.createElement('div');
    wrap.className = 'password-toggle-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    input.classList.add('password-toggle-input');

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle-btn';

    const render = () => {
        const visible = input.type === 'text';
        button.innerHTML = visible ? EYE_OFF : EYE;
        button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
        button.title = visible ? 'Hide password' : 'Show password';
    };

    button.addEventListener('click', () => {
        input.type = input.type === 'password' ? 'text' : 'password';
        render();
        input.focus();
    });

    render();
    wrap.appendChild(button);
}

export function initPasswordToggles(root = document) {
    root.querySelectorAll('input[type="password"]').forEach(enhance);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initPasswordToggles());
} else {
    initPasswordToggles();
}
