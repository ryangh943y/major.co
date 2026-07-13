/**
 * global.js — ProjectCrew / TeamSync
 * Shared helpers: Toast system, scroll reveal, avatar fallbacks,
 * mobile nav active state, count-up animation.
 */

/* ── Toast System ── */
(function () {
  const ICONS = {
    success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
    error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
    warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
  };

  function ensureContainer() {
    let c = document.getElementById('toast-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toast-container';
      document.body.appendChild(c);
    }
    return c;
  }

  window.showToast = function (message, type = 'info', title = null, duration = 4000) {
    const container = ensureContainer();
    const toast = document.createElement('div');
    const titles = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Info' };
    const resolvedTitle = title || titles[type] || 'Info';

    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${ICONS[type] || ICONS.info}</span>
      <div class="toast-body">
        <div class="toast-title">${resolvedTitle}</div>
        <div class="toast-msg">${message}</div>
      </div>
      <button class="toast-close" aria-label="Close">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>`;

    container.appendChild(toast);

    const close = toast.querySelector('.toast-close');
    function dismiss() {
      toast.classList.add('hiding');
      toast.addEventListener('animationend', () => toast.remove(), { once: true });
    }
    close.addEventListener('click', dismiss);
    if (duration > 0) setTimeout(dismiss, duration);
  };
})();

/* ── Scroll Reveal (IntersectionObserver) ── */
(function () {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.classList.add('visible');
        }, i * 60);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

  window.scrollRevealObserver = observer;

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
  });
})();

/* ── Avatar Image Fallback ── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('img[data-fallback-name]').forEach(img => {
    img.addEventListener('error', function () {
      const name = encodeURIComponent(this.dataset.fallbackName || 'U');
      this.src = `https://ui-avatars.com/api/?name=${name}&background=4A90E2&color=fff&size=200&bold=true`;
      this.onerror = null;
    });
    // Also trigger if already errored
    if (img.complete && img.naturalWidth === 0) img.dispatchEvent(new Event('error'));
  });
});

/* ── Mobile Nav Active State ── */
(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.mobile-nav-item').forEach(link => {
      const href = (link.getAttribute('href') || '').split('/').pop();
      if (href === path) {
        link.classList.add('active');
      }
    });
    document.querySelectorAll('.nav-link').forEach(link => {
      const href = (link.getAttribute('href') || '').split('/').pop();
      if (href === path) {
        link.classList.add('active');
      }
    });
  });
})();

/* ── Count-Up Animation ── */
window.animateCount = function (el, target, duration = 1000) {
  const start = performance.now();
  const from = parseInt(el.textContent) || 0;

  function step(now) {
    const progress = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3); // easeOutCubic
    el.textContent = Math.round(from + (target - from) * ease);
    if (progress < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
};

/* ── HTML Escaping ── */
window.escapeHtml = function (str) {
  if (typeof str !== 'string') return str;
  return str.replace(/[&<>'"]/g, tag => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
  }[tag] || tag));
};
window.escapeHTML = window.escapeHtml;

/* ── Password Strength ── */
window.checkPasswordStrength = function (password) {
  let score = 0;
  if (password.length >= 8)  score++;
  if (password.length >= 12) score++;
  if (/[A-Z]/.test(password)) score++;
  if (/[0-9]/.test(password)) score++;
  if (/[^A-Za-z0-9]/.test(password)) score++;
  return score; // 0-5
};

window.updateStrengthMeter = function (inputEl, fillEl, textEl) {
  const val = inputEl.value;
  const score = checkPasswordStrength(val);
  const levels = [
    { width: '0%',   color: '#E2E8F0', label: '' },
    { width: '20%',  color: '#EF4444', label: 'Very Weak' },
    { width: '40%',  color: '#F59E0B', label: 'Weak' },
    { width: '60%',  color: '#FBBF24', label: 'Fair' },
    { width: '80%',  color: '#10B981', label: 'Strong' },
    { width: '100%', color: '#00B894', label: 'Very Strong' },
  ];
  const level = levels[Math.max(0, Math.min(score, 5))];
  if (fillEl) {
    fillEl.style.width = val ? level.width : '0%';
    fillEl.style.background = level.color;
  }
  if (textEl) {
    textEl.textContent = val ? level.label : '';
    textEl.style.color = level.color;
  }
};

