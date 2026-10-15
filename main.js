document.addEventListener('DOMContentLoaded', async () => {
  const page = document.body.getAttribute('data-page');

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

  // Talk to the PHP server (api.php)
  async function api(action, data = null) {
    const options = data
      ? { method: 'POST', body: new URLSearchParams(data) }
      : {};
    try {
      const res = await fetch(`api.php?action=${action}`, options);
      return await res.json();
    } catch (err) {
      return { error: 'Cannot reach the server. Is XAMPP running, and are you opening the site through localhost?' };
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

    loginTab?.addEventListener('click', () => {
      loginTab.classList.add('active');
      registerTab.classList.remove('active');
      loginForm.classList.remove('hidden');
      registerForm.classList.add('hidden');
    });

    registerTab?.addEventListener('click', () => {
      registerTab.classList.add('active');
      loginTab.classList.remove('active');
      registerForm.classList.remove('hidden');
      loginForm.classList.add('hidden');
    });

    // Login Handler
    loginForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('loginUsername').value.trim();
      const password = document.getElementById('loginPassword').value;

      const result = await api('login', { username, password });
      if (result.error) {
        alert(result.error);
        return;
      }
      window.location.href = "confessions.html";
    });

    // Register Handler
    registerForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('regUsername').value.trim();
      const password = document.getElementById('regPassword').value;

      const result = await api('register', { username, password });
      if (result.error) {
        alert(result.error);
        return;
      }
      alert("Account registered successfully!");
      window.location.href = "confessions.html";
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

    // Logout Handler
    document.getElementById('logoutBtn')?.addEventListener('click', async () => {
      await api('logout', {});
      window.location.href = "index.html";
    });

    // Post Confession
    const confessionForm = document.getElementById('confessionForm');
    confessionForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const tag = document.getElementById('postTagInput').value.trim();
      const content = document.getElementById('postContentInput').value.trim();

      const result = await api('post', { tag, content });
      if (result.error) {
        alert(result.error);
        return;
      }
      confessionForm.reset();
      alert("Confession published!");
      loadFeed();
    });

    // Live Search Filter
    const searchInput = document.getElementById('searchInput');
    searchInput?.addEventListener('keyup', () => {
      const queryText = searchInput.value.toLowerCase().trim();
      const cards = document.querySelectorAll('.diary-card');
      cards.forEach(card => {
        const text = card.innerText.toLowerCase();
        card.style.display = text.includes(queryText) ? 'block' : 'none';
      });
    });

    // Render Confessions Feed
    async function loadFeed() {
      const feedContainer = document.getElementById('feedContainer');
      if (!feedContainer) return;

      const result = await api('feed');
      feedContainer.innerHTML = '';

      if (result.error) {
        feedContainer.innerHTML = `<div style="background:white; padding:2rem; border-radius:12px; text-align:center;">${escapeHtml(result.error)}</div>`;
        return;
      }

      const confessions = result.confessions;

      if (confessions.length === 0) {
        feedContainer.innerHTML = `<div style="background:white; padding:2rem; border-radius:12px; text-align:center;">No confessions posted yet. Be the first to share one!</div>`;
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
          <div class="diary-content">${escapeHtml(data.content)}</div>
        `;
        feedContainer.appendChild(card);
      });
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

    function showPanel() {
      adminGate?.classList.add('hidden');
      adminPanel?.classList.remove('hidden');
      loadAdminFeed();
    }

    // Already logged in as admin in this browser session?
    const me = await api('me');
    if (me.admin) showPanel();

    adminLoginForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const password = document.getElementById('adminPasswordInput').value;

      const result = await api('admin_login', { password });
      if (result.error) {
        alert(result.error);
        return;
      }
      showPanel();
    });

    async function loadAdminFeed() {
      const container = document.getElementById('adminFeedContainer');
      if (!container) return;

      const result = await api('feed');
      container.innerHTML = '';

      if (result.error) {
        container.innerHTML = `<div style="background:white; padding:2rem; border-radius:12px; text-align:center;">${escapeHtml(result.error)}</div>`;
        return;
      }

      const confessions = result.confessions;

      if (confessions.length === 0) {
        container.innerHTML = `<div style="background:white; padding:2rem; border-radius:12px; text-align:center;">No confessions entries found.</div>`;
        return;
      }

      confessions.forEach((data) => {
        const card = document.createElement('div');
        card.className = 'diary-card';
        card.innerHTML = `
          <div class="diary-header">
            <span class="diary-tag">${escapeHtml(data.tag)}</span>
            <button class="btn btn-danger delete-btn" data-id="${data.id}" style="padding:0.2rem 0.6rem; font-size:0.75rem;">
              <i class="fa-solid fa-trash"></i> Delete
            </button>
          </div>
          <div class="diary-author">Author: ${escapeHtml(data.author || 'Anonymous')}</div>
          <div class="diary-content">${escapeHtml(data.content)}</div>
        `;
        container.appendChild(card);
      });

      document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const confessionId = e.currentTarget.getAttribute('data-id');
          if (confirm("Delete this confession?")) {
            const result = await api('admin_delete', { id: confessionId });
            if (result.error) {
              alert(result.error);
              return;
            }
            loadAdminFeed();
          }
        });
      });
    }
  }

});