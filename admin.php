<?php
/**
 * موکب احمدآباد — پنل مدیریت (اپراتور)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

secure_session();
$csrf = csrf_token();

$total_q = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);

if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0EA5E9">
<meta name="robots" content="noindex, nofollow">
<title>پنل مدیریت — موکب احمدآباد</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png?v=<?= APP_VERSION ?>">
<link rel="apple-touch-icon" href="assets/img/favicon.png?v=<?= APP_VERSION ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/css/style.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="assets/css/admin.css?v=<?= APP_VERSION ?>">
</head>
<body class="admin-body">

<section class="view admin-view" id="view-login" data-view="login" hidden>
  <div class="admin-login-card">
    <div class="admin-login-logo" aria-hidden="true">
      <img src="assets/img/favicon.png" alt="موکب احمدآباد"
           style="width:96px; height:96px;
                  border-radius:50%;
                  object-fit:cover;
                  box-shadow: 0 8px 24px rgba(220, 38, 38, 0.25),
                              0 0 0 3px #FFFFFF,
                              0 0 0 5px rgba(220, 38, 38, 0.15);">
    </div>

    <h1 class="admin-login-title">پنل مدیریت موکب احمدآباد</h1>
    <p class="admin-login-sub">برای ورود، رمز عبور اپراتور را وارد کنید</p>

    <form id="formLogin" autocomplete="off" novalidate>
      <div class="field">
        <label for="inputPassword" class="field-label">رمز عبور</label>
        <input type="password" id="inputPassword" class="field-input"
               placeholder="••••••••" autocomplete="current-password" required>
        <div class="field-error" id="loginError" hidden></div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" id="btnLogin">
        <span class="btn-label">ورود به پنل</span>
        <span class="btn-spinner" hidden></span>
      </button>
    </form>

    <p class="admin-login-foot">
      <a href="index.php">← بازگشت به صفحه مسابقه</a>
    </p>
  </div>
</section>

<section class="view admin-view admin-view-full" id="view-dashboard" data-view="dashboard" hidden>

  <header class="admin-header">
    <div class="admin-header-inner">
      <div class="admin-brand">
        <img src="assets/img/favicon.png" alt="موکب احمدآباد" class="admin-brand-logo">
        <div>
          <div class="admin-brand-title">پنل اپراتور</div>
          <div class="admin-brand-sub">موکب احمدآباد</div>
        </div>
      </div>

      <div class="admin-header-status">
        <span class="status-pill" id="quizStatusPill">
          <span class="status-dot"></span>
          <span id="quizStatusText">در انتظار شروع</span>
        </span>
      </div>

      <div class="admin-header-actions">
        <a href="index.php" target="_blank" class="btn btn-ghost btn-sm">
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          نمایش سایت
        </a>
        <button type="button" class="btn btn-ghost btn-sm" id="btnLogout">
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          خروج
        </button>
      </div>
    </div>
  </header>

  <div class="admin-control-bar" id="controlBar">
    <div class="control-info">
      <div class="control-qi">
        <span class="control-qi-label">سوال جاری</span>
        <span class="control-qi-num num"><strong id="currQInfo">—</strong> / <span id="totalQInfo"><?= $total_q ?></span></span>
      </div>
      <div class="control-time">
        <span class="control-qi-label">زمان سرور</span>
        <span class="num" id="serverTime">—</span>
      </div>
    </div>

    <div class="control-buttons">
      <button type="button" class="btn btn-primary" id="btnStart" hidden>
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 4l14 8-14 8V4z" fill="currentColor"/></svg>
        شروع مسابقه
      </button>
      <button type="button" class="btn btn-danger" id="btnStop" hidden>
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"/></svg>
        توقف فوری
      </button>
      <button type="button" class="btn btn-warn" id="btnToggleSite">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
          <rect x="3" y="11" width="18" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span id="btnToggleSiteLabel">بستن سایت</span>
            <button type="button" class="btn btn-ghost-danger" id="btnReset">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
          <path d="M21 12a9 9 0 1 1-3-6.7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M21 3v5h-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="12" cy="12" r="1.8" fill="currentColor"/>
        </svg>
        ریست کامل
      </button>
    </div>
  </div>

  <div class="counters" id="counters">
    <div class="counter counter-blue">
      <div class="counter-label">کل شرکت‌کنندگان</div>
      <div class="counter-num num" id="cntParticipants">۰</div>
    </div>
    <div class="counter counter-yellow">
      <div class="counter-label">در انتظار</div>
      <div class="counter-num num" id="cntWaiting">۰</div>
    </div>
    <div class="counter counter-green">
      <div class="counter-label">در حال بازی</div>
      <div class="counter-num num" id="cntPlaying">۰</div>
    </div>
    <div class="counter counter-red">
      <div class="counter-label">حذف‌شده</div>
      <div class="counter-num num" id="cntEliminated">۰</div>
    </div>
    <div class="counter counter-gold">
      <div class="counter-label">برندگان</div>
      <div class="counter-num num" id="cntWinners">۰</div>
    </div>
  </div>

  <nav class="admin-tabs" role="tablist">
    <button class="admin-tab is-active" data-tab="live" role="tab">
      <svg viewBox="0 0 24 24" width="16" height="16"><path d="M3 3v18h18M7 14l4-4 4 4 5-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      آمار زنده
    </button>
    <button class="admin-tab" data-tab="questions" role="tab">
      <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 3v18M3 12h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      سوالات
    </button>
    <button class="admin-tab" data-tab="participants" role="tab">
      <svg viewBox="0 0 24 24" width="16" height="16"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      شرکت‌کنندگان
    </button>
    <button class="admin-tab" data-tab="winners" role="tab">
      <svg viewBox="0 0 24 24" width="16" height="16"><path d="M8 21h8M12 17v4M7 4h10v6a5 5 0 01-10 0V4zM17 6h3a2 2 0 01-2 4M7 6H4a2 2 0 002 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      برندگان
    </button>
    <button class="admin-tab" data-tab="history" role="tab">
      <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2M3 12h2M19 12h2M12 3v2M12 19v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      تاریخچه
    </button>
    <button class="admin-tab" data-tab="settings" role="tab">
      <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33h0a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51h0a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82v0a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
      تنظیمات
    </button>
    <button class="admin-tab admin-tab-danger" data-tab="clear" role="tab">
      <svg viewBox="0 0 24 24" width="16" height="16"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      حذف آمار
    </button>
  </nav>

  <div class="admin-panels">

    <div class="admin-panel is-active" id="panel-live">
      <div class="hardest-banner" id="hardestBanner" hidden>
        <div class="hardest-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          </svg>
        </div>
        <div class="hardest-text">
          سخت‌ترین سوال: <strong id="hardestQNum">—</strong>
          <span class="hardest-sub">(<span id="hardestFail">—</span> نفر پاسخ غلط دادند)</span>
        </div>
      </div>

      <div class="charts-grid">
        <div class="chart-card">
          <div class="chart-card-head"><h3>تعداد پاسخ‌های صحیح به هر سوال</h3></div>
          <div class="chart-card-body"><canvas id="chartBar"></canvas></div>
        </div>
        <div class="chart-card">
          <div class="chart-card-head"><h3>توزیع حذف‌شده‌ها به تفکیک سوال</h3></div>
          <div class="chart-card-body"><canvas id="chartPie"></canvas></div>
        </div>
      </div>

      <div class="chart-card chart-card-full">
        <div class="chart-card-head"><h3>توزیع پاسخ‌ها به تفکیک گزینه</h3></div>
        <div class="chart-card-body"><canvas id="chartDistribution"></canvas></div>
      </div>

      <div class="table-card">
        <div class="table-card-head">
          <h3>جدول تفصیلی سوالات</h3>
          <button class="btn btn-ghost btn-sm" id="btnExportStats">
            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M12 3v12M7 10l5 5 5-5M3 21h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            خروجی CSV
          </button>
        </div>
        <div class="table-wrap">
          <table class="data-table" id="statsTable">
            <thead>
              <tr>
                <th>#</th>
                <th>سوال</th>
                <th>درست</th>
                <th>غلط</th>
                <th>نرخ موفقیت</th>
                <th>میانگین زمان</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="admin-panel" id="panel-questions">
      <div class="panel-head">
        <div style="display:flex;flex-direction:column;gap:8px">
          <h3>مدیریت سوالات</h3>
          <div class="panel-hint">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>
            </svg>
            برای جابجایی، سوال را بکشید و رها کنید
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-ghost btn-sm" id="btnBulkImport">
            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            افزودن گروهی
          </button>
          <button class="btn btn-primary btn-sm" id="btnNewQuestion">
            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            افزودن سوال
          </button>
        </div>
      </div>
      <div class="questions-list" id="questionsList"></div>
    </div>

    <div class="admin-panel" id="panel-participants">
      <div class="panel-head">
        <h3>لیست شرکت‌کنندگان</h3>
        <div class="panel-filters">
          <input type="text" class="field-input field-input-sm" id="filterName" placeholder="جستجوی نام…">
          <select class="field-input field-input-sm" id="filterStatus">
            <option value="">همه وضعیت‌ها</option>
            <option value="waiting">در انتظار</option>
            <option value="playing">در حال بازی</option>
            <option value="eliminated">حذف‌شده</option>
            <option value="winner">برنده</option>
          </select>
        </div>
      </div>
      <div class="table-wrap">
        <table class="data-table" id="participantsTable">
          <thead>
            <tr>
              <th>#</th>
              <th>نام</th>
              <th>زمان ورود</th>
              <th>آخرین سوال</th>
              <th>وضعیت</th>
              <th>کد</th>
              <th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="empty-state" id="participantsEmpty" hidden>
        <p>هنوز شرکت‌کننده‌ای ثبت‌نام نکرده است</p>
      </div>
    </div>

    <div class="admin-panel" id="panel-winners">
      <div class="panel-head">
        <h3>لیست برندگان</h3>
        <div class="panel-filters">
          <input type="text" class="field-input field-input-sm" id="filterWinner" placeholder="جستجو در نام یا کد…">
          <button class="btn btn-ghost btn-sm" id="btnExportWinners">
            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M12 3v12M7 10l5 5 5-5M3 21h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            خروجی CSV
          </button>
          <button class="btn btn-ghost btn-sm" id="btnPrintWinners">
            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            چاپ
          </button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="data-table" id="winnersTable">
          <thead>
            <tr>
              <th>#</th>
              <th>نام برنده</th>
              <th>کد ۵ رقمی</th>
              <th>زمان</th>
              <th>وضعیت دریافت</th>
              <th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="empty-state" id="winnersEmpty" hidden>
        <p>هنوز برنده‌ای اعلام نشده است</p>
      </div>
    </div>

    <div class="admin-panel" id="panel-history">
      <div class="panel-head">
        <div style="display:flex;flex-direction:column;gap:8px">
          <h3>تاریخچه مسابقات</h3>
          <div class="panel-hint">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="9"/>
              <path d="M12 7v5l3 2"/>
            </svg>
            برای مشاهده جزئیات هر روز، روی آن کلیک کنید
          </div>
        </div>
        <button class="btn btn-ghost btn-sm" id="btnRefreshHistory">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1015-6.7L21 8M21 3v5h-5"/>
          </svg>
          بروزرسانی
        </button>
      </div>
      <div class="history-list" id="historyList">
        <div class="empty-state"><p>در حال بارگذاری…</p></div>
      </div>
    </div>

    <div class="admin-panel" id="panel-settings">
      <div class="panel-head"><h3>تنظیمات مسابقه</h3></div>

      <form class="settings-form" id="formSettings">
        <div class="settings-grid">
          <div class="field">
            <label class="field-label">زمان هر سوال (ثانیه)</label>
            <input type="number" class="field-input" id="setQuestionTime" min="5" max="300" required>
          </div>
          <div class="field">
            <label class="field-label">زمان وقفه (ثانیه)</label>
            <input type="number" class="field-input" id="setBreakTime" min="1" max="60" required>
          </div>
          <div class="field">
            <label class="field-label">تعداد سوالات</label>
            <input type="number" class="field-input" id="setTotalQ" min="1" max="50" required>
          </div>
        </div>

        <div class="field">
          <label class="field-label">زمان کل مسابقه (ثانیه) — خالی بگذارید تا خودکار محاسبه شود</label>
          <input type="number" class="field-input" id="setQuizDuration" min="0" max="7200" placeholder="مثال: 330 (یعنی ۵:۳۰)">
          <div class="field-hint">اگه خالی یا ۰ باشه، خودکار = (تعداد سوال × زمان سوال) + ۳۰ محاسبه می‌شه</div>
        </div>

               <div class="field">
  <label class="field-label">مدت زمان تایمر شروع</label>
  <input type="text" class="field-input" id="setEstimatedStart" placeholder="مثال: 2:00:00 برای ۲ ساعت" dir="ltr" maxlength="9">
  <div class="field-hint">
    <strong>راهنمای فرمت:</strong><br>
    • <code>2:00:00</code> → ۲ ساعت<br>
    • <code>1:30:00</code> → ۱ ساعت و ۳۰ دقیقه<br>
    • <code>5:00</code> → ۵ دقیقه<br>
    • <code>300</code> → ۳۰۰ ثانیه (۵ دقیقه)
  </div>
</div>

        <div class="field">
          <label class="field-label">پیام صفحه انتظار</label>
          <input type="text" class="field-input" id="setMsgWaiting" maxlength="120">
        </div>
        <div class="field">
          <label class="field-label">پیام صفحه برنده</label>
          <input type="text" class="field-input" id="setMsgWinner" maxlength="120">
        </div>
        <div class="field">
          <label class="field-label">پیام صفحه حذف</label>
          <input type="text" class="field-input" id="setMsgElim" maxlength="120">
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" id="btnSaveSettings">
            <span class="btn-label">ذخیره تنظیمات</span>
            <span class="btn-spinner" hidden></span>
          </button>
        </div>
      </form>
    </div>

    <div class="admin-panel" id="panel-clear">
      <div class="panel-head"><h3>حذف آمار</h3></div>

      <div class="danger-zone">
        <div class="danger-card">
          <div class="danger-card-body">
            <h4>حذف آمار یک تاریخ مشخص</h4>
            <p class="muted">شرکت‌کنندگان، پاسخ‌ها و آمار آن روز پاک می‌شوند. سوالات امروز حفظ می‌شوند.</p>
            <div class="clear-row">
              <input type="date" class="field-input field-input-sm" id="clearDate">
              <select class="field-input field-input-sm" id="clearScope">
                <option value="stats_only">فقط آمار و پاسخ‌ها</option>
                <option value="full">همه چیز (شامل سوالات گذشته)</option>
              </select>
              <button type="button" class="btn btn-danger btn-sm" id="btnClearStats">
                <svg viewBox="0 0 24 24" width="14" height="14"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                حذف آمار این تاریخ
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<div class="toast-wrap" id="toastWrap" aria-live="polite"></div>

<div class="modal-backdrop" id="modalBackdrop" hidden>
  <div class="modal" id="modal">
    <div class="modal-head">
      <h3 id="modalTitle">—</h3>
      <button type="button" class="modal-close" id="modalClose" aria-label="بستن">
        <svg viewBox="0 0 24 24" width="20" height="20"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
      </button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-foot" id="modalFoot"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
window.__ADMIN__ = {
  version: <?= json_encode(APP_VERSION) ?>,
  csrf:    <?= json_encode($csrf) ?>,
  totalQ:  <?= (int) $total_q ?>,
  today:   <?= json_encode(today()) ?>,
  apiBase: 'api/admin/'
};
</script>
<script src="assets/js/admin.js?v=<?= APP_VERSION ?>" defer></script>
</body>
</html>