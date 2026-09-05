<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Self-Paced Training Videos';

$channelUrl = 'https://www.youtube.com/@EduPortalPK';

$modules = [
    ['name' => 'Front Office', 'videos' => [
        'Front Office Overview & Daily Setup',
        'Managing Admission Inquiries',
        'Complaint Hub – Register & Close',
    ]],
    ['name' => 'Dashboard', 'videos' => [
        'Executive Dashboard – KPIs at a Glance',
        'Staff Dashboard – Daily Overview',
    ]],
    ['name' => 'Students', 'videos' => [
        'Adding a New Student',
        'Student Analytics & Insights',
        'Course/Class Promotion Workflow',
    ]],
    ['name' => 'Attendance', 'videos' => [
        'Marking Daily Attendance',
        'Attendance Analytics & SMS Report',
    ]],
    ['name' => 'Messages', 'videos' => [
        'Sending a New Message',
        'Message History & Tracking',
        'Using Message Templates',
    ]],
    ['name' => 'Fee Collection', 'videos' => [
        'Creating a Monthly Challan',
        'Viewing Challan Details',
        'Recording Fee Payments',
        'Fee Reporting & Fee Settings',
    ]],
    ['name' => 'Examination', 'videos' => [
        'Setting Up Exams & Academic Settings',
        'Entering Marksheets',
        'Viewing Report Cards',
    ]],
    ['name' => 'Timetable', 'videos' => [
        'Period Categories',
        'Creating & Managing Periods',
        'Building a Class Timetable',
    ]],
    ['name' => 'Employees/HRM', 'videos' => [
        'Adding an Employee',
        'Viewing Employee Records',
        'Staff Attendance & Monthly Report',
    ]],
    ['name' => 'Datesheet', 'videos' => [
        'Creating a Datesheet',
        'Generating Roll No Slips',
    ]],
    ['name' => 'Transport', 'videos' => [
        'Managing Vehicles',
        'Routes & Assigning Vehicles',
    ]],
    ['name' => 'Library', 'videos' => [
        'Managing the Book List',
        'Issuing & Returning Books',
    ]],
    ['name' => 'PayRoll', 'videos' => [
        'Creating a PayRoll',
        'Viewing PayRoll Summary',
        'PayRoll & Staff Security Settings',
    ]],
    ['name' => 'Parents Portal', 'videos' => [
        'Parents Portal Overview',
        'Communicating with Parents',
    ]],
    ['name' => 'Expenses', 'videos' => [
        'Adding an Expense',
        'Monthly Expenses Report',
    ]],
    ['name' => 'Cards Generator', 'videos' => [
        'Generating Student Cards',
        'Generating Staff Cards',
    ]],
    ['name' => 'Point of Sale', 'videos' => [
        'POS Dashboard Overview',
        'Recording a Canteen Sale',
    ]],
    ['name' => 'Academic Setup', 'videos' => [
        'Managing Classes',
        'Managing Sections & Subjects',
        'Academic Setup Overview',
    ]],
    ['name' => 'System Settings', 'videos' => [
        'Updating System Settings',
        'Managing Localities',
        'Managing Father Occupations',
    ]],
    ['name' => 'Accounts', 'videos' => [
        'Adding Revenue',
        'Managing Revenue Heads',
        'Revenue List & Reports',
    ]],
];

$totalVideos = 0;
foreach ($modules as $m) { $totalVideos += count($m['videos']); }

