/* ══════════════════════════════════════════════════════════════
   موکب احمدآباد — main.js (نسخه نهایی 1.8.3)
   ══════════════════════════════════════════════════════════════ */
'use strict';

(function () {

  const CFG = window.__MOKEB__ || {};
  const API_BASE = CFG.apiBase || 'api/';

  const OPTION_LETTERS = { A: 'الف', B: 'ب', C: 'ج', D: 'د' };
  const optionLetter = (k) => OPTION_LETTERS[k] || k;

  const LS = {
    sessionId:  'mokeb_session_id_v1',
    name:       'mokeb_name_v1',
    joinedDate: 'mokeb_date_v1',
  };

  const State = {
    sessionId: null,
    name: null,
    pageLoad: null,
    joinedDate: null,
    status: 'idle',
    currentOrder: 0,
    displayedQ: 0,
    currentView: 'idle',
    totalQ: CFG.totalQ || 10,
    questionTime: CFG.qTime || 30,
    quizDuration: 0,
    breakTime: CFG.breakTime || 5,
    waitingCount: 0,
    estimatedStart: '',
    pollHandle: null,
    pollAbort: null,
    countdownTimer: null,
    selectedKey: null,
    answerSubmitted: false,
    answerLocked: false,
    audioCtx: null,
    booted: false,
    currentQuestion: null,
    siteOpen: false,
    timeToStart: null,
    timeToEnd: null,
    startTimerHandle: null,
    endTimerHandle: null,
  };

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const setText = (el, t) => { if (el) el.textContent = t; };

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function formatDuration(sec) {
    sec = Math.max(0, Math.floor(sec));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    if (h > 0) {
      return String(h).padStart(2, '0') + ':' +
             String(m).padStart(2, '0') + ':' +
             String(s).padStart(2, '0');
    }
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
  }

  const Store = {
    get(k) { try { return localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch {} },
    del(k) { try { localStorage.removeItem(k); } catch {} },
    clearSession() {
      this.del(LS.sessionId);
      this.del(LS.joinedDate);
    },
    loadIdentity() {
      State.sessionId  = this.get(LS.sessionId);
      State.name       = this.get(LS.name);
      State.joinedDate = this.get(LS.joinedDate);
    },
    saveIdentity(id, name, date) {
      this.set(LS.sessionId, id);
      this.set(LS.name, name);
      this.set(LS.joinedDate, date);
      State.sessionId = id;
      State.name = name;
      State.joinedDate = date;
    },
  };

  async function api(endpoint, data = {}, opts = {}) {
    const method = opts.method || 'POST';
    const signal = opts.signal || null;
    const timeout = opts.timeout || 15000;

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);

    if (signal) {
      if (signal.aborted) {
        clearTimeout(timer);
        throw new DOMException('Aborted', 'AbortError');
      }
      signal.addEventListener('abort', () => controller.abort(), { once: true });
    }

    const body = Object.assign({}, data);
    if (CFG.csrf && method !== 'GET') body.csrf = CFG.csrf;

    try {
      const res = await fetch(API_BASE + endpoint, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: method === 'GET' ? undefined : JSON.stringify(body),
        signal: controller.signal,
      });

      clearTimeout(timer);
      const json = await res.json().catch(() => ({}));

      if (!res.ok || json.ok === false) {
        const err = new Error(json.error || ('خطای سرور ' + res.status));
        err.status = res.status;
        throw err;
      }
      return json;
    } catch (e) {
      clearTimeout(timer);
      if (e.name === 'AbortError') throw e;
      if (e.status) throw e;
      throw new Error('ارتباط با سرور برقرار نشد');
    }
  }

  const Toast = (function () {
    const wrap = document.getElementById('toastWrap');
    const icons = {
      success: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
      error:   '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
      warn:    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>',
      info:    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    };

    return {
      show(msg, type, dur) {
        type = type || 'info';
        dur = dur || 3200;
        if (!wrap) return;
        const el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.innerHTML = '<span class="toast-icon">' + (icons[type] || icons.info) + '</span>' +
          '<span class="toast-msg">' + escapeHtml(msg) + '</span>';
        wrap.appendChild(el);
        setTimeout(function () {
          el.classList.add('toast-out');
          setTimeout(function () { el.remove(); }, 500);
        }, dur);
      },
    };
  })();

  const Sound = (function () {
    function ctx() {
      if (State.audioCtx) return State.audioCtx;
      try {
        const C = window.AudioContext || window.webkitAudioContext;
        if (!C) return null;
        State.audioCtx = new C();
      } catch (e) { return null; }
      return State.audioCtx;
    }

    function tone(freq, dur, type, vol) {
      dur = dur || 0.12;
      type = type || 'sine';
      vol = vol || 0.12;
      const ac = ctx();
      if (!ac) return;
      if (ac.state === 'suspended') ac.resume().catch(function () {});

      const o = ac.createOscillator();
      const g = ac.createGain();
      o.type = type;
      o.frequency.value = freq;
      g.gain.setValueAtTime(vol, ac.currentTime);
      g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + dur);
      o.connect(g);
      g.connect(ac.destination);
      o.start();
      o.stop(ac.currentTime + dur);
    }

    return {
      tick: function () { tone(880, 0.06, 'square', 0.08); },
      ok: function () {
        tone(660, 0.12, 'sine', 0.14);
        setTimeout(function () { tone(880, 0.18, 'sine', 0.14); }, 110);
      },
      fail: function () {
        tone(200, 0.22, 'sawtooth', 0.12);
        setTimeout(function () { tone(140, 0.28, 'sawtooth', 0.10); }, 130);
      },
      win: function () {
        [523, 659, 784, 1047].forEach(function (f, i) {
          setTimeout(function () { tone(f, 0.28, 'triangle', 0.14); }, i * 130);
        });
      },
      newQuestion: function () {
        tone(523, 0.1, 'sine', 0.18);
        setTimeout(function () { tone(659, 0.1, 'sine', 0.18); }, 110);
        setTimeout(function () { tone(784, 0.18, 'sine', 0.18); }, 220);
      },
    };
  })();

  const Hadith = (function () {
    const list = window.MOKEB_HADITHS || [];
    let currentIndex = -1;
    let handle = null;

    function randomIndex() {
      if (list.length <= 1) return 0;
      let idx;
      do {
        idx = Math.floor(Math.random() * list.length);
      } while (idx === currentIndex);
      return idx;
    }

    function show() {
      const box = document.getElementById('hadithBox');
      const textEl = document.getElementById('hadithText');
      const srcEl = document.getElementById('hadithSrc');
      if (!box || !textEl || !srcEl || !list.length) return;

      currentIndex = randomIndex();
      const h = list[currentIndex];

      box.classList.add('is-fading');
      setTimeout(function () {
        textEl.textContent = h.fa;
        srcEl.textContent = h.src;
        box.classList.remove('is-fading');
      }, 400);
    }

    return {
      startRotation: function () {
        this.stopRotation();
        show();
        handle = setInterval(show, 8000);
      },
      stopRotation: function () {
        if (handle) { clearInterval(handle); handle = null; }
      },
    };
  })();

  function showView(name) {
    $$('.view').forEach(function (v) { v.hidden = true; });
    const el = document.getElementById('view-' + name);
    if (el) el.hidden = false;
    State.currentView = name;

    const hp = document.getElementById('headerProgress');
    const hpb = document.getElementById('headerProgressBar');
    if (hp && hpb) {
      if (name === 'question' || name === 'break' || name === 'others') {
        hp.hidden = false;
        const pct = State.totalQ > 0
          ? Math.min(100, (State.currentOrder / State.totalQ) * 100)
          : 0;
        hpb.style.width = pct + '%';
      } else {
        hp.hidden = true;
      }
    }
  }

  function setUserName(name) {
    const chip = document.getElementById('userChip');
    const n = document.getElementById('userChipName');
    if (chip && n && name) {
      n.textContent = name;
      chip.hidden = false;
    }
  }

  document.addEventListener('pointerdown', function (e) {
    const btn = e.target.closest('.btn, .option');
    if (!btn || btn.disabled) return;
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const r = document.createElement('span');
    r.className = 'ripple';
    r.style.width = r.style.height = size + 'px';
    r.style.left = (e.clientX - rect.left - size / 2) + 'px';
    r.style.top  = (e.clientY - rect.top - size / 2) + 'px';
    btn.appendChild(r);
    setTimeout(function () { r.remove(); }, 700);
  });

  function startCountdown(numId, circleId, seconds) {
    stopCountdown();
    const num = document.getElementById(numId);
    const circle = document.getElementById(circleId);
    if (!num) return;

    const CIRC = 2 * Math.PI * 44;
    if (circle) {
      circle.style.strokeDasharray = CIRC;
      circle.style.strokeDashoffset = 0;
    }

    const start = performance.now();
    const total = Math.max(0.1, seconds);
    let lastBeep = Math.ceil(total);
    num.textContent = Math.ceil(total);

    State.countdownTimer = setInterval(function () {
      const elapsed = (performance.now() - start) / 1000;
      const remain = Math.max(0, total - elapsed);
      const disp = Math.ceil(remain);
      num.textContent = disp;

      if (circle) {
        const off = (elapsed / total) * CIRC;
        circle.style.strokeDashoffset = off.toFixed(2);
      }

      if (disp <= 5 && disp > 0 && disp !== lastBeep) {
        Sound.tick();
        lastBeep = disp;
      }

      if (remain <= 0) {
        clearInterval(State.countdownTimer);
        State.countdownTimer = null;

        if (!State.answerSubmitted && !State.answerLocked) {
          if (State.selectedKey) {
            State.answerSubmitted = true;
            State.answerLocked = true;
            $$('.option').forEach(function (o) { o.disabled = true; });
            const btnSubmit = document.getElementById('btnSubmitAnswer');
            if (btnSubmit) btnSubmit.disabled = true;
            submitAnswer(State.selectedKey);
          } else {
            submitAnswer('NONE');
          }
        }
      }
    }, 100);
  }

  function stopCountdown() {
    if (State.countdownTimer) {
      clearInterval(State.countdownTimer);
      State.countdownTimer = null;
    }
  }

  function startStartTimer() {
    stopStartTimer();
    if (State.timeToStart == null || State.timeToStart <= 0) {
      updateStartCountdown(null);
      return;
    }
    let remaining = State.timeToStart;
    updateStartCountdown(remaining);

    State.startTimerHandle = setInterval(function () {
      remaining--;
      if (remaining <= 0) {
        remaining = 0;
        stopStartTimer();
      }
      updateStartCountdown(remaining);
    }, 1000);
  }

  function stopStartTimer() {
    if (State.startTimerHandle) {
      clearInterval(State.startTimerHandle);
      State.startTimerHandle = null;
    }
  }

  function updateStartCountdown(seconds) {
    const wrap = document.getElementById('startCountdown');
    const val = document.getElementById('startCountdownValue');
    if (!wrap || !val) return;
    if (seconds == null || seconds <= 0) {
      wrap.hidden = true;
      return;
    }
    wrap.hidden = false;
    val.textContent = formatDuration(seconds);
    wrap.classList.toggle('is-low', seconds <= 30);
  }

  function startEndTimer() {
    stopEndTimer();
    if (State.timeToEnd == null || State.timeToEnd <= 0) {
      updateEndTimer(null);
      return;
    }
    let remaining = State.timeToEnd;
    updateEndTimer(remaining);

    State.endTimerHandle = setInterval(function () {
      remaining--;
      if (remaining <= 0) {
        remaining = 0;
        stopEndTimer();
      }
      updateEndTimer(remaining);
    }, 1000);
  }

  function stopEndTimer() {
    if (State.endTimerHandle) {
      clearInterval(State.endTimerHandle);
      State.endTimerHandle = null;
    }
  }

  function updateEndTimer(seconds) {
    const qWrap = document.getElementById('endTimerQuestion');
    const qVal = document.getElementById('endTimeQuestion');
    const oWrap = document.getElementById('endTimerOthers');
    const oVal = document.getElementById('endTimeOthers');

    if (seconds == null || seconds <= 0) {
      if (qWrap) qWrap.hidden = true;
      if (oWrap) oWrap.hidden = true;
      return;
    }
    const txt = formatDuration(seconds);
    if (qWrap) qWrap.hidden = false;
    if (qVal) qVal.textContent = txt;
    if (oWrap) oWrap.hidden = false;
    if (oVal) oVal.textContent = txt;
  }

  function showPrepCountdown(seconds) {
    seconds = seconds || 3;
    return new Promise(function (resolve) {
      const overlay = document.getElementById('countdownOverlay');
      const numEl = document.getElementById('countdownNum');
      if (!overlay || !numEl) { resolve(); return; }

      overlay.hidden = false;
      let n = seconds;

      function step() {
        if (n <= 0) {
          overlay.hidden = true;
          Sound.ok();
          resolve();
          return;
        }
        numEl.textContent = n;
        numEl.style.animation = 'none';
        void numEl.offsetWidth;
        numEl.style.animation = 'countdownPop 1s cubic-bezier(0.34, 1.56, 0.64, 1)';
        Sound.tick();
        n--;
        setTimeout(step, 1000);
      }
      step();
    });
  }

  function viewClosed() {
    stopCountdown();
    stopPolling();
    stopStartTimer();
    stopEndTimer();
    showView('closed');
    startPolling(3000);
  }

  function viewNameEntry() {
    stopCountdown();
    stopPolling();
    stopStartTimer();
    stopEndTimer();

    const form = document.getElementById('formName');
    const input = document.getElementById('inputName');
    const errEl = document.getElementById('nameError');

    if (State.name && input && !input.value) input.value = State.name;
    if (errEl) errEl.hidden = true;

    showView('name');
    setTimeout(function () { if (input) input.focus(); }, 250);

    if (form && !form.dataset.bound) {
      form.dataset.bound = '1';
      form.addEventListener('submit', onSubmitName);
      if (input) {
        input.addEventListener('input', function () {
          input.classList.remove('is-invalid');
          if (errEl) errEl.hidden = true;
        });
      }
    }
  }

  function viewWaiting() {
    stopCountdown();
    Hadith.stopRotation();
    setText(document.getElementById('waitingName'), State.name || '—');
    setUserName(State.name);
    updateWaitingInfo();
    showView('waiting');
    startStartTimer();
    startPolling(2000);
  }

  function updateWaitingInfo(data) {
    if (data) {
      if (data.waiting_count != null) {
        State.waitingCount = Number(data.waiting_count) || 0;
      }
      if (data.total_q) State.totalQ = Number(data.total_q);
      if (data.question_time) State.questionTime = Number(data.question_time);
      if (data.quiz_duration) State.quizDuration = Number(data.quiz_duration);
    }

    const onlineEl = document.getElementById('waitingOnline');
    if (onlineEl) {
      onlineEl.textContent = State.waitingCount.toLocaleString('en-US');
    }

    const durationEl = document.getElementById('waitingDuration');
    if (durationEl) {
      let totalSec = State.quizDuration || 0;
      if (!totalSec) {
        totalSec = (State.totalQ * State.questionTime) + 30;
      }
      durationEl.textContent = formatDuration(totalSec);
    }
  }

  function viewQuestion(q, timeLeft) {
    State.currentQuestion = q;
    State.currentOrder = q.order;
    State.displayedQ = q.order;
    State.answerLocked = false;
    State.selectedKey = null;
    State.answerSubmitted = false;
    setUserName(State.name);

    setText(document.getElementById('qCurrent'), q.order);
    setText(document.getElementById('qTotal'), q.total || State.totalQ);

    const inner = document.getElementById('qProgressFill');
    if (inner) {
      const pct = ((q.order - 1) / (q.total || State.totalQ)) * 100;
      inner.style.width = pct + '%';
    }

    setText(document.getElementById('qText'), q.text);

    if (q.players_left != null) {
      setText(document.getElementById('qPlayersLeft'), q.players_left);
    }

    const wrap = document.getElementById('qOptions');
    wrap.innerHTML = '';
    Object.keys(q.options || {}).forEach(function (key) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'option';
      btn.dataset.key = key;
      btn.innerHTML =
        '<span class="option-key">' + escapeHtml(optionLetter(key)) + '</span>' +
        '<span class="option-text">' + escapeHtml(q.options[key]) + '</span>';
      btn.addEventListener('click', function () { onOptionClick(key, btn); });
      wrap.appendChild(btn);
    });

    const fb = document.getElementById('qFeedback');
    if (fb) {
      fb.hidden = true;
      fb.className = 'q-feedback';
      fb.textContent = '';
    }

    const btnSubmit = document.getElementById('btnSubmitAnswer');
    if (btnSubmit) {
      btnSubmit.disabled = true;
      const lbl = btnSubmit.querySelector('.btn-label');
      if (lbl) lbl.textContent = 'برای ثبت پاسخ یک گزینه را انتخاب کنید';
      btnSubmit.onclick = onSubmitAnswer;
    }

    showView('question');

    const timerSeconds = timeLeft || q.time_limit || 30;

    if (q.order === 1) {
      showPrepCountdown(3).then(function () {
        startCountdown('timerNum', 'timerCircle', timerSeconds);
        Sound.newQuestion();
      });
    } else {
      startCountdown('timerNum', 'timerCircle', timerSeconds);
      Sound.newQuestion();
    }

    startPolling(2500);
    startEndTimer();
  }

  function viewOthers(timeLeft) {
    stopCountdown();
    showView('others');
    startCountdown('othersTimerNum', 'othersTimerCircle', timeLeft || 30);
    Hadith.startRotation();
    startPolling(1200);
    startEndTimer();
  }

  function viewBreak(timeLeft, nextQ) {
    stopCountdown();
    State.currentOrder = (nextQ || 1) - 1;
    showView('break');
    startCountdown('breakNum', 'breakRing', timeLeft || State.breakTime);
    startPolling(1200);
    startEndTimer();
  }

  function viewEliminated(qNum) {
    stopCountdown();
    stopPolling();
    stopEndTimer();
    State.status = 'eliminated';
    setUserName(State.name);
    setText(document.getElementById('elimQNum'), qNum || State.currentOrder || '—');

    const btn = document.getElementById('btnCloseElim');
    if (btn && !btn.dataset.bound) {
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        Store.clearSession();
        State.sessionId = null;
        State.joinedDate = null;
        Toast.show('برای شرکت دوباره، بعداً مراجعه کنید', 'info', 4000);
      });
    }
    showView('eliminated');
    Sound.fail();
  }

  function viewWinner(code) {
    stopCountdown();
    stopPolling();
    stopEndTimer();
    State.status = 'winner';
    setUserName(State.name);
    setText(document.getElementById('winnerName'), State.name || '—');
    setText(document.getElementById('codeValue'), code || '─────');

    const btn = document.getElementById('btnCopyCode');
    if (btn && !btn.dataset.bound) {
      btn.dataset.bound = '1';
      btn.addEventListener('click', onCopyCode);
    }
    showView('winner');
    Sound.win();
    setTimeout(function () { launchConfetti(6000); }, 300);
  }

  function viewError(msg) {
    setText(document.getElementById('errorMessage'), msg || 'ارتباط با سرور قطع شد.');
    const btn = document.getElementById('btnRetry');
    if (btn && !btn.dataset.bound) {
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () { boot(true); });
    }
    showView('error');
  }

  async function onSubmitName(e) {
    e.preventDefault();

    const input = document.getElementById('inputName');
    const errEl = document.getElementById('nameError');
    const btn   = document.getElementById('btnEnter');
    const label = btn && btn.querySelector('.btn-label');
    const spin  = btn && btn.querySelector('.btn-spinner');

    const name = (input.value || '').trim();

    if (name.length < 3) return showFieldError('نام باید حداقل ۳ کاراکتر باشد');
    if (!/^[\u0600-\u06FF\u200C\s]+$/.test(name)) {
      return showFieldError('لطفاً نام را با حروف فارسی وارد کنید');
    }
    if (name.length > 60) return showFieldError('نام بیش از حد طولانی است');

    if (btn) btn.disabled = true;
    if (label) label.textContent = 'در حال ثبت…';
    if (spin) spin.hidden = false;

    try {
      const res = await api('register.php', {
        name: name,
        page_load: State.pageLoad,
      });

      Store.saveIdentity(res.session_id, res.name || name, CFG.today);
      State.name = res.name || name;
      setUserName(State.name);

      Toast.show('با موفقیت ثبت‌نام شدید', 'success', 3000);
      viewWaiting();

    } catch (err) {
      showFieldError(err.message || 'خطا در ثبت‌نام');
      if (label) label.textContent = 'ورود به مسابقه';
      if (spin) spin.hidden = true;
      if (btn) btn.disabled = false;
    }
  }

  function showFieldError(msg) {
    const errEl = document.getElementById('nameError');
    const input = document.getElementById('inputName');
    const btn   = document.getElementById('btnEnter');
    const label = btn && btn.querySelector('.btn-label');
    const spin  = btn && btn.querySelector('.btn-spinner');

    if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
    if (input) { input.classList.add('is-invalid'); input.focus(); }
    if (label) label.textContent = 'ورود به مسابقه';
    if (spin) spin.hidden = true;
    if (btn) btn.disabled = false;
  }

  function onOptionClick(key, btn) {
    if (State.answerSubmitted) return;
    if (State.answerLocked) return;

    $$('.option').forEach(function (o) { o.classList.remove('is-selected'); });

    btn.classList.add('is-selected');
    State.selectedKey = key;

    const btnSubmit = document.getElementById('btnSubmitAnswer');
    if (btnSubmit) {
      btnSubmit.disabled = false;
      const lbl = btnSubmit.querySelector('.btn-label');
      if (lbl) lbl.textContent = 'ثبت پاسخ';
    }

    Sound.tick();
  }

  async function onSubmitAnswer() {
    if (State.answerSubmitted) return;
    if (!State.selectedKey) return;

    State.answerSubmitted = true;
    State.answerLocked = true;

    $$('.option').forEach(function (o) { o.disabled = true; });
    const btnSubmit = document.getElementById('btnSubmitAnswer');
    if (btnSubmit) btnSubmit.disabled = true;

    await submitAnswer(State.selectedKey);
  }

  async function submitAnswer(chosen) {
    if (!State.currentQuestion) return;
    stopCountdown();

    const fb = document.getElementById('qFeedback');
    const q = State.currentQuestion;

    if (chosen === 'NONE' && fb) {
      fb.className = 'q-feedback timeout';
      fb.textContent = 'زمان تمام شد!';
      fb.hidden = false;
    }

    try {
      const res = await api('answer.php', {
        session_id: State.sessionId,
        page_load: State.pageLoad,
        order: q.order,
        chosen: chosen,
      });

      highlightAnswer(q.order, chosen, res.correct_answer);

      if (res.is_correct) {
        if (fb) {
          fb.className = 'q-feedback ok';
          fb.textContent = '✓ صحیح!';
          fb.hidden = false;
        }
        Sound.ok();
      } else {
        if (fb) {
          fb.className = 'q-feedback fail';
          fb.textContent = chosen === 'NONE'
            ? ('✕ زمان تمام شد — پاسخ صحیح: ' + optionLetter(res.correct_answer))
            : ('✕ اشتباه — پاسخ صحیح: ' + optionLetter(res.correct_answer));
          fb.hidden = false;
        }
        Sound.fail();
      }

      State.currentOrder = q.order;

      if (!res.is_correct) {
        setTimeout(function () { viewEliminated(q.order); }, 2000);
        return;
      }

      if (res.is_winner) {
        setTimeout(function () { viewWinner(res.won_code); }, 1800);
        return;
      }

      setTimeout(function () {
        viewOthers(res.time_left || 25);
      }, 1800);

    } catch (err) {
      if (err.status === 403 || err.status === 404 || err.status === 401) {
        Toast.show(err.message || 'دسترسی غیرمجاز', 'error', 4000);
        viewEliminated(q.order);
        return;
      }
      if (err.status === 409) {
        Toast.show(err.message || 'این سوال بسته شده', 'warn', 3000);
        startPolling(1000);
        return;
      }
      Toast.show(err.message || 'خطا در ارسال پاسخ', 'error', 4000);
      State.answerLocked = false;
      $$('.option').forEach(function (o) { o.disabled = false; });
    }
  }

  function highlightAnswer(order, chosen, correctKey) {
    $$('.option').forEach(function (opt) {
      const k = opt.dataset.key;
      if (k === correctKey) opt.classList.add('is-correct');
      else if (k === chosen && chosen !== correctKey) opt.classList.add('is-wrong');
      else opt.classList.add('is-dimmed');
    });
  }

  async function fetchQuestion(order, timeLeft) {
    try {
      const res = await api('question.php', {
        session_id: State.sessionId,
        page_load: State.pageLoad,
        order: order,
      });
      viewQuestion(res, res.time_left || timeLeft);
    } catch (err) {
      if (err.status === 403 || err.status === 404 || err.status === 409) {
        startPolling(1000);
        return;
      }
      Toast.show(err.message || 'خطا در دریافت سوال', 'error', 4000);
      setTimeout(function () { startPolling(1500); }, 2000);
    }
  }

  async function onCopyCode() {
    const codeEl = document.getElementById('codeValue');
    const btn = document.getElementById('btnCopyCode');
    if (!codeEl) return;

    const code = codeEl.textContent.trim();
    if (!code || code === '─────') return;

    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(code);
      } else {
        const ta = document.createElement('textarea');
        ta.value = code;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
      }
      Toast.show('کد کپی شد ✓', 'success', 2500);
      if (btn) {
        btn.classList.add('copied');
        setTimeout(function () { btn.classList.remove('copied'); }, 2200);
      }
    } catch (e) {
      Toast.show('کپی خودکار ممکن نشد — دستی یادداشت کنید', 'warn', 4000);
    }
  }

  function startPolling(interval) {
    interval = interval || 2000;
    stopPolling();
    pollOnce();
    State.pollHandle = setInterval(pollOnce, interval);
  }

  function stopPolling() {
    if (State.pollHandle) {
      clearInterval(State.pollHandle);
      State.pollHandle = null;
    }
    if (State.pollAbort) {
      try { State.pollAbort.abort(); } catch (e) {}
      State.pollAbort = null;
    }
  }

  async function pollOnce() {
    if (!State.sessionId) return;

    if (State.pollAbort) {
      try { State.pollAbort.abort(); } catch (e) {}
    }
    State.pollAbort = new AbortController();

    try {
      const res = await api('status.php', {
        session_id: State.sessionId,
        page_load: State.pageLoad,
      }, { signal: State.pollAbort.signal });

      State.pollAbort = null;

      if (res.name) {
        State.name = res.name;
        setUserName(res.name);
      }

      if (res.time_to_end != null) State.timeToEnd = res.time_to_end;
      if (res.time_to_start != null) State.timeToStart = res.time_to_start;
      if (res.quiz_duration) State.quizDuration = Number(res.quiz_duration);

      switch (res.phase) {
        case 'site_closed':
          viewClosed();
          return;

        case 'waiting':
          if (State.currentView !== 'waiting') {
            viewWaiting();
          } else {
            updateWaitingInfo(res);
            if (State.timeToStart != null) startStartTimer();
          }
          break;

        case 'question':
          if (State.displayedQ !== res.q_num || State.currentView !== 'question') {
            fetchQuestion(res.q_num, res.time_left);
          } else if (res.time_to_end != null) {
            updateEndTimer(res.time_to_end);
          }
          if (res.players_left != null) {
            setText(document.getElementById('qPlayersLeft'), res.players_left);
          }
          break;

        case 'answered_waiting':
          if (State.currentView !== 'others') viewOthers(res.time_left);
          break;

        case 'break':
          if (State.currentView !== 'break') viewBreak(res.time_left, res.next_q);
          break;

        case 'eliminated':
          viewEliminated(res.eliminated_at_q || State.currentOrder);
          break;

        case 'winner':
          viewWinner(res.won_code);
          break;

        case 'finished':
          stopPolling();
          break;
      }

    } catch (err) {
      if (err.name === 'AbortError') return;
      if (err.status === 404 || err.status === 401) {
        Store.clearSession();
        viewNameEntry();
        return;
      }
      console.warn('[Poll]', err.message);
    }
  }

  function launchConfetti(duration) {
    duration = duration || 5500;
    const canvas = document.getElementById('confettiCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    let W, H;

    function resize() {
      W = canvas.width  = canvas.clientWidth  * dpr;
      H = canvas.height = canvas.clientHeight * dpr;
    }
    resize();
    window.addEventListener('resize', resize);

    const colors = ['#16A34A','#0EA5E9','#FACC15','#EAB308','#DC2626','#FFFFFF','#22C55E','#38BDF8'];
    const particles = [];
    const COUNT = 140;
    const startTime = performance.now();

    for (let i = 0; i < COUNT; i++) {
      particles.push({
        x: Math.random() * W,
        y: -Math.random() * H * 0.6 - 20,
        w: (6 + Math.random() * 8) * dpr,
        h: (6 + Math.random() * 10) * dpr,
        vx: (Math.random() - 0.5) * 2.2 * dpr,
        vy: (1.4 + Math.random() * 2.2) * dpr,
        rot: Math.random() * Math.PI * 2,
        rotV: (Math.random() - 0.5) * 0.22,
        color: colors[(Math.random() * colors.length) | 0],
      });
    }

    let raf = null;
    function frame(now) {
      const t = (now - startTime) / 1000;
      ctx.clearRect(0, 0, W, H);
      const fade = t > 4 ? Math.max(0, 1 - (t - 4) / 1.5) : 1;

      for (let i = 0; i < particles.length; i++) {
        const p = particles[i];
        p.x += p.vx;
        p.y += p.vy;
        p.vy += 0.03 * dpr;
        p.rot += p.rotV;
        p.vx *= 0.999;

        const w = Math.abs(Math.cos(p.rot)) * p.w;

        ctx.save();
        ctx.globalAlpha = fade;
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);
        ctx.fillStyle = p.color;
        ctx.fillRect(-w / 2, -p.h / 2, w, p.h);
        ctx.restore();

        if (p.y > H + 40) {
          p.y = -20;
          p.x = Math.random() * W;
          p.vy = (1.4 + Math.random() * 2.2) * dpr;
        }
      }

      if (t < duration / 1000) {
        raf = requestAnimationFrame(frame);
      } else {
        ctx.clearRect(0, 0, W, H);
        window.removeEventListener('resize', resize);
      }
    }
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(frame);
  }

  function generatePageLoadToken() {
    const arr = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(arr);
    return Array.from(arr, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
  }

  async function boot(fromRetry) {
    if (State.booted && !fromRetry) return;
    State.booted = true;

    State.pageLoad = generatePageLoadToken();
    Store.loadIdentity();

    try {
      const site = await api('site-status.php', {}, { method: 'GET' });
      State.siteOpen = !!site.site_open;
      State.timeToStart = site.time_to_start;
      State.timeToEnd = site.time_to_end;

      if (!State.siteOpen) {
        viewClosed();
        return;
      }
    } catch (e) {}

    const today = CFG.today || '';

    if (State.sessionId && State.joinedDate === today) {
      try {
        const res = await api('status.php', {
          session_id: State.sessionId,
          page_load: State.pageLoad,
        });
        if (res.name) {
          State.name = res.name;
          setUserName(res.name);
        }

        if (res.time_to_start != null) State.timeToStart = res.time_to_start;
        if (res.time_to_end != null) State.timeToEnd = res.time_to_end;
        if (res.quiz_duration) State.quizDuration = Number(res.quiz_duration);

        switch (res.phase) {
          case 'site_closed': viewClosed(); break;
          case 'waiting': viewWaiting(); break;
          case 'question': fetchQuestion(res.q_num, res.time_left); break;
          case 'answered_waiting': viewOthers(res.time_left); break;
          case 'break': viewBreak(res.time_left, res.next_q); break;
          case 'eliminated': viewEliminated(res.eliminated_at_q); break;
          case 'winner': viewWinner(res.won_code); break;
          default: viewNameEntry();
        }
      } catch (err) {
        if (err.status === 404 || err.status === 401) {
          Store.clearSession();
          viewNameEntry();
        } else {
          viewNameEntry();
        }
      }
    } else {
      if (State.sessionId && State.joinedDate !== today) Store.clearSession();
      viewNameEntry();
    }
  }

  window.addEventListener('beforeunload', function () {
    stopPolling();
    stopCountdown();
    stopStartTimer();
    stopEndTimer();
  });

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      if (State.currentView === 'waiting' ||
          State.currentView === 'others' ||
          State.currentView === 'break') {
        stopPolling();
      }
    } else {
      if (State.currentView === 'waiting') startPolling(2000);
      else if (State.currentView === 'others') startPolling(1200);
      else if (State.currentView === 'break') startPolling(1200);
      else if (State.currentView === 'question') startPolling(2500);
    }
  });

  window.addEventListener('unhandledrejection', function (e) {
    console.error('[Unhandled]', e.reason);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { boot(); });
  } else {
    boot();
  }

  window.__MOKEB_DEBUG__ = { State: State, Store: Store, api: api, boot: boot, Hadith: Hadith, Sound: Sound };

})();