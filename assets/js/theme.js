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

  // SEB Controls Logic
  document.addEventListener('DOMContentLoaded', () => {
    const sebToggleBtn = document.getElementById('sebToggleBtn');
    if (!sebToggleBtn) return;

    // Create the modal HTML
    const modalHtml = `
      <div class="confirm-modal-overlay" id="sebConfirmModal">
        <div class="confirm-modal-box">
          <h3><i class="fas fa-shield-alt"></i> Confirm SEB Status</h3>
          <p id="sebConfirmMsg" style="margin: 1rem 0; color: var(--text-muted);"></p>
          <div class="confirm-modal-actions" style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
            <button class="btn-confirm-cancel" id="cancelSebBtn" style="padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-hover); color: var(--text-main); cursor: pointer;">Cancel</button>
            <button class="btn-confirm-logout" id="confirmSebBtn" style="padding: 0.5rem 1rem; border-radius: 6px; border: none; background: var(--primary); color: white; font-weight: bold; cursor: pointer;">Yes, Confirm</button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = document.getElementById('sebConfirmModal');
    const cancelBtn = document.getElementById('cancelSebBtn');
    const confirmBtn = document.getElementById('confirmSebBtn');
    const msgEl = document.getElementById('sebConfirmMsg');
    
    let pendingState = null;

    sebToggleBtn.addEventListener('click', () => {
      const isEnabled = sebToggleBtn.getAttribute('data-enabled') === 'true';
      pendingState = !isEnabled;
      
      if (pendingState) {
          msgEl.textContent = 'Are you sure you want to turn ON Safe Exam Browser requirement? Users will only be able to access the library via SEB.';
      } else {
          msgEl.textContent = 'Are you sure you want to turn OFF Safe Exam Browser requirement? Anyone will be able to access the library.';
      }
      modal.classList.add('active');
    });

    cancelBtn.addEventListener('click', () => {
      modal.classList.remove('active');
      pendingState = null;
    });

    confirmBtn.addEventListener('click', async () => {
      if (pendingState === null) return;
      
      modal.classList.remove('active');
      const newState = pendingState;
      const oldState = !newState;
      
      updateSebUI(newState);
      
      try {
        const response = await fetch('settings_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'toggle_seb', state: newState })
        });
        
        const data = await response.json();
        if (!data.success) {
          updateSebUI(oldState);
          alert('Failed to update SEB setting: ' + (data.error || 'Unknown error'));
        }
      } catch (error) {
        updateSebUI(oldState);
        alert('Network error while updating SEB setting.');
      }
    });

    // Close if clicked outside the box
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.remove('active');
      }
    });

    function updateSebUI(state) {
      sebToggleBtn.setAttribute('data-enabled', state);
      if (state) {
        sebToggleBtn.textContent = 'SEB OFF';
        sebToggleBtn.style.background = '#10b981';
      } else {
        sebToggleBtn.textContent = 'SEB ON';
        sebToggleBtn.style.background = '#ef4444';
      }
    }
  });

})();