include __DIR__ . '/includes/header.php';
?>
<style>
.vhub { padding: 12px 6px 48px; color: #2A3F54; }
.vhub-crumb { font-size: 12.5px; color: #8A99A8; padding: 4px 4px 14px; }
.vhub-crumb a { color: #3E7CB1; text-decoration: none; }
.vhub-crumb a:hover { text-decoration: underline; }
.vhub-hero { display: flex; align-items: flex-start; gap: 16px; padding: 4px 4px 0; }
.vhub-hero .vhub-hero-ic { width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0; background: linear-gradient(135deg, #5B9BD5, #3E7CB1); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 23px; }
.vhub-hero h1 { margin: 0 0 5px; font-size: 24px; font-weight: 800; color: #2A3F54; }
.vhub-hero p { margin: 0; font-size: 13.5px; color: #73879C; max-width: 760px; line-height: 1.55; }
.vhub-overall { margin: 18px 0 0; background: #fff; border: 1px solid #E6E9ED; border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; gap: 20px; box-shadow: 0 1px 3px rgba(42,63,84,.06); }
.vhub-ring { position: relative; width: 62px; height: 62px; flex-shrink: 0; }
.vhub-ring svg { width: 62px; height: 62px; transform: rotate(-90deg); }
.vhub-ring circle { fill: none; stroke-width: 6; }
.vhub-ring .bg { stroke: #E4E8EC; }
.vhub-ring .fg { stroke: #3E7CB1; stroke-linecap: round; stroke-dasharray: 163.36; stroke-dashoffset: 163.36; transition: stroke-dashoffset .45s ease; }
.vhub-ring.is-done .fg { stroke: #27AE60; }
.vhub-ring span { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; color: #2A3F54; }
.vhub-overall-txt { line-height: 1.5; }
.vhub-overall-txt .big { font-size: 16px; font-weight: 700; color: #2A3F54; }
.vhub-overall-txt .sub { display: block; font-size: 12.5px; color: #8A99A8; margin-top: 2px; }
.vhub-done-banner { display: none; margin-top: 14px; background: #E8F5E9; border: 1px solid #BFE3C3; color: #1E7B34; border-radius: 10px; padding: 12px 16px; font-size: 13.5px; font-weight: 600; }
.vhub-done-banner i { margin-right: 7px; }
.vhub-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 18px 0 16px; }
.vhub-search { position: relative; flex: 1 1 240px; max-width: 340px; }
.vhub-search input { width: 100%; height: 40px; border: 1px solid #D6DEE5; border-radius: 9px; padding: 0 14px 0 38px; font-size: 14px; outline: none; background: #fff; color: #2A3F54; }
.vhub-search input:focus { border-color: #3E7CB1; box-shadow: 0 0 0 3px rgba(62,124,177,.14); }
.vhub-search i { position: absolute; left: 14px; top: 13px; color: #9AA7B4; font-size: 14px; }
.vhub-filters { display: flex; gap: 3px; background: #EDF1F4; border-radius: 9px; padding: 3px; }
.vhub-filters button { border: 0; background: transparent; padding: 7px 13px; border-radius: 7px; font-size: 13px; color: #5A6B7B; cursor: pointer; transition: all .15s; }
.vhub-filters button.is-active { background: #fff; color: #2A3F54; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,.09); }
.vhub-link { border: 0; background: transparent; color: #3E7CB1; font-size: 13px; cursor: pointer; padding: 6px 4px; }
.vhub-link:hover { text-decoration: underline; }
.vhub-toolbar .spacer { flex: 1 1 auto; }
.vhub-wrap { display: flex; gap: 26px; align-items: flex-start; }
.vhub-nav { width: 236px; flex-shrink: 0; position: sticky; top: 16px; }
.vhub-nav-inner { background: #fff; border: 1px solid #E6E9ED; border-radius: 12px; padding: 10px; box-shadow: 0 1px 3px rgba(42,63,84,.06); max-height: calc(100vh - 60px); overflow: auto; }
.vhub-nav h4 { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #9AA7B4; margin: 6px 10px 10px; }
.vhub-nav a { display: flex; align-items: center; gap: 9px; padding: 8px 10px; border-radius: 8px; font-size: 13px; color: #5A6B7B; text-decoration: none; }
.vhub-nav a:hover { background: #F2F6F9; color: #2A3F54; }
.vhub-nav a .n { width: 21px; height: 21px; border-radius: 6px; background: #EDF1F4; color: #5A6B7B; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.vhub-nav a.done .n { background: #E8F5E9; color: #27AE60; }
.vhub-nav a .t { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vhub-main { flex: 1; min-width: 0; }
.vhub-module { background: #fff; border: 1px solid #E6E9ED; border-radius: 12px; margin-bottom: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(42,63,84,.06); scroll-margin-top: 16px; }
.vhub-module-head { display: flex; align-items: center; gap: 14px; padding: 15px 18px; cursor: pointer; user-select: none; outline: none; }
.vhub-module-head:hover { background: #F7FAFC; }
.vhub-badge { width: 34px; height: 34px; border-radius: 10px; background: #EAF2F8; color: #3E7CB1; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.vhub-module.is-complete .vhub-badge { background: #E8F5E9; color: #27AE60; }
.vhub-module-title { font-size: 15.5px; font-weight: 700; color: #2A3F54; margin: 0; }
.vhub-module-sub { display: block; font-size: 12px; color: #8A99A8; margin-top: 2px; }
.vhub-module-meta { margin-left: auto; display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
.vhub-count { font-size: 12px; color: #8A99A8; white-space: nowrap; }
.vhub-bar { width: 110px; height: 6px; border-radius: 99px; background: #E4E8EC; overflow: hidden; }
.vhub-bar > i { display: block; height: 100%; width: 0; background: #3E7CB1; transition: width .35s ease; }
.vhub-module.is-complete .vhub-bar > i { background: #27AE60; }
.vhub-chev { color: #9AA7B4; transition: transform .25s; font-size: 14px; }
.vhub-module.is-open .vhub-chev { transform: rotate(180deg); }
.vhub-module-body { display: none; border-top: 1px solid #EEF1F4; padding: 6px 0; }
.vhub-module.is-open .vhub-module-body { display: block; }
.vhub-vid { display: flex; align-items: center; gap: 12px; padding: 11px 18px 11px 20px; cursor: pointer; border-left: 3px solid transparent; transition: background .12s, border-color .12s; }
.vhub-vid:hover { background: #F5F9FC; border-left-color: #3E7CB1; }
.vhub-vid-ico { width: 30px; height: 30px; border-radius: 50%; background: #EAF2F8; color: #3E7CB1; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
.vhub-vid.is-watched .vhub-vid-ico { background: #E8F5E9; color: #27AE60; }
.vhub-vid-name { font-size: 14px; color: #2A3F54; flex: 1; min-width: 0; }
.vhub-vid.is-watched .vhub-vid-name { color: #8A99A8; }
.vhub-vid-status { font-size: 11px; color: #B6C0CB; display: flex; align-items: center; gap: 5px; white-space: nowrap; }
.vhub-vid.is-watched .vhub-vid-status { color: #27AE60; }
.vhub-watch { border: 0; background: #3E7CB1; color: #fff; font-size: 12px; font-weight: 600; border-radius: 8px; padding: 7px 14px; cursor: pointer; flex-shrink: 0; }
.vhub-watch:hover { background: #33689A; }
.vhub-vid.is-watched .vhub-watch { background: #E8F5E9; color: #27AE60; }
.vhub-noresults { display: none; text-align: center; padding: 44px 20px; color: #95A5A6; font-size: 13.5px; }
.vhub-empty { text-align: center; padding: 56px 20px; color: #95A5A6; }
.vhub-empty i { font-size: 44px; color: #D5DBDB; display: block; margin-bottom: 12px; }
.vhub-modal { position: fixed; inset: 0; background: rgba(20,29,38,.74); display: none; align-items: center; justify-content: center; z-index: 99999; padding: 20px; }
.vhub-modal.is-open { display: flex; }
.vhub-modal-box { background: #fff; border-radius: 14px; width: 960px; max-width: 100%; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 70px rgba(0,0,0,.45); }
.vhub-modal-head { display: flex; align-items: center; gap: 12px; padding: 13px 16px; border-bottom: 1px solid #EEF1F4; }
.vhub-modal-head h3 { margin: 0; font-size: 15px; font-weight: 700; color: #2A3F54; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vhub-x { border: 0; background: #F1F4F6; width: 32px; height: 32px; border-radius: 9px; cursor: pointer; color: #5A6B7B; font-size: 18px; line-height: 1; flex-shrink: 0; }
.vhub-x:hover { background: #E6EAEE; }
.vhub-embed { position: relative; width: 100%; padding-top: 56.25%; background: #000; }
.vhub-embed iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.vhub-placeholder { position: absolute; inset: 0; background: linear-gradient(135deg, #12324f, #25567f); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 24px; }
.vhub-placeholder i { font-size: 52px; opacity: .9; margin-bottom: 14px; }
.vhub-placeholder h4 { margin: 0 0 6px; font-weight: 700; }
.vhub-placeholder p { font-size: 13px; opacity: .8; max-width: 420px; line-height: 1.5; margin: 0 0 14px; }
.vhub-placeholder .btn { border-radius: 8px; padding: 9px 18px; font-weight: 600; font-size: 13px; }
.vhub-modal-foot { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-top: 1px solid #EEF1F4; flex-wrap: wrap; }
.vhub-check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #2A3F54; cursor: pointer; margin-right: auto; margin-bottom: 0; font-weight: 500; }
.vhub-check input { width: 16px; height: 16px; }
.vhub-navbtn { border: 1px solid #D6DEE5; background: #fff; padding: 8px 14px; border-radius: 9px; font-size: 13px; cursor: pointer; color: #2A3F54; }
.vhub-navbtn:hover:not(:disabled) { background: #F5F9FC; border-color: #3E7CB1; }
.vhub-navbtn:disabled { opacity: .45; cursor: not-allowed; }
@media (max-width: 991px) { .vhub-nav { display: none; } }
@media (max-width: 600px) {
    .vhub-module-meta .vhub-bar { display: none; }
    .vhub-hero h1 { font-size: 20px; }
}
</style>

<div class="main-content">
    <div class="container-fluid vhub">
        <div class="vhub-crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> <i class="fa fa-angle-double-right"></i> <span>Training Videos</span></div>

        <div class="vhub-hero">
            <div class="vhub-hero-ic"><i class="fa fa-play"></i></div>
            <div>
                <h1>Self-Paced Training Videos</h1>
                <p>Watch guided walkthroughs for every module of the system, mark videos as watched, and track your completion below.</p>
            </div>
        </div>

        <div class="vhub-overall">
            <div class="vhub-ring" id="vhubRing">
                <svg>
                    <circle class="bg" cx="31" cy="31" r="26"></circle>
                    <circle class="fg" id="vhubRingFg" cx="31" cy="31" r="26"></circle>
                </svg>
                <span id="vhubRingTxt">0%</span>
            </div>
            <div class="vhub-overall-txt">
                <span class="big"><span id="vhubDoneCount">0</span> of <?php echo (int) $totalVideos; ?> videos watched</span>
                <span class="sub" id="vhubOverallSub">Start with Module 1 and work your way down.</span>
            </div>
        </div>

        <div class="vhub-done-banner" id="vhubDoneBanner">
            <i class="fa fa-check-circle"></i> You've completed every training video — you're ready to go!
        </div>

        <div class="vhub-toolbar">
            <div class="vhub-search">
                <i class="fa fa-search"></i>
                <input type="text" id="vhubSearch" placeholder="Search a topic (e.g. challan, mark sheet, SMS)... " autocomplete="off">
            </div>
            <div class="vhub-filters" role="tablist">
                <button type="button" class="is-active" data-mode="all">All</button>
                <button type="button" data-mode="unwatched">Not watched</button>
                <button type="button" data-mode="watched">Watched</button>
            </div>
            <span class="spacer"></span>
            <button type="button" class="vhub-link" id="vhubExpand">Expand all</button>
            <button type="button" class="vhub-link" id="vhubCollapse">Collapse all</button>
            <button type="button" class="vhub-link" id="vhubReset">Reset progress</button>
        </div>

        <div class="vhub-wrap">
            <aside class="vhub-nav">
                <div class="vhub-nav-inner">
                    <h4>Modules</h4>
                    <?php foreach ($modules as $mi => $mod): ?>
                    <a href="#vhub-mod-<?php echo $mi; ?>" data-nav="<?php echo $mi; ?>">
                        <span class="n"><?php echo $mi + 1; ?></span>
                        <span class="t"><?php echo e($mod['name']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <main class="vhub-main">
                <?php foreach ($modules as $mi => $mod): ?>
                <div class="vhub-module <?php echo $mi === 0 ? 'is-open' : ''; ?>" id="vhub-mod-<?php echo $mi; ?>" data-mod="<?php echo $mi; ?>">
                    <div class="vhub-module-head" role="button" tabindex="0" aria-expanded="<?php echo $mi === 0 ? 'true' : 'false'; ?>">
                        <span class="vhub-badge"><?php echo $mi + 1; ?></span>
                        <div style="min-width:0; flex:1;">
                            <h3 class="vhub-module-title"><?php echo e($mod['name']); ?></h3>
                        </div>
                        <div class="vhub-module-meta">
                            <span class="vhub-count"><span class="wc">0</span>/<?php echo count($mod['videos']); ?> watched</span>
                            <span class="vhub-bar"><i style="width:0%;"></i></span>
                            <i class="fa fa-chevron-down vhub-chev"></i>
                        </div>
                    </div>
                    <div class="vhub-module-body">
                        <?php foreach ($mod['videos'] as $vi => $title): ?>
                        <?php $key = 'm' . $mi . 'v' . $vi; ?>
                        <div class="vhub-vid" data-key="<?php echo $key; ?>" data-name="<?php echo e($title); ?>" data-mod="<?php echo $mi; ?>">
                            <span class="vhub-vid-ico"><i class="fa fa-play"></i></span>
                            <span class="vhub-vid-name"><?php echo e($title); ?></span>
                            <span class="vhub-vid-status"><i class="fa fa-circle-o"></i> <span class="lbl">Not watched</span></span>
                            <button type="button" class="vhub-watch" data-key="<?php echo $key; ?>" data-name="<?php echo e($title); ?>" data-mod="<?php echo $mi; ?>">Watch Now</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="vhub-noresults" id="vhubNoResults">No videos match your search.</div>
            </main>
        </div>
    </div>
</div>

<!-- video player modal -->
<div class="vhub-modal" id="vhubModal" role="dialog" aria-modal="true" aria-labelledby="vhubModalTitle">
    <div class="vhub-modal-box">
        <div class="vhub-modal-head">
            <span class="vhub-badge"><i class="fa fa-play"></i></span>
            <h3 id="vhubModalTitle">Video</h3>
            <button type="button" class="vhub-x" id="vhubClose" aria-label="Close">x</button>
        </div>
        <div class="vhub-embed" id="vhubEmbed"></div>
        <div class="vhub-modal-foot">
            <label class="vhub-check"><input type="checkbox" id="vhubWatched"> Mark as watched</label>
            <button type="button" class="vhub-navbtn" id="vhubPrev"><i class="fa fa-angle-left"></i> Previous</button>
            <button type="button" class="vhub-navbtn" id="vhubNext">Next <i class="fa fa-angle-right"></i></button>
        </div>
    </div>
</div>

<script type="text/javascript">
(function () {
    "use strict";
    var STORE_KEY = 'eduportal_video_progress_v1';
    var CHANNEL_URL = <?php echo json_encode($channelUrl); ?>;
    var TOTAL = <?php echo (int) $totalVideos; ?>;
    var RING_C = 163.36;

    var rows = [].slice.call(document.querySelectorAll('.vhub-vid[data-key]'));
    var modal = document.getElementById('vhubModal');
    var embed = document.getElementById('vhubEmbed');
    var titleEl = document.getElementById('vhubModalTitle');
    var chk = document.getElementById('vhubWatched');
    var prevBtn = document.getElementById('vhubPrev');
    var nextBtn = document.getElementById('vhubNext');
    var closeBtn = document.getElementById('vhubClose');
    var current = -1;
    var autoT = null;

    function loadProgress() {
        try { return JSON.parse(localStorage.getItem(STORE_KEY)) || {}; }
        catch (e) { return {}; }
    }
    function saveProgress() {
        try { localStorage.setItem(STORE_KEY, JSON.stringify(progress)); } catch (e) {}
    }
    var progress = loadProgress();

    function isWatched(key) { return !!progress[key]; }

    function setWatched(key, val) {
        if (val) { progress[key] = 1; } else { delete progress[key]; }
        saveProgress();
        rows.forEach(function (row) {
            if (row.getAttribute('data-key') === key) { paintRow(row); }
        });
        recalc();
    }

    function paintRow(row) {
        var key = row.getAttribute('data-key');
        if (!key) { return; }
        var w = isWatched(key);
        row.classList.toggle('is-watched', w);
        var ico = row.querySelector('.vhub-vid-ico i');
        var st = row.querySelector('.vhub-vid-status');
        if (ico) { ico.className = w ? 'fa fa-check' : 'fa fa-play'; }
        if (st) {
            st.innerHTML = w
                ? '<i class="fa fa-check-circle"></i> <span class="lbl">Watched</span>'
                : '<i class="fa fa-circle-o"></i> <span class="lbl">Not watched</span>';
        }
    }

    function recalc() {
        var grandDone = 0, grandTotal = 0;
        document.querySelectorAll('.vhub-module').forEach(function (mod) {
            var mRows = [].slice.call(mod.querySelectorAll('.vhub-vid[data-key]'));
            var done = mRows.filter(function (r) { return isWatched(r.getAttribute('data-key')); }).length;
            grandTotal += mRows.length;
            grandDone += done;
            var pct = mRows.length ? Math.round(done / mRows.length * 100) : 0;
            var bar = mod.querySelector('.vhub-bar > i');
            if (bar) { bar.style.width = pct + '%'; }
            var wc = mod.querySelector('.vhub-count .wc');
            if (wc) { wc.textContent = done; }
            var complete = mRows.length > 0 && done === mRows.length;
            mod.classList.toggle('is-complete', complete);
            var navLink = document.querySelector('.vhub-nav a[data-nav="' + mod.getAttribute('data-mod') + '"]');
            if (navLink) { navLink.classList.toggle('done', complete); }
        });
        var opct = grandTotal ? Math.round(grandDone / grandTotal * 100) : 0;
        var dc = document.getElementById('vhubDoneCount');
        if (dc) { dc.textContent = grandDone; }
        var fg = document.getElementById('vhubRingFg');
        if (fg) { fg.style.strokeDashoffset = RING_C * (1 - (grandTotal ? grandDone / grandTotal : 0)); }
        var rt = document.getElementById('vhubRingTxt');
        if (rt) { rt.textContent = opct + '%'; }
        var allDone = grandTotal > 0 && grandDone === grandTotal;
        var ring = document.getElementById('vhubRing');
        if (ring) { ring.classList.toggle('is-done', allDone); }
        var banner = document.getElementById('vhubDoneBanner');
        if (banner) { banner.style.display = allDone ? 'block' : 'none'; }
        var sub = document.getElementById('vhubOverallSub');
        if (sub) {
            sub.textContent = allDone
                ? 'All modules complete — great work!'
                : (grandDone === 0 ? 'Start with Module 1 and work your way down.' : 'Keep going — ' + (grandTotal - grandDone) + ' video(s) left.');
        }
    }

    function openVideo(row) {
        if (!row) { return; }
        var idx = rows.indexOf(row);
        var key = row.getAttribute('data-key');
        var name = row.getAttribute('data-name') || 'Video';
        current = idx;
        titleEl.textContent = name;
        chk.checked = isWatched(key);

        embed.innerHTML =
            '<div class="vhub-placeholder">' +
            '<i class="fa fa-youtube-play"></i>' +
            '<h4>Coming Soon</h4>' +
            '<p>This training video has not been published yet. Meanwhile you can explore the module yourself or watch it on the official EduPortal training channel.</p>' +
            '<a href="' + CHANNEL_URL + '" target="_blank" rel="noopener" class="btn btn-warning"><i class="fa fa-external-link"></i> Visit Training Channel</a>' +
            '</div>';

        prevBtn.disabled = current <= 0;
        nextBtn.disabled = current >= rows.length - 1;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        closeBtn.focus();

        if (autoT) { clearTimeout(autoT); }
        autoT = setTimeout(function () {
            if (modal.classList.contains('is-open') && current === idx) {
                setWatched(key, true);
                chk.checked = true;
            }
        }, 4000);
    }

    function closeModal() {
        if (autoT) { clearTimeout(autoT); autoT = null; }
        modal.classList.remove('is-open');
        embed.innerHTML = '';
        document.body.style.overflow = '';
        current = -1;
    }

    rows.forEach(function (row) {
        paintRow(row);
        row.addEventListener('click', function (e) {
            if (e.target.classList.contains('vhub-watch')) { return; }
            openVideo(row);
        });
        var btn = row.querySelector('.vhub-watch');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                openVideo(row);
            });
        }
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) { closeModal(); } });
    chk.addEventListener('change', function () {
        if (current > -1) { setWatched(rows[current].getAttribute('data-key'), chk.checked); }
    });
    prevBtn.addEventListener('click', function () { if (current > 0) { openVideo(rows[current - 1]); } });
    nextBtn.addEventListener('click', function () { if (current < rows.length - 1) { openVideo(rows[current + 1]); } });
    document.addEventListener('keydown', function (e) {
        if (!modal.classList.contains('is-open')) { return; }
        if (e.key === 'Escape') { closeModal(); }
        else if (e.key === 'ArrowLeft' && !prevBtn.disabled) { prevBtn.click(); }
        else if (e.key === 'ArrowRight' && !nextBtn.disabled) { nextBtn.click(); }
    });

    document.querySelectorAll('.vhub-module-head').forEach(function (h) {
        function toggle() {
            var open = h.parentNode.classList.toggle('is-open');
            h.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        h.addEventListener('click', toggle);
        h.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
        });
    });

    function applyFilter() {
        var mode = document.querySelector('.vhub-filters button.is-active').getAttribute('data-mode');
        var q = document.getElementById('vhubSearch').value.trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var watched = isWatched(row.getAttribute('data-key'));
            var ok = true;
            if (mode === 'watched' && !watched) { ok = false; }
            if (mode === 'unwatched' && watched) { ok = false; }
            var name = (row.getAttribute('data-name') || '').toLowerCase();
            var modTitle = '';
            var modEl = document.getElementById('vhub-mod-' + row.getAttribute('data-mod'));
            if (modEl) {
                var t = modEl.querySelector('.vhub-module-title');
                if (t) { modTitle = t.textContent.toLowerCase(); }
            }
            if (q && name.indexOf(q) === -1 && modTitle.indexOf(q) === -1) { ok = false; }
            row.style.display = ok ? '' : 'none';
            if (ok) { visible++; }
        });
        document.querySelectorAll('.vhub-module').forEach(function (mod) {
            var has = [].slice.call(mod.querySelectorAll('.vhub-vid[data-key]')).some(function (r) { return r.style.display !== 'none'; });
            if (mode !== 'all' || q) {
                mod.querySelector('.vhub-module-head').style.display = has ? '' : 'none';
            } else {
                mod.querySelector('.vhub-module-head').style.display = '';
            }
        });
        document.getElementById('vhubNoResults').style.display = visible === 0 ? 'block' : 'none';
    }

    document.querySelectorAll('.vhub-filters button').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.vhub-filters button').forEach(function (x) { x.classList.remove('is-active'); });
            b.classList.add('is-active');
            applyFilter();
        });
    });
    document.getElementById('vhubSearch').addEventListener('input', applyFilter);

    document.getElementById('vhubExpand').addEventListener('click', function () {
        document.querySelectorAll('.vhub-module').forEach(function (m) {
            m.classList.add('is-open');
            var h = m.querySelector('.vhub-module-head');
            if (h) { h.setAttribute('aria-expanded', 'true'); }
        });
    });
    document.getElementById('vhubCollapse').addEventListener('click', function () {
        document.querySelectorAll('.vhub-module').forEach(function (m) {
            m.classList.remove('is-open');
            var h = m.querySelector('.vhub-module-head');
            if (h) { h.setAttribute('aria-expanded', 'false'); }
        });
    });
    document.getElementById('vhubReset').addEventListener('click', function () {
        if (!confirm('Reset all video progress? This cannot be undone.')) { return; }
        progress = {};
        saveProgress();
        rows.forEach(paintRow);
        recalc();
    });

    recalc();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>