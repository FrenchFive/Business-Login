/*
 * Applies the light/dark theme before the page paints (loaded as a classic,
 * render-blocking script in <head>) and wires every [data-theme-toggle] button.
 * The choice is remembered in localStorage; otherwise the OS setting is followed.
 */
(() => {
    const KEY = 'keystone-theme';
    const root = document.documentElement;
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

    const stored = () => {
        try {
            return localStorage.getItem(KEY);
        } catch {
            return null;
        }
    };

    const apply = () => {
        const choice = stored();
        root.dataset.theme = choice === 'light' || choice === 'dark' ? choice : systemDark.matches ? 'dark' : 'light';
    };

    apply();
    systemDark.addEventListener('change', apply);

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-theme-toggle]')) {
            return;
        }

        try {
            localStorage.setItem(KEY, root.dataset.theme === 'dark' ? 'light' : 'dark');
        } catch {
            // Storage disabled: toggle for this page view only.
            root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            return;
        }

        apply();
    });
})();