/* ── Notification Center ── */
(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const bellBtn = document.querySelector('.header-icon-btn[aria-label="Notifications"]');
    if (!bellBtn) return;

    // Remove inline redirects
    bellBtn.removeAttribute('onclick');
    
    // Position parent relatively
    const parent = bellBtn.parentElement;
    parent.style.position = 'relative';
    
    // Create Badge
    const badge = document.createElement('span');
    badge.id = 'notificationBadge';
    badge.className = 'hidden absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full text-[9px] flex items-center justify-center font-bold';
    badge.textContent = '0';
    bellBtn.style.position = 'relative';
    bellBtn.appendChild(badge);

    // Create Dropdown
    const dropdown = document.createElement('div');
    dropdown.id = 'notificationDropdown';
    dropdown.className = 'notif-dropdown hidden';
    dropdown.innerHTML = `
      <div class="notif-header">
        <span class="notif-header-title">Notifications</span>
        <button id="markAllReadBtn" class="notif-mark-read">Mark all as read</button>
      </div>
      <div id="notificationList" class="notif-list">
        <div class="py-6 text-center text-xs text-gray-400">Loading notifications...</div>
      </div>
    `;
    parent.appendChild(dropdown);

    // Toggle dropdown
    bellBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('hidden');
      if (!dropdown.classList.contains('hidden')) {
        loadNotificationsList();
      }
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
        dropdown.classList.add('hidden');
      }
    });

    // Mark all as read
    const markAllReadBtn = dropdown.querySelector('#markAllReadBtn');
    markAllReadBtn.addEventListener('click', async (e) => {
      e.stopPropagation();
      try {
        const res = await fetch('backend/mark_notification_read.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({})
        });
        const data = await res.json();
        if (data.success) {
          showToast('All notifications marked as read.', 'success');
          loadNotificationsList();
          updateUnreadCount(0);
        }
      } catch (err) {
        console.error(err);
      }
    });

    // Load Notification List
    async function loadNotificationsList() {
      const listContainer = dropdown.querySelector('#notificationList');
      try {
        const res = await fetch('backend/get_notifications.php');
        const data = await res.json();
        if (data.success) {
          updateUnreadCount(data.unread_count);
          if (data.notifications.length === 0) {
            listContainer.innerHTML = `<div class="py-6 text-center text-xs text-gray-400">No new notifications.</div>`;
            return;
          }
          
          listContainer.innerHTML = '';
          data.notifications.forEach(item => {
            const notifItem = document.createElement('div');
            notifItem.className = `notif-item ${item.is_read ? '' : 'unread'}`;
            
            // Format time relative or simple
            const date = new Date(item.created_at);
            const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' ' + date.toLocaleDateString([], { month: 'short', day: 'numeric' });
            
            notifItem.innerHTML = `
              <div class="notif-dot"></div>
              <div class="flex-1">
                <p class="notif-text">${escapeHTML(item.message)}</p>
                <p class="notif-time">${timeStr}</p>
              </div>
            `;
            
            // Click to read and redirect
            notifItem.addEventListener('click', async () => {
              try {
                await fetch('backend/mark_notification_read.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ notification_id: item.id })
                });
                
                // Determine redirect path
                let redirectUrl = 'dashboard.php';
                if (item.type === 'message') {
                  redirectUrl = 'messages.php';
                } else if (item.type === 'connection' || item.type === 'connection_request') {
                  redirectUrl = 'partners.php';
                } else if (item.type === 'project' || item.type === 'project_join_request') {
                  redirectUrl = 'projects.php';
                }
                
                window.location.href = redirectUrl;
              } catch (err) {
                console.error(err);
              }
            });
            
            listContainer.appendChild(notifItem);
          });
        }
      } catch (err) {
        listContainer.innerHTML = `<div class="py-6 text-center text-xs text-red-400">Failed to load notifications.</div>`;
      }
    }

    function updateUnreadCount(count) {
      if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('hidden');
      } else {
        badge.classList.add('hidden');
      }
    }

    function escapeHTML(str) {
      return str.replace(/[&<>'"]/g, tag => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
      }[tag] || tag));
    }

    // Background Poll
    async function pollNotifications() {
      try {
        const res = await fetch('backend/get_notifications.php');
        const data = await res.json();
        if (data.success) {
          updateUnreadCount(data.unread_count);
        }
      } catch (err) {
        // Silent error to prevent console cluttering during background tasks
      }
    }

    // Initial check and start poll
    pollNotifications();
    setInterval(pollNotifications, 10000);
  });

  // Load theme preference immediately to prevent background flash on slower rendering engines
  if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-theme');
  }

  // Inject Theme Toggle Button globally (Creative Feature #15)
  document.addEventListener('DOMContentLoaded', () => {
    const headerActions = document.querySelector('.header-actions');
    if (headerActions) {
      const toggleBtn = document.createElement('button');
      toggleBtn.id = 'themeToggleBtn';
      toggleBtn.className = 'header-icon-btn mr-1.5';
      toggleBtn.setAttribute('aria-label', 'Toggle Theme');
      toggleBtn.setAttribute('title', 'Toggle Light/Dark Mode');
      
      const isDark = document.body.classList.contains('dark-theme');
      toggleBtn.innerHTML = isDark 
        ? `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`
        : `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
      
      toggleBtn.addEventListener('click', () => {
        const currentlyDark = document.body.classList.toggle('dark-theme');
        localStorage.setItem('theme', currentlyDark ? 'dark' : 'light');
        toggleBtn.innerHTML = currentlyDark
          ? `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`
          : `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
      });
      
      headerActions.insertBefore(toggleBtn, headerActions.firstChild);
    }
  });
})();

