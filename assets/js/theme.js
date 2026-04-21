/**
 * Theme Toggle - Light/Dark Mode
 * Persists user preference to localStorage.
 * Must be loaded BEFORE </body> for instant theme application.
 */
(function () {
  const STORAGE_KEY = 'pgim-theme';

  // Apply saved theme instantly to avoid flash of wrong theme
  const savedTheme = localStorage.getItem(STORAGE_KEY) || 'light';
  document.documentElement.setAttribute('data-theme', savedTheme);

  document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('themeToggle');
    if (!toggleBtn) return;

    const icon = toggleBtn.querySelector('.theme-toggle-knob i');

    function updateIcon(theme) {
      if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
      }
    }

    // Set initial icon state
    updateIcon(savedTheme);

    toggleBtn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';

      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem(STORAGE_KEY, next);
      updateIcon(next);
    });
  });
})();
