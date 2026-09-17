<?php
/**
 * موکب احمدآباد — صفحه عمومی مسابقه
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$quiz_running = quiz_is_running();
$total_q      = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);
$question_time= (int) setting_get('question_time', DEFAULT_QUESTION_TIME);
$break_time   = (int) setting_get('break_time', DEFAULT_BREAK_TIME);
$msg_waiting  = setting_get('msg_waiting', 'منتظر تأیید اپراتور باشید');
$msg_winner   = setting_get('msg_winner', 'تبریک! شما برنده شدید');
$msg_elim     = setting_get('msg_eliminated', 'متأسفانه از مسابقه حذف شدید');

secure_session();
$csrf = csrf_token();

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#16A34A">
<meta name="description" content="مسابقه زنده موکب احمدآباد — ۱۰ سوال، حذف تدریجی، کد برنده">
<meta name="robots" content="noindex, nofollow">

<meta property="og:title" content="مسابقه زنده موکب احمدآباد">
<meta property="og:description" content="در مسابقه ۱۰ سوالی موکب احمدآباد شرکت کنید و جایزه بگیرید">
<meta property="og:type" content="website">
<meta property="og:locale" content="fa_IR">

<title>موکب احمدآباد</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png?v=<?= APP_VERSION ?>">
<link rel="apple-touch-icon" href="assets/img/favicon.png?v=<?= APP_VERSION ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/css/style.css?v=<?= APP_VERSION ?>">
<link rel="preload" as="image" href="assets/img/spiritual-bg.svg">
</head>
<body data-quiz-running="<?= $quiz_running ? '1' : '0' ?>">

<div class="spiritual-bg" aria-hidden="true"></div>

<header class="site-header" id="siteHeader">
  <div class="header-inner">
    <div class="brand">
      <img src="assets/img/favicon.png" alt="موکب احمدآباد" class="brand-logo">
      <div class="brand-text">
        <span class="brand-title">موکب احمدآباد</span>
        <span class="brand-sub">مسابقه زنده</span>
      </div>
    </div>

    <div class="user-chip" id="userChip" hidden>
      <span class="user-chip-label">شرکت‌کننده</span>
      <span class="user-chip-name" id="userChipName">—</span>
    </div>
  </div>

  <div class="header-progress" id="headerProgress" hidden>
    <div class="header-progress-bar" id="headerProgressBar" style="width: 0%"></div>
  </div>
</header>

<main class="app" id="app">

  <!-- ═══════ VIEW 0 — سایت بسته ═══════ -->
  <section class="view" id="view-closed" data-view="closed" hidden>
    <div class="card card-closed">
      <div class="closed-icon" aria-hidden="true">
        <svg viewBox="0 0 64 64" width="80" height="80">
          <defs>
            <linearGradient id="cg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#F59E0B"/>
              <stop offset="1" stop-color="#D97706"/>
            </linearGradient>
          </defs>
          <circle cx="32" cy="32" r="28" fill="url(#cg)" opacity="0.12"/>
          <rect x="18" y="28" width="28" height="22" rx="4" fill="url(#cg)"/>
          <path d="M22 28V20a10 10 0 0 1 20 0v8" fill="none" stroke="url(#cg)" stroke-width="4" stroke-linecap="round"/>
          <circle cx="32" cy="39" r="3" fill="#FFFFFF"/>
          <rect x="30.5" y="39" width="3" height="6" fill="#FFFFFF"/>
        </svg>
      </div>
      <h2 class="card-title">منتظر باز کردن سایت توسط مجری باشید</h2>
      <p class="card-subtitle">به‌زودی سایت توسط اپراتور موکب باز خواهد شد</p>
      <div class="closed-hint">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M12 6v6l4 2"/>
        </svg>
        <span>این صفحه به‌طور خودکار بروزرسانی می‌شود</span>
      </div>
      <div class="dots-loading" aria-label="در حال انتظار">
        <span></span><span></span><span></span>
      </div>
    </div>
  </section>

  <!-- ═══════ VIEW 1 — ورود نام ═══════ -->
  <section class="view" id="view-name" data-view="name" hidden>
    <div class="card card-name">
      <div class="card-icon icon-welcome" aria-hidden="true">
        <svg viewBox="0 0 64 64" width="64" height="64">
          <defs>
            <linearGradient id="gw" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#16A34A"/>
              <stop offset="1" stop-color="#0EA5E9"/>
            </linearGradient>
          </defs>
          <circle cx="32" cy="32" r="30" fill="url(#gw)" opacity="0.15"/>
          <circle cx="32" cy="32" r="22" fill="url(#gw)"/>
          <path d="M32 16l4 8 9 1.4-6.5 6.4 1.5 9L32 36.7 24 40.8l1.5-9L19 25.4l9-1.4z" fill="#fff"/>
        </svg>
      </div>

      <h1 class="card-title">به مسابقه موکب احمدآباد خوش آمدید</h1>
      <p class="card-subtitle">برای شرکت در مسابقه، نام و نام خانوادگی خود را وارد کنید</p>

      <ul class="rules">
        <li class="rule">
          <span class="rule-icon rule-icon-green" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <span>۱۰ سوال، هر سوال ۳۰ ثانیه فرصت</span>
        </li>
        <li class="rule">
          <span class="rule-icon rule-icon-yellow" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <span>هر پاسخ غلط = حذف فوری از مسابقه</span>
        </li>
        <li class="rule">
          <span class="rule-icon rule-icon-red" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
          </span>
          <span>رفرش صفحه = حذف خودکار</span>
        </li>
        <li class="rule">
          <span class="rule-icon rule-icon-blue" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8l-6.2 4.5 2.4-7.4L2 9.4h7.6z" fill="currentColor"/></svg>
          </span>
          <span>با پاسخ صحیح به همه، کد ۵ رقمی جایزه بگیرید</span>
        </li>
      </ul>

      <form class="form" id="formName" autocomplete="off" novalidate>
        <div class="field">
          <label for="inputName" class="field-label">نام و نام خانوادگی</label>
          <input
            type="text"
            id="inputName"
            name="name"
            class="field-input"
            placeholder="مثال: علی محمدی"
            minlength="3"
            maxlength="60"
            autocomplete="name"
            inputmode="text"
            required>
          <div class="field-error" id="nameError" hidden></div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" id="btnEnter">
          <span class="btn-label">ورود به مسابقه</span>
          <span class="btn-spinner" hidden aria-hidden="true"></span>
        </button>
      </form>

      <p class="card-footnote">
        با ورود، شما قوانین مسابقه را می‌پذیرید. هر شرکت‌کننده فقط یک بار می‌تواند وارد شود.
      </p>
    </div>
  </section>

  <!-- ═══════ VIEW 2 — انتظار تأیید اپراتور ═══════ -->
  <section class="view" id="view-waiting" data-view="waiting" hidden>
    <div class="card card-waiting">
      <div class="hourglass" aria-hidden="true">
        <svg viewBox="0 0 64 64" width="88" height="88">
          <defs>
            <linearGradient id="hg" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stop-color="#0EA5E9"/>
              <stop offset="1" stop-color="#16A34A"/>
            </linearGradient>
          </defs>
          <path d="M16 6h32v4a16 16 0 01-16 16A16 16 0 0116 10V6z" fill="url(#hg)" opacity="0.9"/>
          <path d="M16 58h32v-4a16 16 0 00-16-16 16 16 0 00-16 16v4z" fill="url(#hg)" opacity="0.9"/>
          <rect x="14" y="4" width="36" height="3" rx="1.5" fill="#0F172A"/>
          <rect x="14" y="57" width="36" height="3" rx="1.5" fill="#0F172A"/>
        </svg>
      </div>

      <h2 class="card-title"><?= htmlspecialchars($msg_waiting, ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="card-subtitle">به‌محض شروع مسابقه توسط اپراتور، سوال اول نمایش داده می‌شود</p>

      <!-- تایمر تا شروع مسابقه -->
      <div class="start-countdown" id="startCountdown" hidden>
        <div class="start-countdown-label">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 7v5l3 2"/>
          </svg>
          تا شروع مسابقه
        </div>
        <div class="start-countdown-value" id="startCountdownValue">--:--</div>
      </div>

      <div class="welcome-chip">
        <svg class="welcome-chip-emoji" viewBox="0 0 24 24" width="20" height="20"
             fill="currentColor" aria-hidden="true">
          <path d="M12 2l2.4 6.8L21 11l-6.6 2.2L12 20l-2.4-6.8L3 11l6.6-2.2z"/>
        </svg>
        <span>خوش آمدید،</span>
        <strong id="waitingName">—</strong>
      </div>

      <!-- اطلاعات: تعداد نفرات + زمان مسابقه -->
      <div class="waiting-extras">
        <div class="waiting-extra">
          <svg class="waiting-extra-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          <span class="waiting-extra-text"><strong id="waitingOnline">۰</strong> نفر در انتظار شروع</span>
        </div>

        <div class="waiting-extra waiting-extra-duration">
          <svg class="waiting-extra-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 7v5l3 2"/>
          </svg>
          <span class="waiting-extra-text">زمان مسابقه: <strong id="waitingDuration" dir="ltr">—</strong></span>
        </div>
      </div>

           <p class="card-footnote warn-note">
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        این صفحه را رفرش نکنید — رفرش باعث حذف شما می‌شود
      </p>

      <div class="dots-loading" aria-label="در حال انتظار">
        <span></span><span></span><span></span>
      </div>
    </div>
  </section>

  <!-- ═══════ VIEW 3 — نمایش سوال ═══════ -->
  <section class="view" id="view-question" data-view="question" hidden>
    <div class="card card-question">
      <div class="q-top-row">
        <div class="q-live-players">
          <svg viewBox="0 0 24 24" width="16" height="16"
               fill="none" stroke="currentColor" stroke-width="2.2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          <strong id="qPlayersLeft">—</strong> نفر در مسابقه
        </div>

        <div class="end-timer" id="endTimerQuestion" hidden>
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 7v5l3 2"/>
          </svg>
          <span>زمان مسابقه:</span>
          <strong id="endTimeQuestion">--:--</strong>
        </div>
      </div>

      <div class="q-progress">
        <div class="q-progress-info">
          <span class="q-progress-label">سوال</span>
          <span class="q-progress-num"><strong id="qCurrent">1</strong> از <span id="qTotal">10</span></span>
        </div>
        <div class="q-progress-track">
          <div class="q-progress-fill" id="qProgressFill" style="width: 0%"></div>
        </div>
      </div>

      <div class="q-timer" id="qTimer" data-state="ok" aria-hidden="true">
        <svg viewBox="0 0 100 100" width="76" height="76">
          <circle cx="50" cy="50" r="44" class="timer-bg"/>
          <circle cx="50" cy="50" r="44" class="timer-fg" id="timerCircle"/>
        </svg>
        <span class="timer-num" id="timerNum">30</span>
      </div>

      <h2 class="q-text" id="qText">—</h2>

      <div class="q-options" id="qOptions" role="list"></div>

      <button type="button" class="btn btn-primary btn-lg btn-submit-answer" id="btnSubmitAnswer" disabled>
        <span class="btn-label">برای ثبت پاسخ یک گزینه را انتخاب کنید</span>
        <svg class="btn-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"/>
        </svg>
      </button>

      <div class="q-feedback" id="qFeedback" hidden></div>
    </div>
  </section>

  <!-- ═══════ VIEW 3.5 — منتظر دیگران ═══════ -->
  <section class="view" id="view-others" data-view="others" hidden>
    <div class="card card-others">
      <div class="others-icon" aria-hidden="true">
        <svg viewBox="0 0 64 64" width="72" height="72">
          <defs>
            <linearGradient id="og" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#16A34A"/>
              <stop offset="1" stop-color="#0EA5E9"/>
            </linearGradient>
          </defs>
          <circle cx="32" cy="32" r="28" fill="url(#og)" opacity="0.12"/>
          <circle cx="32" cy="32" r="22" fill="none" stroke="url(#og)" stroke-width="3"/>
          <path d="M22 32l7 7 14-14" fill="none" stroke="url(#og)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      <h2 class="card-title text-success">پاسخ شما ثبت شد</h2>

      <div class="end-timer end-timer-others" id="endTimerOthers" hidden>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="9"/>
          <path d="M12 7v5l3 2"/>
        </svg>
        <span>زمان مسابقه:</span>
        <strong id="endTimeOthers">--:--</strong>
      </div>

      <p class="card-subtitle">منتظر باشید تا سایر شرکت‌کنندگان هم پاسخ دهند</p>

      <div class="hadith-box" id="hadithBox">
        <svg class="hadith-mark" viewBox="0 0 24 24" width="42" height="42"
             fill="currentColor" aria-hidden="true">
          <path d="M6.5 5C4 5 2 7 2 9.5v3C2 14.9 4 17 6.5 17c1 0 1.9-.3 2.6-.8-.6 1.4-1.8 2.5-3.4 3l.6 1.4c3.5-1 6-4 6-8.1V9.5C12.3 7 10.3 5 7.8 5h-1.3zm11 0C15 5 13 7 13 9.5v3c0 2.4 2 4.5 4.5 4.5 1 0 1.9-.3 2.6-.8-.6 1.4-1.8 2.5-3.4 3l.6 1.4c3.5-1 6-4 6-8.1V9.5C23.3 7 21.3 5 18.8 5h-1.3z"/>
        </svg>
        <p class="hadith-text" id="hadithText">—</p>
        <p class="hadith-src" id="hadithSrc">—</p>
      </div>

      <div class="q-timer" id="othersTimer" data-state="ok" aria-hidden="true">
        <svg viewBox="0 0 100 100" width="78" height="78">
          <circle cx="50" cy="50" r="44" class="timer-bg"/>
          <circle cx="50" cy="50" r="44" class="timer-fg" id="othersTimerCircle"/>
        </svg>
        <span class="timer-num" id="othersTimerNum">30</span>
      </div>

      <p class="card-footnote">سوال بعدی به‌زودی شروع می‌شود…</p>
    </div>
  </section>

  <!-- ═══════ VIEW 4 — وقفه ═══════ -->
  <section class="view" id="view-break" data-view="break" hidden>
    <div class="card card-break">
      <h2 class="break-title">سوال بعدی در</h2>

      <div class="break-ring" aria-hidden="true">
        <svg viewBox="0 0 120 120" width="140" height="140">
          <defs>
            <linearGradient id="breakGrad" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#16A34A"/>
              <stop offset="1" stop-color="#0EA5E9"/>
            </linearGradient>
          </defs>
          <circle cx="60" cy="60" r="52" class="break-ring-bg"/>
          <circle cx="60" cy="60" r="52" class="break-ring-fg" id="breakRing"/>
        </svg>
        <span class="break-num" id="breakNum">5</span>
      </div>

      <p class="break-hint">آماده باشید…</p>
    </div>
  </section>

  <!-- ═══════ VIEW 5 — حذف ═══════ -->
  <section class="view" id="view-eliminated" data-view="eliminated" hidden>
    <div class="card card-eliminated">
      <div class="elim-icon" aria-hidden="true">
        <svg viewBox="0 0 64 64" width="80" height="80">
          <circle cx="32" cy="32" r="28" fill="#DC2626" opacity="0.12"/>
          <circle cx="32" cy="32" r="22" fill="#DC2626"/>
          <path d="M24 24l16 16M40 24L24 40" stroke="#fff" stroke-width="4" stroke-linecap="round"/>
        </svg>
      </div>

      <h2 class="card-title text-danger"><?= htmlspecialchars($msg_elim, ENT_QUOTES, 'UTF-8') ?></h2>

      <div class="elim-info">
        <span>شما در </span>
        <strong class="elim-q">سوال <span id="elimQNum">—</span></strong>
        <span> پاسخ نادرست دادید</span>
      </div>

      <p class="card-subtitle spiritual">
        <svg viewBox="0 0 24 24" width="22" height="22"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"
             style="display:inline-block;vertical-align:-5px;margin-left:6px;color:var(--c-primary);">
          <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
          <path d="M2 21c0-3 1.85-5.36 5.08-6"/>
        </svg>
        إنشاءالله در فرصت‌های بعدی موفق باشید.<br>
        تلاش شما ارزشمند است.
      </p>

      <button type="button" class="btn btn-ghost btn-lg" id="btnCloseElim">
        بستن
      </button>

      <p class="card-footnote">
        امکان شرکت مجدد در همین مسابقه وجود ندارد.
      </p>
    </div>
  </section>

  <!-- ═══════ VIEW 6 — برنده ═══════ -->
  <section class="view" id="view-winner" data-view="winner" hidden>
    <canvas class="confetti-canvas" id="confettiCanvas" aria-hidden="true"></canvas>

    <div class="card card-winner">
      <div class="winner-trophy" aria-hidden="true">
        <svg viewBox="0 0 64 64" width="96" height="96">
          <defs>
            <linearGradient id="tg" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stop-color="#FACC15"/>
              <stop offset="1" stop-color="#CA8A04"/>
            </linearGradient>
          </defs>
          <path d="M18 8h28v10a14 14 0 01-28 0V8z" fill="url(#tg)"/>
          <path d="M14 12h-6a8 8 0 008 8M50 12h6a8 8 0 01-8 8" fill="none" stroke="#CA8A04" stroke-width="3" stroke-linecap="round"/>
          <rect x="28" y="30" width="8" height="12" fill="#CA8A04"/>
          <rect x="20" y="42" width="24" height="6" rx="2" fill="url(#tg)"/>
          <rect x="16" y="48" width="32" height="6" rx="2" fill="#0F172A"/>
        </svg>
      </div>

      <h2 class="card-title text-success"><?= htmlspecialchars($msg_winner, ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="card-subtitle">شما با پاسخ صحیح به هر ۱۰ سوال، به کد جایزه دست یافتید</p>

      <div class="winner-name" id="winnerName">—</div>

      <div class="code-card">
        <div class="code-card-label">کد جایزه شما</div>
        <div class="code-value" id="codeValue" dir="ltr">─────</div>
        <button type="button" class="btn btn-copy" id="btnCopyCode" aria-label="کپی کد">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
            <rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
            <path d="M5 15V6a2 2 0 012-2h9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span>کپی کد</span>
        </button>
      </div>

      <div class="winner-hint">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8l-6.2 4.5 2.4-7.4L2 9.4h7.6z" fill="currentColor"/></svg>
        این کد را برای دریافت جایزه به مجری موکب نشان دهید
      </div>

      <div class="winner-warning">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        هر کد فقط یک بار قابل استفاده است و غیرقابل انتقال می‌باشد
      </div>
    </div>
  </section>

  <!-- ═══════ VIEW 7 — خطا ═══════ -->
  <section class="view" id="view-error" data-view="error" hidden>
    <div class="card card-error">
      <div class="error-icon" aria-hidden="true">
        <svg viewBox="0 0 64 64" width="72" height="72">
          <circle cx="32" cy="32" r="24" fill="#FACC15" opacity="0.18"/>
          <circle cx="32" cy="32" r="20" fill="#FACC15"/>
          <path d="M32 20v16M32 44h.01" stroke="#0F172A" stroke-width="4" stroke-linecap="round"/>
        </svg>
      </div>
      <h2 class="card-title">مشکل ارتباطی</h2>
      <p class="card-subtitle" id="errorMessage">ارتباط با سرور قطع شد. در حال تلاش مجدد…</p>
      <button type="button" class="btn btn-primary" id="btnRetry">تلاش مجدد</button>
    </div>
  </section>

</main>

<footer class="site-footer">
  <div class="footer-inner">
    <a href="https://eitaa.com/mokeb_ahmadabad"
       target="_blank"
       rel="noopener noreferrer"
       class="footer-brand"
       title="کانال خانواده موکب احمدآباد در ایتا">
      <img src="assets/img/favicon.png" alt="موکب احمدآباد" class="footer-logo">
      <div class="footer-brand-text">
        <span class="footer-brand-title">کانال خانواده موکب احمدآباد</span>
      </div>
      <svg class="footer-brand-icon" viewBox="0 0 24 24" width="14" height="14"
           fill="none" stroke="currentColor" stroke-width="2.6"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M7 17L17 7"/>
        <path d="M9 7h8v8"/>
      </svg>
    </a>

    <div class="footer-mid">
      <span class="footer-copy">© <span id="footerYear">1405</span> — تمامی حقوق برای موکب احمدآباد محفوظ است</span>
    </div>

    <div class="footer-spirit">
      <span>باید برخواست ✊</span>
    </div>
  </div>
</footer>

<div class="toast-wrap" id="toastWrap" aria-live="polite" aria-atomic="true"></div>

<script>
  window.__MOKEB__ = {
    version: <?= json_encode(APP_VERSION) ?>,
    csrf:    <?= json_encode($csrf) ?>,
    totalQ:  <?= (int) $total_q ?>,
    qTime:   <?= (int) $question_time ?>,
    breakTime: <?= (int) $break_time ?>,
    quizRunning: <?= $quiz_running ? 'true' : 'false' ?>,
    apiBase: 'api/',
    today:   <?= json_encode(today()) ?>
  };
</script>
<script src="assets/js/hadiths.js?v=<?= APP_VERSION ?>" defer></script>
<script src="assets/js/main.js?v=<?= APP_VERSION ?>" defer></script>

<div class="countdown-overlay" id="countdownOverlay" hidden>
  <div class="countdown-content">
    <div class="countdown-hint">آماده باشید…</div>
    <div class="countdown-num" id="countdownNum">3</div>
    <div class="countdown-sub">سوال بعدی</div>
  </div>
</div>
</body>
</html>