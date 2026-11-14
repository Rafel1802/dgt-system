{{-- Global daily-report reminder: pops up 15 minutes before the 5:00 PM (Asia/Phnom_Penh) deadline for every member who has not yet submitted. --}}
@once
    @include('production-reports.partials.styles')
@endonce

<div id="pr-remind" class="pr-remind" data-turbo-permanent role="alert" aria-live="assertive">
    <div class="flex items-start gap-3">
        <div class="shrink-0 w-10 h-10 rounded-2xl flex items-center justify-center text-xl" style="background:rgba(245,158,11,.16)">⏰</div>
        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
                <div class="pr-remind-title" id="pr-remind-title">Daily Report due soon</div>
                <button type="button" id="pr-remind-close" class="text-lg leading-none opacity-60 hover:opacity-100" aria-label="Dismiss">&times;</button>
            </div>
            <div class="text-xs mt-0.5 opacity-80" id="pr-remind-text">Submit your Daily Production Report before the deadline.</div>
            <div class="flex items-end justify-between gap-3 mt-2">
                <div>
                    <div class="text-[10px] uppercase tracking-wider opacity-60" id="pr-remind-label">Time left</div>
                    <div class="pr-remind-time" id="pr-remind-time">--:--</div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="pr-remind-snooze" class="text-[11px] font-bold px-3 py-2 rounded-xl border" style="border-color:var(--pr-border,#e2e8f0)">Remind me in 3 min</button>
                    <a id="pr-remind-link" href="#" class="pr-btn pr-btn-indigo">Submit now →</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__prDailyReminder) return;
    window.__prDailyReminder = true;

    var STATUS_URL = @json(route('production-reports.reminder-status'));
    var deadlineAt = null, ticker = null, snoozedUntil = 0, urgentShown = false;

    function $(id) { return document.getElementById(id); }
    function pad(n) { return String(n).padStart(2, '0'); }
    function fmt(sec) { return pad(Math.floor(sec / 60)) + ':' + pad(sec % 60); }

    function render() {
        var box = $('pr-remind');
        if (!box || deadlineAt === null) return null;
        var diff = Math.round((deadlineAt - Date.now()) / 1000);
        var overdue = diff < 0, sec = Math.abs(diff);
        box.classList.toggle('is-urgent', overdue || sec <= 300);
        $('pr-remind-time').textContent = (overdue ? '+' : '') + fmt(sec);
        $('pr-remind-label').textContent = overdue ? 'Overdue by' : 'Time left';
        $('pr-remind-title').textContent = overdue ? 'Daily Report is overdue' : 'Daily Report due in ' + Math.max(1, Math.ceil(sec / 60)) + ' min';
        $('pr-remind-text').textContent = overdue
            ? 'The 5:00 PM deadline has passed — please submit now so it is recorded.'
            : 'Please submit your Daily Production Report before the deadline.';
        return diff;
    }

    function open() {
        var box = $('pr-remind');
        if (!box || (snoozedUntil && Date.now() < snoozedUntil)) return;
        render();
        box.classList.add('is-open');
        try { if (navigator.vibrate) navigator.vibrate([120, 80, 120]); } catch (e) {}
    }
    function close() { var b = $('pr-remind'); if (b) b.classList.remove('is-open'); }

    function tick() {
        var diff = render();
        if (diff === null) return;
        var box = $('pr-remind');
        // Re-open after a snooze ends, and again when the final 5 minutes start
        if (snoozedUntil && Date.now() >= snoozedUntil) { snoozedUntil = 0; open(); }
        if (!urgentShown && diff <= 300) { urgentShown = true; snoozedUntil = 0; open(); }
    }

    function poll() {
        fetch(STATUS_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { show: false }; })
            .then(function (d) {
                if (!d || !d.show) {
                    deadlineAt = null; urgentShown = false; close();
                    if (ticker) { clearInterval(ticker); ticker = null; }
                    return;
                }
                var first = deadlineAt === null;
                deadlineAt = Date.now() + d.seconds_left * 1000;
                $('pr-remind-link').setAttribute('href', d.url);
                render();
                if (!ticker) ticker = setInterval(tick, 1000);
                if (first) open();
            })
            .catch(function () {});
    }

    function init() {
        if (!$('pr-remind')) return;
        $('pr-remind-close').addEventListener('click', function () { snoozedUntil = Date.now() + 5 * 60 * 1000; close(); });
        $('pr-remind-snooze').addEventListener('click', function () { snoozedUntil = Date.now() + 3 * 60 * 1000; close(); });
        poll();
        setInterval(poll, 60000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
