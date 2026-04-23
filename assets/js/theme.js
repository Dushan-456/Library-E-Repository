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

  // Logout Confirmation Modal Logic
  document.addEventListener('DOMContentLoaded', () => {
    const logoutLinks = document.querySelectorAll('a[href="logout.php"], a.logout-link');
    if (logoutLinks.length === 0) return;

    // Create the modal HTML
    const modalHtml = `
      <div class="confirm-modal-overlay" id="logoutConfirmModal">
        <div class="confirm-modal-box">
          <h3><i class="fas fa-sign-out-alt"></i> Confirm Logout</h3>
          <p>Are you sure you want to log out of your account?</p>
          <div class="confirm-modal-actions">
            <button class="btn-confirm-cancel" id="cancelLogoutBtn">Cancel</button>
            <button class="btn-confirm-logout" id="confirmLogoutBtn">Yes, Logout</button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = document.getElementById('logoutConfirmModal');
    const cancelBtn = document.getElementById('cancelLogoutBtn');
    const confirmBtn = document.getElementById('confirmLogoutBtn');

    logoutLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        modal.classList.add('active');
      });
    });

    cancelBtn.addEventListener('click', () => {
      modal.classList.remove('active');
    });

    confirmBtn.addEventListener('click', () => {
      window.location.href = 'logout.php';
    });

    // Close if clicked outside the box
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.remove('active');
      }
    });
  });
})();
