/* Match SelfBio's dark theme by default; allow an explicit light preference. */
(function () {
    var KEY = 'killdoku-admin-theme';
    var root = document.documentElement;

    function read() {
        try {
            var value = window.localStorage.getItem(KEY);
            return value === 'dark' || value === 'light' ? value : null;
        } catch (e) {
            return null;
        }
    }

    function current() {
        return read() || 'dark';
    }

    function apply(theme) {
        root.setAttribute('data-admin-theme', theme);
        root.setAttribute('data-bs-theme', theme);
        var button = document.querySelector('.admin-theme-toggle');
        if (button) button.textContent = theme === 'dark' ? '☀ Светлая тема' : '☾ Тёмная тема';
    }

    function ensureButton() {
        if (document.querySelector('.admin-theme-toggle') || !document.body) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'admin-theme-toggle';
        button.addEventListener('click', function () {
            var next = current() === 'dark' ? 'light' : 'dark';
            try {
                window.localStorage.setItem(KEY, next);
            } catch (e) {
                /* the choice lasts until the page is closed */
            }
            apply(next);
        });
        document.body.appendChild(button);
        apply(current());
    }

    apply(current());
    document.addEventListener('DOMContentLoaded', ensureButton);
    document.addEventListener('turbo:load', ensureButton);
    document.addEventListener('turbo:render', ensureButton);
})();
