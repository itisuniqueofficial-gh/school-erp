/* Student profile: Identity & Roll Numbers (PRN copy feedback + change-roll form validation). */
(function () {
    'use strict';

    // PRN copy: inline "Copied" feedback. The global [data-copy] handler does the copying.
    document.querySelectorAll('[data-idr-copy]').forEach(function (btn) {
        var label = btn.querySelector('span');
        var status = btn.parentNode.querySelector('.idr-copy-status');
        var timer;
        btn.addEventListener('click', function () {
            btn.classList.add('is-copied');
            label.textContent = 'Copied';
            if (status) { status.textContent = 'PRN copied to clipboard'; }
            clearTimeout(timer);
            timer = setTimeout(function () {
                btn.classList.remove('is-copied');
                label.textContent = 'Copy';
                if (status) { status.textContent = ''; }
            }, 2000);
        });
    });

    // Roll-number form: validate first, then let the shared [data-confirm] dialog run.
    var form = document.querySelector('[data-idr-form]');
    if (!form) { return; }
    var roll = form.elements['roll_no'];
    var reason = form.elements['reason'];
    var btn = form.querySelector('button[type="submit"]');
    var current = form.getAttribute('data-idr-current');

    function fail(field, msg) {
        field.classList.add('field-invalid');
        field.setAttribute('aria-invalid', 'true');
        var m = field.nextElementSibling;
        if (!m || !m.classList.contains('field-error-msg')) {
            m = document.createElement('p');
            m.className = 'field-error-msg';
            field.parentNode.insertBefore(m, field.nextSibling);
        }
        m.textContent = msg;
        m.setAttribute('role', 'alert');
        return false;
    }
    function clear(field) {
        field.classList.remove('field-invalid');
        field.removeAttribute('aria-invalid');
        var m = field.nextElementSibling;
        if (m && m.classList.contains('field-error-msg')) { m.remove(); }
    }
    function check() {
        var ok = true, first = null;
        var v = roll.value.trim(), r = reason.value.trim();
        clear(roll); clear(reason);
        if (v === '') { ok = fail(roll, 'Enter the new roll number.'); first = first || roll; }
        else if (!/^[0-9]{1,5}$/.test(v) || parseInt(v, 10) < 1) { ok = fail(roll, 'Use a whole number from 1 to 99999.'); first = first || roll; }
        else if (v === current) { ok = fail(roll, 'This is already the current roll number.'); first = first || roll; }
        if (r === '') { ok = fail(reason, 'Enter a reason for the change.'); first = first || reason; }
        if (first) { first.focus(); }
        return ok;
    }

    // Bound on the button itself, so it runs before the document-level confirm handler.
    btn.addEventListener('click', function (e) {
        if (btn.dataset.confirmed === '1') { return; }
        if (!check()) { e.preventDefault(); e.stopPropagation(); return; }
        var msg = 'Change the roll number from ' + current + ' to ' + roll.value.trim() + '? This is recorded in the audit history.';
        btn.setAttribute('data-confirm', msg);
        if (window.jQuery) { window.jQuery(btn).data('confirm', msg); } // jQuery caches data-*
    });
    [roll, reason].forEach(function (f) {
        f.addEventListener('input', function () { if (f.classList.contains('field-invalid')) { check(); } });
    });
})();
