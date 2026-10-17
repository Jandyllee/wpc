document.addEventListener('DOMContentLoaded', async () => {
  const page = document.body.getAttribute('data-page');

  document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const passwordInput = document.getElementById(toggle.getAttribute('aria-controls'));
      const icon = toggle.querySelector('i');
      if (!passwordInput || !icon) return;

      const isVisible = passwordInput.type === 'password';
      passwordInput.type = isVisible ? 'text' : 'password';
      toggle.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
      toggle.setAttribute('aria-pressed', String(isVisible));
      icon.classList.toggle('fa-eye', !isVisible);
      icon.classList.toggle('fa-eye-slash', isVisible);
    });
  });

  // Helper function to sanitize user inputs
  function escapeHtml(text) {
    return (text || '').replace(/[&<>"']/g, (m) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[m]));
  }

  function createConfessionContent(text) {
    const content = document.createElement('div');
    content.className = 'diary-content';

    const confessionText = text || '';
    const wordMatches = [...confessionText.matchAll(/\S+/g)];
    const previewWordLimit = 30;
    if (wordMatches.length <= previewWordLimit) {
      content.textContent = confessionText;
      return content;
    }

    const preview = document.createElement('span');
    preview.textContent = `${confessionText.slice(0, wordMatches[previewWordLimit].index).trimEnd()}…`;
    const fullContent = document.createElement('span');
    fullContent.textContent = confessionText;
    fullContent.hidden = true;

    const toggle = document.createElement('button');
    toggle.className = 'confession-expand-toggle';
    toggle.type = 'button';
    toggle.textContent = 'Show more';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.addEventListener('click', () => {
      const expanded = toggle.getAttribute('aria-expanded') !== 'true';
      preview.hidden = expanded;
      fullContent.hidden = !expanded;
      toggle.textContent = expanded ? 'Show less' : 'Show more';
      toggle.setAttribute('aria-expanded', String(expanded));
    });

    content.append(preview, fullContent, toggle);
    return content;
  }

  // Talk to the PHP API.
  let csrfToken = null;

  async function api(action, data = null, query = null) {
    try {
      let options = {};
      if (data) {
        if (!csrfToken) {
          const csrfResponse = await fetch('../backend/api.php?action=csrf', {
            credentials: 'same-origin',
            cache: 'no-store'
          });
          const csrfResult = await csrfResponse.json();
          if (!csrfResponse.ok || !csrfResult.csrf_token) {
            return { error: csrfResult.error || 'Your session expired. Refresh the page and try again.' };
          }
          csrfToken = csrfResult.csrf_token;
        }
        options = {
          method: 'POST',
          credentials: 'same-origin',
          body: new URLSearchParams({ ...data, _csrf: csrfToken })
        };
      }
      const queryParams = new URLSearchParams({ action, ...(query || {}) });
      const res = await fetch(`../backend/api.php?${queryParams}`, options);
      const result = await res.json();
      if (result.csrf_token) csrfToken = result.csrf_token;
      return result;
    } catch (err) {
      return { error: 'Unable to connect to the server. Please try again later.' };
    }
  }

  // =========================================================
  // PAGE 1: HOME PAGE (index.html)
  // =========================================================
  if (page === 'home') {
    const me = await api('me');
    if (me.user) {
      window.location.href = "confessions.html";
      return;
    }

    const loginTab = document.getElementById('loginTab');
    const registerTab = document.getElementById('registerTab');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotPasswordLink = document.querySelector('.auth-form-link');
    const authIntroTitle = document.getElementById('authIntroTitle');
    const authIntroCopy = document.getElementById('authIntroCopy');
    const authIntroIcon = document.querySelector('#authIntroIcon i');

    function setAuthMode(mode) {
      const isLogin = mode === 'login';
      loginTab.classList.toggle('active', isLogin);
      registerTab.classList.toggle('active', !isLogin);
      loginTab.setAttribute('aria-selected', String(isLogin));
      registerTab.setAttribute('aria-selected', String(!isLogin));
      loginForm.classList.toggle('hidden', !isLogin);
      registerForm.classList.toggle('hidden', isLogin);
      forgotPasswordLink?.classList.toggle('hidden', !isLogin);
      authIntroTitle.textContent = isLogin ? 'Welcome back' : 'Join the community';
      authIntroCopy.textContent = isLogin
        ? 'Sign in to join the conversation.'
        : 'Create an account and share your story.';
      authIntroIcon.className = isLogin ? 'fa-solid fa-feather-pointed' : 'fa-solid fa-user-plus';
    }

    loginTab?.addEventListener('click', () => {
      setAuthMode('login');
    });

    registerTab?.addEventListener('click', () => {
      setAuthMode('register');
    });

    // Login Handler
    loginForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('loginUsername').value.trim();
      const password = document.getElementById('loginPassword').value;

      const result = await api('login', { username, password });
      if (result.error) {
        await Swal.fire({
          icon: 'error',
          title: 'Login failed',
          text: result.error,
          confirmButtonColor: '#0055FE'
        });
        return;
      }
      window.location.href = "confessions.html";
    });

    // Register Handler
    registerForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('regUsername').value.trim();
      const email = document.getElementById('regEmail').value.trim();
      const password = document.getElementById('regPassword').value;

      const result = await api('register', { username, email, password });
      if (result.error) {
        await Swal.fire({
          icon: 'error',
          title: result.code === 'email_already_registered' ? 'Email already registered' : 'Registration failed',
          text: result.error,
          confirmButtonColor: '#0055FE'
        });
        return;
      }
      await Swal.fire({
        icon: 'success',
        title: 'Account created',
        text: 'Your account was registered successfully.',
        confirmButtonColor: '#0055FE'
      });
      window.location.href = "confessions.html";
    });
  }

  if (page === 'forgot-password') {
    const form = document.getElementById('forgotPasswordForm');
    const status = document.getElementById('forgotPasswordStatus');

    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submitButton = form.querySelector('[type="submit"]');
      if (!window.Swal) {
        status.textContent = 'The verification dialog could not be loaded. Refresh the page and try again.';
        status.classList.remove('hidden', 'form-status-success');
        status.classList.add('form-status-error');
        return;
      }

      submitButton.disabled = true;
      Swal.fire({
        title: 'Sending verification code',
        text: 'Please wait while we send the code to your email.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
      });

      const email = document.getElementById('resetEmail').value.trim();
      const result = await api('forgot_password', { email });
      if (result.error) {
        await Swal.fire({
          icon: 'error',
          title: 'Unable to verify email',
          text: result.error,
          confirmButtonColor: '#0055FE'
        });
        submitButton.disabled = false;
        return;
      }

      const verification = await Swal.fire({
        title: 'Check your email',
        html: `
          <p class="otp-description">Enter the six-digit code we sent you. It expires in 10 minutes.</p>
          <div class="otp-inputs" role="group" aria-label="Six-digit verification code">
            ${Array.from({ length: 6 }, (_, index) => `
              <input
                class="otp-digit"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="1"
                autocomplete="${index === 0 ? 'one-time-code' : 'off'}"
                aria-label="Digit ${index + 1} of 6"
              >
            `).join('')}
          </div>
          <p class="otp-paste-hint"><i class="fa-regular fa-paste" aria-hidden="true"></i> You can paste the full code into any box.</p>
        `,
        customClass: {
          popup: 'otp-verification-popup'
        },
        confirmButtonText: 'Verify code',
        confirmButtonColor: '#0055FE',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        didOpen: () => {
          const inputs = [...Swal.getPopup().querySelectorAll('.otp-digit')];
          const fillDigits = (value, startIndex = 0) => {
            const digits = value.replace(/\D/g, '').slice(0, inputs.length - startIndex);
            digits.split('').forEach((digit, offset) => {
              inputs[startIndex + offset].value = digit;
            });
            inputs[Math.min(startIndex + digits.length, inputs.length - 1)].focus();
          };

          inputs.forEach((input, index) => {
            input.addEventListener('input', () => {
              const digits = input.value.replace(/\D/g, '');
              if (digits.length > 1) {
                fillDigits(digits, index);
                return;
              }
              input.value = digits;
              if (digits && index < inputs.length - 1) inputs[index + 1].focus();
            });
            input.addEventListener('keydown', (event) => {
              if (event.key === 'Backspace' && !input.value && index > 0) {
                inputs[index - 1].focus();
              } else if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                inputs[index - 1].focus();
              } else if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                event.preventDefault();
                inputs[index + 1].focus();
              }
            });
            input.addEventListener('paste', (event) => {
              const pastedText = event.clipboardData?.getData('text') || '';
              const digits = pastedText.replace(/\D/g, '');
              if (!digits) return;
              event.preventDefault();
              fillDigits(digits);
            });
          });

          inputs[0].focus();
        },
        preConfirm: async () => {
          const code = [...Swal.getPopup().querySelectorAll('.otp-digit')]
            .map(input => input.value)
            .join('');
          if (!/^\d{6}$/.test(code)) {
            Swal.showValidationMessage('Enter the six-digit code from your email.');
            return false;
          }
          const verifyResult = await api('verify_reset_code', {
            challenge_id: String(result.challenge_id),
            code
          });
          if (verifyResult.error) {
            Swal.showValidationMessage(verifyResult.error);
            return false;
          }
          return true;
        }
      });

      if (!verification.isConfirmed) {
        submitButton.disabled = false;
        return;
      }

      const resetResult = await Swal.fire({
        title: 'Set a new password',
        html: `
          <div class="password-reset-content">
            <div class="password-reset-intro">
              <span class="password-reset-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
              <p>Choose a new password to secure your WESTPRIME CONFESSIONS account.</p>
            </div>
            <div class="password-reset-field">
              <label for="swalNewPassword">New password</label>
              <span class="password-reset-input-wrap">
                <input id="swalNewPassword" type="password" autocomplete="new-password" placeholder="At least 12 characters">
                <button type="button" data-password-field="swalNewPassword" aria-label="Show new password" aria-pressed="false">
                  <i class="fa-solid fa-eye" aria-hidden="true"></i>
                </button>
              </span>
            </div>
            <div class="password-reset-field">
              <label for="swalConfirmPassword">Confirm new password</label>
              <span class="password-reset-input-wrap">
                <input id="swalConfirmPassword" type="password" autocomplete="new-password" placeholder="Enter it again">
                <button type="button" data-password-field="swalConfirmPassword" aria-label="Show confirmation password" aria-pressed="false">
                  <i class="fa-solid fa-eye" aria-hidden="true"></i>
                </button>
              </span>
            </div>
            <p class="password-reset-hint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Use 12–72 characters (maximum 72 bytes).</p>
          </div>
        `,
        customClass: {
          popup: 'password-reset-popup'
        },
        focusConfirm: false,
        confirmButtonText: 'Reset password',
        confirmButtonColor: '#FFD000',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        didOpen: () => {
          Swal.getPopup().querySelectorAll('[data-password-field]').forEach((toggle) => {
            toggle.addEventListener('click', () => {
              const input = document.getElementById(toggle.dataset.passwordField);
              const icon = toggle.querySelector('i');
              const isVisible = input.type === 'password';
              input.type = isVisible ? 'text' : 'password';
              toggle.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
              toggle.setAttribute('aria-pressed', String(isVisible));
              icon.classList.toggle('fa-eye', !isVisible);
              icon.classList.toggle('fa-eye-slash', isVisible);
            });
          });
        },
        preConfirm: async () => {
          const password = document.getElementById('swalNewPassword').value;
          const confirmPassword = document.getElementById('swalConfirmPassword').value;
          if (password.length < 12 || new TextEncoder().encode(password).length > 72) {
            Swal.showValidationMessage('Password must be between 12 and 72 characters.');
            return false;
          }
          if (password !== confirmPassword) {
            Swal.showValidationMessage('Passwords do not match.');
            return false;
          }

          const passwordResult = await api('reset_password', {
            password,
            confirm_password: confirmPassword
          });
          if (passwordResult.error) {
            Swal.showValidationMessage(passwordResult.error);
            return false;
          }
          return true;
        }
      });

      if (resetResult.isConfirmed) {
        await Swal.fire({
          icon: 'success',
          title: 'Password updated',
          text: 'Your password has been changed. You can now log in.',
          confirmButtonColor: '#0055FE'
        });
        window.location.href = 'index.html';
        return;
      }

      submitButton.disabled = false;
    });
  }

  if (page === 'contact') {
    const form = document.getElementById('contactForm');
    const status = document.getElementById('contactStatus');
    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submitButton = form.querySelector('[type="submit"]');
      if (!window.Swal) {
        status.textContent = 'The message dialog could not be loaded. Refresh the page and try again.';
        status.classList.remove('hidden', 'form-status-success');
        status.classList.add('form-status-error');
        return;
      }

      submitButton.disabled = true;
      Swal.fire({
        title: 'Sending your message',
        text: 'Please wait while we deliver it to the WESTPRIME CONFESSIONS team.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
      });

      const fields = new FormData(form);
      const result = await api('contact', Object.fromEntries(fields.entries()));
      if (result.error) {
        await Swal.fire({
          icon: 'error',
          title: 'Message not sent',
          text: result.error,
          confirmButtonColor: '#0055FE'
        });
        submitButton.disabled = false;
        return;
      }

      form.reset();
      await Swal.fire({
        icon: 'success',
        title: 'Message sent',
        text: 'Thanks for reaching out. Your message has been delivered to the team.',
        confirmButtonColor: '#0055FE'
      });
      submitButton.disabled = false;
    });
  }

  // =========================================================
  // PAGE 2: CONFESSIONS FEED (confessions.html)
  // =========================================================
  if (page === 'confessions') {
    const me = await api('me');
    if (!me.user) {
      window.location.href = "index.html";
      return;
    }

    const userGreeting = document.getElementById('userGreeting');
    if (userGreeting) userGreeting.innerText = `Hi, ${me.user}`;
    const feedCount = document.getElementById('feedCount');
    const postContentInput = document.getElementById('postContentInput');
    const postCharacterCount = document.getElementById('postCharacterCount');
    const maxConfessionCharacters = 1000;

    function updateConfessionCharacterCount() {
      const characters = Array.from(postContentInput.value);
      const wasTrimmed = characters.length > maxConfessionCharacters;
      if (wasTrimmed) {
        const cursorPosition = postContentInput.selectionStart;
        postContentInput.value = characters.slice(0, maxConfessionCharacters).join('');
        postContentInput.setSelectionRange(
          Math.min(cursorPosition, postContentInput.value.length),
          Math.min(cursorPosition, postContentInput.value.length)
        );
      }

      const currentCharacterCount = Array.from(postContentInput.value).length;
      if (postCharacterCount) {
        const limitReached = wasTrimmed || currentCharacterCount === maxConfessionCharacters;
        postCharacterCount.textContent = `${currentCharacterCount.toLocaleString()} / ${maxConfessionCharacters.toLocaleString()} characters${limitReached ? ' (maximum)' : ''}`;
        postCharacterCount.classList.toggle('character-limit-reached', limitReached);
      }
    }

    postContentInput?.addEventListener('input', updateConfessionCharacterCount);
    updateConfessionCharacterCount();

    // Logout Handler
    document.getElementById('logoutBtn')?.addEventListener('click', async () => {
      await api('logout', {});
      window.location.href = "index.html";
    });

    // Post Confession
    const confessionForm = document.getElementById('confessionForm');
    const publishButton = confessionForm?.querySelector('[type="submit"]');
    let isPublishing = false;
    confessionForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (isPublishing) return;

      isPublishing = true;
      if (publishButton) publishButton.disabled = true;
      try {
        const tag = document.getElementById('postTagInput').value.trim();
        const content = postContentInput.value.trim();
        const characterCount = Array.from(content).length;
        if (characterCount > maxConfessionCharacters) {
          await Swal.fire({
            icon: 'error',
            title: 'Confession is too long',
            text: `Confessions can contain up to ${maxConfessionCharacters.toLocaleString()} characters.`,
            confirmButtonColor: '#0055FE'
          });
          return;
        }
        const anonymous = document.getElementById('postAnonymousInput').checked ? '1' : '0';

        const result = await api('post', { tag, content, anonymous });
        if (result.error) {
          await Swal.fire({
            icon: 'error',
            title: 'Unable to publish',
            text: result.error,
            confirmButtonColor: '#0055FE'
          });
          return;
        }
        confessionForm.reset();
        await Swal.fire({
          icon: 'success',
          title: 'Published',
          text: 'Your confession has been published.',
          confirmButtonColor: '#0055FE'
        });
        loadFeed();
      } finally {
        isPublishing = false;
        if (publishButton) publishButton.disabled = false;
      }
    });

    // Live Search Filter
    const searchInput = document.getElementById('searchInput');
    function updateFeedCount() {
      const cards = document.querySelectorAll('#feedContainer .diary-card');
      const visibleCount = [...cards].filter(card => card.style.display !== 'none').length;
      const hasSearch = Boolean(searchInput?.value.trim());
      if (feedCount) {
        feedCount.textContent = hasSearch
          ? `${visibleCount} of ${cards.length} entries`
          : `${cards.length} ${cards.length === 1 ? 'entry' : 'entries'}`;
      }

      const noResults = document.querySelector('#feedContainer .feed-no-results');
      noResults?.classList.toggle('hidden', visibleCount > 0 || cards.length === 0);
    }

    searchInput?.addEventListener('input', () => {
      const queryText = searchInput.value.toLowerCase().trim();
      const cards = document.querySelectorAll('.diary-card');
      cards.forEach(card => {
        const text = card.innerText.toLowerCase();
        card.style.display = text.includes(queryText) ? '' : 'none';
      });
      updateFeedCount();
    });

    // Render Confessions Feed
    async function loadFeed() {
      const feedContainer = document.getElementById('feedContainer');
      if (!feedContainer) return;

      const result = await api('feed');
      feedContainer.innerHTML = '';

      if (result.error) {
        feedContainer.innerHTML = `<div class="feed-message feed-message-error">${escapeHtml(result.error)}</div>`;
        if (feedCount) feedCount.textContent = 'Unavailable';
        return;
      }

      const confessions = result.confessions;

      if (confessions.length === 0) {
        feedContainer.innerHTML = '<div class="feed-message">No confessions posted yet. Be the first to share one!</div>';
        if (feedCount) feedCount.textContent = '0 entries';
        return;
      }

      confessions.forEach((data) => {
        const card = document.createElement('div');
        card.className = 'diary-card';
        card.innerHTML = `
          <div class="diary-header">
            <span class="diary-tag">${escapeHtml(data.tag)}</span>
            <span class="diary-time">${new Date(data.ts * 1000).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
          </div>
          <div class="diary-author">Posted by: ${escapeHtml(data.author || 'Anonymous')}</div>
        `;
        card.appendChild(createConfessionContent(data.content));
        feedContainer.appendChild(card);
      });

      const noResults = document.createElement('div');
      noResults.className = 'feed-message feed-no-results hidden';
      noResults.textContent = 'No confessions match your search.';
      feedContainer.appendChild(noResults);
      updateFeedCount();
    }

    loadFeed();
  }

  // =========================================================
  // PAGE 3: ADMIN DASHBOARD (admin.html)
  // =========================================================
  if (page === 'admin') {
    const adminGate = document.getElementById('adminGate');
    const adminPanel = document.getElementById('adminPanel');
    const adminLoginForm = document.getElementById('adminLoginForm');
    const periodFilter = document.getElementById('adminPeriodFilter');
    const customRange = document.getElementById('adminCustomRange');
    const dateFrom = document.getElementById('adminDateFrom');
    const dateTo = document.getElementById('adminDateTo');
    const applyDateRange = document.getElementById('applyAdminDateRange');

    document.getElementById('adminLogoutBtn')?.addEventListener('click', async () => {
      const result = await api('logout', {});
      if (result.error) {
        await Swal.fire({
          icon: 'error',
          title: 'Unable to log out',
          text: result.error,
          confirmButtonColor: '#0055FE'
        });
        return;
      }
      window.location.href = 'index.html';
    });

    function showPanel() {
      adminGate?.classList.add('hidden');
      adminPanel?.classList.remove('hidden');
      loadAdminFeed();
    }

    function currentAdminFilters() {
      if (periodFilter?.value === 'custom') {
        return {
          period: 'custom',
          from: dateFrom?.value || '',
          to: dateTo?.value || ''
        };
      }
      return { period: periodFilter?.value || 'all' };
    }

    // Already logged in as admin in this browser session?
    const me = await api('me');
    if (me.admin) showPanel();

    adminLoginForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const password = document.getElementById('adminPasswordInput').value;

      const result = await api('admin_login', { password });
      if (result.error) {
        await Swal.fire({
          icon: 'error',
          title: 'Access denied',
          text: result.error,
          confirmButtonColor: '#0055FE'
        });
        return;
      }
      showPanel();
    });

    async function loadAdminFeed(filters = currentAdminFilters()) {
      const container = document.getElementById('adminFeedContainer');
      if (!container) return;
      const feedCount = document.getElementById('adminFeedCount');

      if (feedCount) feedCount.textContent = 'Loading entries';
      container.innerHTML = '<div class="feed-message admin-loading-state"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> Loading confessions…</div>';

      const result = await api('feed', null, filters);
      container.innerHTML = '';

      if (result.error) {
        container.innerHTML = `<div class="feed-message feed-message-error">${escapeHtml(result.error)}</div>`;
        if (feedCount) feedCount.textContent = 'Unavailable';
        return;
      }

      const confessions = result.confessions;
      if (feedCount) {
        feedCount.textContent = `${confessions.length} ${confessions.length === 1 ? 'entry' : 'entries'}`;
      }

      if (confessions.length === 0) {
        container.innerHTML = `
          <div class="feed-message admin-empty-state">
            <span class="admin-empty-icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></span>
            <strong>All caught up</strong>
            <span>No confession entries found for this date range. Try a wider range.</span>
          </div>
        `;
        return;
      }

      confessions.forEach((data) => {
        const card = document.createElement('div');
        card.className = 'diary-card admin-diary-card';
        card.innerHTML = `
          <div class="diary-header">
            <div class="admin-card-meta">
              <span class="diary-tag">${escapeHtml(data.tag)}</span>
              <span class="diary-time">${new Date(data.ts * 1000).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })}</span>
            </div>
            <button class="btn btn-danger delete-btn" data-id="${data.id}" aria-label="Delete confession tagged ${escapeHtml(data.tag)}">
              <i class="fa-solid fa-trash"></i> Delete
            </button>
          </div>
          <div class="diary-author">
            <i class="fa-regular fa-user" aria-hidden="true"></i>
            ${escapeHtml(data.author || 'Anonymous')}
            ${data.is_anonymous ? '<span class="admin-anonymous-label">Anonymous to students</span>' : ''}
          </div>
        `;
        card.appendChild(createConfessionContent(data.content));
        container.appendChild(card);
      });

      document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const confessionId = e.currentTarget.getAttribute('data-id');
          const confirmation = await Swal.fire({
            icon: 'warning',
            title: 'Delete confession?',
            text: 'This action cannot be undone.',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel'
          });
          if (!confirmation.isConfirmed) return;

          const result = await api('admin_delete', { id: confessionId });
          if (result.error) {
            await Swal.fire({
              icon: 'error',
              title: 'Unable to delete',
              text: result.error,
              confirmButtonColor: '#0055FE'
            });
            return;
          }
          await Swal.fire({
            icon: 'success',
            title: 'Deleted',
            text: 'The confession has been deleted.',
            confirmButtonColor: '#0055FE'
          });
          loadAdminFeed();
        });
      });
    }

    periodFilter?.addEventListener('change', () => {
      const period = periodFilter.value;
      customRange?.classList.toggle('hidden', period !== 'custom');
      if (period !== 'custom') {
        loadAdminFeed({ period });
      }
    });

    applyDateRange?.addEventListener('click', () => {
      const from = dateFrom.value;
      const to = dateTo.value;
      if (!from || !to) {
        Swal.fire({
          icon: 'info',
          title: 'Choose both dates',
          text: 'Select a start date and an end date to filter confessions.',
          confirmButtonColor: '#0055FE'
        });
        return;
      }
      if (to < from) {
        Swal.fire({
          icon: 'error',
          title: 'Date range is reversed',
          text: 'The end date must be the same as or later than the start date.',
          confirmButtonColor: '#0055FE'
        });
        return;
      }
      loadAdminFeed({ period: 'custom', from, to });
    });
  }

});