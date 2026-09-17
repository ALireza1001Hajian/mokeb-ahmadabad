/* ══════════════════════════════════════════════════════════════
   موکب احمدآباد — admin.js (نسخه نهایی 1.7.0)
   ══════════════════════════════════════════════════════════════ */
'use strict';

(function () {

  const CFG = window.__ADMIN__ || {};
  const API = CFG.apiBase || 'api/admin/';

  const OPTION_LETTERS = { A: 'الف', B: 'ب', C: 'ج', D: 'د' };
  const optionLetter = (k) => OPTION_LETTERS[k] || k;

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  /* ═══════════════════════════════════════════════════════
     STATE
     ═══════════════════════════════════════════════════════ */
  const State = {
    csrf: CFG.csrf || '',
    loggedIn: false,
    running: false,
    currentQ: 0,
    totalQ: CFG.totalQ || 10,
    statsHandle: null,
    statsAbort: null,
    chartBar: null,
    chartPie: null,
    chartDistribution: null,
    lastStats: null,
    historyLoaded: false,
  };

  /* ═══════════════════════════════════════════════════════
     API HELPER
     ═══════════════════════════════════════════════════════ */
  async function api(endpoint, data = {}, opts = {}) {
    const { method = 'POST', signal = null } = opts;
    const controller = new AbortController();
    if (signal) {
      if (signal.aborted) throw new DOMException('Aborted', 'AbortError');
      signal.addEventListener('abort', () => controller.abort(), { once: true });
    }
    const body = { ...data, csrf: State.csrf };

    try {
      const res = await fetch(API + endpoint, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: method === 'GET' ? undefined : JSON.stringify(body),
        signal: controller.signal,
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok || json.ok === false) {
        const err = new Error(json.error || `خطا (${res.status})`);
        err.status = res.status;
        throw err;
      }
      return json;
    } catch (e) {
      if (e.name === 'AbortError') throw e;
      if (e.status) throw e;
      throw new Error('ارتباط با سرور برقرار نشد');
    }
  }

  /* ═══════════════════════════════════════════════════════
     UTILS
     ═══════════════════════════════════════════════════════ */
  function escapeHtml(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function faNum(n) {
    return Number(n || 0).toLocaleString('en-US');
  }

  function download(content, filename) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  function formatDateFa(dateStr) {
    try {
      const d = new Date(dateStr + 'T00:00:00');
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString('fa-IR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    } catch { return dateStr; }
  }

  function formatDuration(sec) {
    if (!sec || sec <= 0) return '';
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    if (m === 0) return `${s} ثانیه`;
    if (s === 0) return `${m} دقیقه`;
    return `${m}:${String(s).padStart(2, '0')}`;
  }

  /* ═══════════════════════════════════════════════════════
     TOAST
     ═══════════════════════════════════════════════════════ */
  const Toast = (() => {
    const wrap = document.getElementById('toastWrap');
    const icons = {
      success: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
      error:   '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
      warn:    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>',
      info:    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    };

    function show(msg, type = 'info', duration = 3200) {
      if (!wrap) return;
      const el = document.createElement('div');
      el.className = `toast toast-${type}`;
      el.innerHTML = `<span class="toast-icon">${icons[type] || icons.info}</span>
        <span class="toast-msg">${escapeHtml(msg)}</span>`;
      wrap.appendChild(el);
      setTimeout(() => {
        el.classList.add('toast-out');
        setTimeout(() => el.remove(), 400);
      }, duration);
    }

    return { show };
  })();

  /* ═══════════════════════════════════════════════════════
     MODAL
     ═══════════════════════════════════════════════════════ */
  const Modal = (() => {
    const backdrop = document.getElementById('modalBackdrop');
    const titleEl  = document.getElementById('modalTitle');
    const bodyEl   = document.getElementById('modalBody');
    const footEl   = document.getElementById('modalFoot');
    const closeBtn = document.getElementById('modalClose');

    let escHandler = null;

    function open({ title, body, buttons = [] }) {
      titleEl.textContent = title || '';
      bodyEl.innerHTML = body || '';
      footEl.innerHTML = '';

      buttons.forEach((b) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn ' + (b.class || 'btn-ghost');
        btn.textContent = b.label;
        btn.addEventListener('click', () => {
          if (b.onClick) b.onClick();
          if (b.close !== false) close();
        });
        footEl.appendChild(btn);
      });

      backdrop.hidden = false;
      escHandler = (e) => { if (e.key === 'Escape') close(); };
      document.addEventListener('keydown', escHandler);
      return { close };
    }

    function close() {
      backdrop.hidden = true;
      if (escHandler) document.removeEventListener('keydown', escHandler);
      escHandler = null;
    }

    closeBtn.addEventListener('click', close);
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });

    return { open, close };
  })();

  /* ═══════════════════════════════════════════════════════
     VIEW SWITCHING
     ═══════════════════════════════════════════════════════ */
  function showView(name) {
    $$('.admin-view').forEach((v) => { v.hidden = true; });
    const el = document.getElementById('view-' + name);
    if (el) el.hidden = false;
  }

  /* ═══════════════════════════════════════════════════════
     LOGIN
     ═══════════════════════════════════════════════════════ */
  function bindLogin() {
    const form = document.getElementById('formLogin');
    const input = document.getElementById('inputPassword');
    const errEl = document.getElementById('loginError');
    const btn = document.getElementById('btnLogin');
    const label = btn.querySelector('.btn-label');
    const spin = btn.querySelector('.btn-spinner');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const pw = input.value;
      if (!pw) return showErr('رمز عبور را وارد کنید');

      btn.disabled = true;
      label.textContent = 'در حال ورود…';
      spin.hidden = false;

      try {
        const res = await api('login.php', { password: pw });
        if (res.csrf) State.csrf = res.csrf;
        Toast.show('خوش آمدید', 'success');
        await enterDashboard();
      } catch (err) {
        showErr(err.message || 'خطا در ورود');
        input.value = '';
        input.focus();
      } finally {
        btn.disabled = false;
        label.textContent = 'ورود به پنل';
        spin.hidden = true;
      }
    });

    function showErr(msg) {
      errEl.textContent = msg;
      errEl.hidden = false;
      input.classList.add('is-invalid');
    }

    input.addEventListener('input', () => {
      input.classList.remove('is-invalid');
      errEl.hidden = true;
    });
  }

  /* ═══════════════════════════════════════════════════════
     BOOT
     ═══════════════════════════════════════════════════════ */
  async function boot() {
    try {
      const res = await api('me.php', {}, { method: 'GET' });
      if (res.logged_in) {
        if (res.csrf) State.csrf = res.csrf;
        await enterDashboard();
      } else {
        showView('login');
        setTimeout(() => document.getElementById('inputPassword').focus(), 200);
      }
    } catch {
      showView('login');
    }
  }

  /* ═══════════════════════════════════════════════════════
     DASHBOARD
     ═══════════════════════════════════════════════════════ */
  async function enterDashboard() {
    State.loggedIn = true;
    showView('dashboard');
    bindDashboard();
    await loadSettings();
    await refreshStats();
    startStatsPolling();
    startServerClock();
  }

  let dashboardBound = false;
  function bindDashboard() {
    if (dashboardBound) return;
    dashboardBound = true;

    $('#btnLogout').addEventListener('click', async () => {
      try { await api('logout.php'); } catch {}
      location.reload();
    });

    $('#btnStart').addEventListener('click', onStart);
    $('#btnStop').addEventListener('click', onStop);
    $('#btnReset').addEventListener('click', onReset);
    $('#btnToggleSite').addEventListener('click', onToggleSite);

    $$('.admin-tab').forEach((tab) => {
      tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    const fName = $('#filterName');
    const fStatus = $('#filterStatus');
    let pt;
    fName.addEventListener('input', () => {
      clearTimeout(pt); pt = setTimeout(loadParticipants, 350);
    });
    fStatus.addEventListener('change', loadParticipants);

    const fW = $('#filterWinner');
    let wt;
    fW.addEventListener('input', () => {
      clearTimeout(wt); wt = setTimeout(loadWinners, 350);
    });

    $('#btnExportStats').addEventListener('click', exportStatsCsv);
    $('#btnExportWinners').addEventListener('click', exportWinnersCsv);
    $('#btnPrintWinners').addEventListener('click', () => window.print());

    $('#btnNewQuestion').addEventListener('click', () => openQuestionModal(null));
    $('#btnBulkImport').addEventListener('click', openBulkImportModal);
    $('#btnRefreshHistory').addEventListener('click', () => loadHistory(true));

    $('#formSettings').addEventListener('submit', saveSettings);
    $('#btnClearStats').addEventListener('click', onClearStats);
  }

  function switchTab(name) {
    $$('.admin-tab').forEach((t) => t.classList.toggle('is-active', t.dataset.tab === name));
    $$('.admin-panel').forEach((p) => p.classList.toggle('is-active', p.id === 'panel-' + name));

    if (name === 'participants') loadParticipants();
    if (name === 'winners') loadWinners();
    if (name === 'questions') loadQuestions();
    if (name === 'history') loadHistory();
  }

  /* ═══════════════════════════════════════════════════════
     CONTROLS
     ═══════════════════════════════════════════════════════ */
  async function onStart() {
    if (!confirm('مطمئنید می‌خواهید مسابقه را شروع کنید؟')) return;
    try {
      await api('start.php');
      Toast.show('مسابقه شروع شد', 'success');
      refreshStats();
    } catch (e) { Toast.show(e.message, 'error'); }
  }

  async function onStop() {
    if (!confirm('مسابقه فوراً متوقف می‌شود و همه شرکت‌کنندگان فعال حذف می‌شوند. مطمئنید؟')) return;
    try {
      await api('stop.php');
      Toast.show('مسابقه متوقف شد', 'warn');
      refreshStats();
    } catch (e) { Toast.show(e.message, 'error'); }
  }

  function onReset() {
    Modal.open({
      title: 'ریست کامل مسابقه',
      body: `
        <p>⚠️ <strong>هشدار:</strong> این عمل تمام شرکت‌کنندگان، پاسخ‌ها و آمار امروز را پاک می‌کند.</p>
        <p>سوالات امروز باقی می‌مانند.</p>
        <div class="field">
          <label class="field-label">برای تأیید، کلمه RESET را وارد کنید</label>
          <input type="text" class="field-input" id="resetConfirm" placeholder="RESET" dir="ltr">
        </div>
      `,
      buttons: [
        { label: 'انصراف', class: 'btn-ghost' },
        {
          label: 'ریست کن',
          class: 'btn-danger',
          onClick: async () => {
            const val = document.getElementById('resetConfirm').value.trim();
            if (val !== 'RESET') {
              Toast.show('کد تأیید اشتباه است', 'error');
              return false;
            }
            try {
              const res = await api('reset.php', { confirm: 'RESET' });
              Toast.show(`ریست شد (${res.cleared.participants} شرکت‌کننده پاک شد)`, 'success');
              refreshStats();
            } catch (e) { Toast.show(e.message, 'error'); }
          },
        },
      ],
    });
  }

  async function onToggleSite() {
    const isOpen = $('#btnToggleSite').classList.contains('is-open');
    const msg = isOpen
      ? 'مطمئنید می‌خواهید سایت را ببندید؟ کاربران جدید نمی‌توانند وارد شوند.'
      : 'مطمئنید می‌خواهید سایت را باز کنید؟ تایمر شروع تقریبی فعال می‌شود.';

    if (!confirm(msg)) return;

    try {
      const res = await api('toggle_site.php');
      Toast.show(res.message, 'success');
      updateSiteButton(!!res.site_open);
      refreshStats();
    } catch (e) { Toast.show(e.message, 'error'); }
  }

  function updateSiteButton(isOpen) {
    const btn = $('#btnToggleSite');
    const lbl = $('#btnToggleSiteLabel');
    if (!btn || !lbl) return;

    if (isOpen) {
      btn.classList.add('is-open');
      lbl.textContent = 'سایت باز است';
    } else {
      btn.classList.remove('is-open');
      lbl.textContent = 'بستن سایت';
    }
  }

  /* ═══════════════════════════════════════════════════════
     STATS
     ═══════════════════════════════════════════════════════ */
  function startStatsPolling() {
    stopStatsPolling();
    State.statsHandle = setInterval(refreshStats, 2000);
  }

  function stopStatsPolling() {
    if (State.statsHandle) clearInterval(State.statsHandle);
    if (State.statsAbort) try { State.statsAbort.abort(); } catch {}
    State.statsAbort = null;
  }

  async function refreshStats() {
    if (State.statsAbort) try { State.statsAbort.abort(); } catch {}
    State.statsAbort = new AbortController();

    try {
      const res = await api('stats.php', {}, {
        method: 'GET',
        signal: State.statsAbort.signal,
      });
      State.statsAbort = null;
      State.lastStats = res;
      renderCounters(res);
      renderStatus(res);
      updateSiteButton(!!res.site_open);
      renderBarChart(res);
      renderDistributionChart(res);
      renderPieChart(res);
      renderStatsTable(res);
    } catch (e) {
      if (e.name === 'AbortError') return;
      console.warn('[stats]', e.message);
    }
  }

  function renderCounters(res) {
    const c = res.counts || {};
    $('#cntParticipants').textContent = faNum(c.participants);
    $('#cntWaiting').textContent = faNum(c.waiting);
    $('#cntPlaying').textContent = faNum(c.playing);
    $('#cntEliminated').textContent = faNum(c.eliminated);
    $('#cntWinners').textContent = faNum(c.winners);
  }

  function renderStatus(res) {
    State.running = !!res.quiz_running;
    State.currentQ = res.current_q || 0;

    const pill = $('#quizStatusPill');
    const txt = $('#quizStatusText');
    const btnStart = $('#btnStart');
    const btnStop = $('#btnStop');

    if (State.running) {
      pill.classList.add('is-running');
      pill.classList.remove('is-stopped');
      txt.textContent = 'در حال اجرا';
      btnStart.hidden = true;
      btnStop.hidden = false;
    } else {
      pill.classList.remove('is-running');
      pill.classList.add('is-stopped');
      txt.textContent = 'در انتظار شروع';
      btnStart.hidden = false;
      btnStop.hidden = true;
    }

    $('#currQInfo').textContent = res.current_q > 0 ? res.current_q : '—';
    $('#totalQInfo').textContent = State.totalQ;
  }

  function renderBarChart(res) {
    const rows = res.per_question || [];
    const labels = rows.map((r) => `سوال ${r.order}`);
    const oks = rows.map((r) => r.ok);

    if (State.chartBar) {
      State.chartBar.data.labels = labels;
      State.chartBar.data.datasets[0].data = oks;
      State.chartBar.update('none');
      return;
    }

    const ctx = document.getElementById('chartBar');
    if (!ctx) return;

    State.chartBar = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'پاسخ صحیح',
          data: oks,
          backgroundColor: 'rgba(22, 163, 74, 0.75)',
          borderColor: '#16A34A',
          borderWidth: 2,
          borderRadius: 8,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 500 },
        plugins: {
          legend: { display: false },
          tooltip: { rtl: true, textDirection: 'rtl' },
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#F1F5F9' } },
          x: { grid: { display: false } },
        },
      },
    });
  }

  function renderDistributionChart(res) {
    const dist = res.distribution || {};
    const distLabels = [];
    const distA = [], distB = [], distC = [], distD = [], distNone = [];

    Object.keys(dist).sort((a, b) => a - b).forEach((k) => {
      const d = dist[k];
      distLabels.push(`سوال ${k}`);
      distA.push(d.A || 0);
      distB.push(d.B || 0);
      distC.push(d.C || 0);
      distD.push(d.D || 0);
      distNone.push(d.NONE || 0);
    });

    if (State.chartDistribution) {
      State.chartDistribution.data.labels = distLabels;
      State.chartDistribution.data.datasets[0].data = distA;
      State.chartDistribution.data.datasets[1].data = distB;
      State.chartDistribution.data.datasets[2].data = distC;
      State.chartDistribution.data.datasets[3].data = distD;
      State.chartDistribution.data.datasets[4].data = distNone;
      State.chartDistribution.update('none');
      return;
    }

    const ctx = document.getElementById('chartDistribution');
    if (!ctx) return;

    State.chartDistribution = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: distLabels,
        datasets: [
          { label: 'الف',     data: distA,    backgroundColor: 'rgba(220, 38, 38, 0.85)',  borderRadius: 6 },
          { label: 'ب',       data: distB,    backgroundColor: 'rgba(14, 165, 233, 0.85)', borderRadius: 6 },
          { label: 'ج',       data: distC,    backgroundColor: 'rgba(234, 179, 8, 0.85)',  borderRadius: 6 },
          { label: 'د',       data: distD,    backgroundColor: 'rgba(22, 163, 74, 0.85)',  borderRadius: 6 },
          { label: 'بی‌پاسخ', data: distNone, backgroundColor: 'rgba(148, 163, 184, 0.7)', borderRadius: 6 },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 500 },
        plugins: {
          legend: {
            position: 'bottom',
            rtl: true,
            labels: { padding: 14, font: { family: 'Vazirmatn', size: 12 } },
          },
          tooltip: { rtl: true, textDirection: 'rtl' },
        },
        scales: {
          x: { stacked: true, grid: { display: false } },
          y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#F1F5F9' } },
        },
      },
    });
  }

  function renderPieChart(res) {
    const elimData = res.eliminated_by_q || [];
    const pieLabels = elimData.map((e) => `سوال ${e.q_num}`);
    const pieValues = elimData.map((e) => Number(e.cnt));

    const palette = ['#DC2626','#EA580C','#F59E0B','#FACC15','#84CC16','#16A34A','#0EA5E9','#6366F1','#A855F7','#EC4899'];

    if (State.chartPie) {
      State.chartPie.data.labels = pieLabels;
      State.chartPie.data.datasets[0].data = pieValues;
      State.chartPie.data.datasets[0].backgroundColor = pieLabels.map((_, i) => palette[i % palette.length]);
      State.chartPie.update('none');
      return;
    }

    const ctx = document.getElementById('chartPie');
    if (!ctx) return;

    State.chartPie = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: pieLabels.length ? pieLabels : ['بدون داده'],
        datasets: [{
          data: pieValues.length ? pieValues : [1],
          backgroundColor: pieLabels.length
            ? pieLabels.map((_, i) => palette[i % palette.length])
            : ['#E2E8F0'],
          borderWidth: 2,
          borderColor: '#fff',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', rtl: true, labels: { padding: 10, font: { family: 'Vazirmatn' } } },
          tooltip: { rtl: true, textDirection: 'rtl' },
        },
      },
    });
  }

  function renderStatsTable(res) {
    const tbody = $('#statsTable tbody');
    if (!tbody) return;

    const rows = res.per_question || [];
    const avg = res.avg_response || {};
    const hardestQ = res.hardest_q || 0;

    tbody.innerHTML = rows.map((r) => {
      const rt = avg[r.order] || avg[String(r.order)] || 0;
      let timeClass = '';
      if (rt > 0 && rt <= 5) timeClass = 'is-fast';
      else if (rt > 15) timeClass = 'is-slow';

      const isHardest = (r.order === hardestQ && r.fail > 0);
      const marker = isHardest ? ' 🔴' : '';

      return `
        <tr>
          <td class="num">${r.order}${marker}</td>
          <td>سوال ${r.order}</td>
          <td class="num" style="color:#16A34A;font-weight:700">${faNum(r.ok)}</td>
          <td class="num" style="color:#DC2626;font-weight:700">${faNum(r.fail)}</td>
          <td class="num">${r.rate}%</td>
          <td>${rt > 0
            ? `<span class="time-badge ${timeClass}">${rt}s</span>`
            : '<span style="color:#94A3B8">—</span>'}</td>
        </tr>
      `;
    }).join('');

    const banner = $('#hardestBanner');
    if (banner) {
      if (res.hardest_q && res.hardest_fail > 0) {
        $('#hardestQNum').textContent = res.hardest_q;
        $('#hardestFail').textContent = faNum(res.hardest_fail);
        banner.hidden = false;
      } else {
        banner.hidden = true;
      }
    }
  }

  function exportStatsCsv() {
    const res = State.lastStats;
    if (!res || !res.per_question) return Toast.show('داده‌ای موجود نیست', 'warn');

    let csv = '\xEF\xBB\xBF';
    csv += 'شماره سوال,تعداد درست,تعداد غلط,نرخ موفقیت\n';
    res.per_question.forEach((r) => {
      csv += `${r.order},${r.ok},${r.fail},${r.rate}%\n`;
    });
    download(csv, `stats_${CFG.today}.csv`);
  }

  /* ═══════════════════════════════════════════════════════
     QUESTIONS
     ═══════════════════════════════════════════════════════ */
  async function loadQuestions() {
    try {
      const res = await api('questions.php', { action: 'list' });
      renderQuestions(res.questions || []);
    } catch (e) { Toast.show(e.message, 'error'); }
  }

  function renderQuestions(list) {
    const wrap = $('#questionsList');
    if (!list.length) {
      wrap.innerHTML = '<div class="empty-state"><p>هیچ سوالی ثبت نشده است</p></div>';
      return;
    }

    wrap.innerHTML = list.map((q) => `
      <div class="question-item draggable" draggable="true" data-id="${q.id}" data-order="${q.order_num}">
        <div class="question-drag-handle" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="18" r="1"/>
            <circle cx="15" cy="6" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="18" r="1"/>
          </svg>
        </div>
        <div class="question-num-badge">${q.order_num}</div>
        <div class="question-body">
          <h4>${escapeHtml(q.text)}</h4>
          <div class="question-options">
            ${['A','B','C','D'].map((k) => {
              const val = q['option_' + k.toLowerCase()];
              if (!val) return '';
              const cls = q.correct === k ? 'is-correct' : '';
              return `<div class="question-opt ${cls}"><strong>${optionLetter(k)}</strong>${escapeHtml(val)}</div>`;
            }).join('')}
          </div>
        </div>
        <div class="question-actions">
          <button class="icon-btn" data-act="edit" title="ویرایش">
            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button class="icon-btn danger" data-act="delete" title="حذف">
            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>
      </div>
    `).join('');

    $$('.question-item', wrap).forEach((item) => {
      const id = Number(item.dataset.id);
      const q = list.find((x) => Number(x.id) === id);

      item.querySelector('[data-act="edit"]').addEventListener('click', () => openQuestionModal(q));
      item.querySelector('[data-act="delete"]').addEventListener('click', () => deleteQuestion(q));
    });

    bindDragDropQuestions(wrap, list);
  }

  let dragSrcEl = null;

  function bindDragDropQuestions(wrap, list) {
    const items = $$('.question-item', wrap);

    items.forEach((item) => {
      item.addEventListener('dragstart', (e) => {
        dragSrcEl = item;
        item.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', item.dataset.id);
      });

      item.addEventListener('dragend', () => {
        item.classList.remove('dragging');
        items.forEach((i) => i.classList.remove('drag-over'));
        dragSrcEl = null;
      });

      item.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (dragSrcEl === item) return;
        item.classList.add('drag-over');
      });

      item.addEventListener('dragleave', () => {
        item.classList.remove('drag-over');
      });

      item.addEventListener('drop', async (e) => {
        e.preventDefault();
        item.classList.remove('drag-over');
        if (!dragSrcEl || dragSrcEl === item) return;

        const allItems = $$('.question-item', wrap);
        const srcIndex = allItems.indexOf(dragSrcEl);
        const dstIndex = allItems.indexOf(item);

        if (srcIndex < dstIndex) {
          item.parentNode.insertBefore(dragSrcEl, item.nextSibling);
        } else {
          item.parentNode.insertBefore(dragSrcEl, item);
        }

        await saveNewOrder(wrap, list);
      });
    });
  }

  async function saveNewOrder(wrap, list) {
    const items = $$('.question-item', wrap);
    const orders = items.map((el, i) => ({
      id: Number(el.dataset.id),
      order: i + 1,
    }));

    try {
      await api('questions.php', { action: 'reorder', orders });

      items.forEach((el, i) => {
        const badge = el.querySelector('.question-num-badge');
        if (badge) badge.textContent = i + 1;
      });

      Toast.show('ترتیب سوالات ذخیره شد', 'success', 1800);
      refreshStats();
    } catch (e) {
      Toast.show(e.message || 'خطا در ذخیره ترتیب', 'error');
      loadQuestions();
    }
  }

  function openQuestionModal(q) {
    const isEdit = !!q;
    const data = q || {
      id: 0, order_num: 0, text: '',
      option_a: '', option_b: '', option_c: '', option_d: '',
      correct: 'A',
    };

    if (!isEdit) {
      data.order_num = (State.lastStats?.per_question?.length || 0) + 1;
    }

    Modal.open({
      title: isEdit ? 'ویرایش سوال' : 'افزودن سوال',
      body: `
        <div class="field">
          <label class="field-label">شماره سوال</label>
          <input type="number" class="field-input" id="qOrder" min="1" max="50" value="${data.order_num}">
        </div>
        <div class="field">
          <label class="field-label">متن سوال</label>
          <textarea class="field-input" id="qText" rows="3" maxlength="500">${escapeHtml(data.text)}</textarea>
        </div>
        ${['A','B','C','D'].map((k) => `
          <div class="field">
            <label class="field-label">گزینه ${optionLetter(k)}</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="radio" name="qCorrect" value="${k}" ${data.correct === k ? 'checked' : ''} id="qCorrect${k}">
              <input type="text" class="field-input" id="qOpt${k}" maxlength="200" value="${escapeHtml(data['option_' + k.toLowerCase()] || '')}" style="flex:1">
            </div>
          </div>
        `).join('')}
      `,
      buttons: [
        { label: 'انصراف', class: 'btn-ghost' },
        {
          label: 'ذخیره',
          class: 'btn-primary',
          onClick: async () => {
            const payload = {
              action: 'save',
              id: data.id,
              order_num: Number($('#qOrder').value),
              text: $('#qText').value.trim(),
              option_a: $('#qOptA').value.trim(),
              option_b: $('#qOptB').value.trim(),
              option_c: $('#qOptC').value.trim(),
              option_d: $('#qOptD').value.trim(),
              correct: (document.querySelector('input[name="qCorrect"]:checked') || {}).value || '',
            };

            if (!payload.text || payload.text.length < 3) {
              Toast.show('متن سوال را کامل وارد کنید', 'error');
              return false;
            }
            if (!payload.option_a || !payload.option_b) {
              Toast.show('گزینه الف و ب الزامی هستند', 'error');
              return false;
            }
            if (!payload.correct) {
              Toast.show('گزینه صحیح را انتخاب کنید', 'error');
              return false;
            }

            try {
              await api('questions.php', payload);
              Toast.show(isEdit ? 'سوال ویرایش شد' : 'سوال افزوده شد', 'success');
              loadQuestions();
              refreshStats();
            } catch (e) {
              Toast.show(e.message, 'error');
              return false;
            }
          },
        },
      ],
    });
  }

  function deleteQuestion(q) {
    Modal.open({
      title: 'حذف سوال',
      body: `<p>آیا از حذف «${escapeHtml(q.text.substring(0, 80))}…» مطمئن هستید؟</p>`,
      buttons: [
        { label: 'انصراف', class: 'btn-ghost' },
        {
          label: 'حذف', class: 'btn-danger',
          onClick: async () => {
            try {
              await api('questions.php', { action: 'delete', id: q.id });
              Toast.show('سوال حذف شد', 'success');
              loadQuestions();
            } catch (e) { Toast.show(e.message, 'error'); }
          },
        },
      ],
    });
  }

  function openBulkImportModal() {
    Modal.open({
      title: 'افزودن گروهی سوالات',
      body: `
        <p style="font-size:0.92rem;color:#334155">
          هر سوال در <strong>یک خط</strong> با فرمت زیر:
        </p>
        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:14px;font-family:monospace;font-size:0.82rem;direction:rtl;text-align:right;line-height:1.9">
          متن سوال | گزینه الف | گزینه ب | گزینه ج | گزینه د | B
        </div>
        <p style="font-size:0.85rem;color:#64748B;line-height:1.9">
          • اگر گزینه ج یا د ندارید، به جای آن <code>-</code> بگذارید<br>
          • حرف آخر پاسخ صحیح است: <strong>A</strong>، <strong>B</strong>، <strong>C</strong>، <strong>D</strong><br>
          • خطوطی که با <code>#</code> شروع شوند نادیده گرفته می‌شوند
        </p>
        <div class="field">
          <label class="field-label">متن سوالات</label>
          <textarea class="field-input" id="bulkText" rows="10" style="font-family:monospace;font-size:0.88rem;direction:rtl;text-align:right;resize:vertical;min-height:180px" placeholder="کدام سوره قرآن قلب قرآن است؟ | سوره یس | سوره الرحمن | سوره کهف | سوره ملک | A&#10;تعداد سوره‌های قرآن چند است؟ | ۱۰۰ | ۱۱۴ | ۱۲۰ | ۹۹ | B"></textarea>
        </div>
        <div id="bulkResult" style="display:none"></div>
      `,
      buttons: [
        { label: 'انصراف', class: 'btn-ghost' },
        {
          label: 'افزودن',
          class: 'btn-primary',
          close: false,
          onClick: async () => {
            const text = document.getElementById('bulkText').value;
            if (!text.trim()) {
              Toast.show('متن سوالات را وارد کنید', 'error');
              return false;
            }
            try {
              const res = await api('questions.php', {
                action: 'bulk_import',
                text,
              });
              let html = `<div style="background:rgba(22,163,74,0.1);border:1px solid rgba(22,163,74,0.3);color:#15803D;padding:12px 16px;border-radius:10px;font-weight:700">✅ ${res.imported} سوال اضافه شد</div>`;
              if (res.errors && res.errors.length) {
                html += `<div style="margin-top:10px;background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.25);color:#B91C1C;padding:12px 16px;border-radius:10px;font-size:0.85rem;line-height:1.9">
                  <strong>خطاها:</strong><br>${res.errors.map((e) => '• ' + escapeHtml(e)).join('<br>')}
                </div>`;
              }
              const resDiv = document.getElementById('bulkResult');
              resDiv.innerHTML = html;
              resDiv.style.display = 'block';

              Toast.show(`${res.imported} سوال اضافه شد`, 'success');
              loadQuestions();
              refreshStats();

              if (!res.errors || res.errors.length === 0) {
                setTimeout(() => Modal.close(), 1500);
              }
            } catch (e) {
              Toast.show(e.message, 'error');
            }
            return false;
          },
        },
      ],
    });
  }

  /* ═══════════════════════════════════════════════════════
     PARTICIPANTS
     ═══════════════════════════════════════════════════════ */
  async function loadParticipants() {
    const status = $('#filterStatus').value;
    const search = $('#filterName').value.trim();

    try {
      const res = await api('participants.php', { action: 'list', status, search });
      renderParticipants(res.participants || []);
    } catch (e) { Toast.show(e.message, 'error'); }
  }

  function renderParticipants(list) {
    const tbody = $('#participantsTable tbody');
    const empty = $('#participantsEmpty');
    if (!list.length) {
      tbody.innerHTML = '';
      empty.hidden = false;
      return;
    }
    empty.hidden = true;

    const badgeCls = {
      waiting: 'badge-waiting',
      playing: 'badge-playing',
      eliminated: 'badge-eliminated',
      winner: 'badge-winner',
    };

    tbody.innerHTML = list.map((p, i) => `
      <tr>
        <td class="num">${i + 1}</td>
        <td>${escapeHtml(p.name)}</td>
        <td class="num" style="font-size:0.82rem">${p.joined_at ? p.joined_at.substring(11, 19) : '—'}</td>
        <td class="num">${p.current_q > 0 ? p.current_q : '—'}</td>
        <td><span class="status-badge ${badgeCls[p.status] || ''}">${p.status_fa}</span></td>
        <td>${p.won_code ? `<span class="code-tag">${p.won_code}</span>` : '—'}</td>
        <td>
          <button class="icon-btn danger" data-del="${p.id}" title="حذف">
            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
          </button>
        </td>
      </tr>
    `).join('');

    $$('[data-del]', tbody).forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = Number(btn.dataset.del);
        const p = list.find((x) => x.id === id);
        Modal.open({
          title: 'حذف شرکت‌کننده',
          body: `<p>آیا از حذف «${escapeHtml(p.name)}» مطمئن هستید؟</p>`,
          buttons: [
            { label: 'انصراف', class: 'btn-ghost' },
            {
              label: 'حذف', class: 'btn-danger',
              onClick: async () => {
                try {
                  await api('participants.php', { action: 'delete', id });
                  Toast.show('حذف شد', 'success');
                  loadParticipants();
                  refreshStats();
                } catch (e) { Toast.show(e.message, 'error'); }
              },
            },
          ],
        });
      });
    });
  }

  /* ═══════════════════════════════════════════════════════
     WINNERS
     ═══════════════════════════════════════════════════════ */
  async function loadWinners() {
    const search = $('#filterWinner').value.trim();
    try {
      const res = await api('winners.php', { action: 'list', search });
      renderWinners(res.winners || []);
    } catch (e) { Toast.show(e.message, 'error'); }
  }

  function renderWinners(list) {
    const tbody = $('#winnersTable tbody');
    const empty = $('#winnersEmpty');
    if (!list.length) {
      tbody.innerHTML = '';
      empty.hidden = false;
      return;
    }
    empty.hidden = true;

    tbody.innerHTML = list.map((w, i) => `
      <tr>
        <td class="num">${i + 1}</td>
        <td>${escapeHtml(w.name)}</td>
        <td><span class="code-tag">${w.won_code}</span></td>
        <td class="num" style="font-size:0.82rem">${w.won_at ? w.won_at.substring(11, 16) : '—'}</td>
        <td>
          ${w.claimed == 1
            ? '<span class="status-badge badge-claimed">✓ دریافت شد</span>'
            : '<span class="status-badge badge-waiting">در انتظار دریافت</span>'}
        </td>
        <td>
          <button class="btn ${w.claimed == 1 ? 'btn-ghost' : 'btn-primary'} btn-sm" data-claim="${w.id}" data-claimed="${w.claimed}">
            ${w.claimed == 1 ? 'لغو دریافت' : 'علامت‌گذاری دریافت'}
          </button>
        </td>
      </tr>
    `).join('');

    $$('[data-claim]', tbody).forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = Number(btn.dataset.claim);
        const claimed = btn.dataset.claimed === '1';
        try {
          await api('winners.php', { action: claimed ? 'unclaim' : 'claim', id });
          Toast.show(claimed ? 'علامت برداشته شد' : 'علامت‌گذاری شد', 'success');
          loadWinners();
          refreshStats();
        } catch (e) { Toast.show(e.message, 'error'); }
      });
    });
  }

  function exportWinnersCsv() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = API + 'winners.php';
    form.target = '_blank';

    const inputs = { action: 'export_csv', csrf: State.csrf };
    Object.entries(inputs).forEach(([k, v]) => {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = v;
      form.appendChild(i);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
  }

  /* ═══════════════════════════════════════════════════════
     SETTINGS
     ═══════════════════════════════════════════════════════ */
    async function loadSettings() {
    try {
      const res = await api('settings.php', { action: 'get' });
      const s = res.settings || {};
      $('#setQuestionTime').value = s.question_time ?? 30;
      $('#setBreakTime').value = s.break_time ?? 5;
      $('#setTotalQ').value = s.total_questions ?? 10;
      $('#setMsgWaiting').value = s.msg_waiting || '';
      $('#setMsgWinner').value = s.msg_winner || '';
      $('#setMsgElim').value = s.msg_eliminated || '';
      $('#setEstimatedStart').value = s.estimated_start || '';
      $('#setQuizDuration').value = s.quiz_duration || '';
      State.totalQ = s.total_questions ?? 10;
      $('#totalQInfo').textContent = State.totalQ;
    } catch (e) { console.warn(e); }
  }

    async function saveSettings(e) {
    e.preventDefault();
    const btn = $('#btnSaveSettings');
    const label = btn.querySelector('.btn-label');
    const spin = btn.querySelector('.btn-spinner');

    btn.disabled = true;
    label.textContent = 'در حال ذخیره…';
    spin.hidden = false;

    const quizDuration = $('#setQuizDuration').value.trim();

    try {
      await api('settings.php', {
        action: 'save',
        question_time: Number($('#setQuestionTime').value),
        break_time: Number($('#setBreakTime').value),
        total_questions: Number($('#setTotalQ').value),
        msg_waiting: $('#setMsgWaiting').value.trim(),
        msg_winner: $('#setMsgWinner').value.trim(),
        msg_eliminated: $('#setMsgElim').value.trim(),
        estimated_start: $('#setEstimatedStart').value.trim(),
        quiz_duration: quizDuration === '' ? 0 : Number(quizDuration),
      });
      Toast.show('تنظیمات ذخیره شد', 'success');
      State.totalQ = Number($('#setTotalQ').value);
      $('#totalQInfo').textContent = State.totalQ;
    } catch (err) {
      Toast.show(err.message, 'error');
    } finally {
      btn.disabled = false;
      label.textContent = 'ذخیره تنظیمات';
      spin.hidden = true;
    }
  }

  /* ═══════════════════════════════════════════════════════
     CLEAR STATS
     ═══════════════════════════════════════════════════════ */
  function onClearStats() {
    const date = $('#clearDate').value;
    const scope = $('#clearScope').value;

    if (!date) return Toast.show('تاریخ را انتخاب کنید', 'error');

    Modal.open({
      title: 'تأیید حذف آمار',
      body: `<p>آمار تاریخ <strong dir="ltr">${date}</strong> حذف می‌شود.</p>
             <p class="muted">این عمل قابل بازگشت نیست.</p>`,
      buttons: [
        { label: 'انصراف', class: 'btn-ghost' },
        {
          label: 'تأیید و حذف', class: 'btn-danger',
          onClick: async () => {
            try {
              const res = await api('clear_stats.php', { date, scope });
              Toast.show(res.message, 'success');
              refreshStats();
            } catch (e) { Toast.show(e.message, 'error'); }
          },
        },
      ],
    });
  }

  /* ═══════════════════════════════════════════════════════
     HISTORY
     ═══════════════════════════════════════════════════════ */
  async function loadHistory(force = false) {
    if (State.historyLoaded && !force) return;

    const wrap = $('#historyList');
    wrap.innerHTML = '<div class="history-empty">در حال بارگذاری…</div>';

    try {
      const res = await api('history.php', { action: 'list' });
      const list = res.history || [];

      if (!list.length) {
        wrap.innerHTML = '<div class="history-empty"><p>هنوز مسابقه‌ای برگزار نشده است</p></div>';
        return;
      }

      wrap.innerHTML = list.map((h) => {
        const d = formatDateFa(h.date);
        const dur = formatDuration(h.duration);
        return `
          <div class="history-card" data-date="${h.date}">
            <div class="history-card-head">
              <div class="history-date">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2"/>
                  <path d="M16 2v4M8 2v4M3 10h18"/>
                </svg>
                ${d}
              </div>
              ${dur ? `<span class="history-duration">${dur}</span>` : ''}
            </div>
            <div class="history-stats">
              <div class="history-stat history-stat-participants">
                <div class="history-stat-num">${faNum(h.participants)}</div>
                <div class="history-stat-label">شرکت‌کننده</div>
              </div>
              <div class="history-stat history-stat-eliminated">
                <div class="history-stat-num">${faNum(h.eliminated)}</div>
                <div class="history-stat-label">حذف‌شده</div>
              </div>
              <div class="history-stat history-stat-winners">
                <div class="history-stat-num">${faNum(h.winners)}</div>
                <div class="history-stat-label">برنده</div>
              </div>
            </div>
          </div>
        `;
      }).join('');

      $$('.history-card', wrap).forEach((card) => {
        card.addEventListener('click', () => openHistoryDetails(card.dataset.date));
      });

      State.historyLoaded = true;
    } catch (e) {
      wrap.innerHTML = `<div class="history-empty"><p>خطا: ${escapeHtml(e.message)}</p></div>`;
    }
  }

  async function openHistoryDetails(date) {
    Modal.open({
      title: `جزئیات مسابقه ${formatDateFa(date)}`,
      body: '<div style="padding:20px;text-align:center;color:#94A3B8">در حال بارگذاری…</div>',
      buttons: [{ label: 'بستن', class: 'btn-ghost' }],
    });

    try {
      const res = await api('history.php', { action: 'details', date });

      const s = res.summary || {};
      const qs = res.questions || [];
      const winners = res.winners || [];

      let html = '<div class="history-details">';

      html += `
        <div class="history-details-summary">
          <div>
            <div class="num">${faNum(s.participants)}</div>
            <span class="lbl">شرکت‌کننده</span>
          </div>
          <div>
            <div class="num" style="color:#DC2626">${faNum(s.eliminated)}</div>
            <span class="lbl">حذف‌شده</span>
          </div>
          <div>
            <div class="num" style="color:#CA8A04">${faNum(s.winners)}</div>
            <span class="lbl">برنده</span>
          </div>
        </div>
      `;

      if (qs.length) {
        html += '<div><h4>آمار سوالات</h4><table class="history-q-table"><thead><tr>';
        html += '<th>#</th><th>درست</th><th>غلط</th><th>نرخ</th>';
        html += '</tr></thead><tbody>';
        qs.forEach((q) => {
          html += `<tr>
            <td>سوال ${q.order}</td>
            <td style="color:#16A34A;font-weight:700">${faNum(q.ok)}</td>
            <td style="color:#DC2626;font-weight:700">${faNum(q.fail)}</td>
            <td>${q.rate}%</td>
          </tr>`;
        });
        html += '</tbody></table></div>';
      }

      if (winners.length) {
        html += '<div><h4>برندگان</h4><div class="history-winners-list">';
        winners.forEach((w) => {
          html += `
            <div class="history-winner-row">
              <span class="history-winner-name">🏆 ${escapeHtml(w.name)}</span>
              <span class="history-winner-code">${w.won_code}</span>
            </div>
          `;
        });
        html += '</div></div>';
      }

      html += '</div>';

      $('#modalBody').innerHTML = html;
    } catch (e) {
      $('#modalBody').innerHTML = `<div style="padding:20px;text-align:center;color:#DC2626">خطا: ${escapeHtml(e.message)}</div>`;
    }
  }

  /* ═══════════════════════════════════════════════════════
     SERVER CLOCK
     ═══════════════════════════════════════════════════════ */
  function startServerClock() {
    setInterval(() => {
      const el = $('#serverTime');
      if (el) el.textContent = new Date().toLocaleTimeString('en-GB');
    }, 1000);
  }

  /* ═══════════════════════════════════════════════════════
     START
     ═══════════════════════════════════════════════════════ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      bindLogin();
      boot();
    });
  } else {
    bindLogin();
    boot();
  }

  window.__ADMIN_DEBUG__ = { State, api, refreshStats, loadParticipants, loadWinners, loadQuestions };

})();