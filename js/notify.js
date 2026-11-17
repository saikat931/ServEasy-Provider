/**
 * ServEasy Notification System — notify.js
 * 
 * Usage:
 *   Notify.success('Operation completed!');
 *   Notify.error('Something went wrong.');
 *   Notify.warning('Please check your input.');
 *   Notify.info('Your OTP is: 123456');
 * 
 *   // Confirmation (returns Promise<boolean>):
 *   const ok = await Notify.confirm('Are you sure you want to delete this?');
 *   if (ok) { ... }
 * 
 *   // With options:
 *   const ok = await Notify.confirm('Delete this item permanently?', {
 *     title: 'Delete Item',
 *     confirmText: 'Delete',
 *     cancelText: 'Keep',
 *     type: 'danger' // 'warning' | 'danger' | 'info'
 *   });
 */

const Notify = (() => {
  'use strict';

  // ── SVG Icons (no external dependency) ──
  const SVG_ICONS = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    error: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    close: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    confirmWarning: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    confirmDanger: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
    confirmInfo: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  };

  // ── Config ──
  const DEFAULTS = {
    successDuration: 4000,
    errorDuration: 6000,
    warningDuration: 5000,
    infoDuration: 4000,
  };

  const TYPE_CONFIG = {
    success: {
      title: 'Success',
      duration: DEFAULTS.successDuration,
    },
    error: {
      title: 'Error',
      duration: DEFAULTS.errorDuration,
    },
    warning: {
      title: 'Warning',
      duration: DEFAULTS.warningDuration,
    },
    info: {
      title: 'Info',
      duration: DEFAULTS.infoDuration,
    },
  };

  let containerEl = null;

  // ── Ensure DOM container exists ──
  function ensureContainer() {
    if (containerEl && document.body.contains(containerEl)) return;
    containerEl = document.getElementById('se-toast-container');
    if (!containerEl) {
      containerEl = document.createElement('div');
      containerEl.id = 'se-toast-container';
      containerEl.setAttribute('role', 'alert');
      containerEl.setAttribute('aria-live', 'assertive');
      document.body.appendChild(containerEl);
    }
  }

  // ── Dismiss all active toasts immediately ──
  function clearAll() {
    if (!containerEl) return;
    const toasts = containerEl.querySelectorAll('.se-toast');
    toasts.forEach(toast => {
      toast.remove();
    });
  }

  // ── Create and show a toast ──
  function showToast(type, message, options = {}) {
    ensureContainer();

    // If single or clearExisting is requested, clear all existing toasts immediately
    if (options.single || options.clearExisting) {
      clearAll();
    }

    const config = TYPE_CONFIG[type] || TYPE_CONFIG.info;
    const duration = options.duration || config.duration;
    const title = options.title || config.title;

    const toast = document.createElement('div');
    toast.className = `se-toast se-toast--${type}`;
    toast.innerHTML = `
      <div class="se-toast__icon">
        ${SVG_ICONS[type] || SVG_ICONS.info}
      </div>
      <div class="se-toast__body">
        <p class="se-toast__title">${escapeHtml(title)}</p>
        <p class="se-toast__message">${escapeHtml(message)}</p>
      </div>
      <button class="se-toast__close" aria-label="Close notification">
        ${SVG_ICONS.close}
      </button>
      <div class="se-toast__progress" style="animation-duration: ${duration}ms;"></div>
    `;

    // Close button handler
    const closeBtn = toast.querySelector('.se-toast__close');
    closeBtn.addEventListener('click', () => removeToast(toast));

    containerEl.appendChild(toast);

    // Auto-dismiss timer
    let timerId = setTimeout(() => removeToast(toast), duration);

    // Pause timer on hover
    toast.addEventListener('mouseenter', () => {
      clearTimeout(timerId);
      const progress = toast.querySelector('.se-toast__progress');
      if (progress) progress.style.animationPlayState = 'paused';
    });

    toast.addEventListener('mouseleave', () => {
      const progress = toast.querySelector('.se-toast__progress');
      if (progress) progress.style.animationPlayState = 'running';
      timerId = setTimeout(() => removeToast(toast), 2000);
    });

    return toast;
  }

  // ── Remove a toast with animation ──
  function removeToast(toast) {
    if (!toast || toast.classList.contains('se-toast-removing')) return;
    toast.classList.add('se-toast-removing');
    toast.addEventListener('animationend', () => {
      toast.remove();
    });
  }

  // ── Confirmation Modal ──
  function showConfirm(message, options = {}) {
    return new Promise((resolve) => {
      const {
        title = 'Confirm Action',
        confirmText = 'Confirm',
        cancelText = 'Cancel',
        type = 'warning', // 'warning' | 'danger' | 'info'
      } = options;

      const iconKey = {
        warning: 'confirmWarning',
        danger: 'confirmDanger',
        info: 'confirmInfo',
      };

      const overlay = document.createElement('div');
      overlay.className = 'se-confirm-overlay';
      overlay.innerHTML = `
        <div class="se-confirm-card">
          <div class="se-confirm__icon se-confirm__icon--${type}">
            ${SVG_ICONS[iconKey[type]] || SVG_ICONS.confirmWarning}
          </div>
          <h3 class="se-confirm__title">${escapeHtml(title)}</h3>
          <p class="se-confirm__message">${escapeHtml(message)}</p>
          <div class="se-confirm__actions">
            <button class="se-confirm__btn se-confirm__btn--cancel">${escapeHtml(cancelText)}</button>
            <button class="se-confirm__btn ${type === 'danger' ? 'se-confirm__btn--danger' : 'se-confirm__btn--confirm'}">${escapeHtml(confirmText)}</button>
          </div>
        </div>
      `;

      document.body.appendChild(overlay);

      // Trigger animation
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          overlay.classList.add('se-confirm-visible');
        });
      });

      const cancelBtn = overlay.querySelector('.se-confirm__btn--cancel');
      const confirmBtn = overlay.querySelector(
        `.se-confirm__btn--confirm, .se-confirm__btn--danger`
      );

      function close(result) {
        overlay.classList.remove('se-confirm-visible');
        setTimeout(() => {
          overlay.remove();
          resolve(result);
        }, 300);
      }

      cancelBtn.addEventListener('click', () => close(false));
      confirmBtn.addEventListener('click', () => close(true));

      // Backdrop click to cancel
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close(false);
      });

      // Escape key to cancel
      function handleKeydown(e) {
        if (e.key === 'Escape') {
          document.removeEventListener('keydown', handleKeydown);
          close(false);
        }
      }
      document.addEventListener('keydown', handleKeydown);
    });
  }

  // ── Prompt / Input Alert Modal ──
  function showPrompt(message, options = {}) {
    return new Promise((resolve) => {
      const {
        title = 'Please Confirm',
        placeholder = 'Please enter reason (optional)...',
        defaultValue = '',
        confirmText = 'Submit',
        cancelText = 'Cancel',
        type = 'danger', // 'warning' | 'danger' | 'info'
      } = options;

      const iconKey = {
        warning: 'confirmWarning',
        danger: 'confirmDanger',
        info: 'confirmInfo',
      };

      const overlay = document.createElement('div');
      overlay.className = 'se-confirm-overlay';
      overlay.innerHTML = `
        <div class="se-confirm-card">
          <div class="se-confirm__icon se-confirm__icon--${type}">
            ${SVG_ICONS[iconKey[type]] || SVG_ICONS.confirmDanger}
          </div>
          <h3 class="se-confirm__title">${escapeHtml(title)}</h3>
          <p class="se-confirm__message" style="margin-bottom: 16px;">${escapeHtml(message)}</p>
          <div style="margin-bottom: 24px; text-align: left;">
            <input type="text" class="se-prompt__input" value="${escapeHtml(defaultValue)}" placeholder="${escapeHtml(placeholder)}" style="width: 100%; padding: 12px 16px; border-radius: 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #f1f5f9; font-size: 14px; outline: none; transition: all 0.2s ease; box-sizing: border-box;" />
          </div>
          <div class="se-confirm__actions">
            <button class="se-confirm__btn se-confirm__btn--cancel">${escapeHtml(cancelText)}</button>
            <button class="se-confirm__btn ${type === 'danger' ? 'se-confirm__btn--danger' : 'se-confirm__btn--confirm'}">${escapeHtml(confirmText)}</button>
          </div>
        </div>
      `;

      document.body.appendChild(overlay);

      const input = overlay.querySelector('.se-prompt__input');
      input.addEventListener('focus', () => {
        input.style.borderColor = type === 'danger' ? 'rgba(239, 68, 68, 0.6)' : 'rgba(59, 130, 246, 0.6)';
        input.style.boxShadow = type === 'danger' ? '0 0 0 3px rgba(239, 68, 68, 0.15)' : '0 0 0 3px rgba(59, 130, 246, 0.15)';
      });
      input.addEventListener('blur', () => {
        input.style.borderColor = 'rgba(255,255,255,0.15)';
        input.style.boxShadow = 'none';
      });

      // Trigger animation
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          overlay.classList.add('se-confirm-visible');
          input.focus();
        });
      });

      const cancelBtn = overlay.querySelector('.se-confirm__btn--cancel');
      const confirmBtn = overlay.querySelector(
        `.se-confirm__btn--confirm, .se-confirm__btn--danger`
      );

      function close(result) {
        overlay.classList.remove('se-confirm-visible');
        setTimeout(() => {
          overlay.remove();
          resolve(result);
        }, 300);
      }

      cancelBtn.addEventListener('click', () => close(null));
      confirmBtn.addEventListener('click', () => close(input.value.trim()));

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          close(input.value.trim());
        }
      });

      // Backdrop click to cancel
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close(null);
      });

      // Escape key to cancel
      function handleKeydown(e) {
        if (e.key === 'Escape') {
          document.removeEventListener('keydown', handleKeydown);
          close(null);
        }
      }
      document.addEventListener('keydown', handleKeydown);
    });
  }

  // ── Utility ──
  function escapeHtml(str) {
    if (typeof str !== 'string') str = String(str);
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  // ── Public API ──
  return {
    success: (msg, opts) => showToast('success', msg, opts),
    error: (msg, opts) => showToast('error', msg, opts),
    warning: (msg, opts) => showToast('warning', msg, opts),
    info: (msg, opts) => showToast('info', msg, opts),
    clearAll: () => clearAll(),
    confirm: (msg, opts) => showConfirm(msg, opts),
    prompt: (msg, opts) => showPrompt(msg, opts),
  };
})();
