/* ==========================================================================
   School ERP - Application JavaScript
   Progressive enhancement: CSRF for AJAX, toasts, sidebar, confirm dialogs,
   table search, and method-spoofing for delete actions.
   ========================================================================== */
(function ($) {
    'use strict';

    // --- CSRF token for all AJAX requests ---------------------------------
    const token = $('meta[name="csrf-token"]').attr('content');
    if (token) {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });
    }

    // --- Toast notifications ----------------------------------------------
    window.toast = function (message, type) {
        type = type || 'info';
        const colors = {
            success: 'bg-green-600', error: 'bg-red-600',
            warning: 'bg-amber-500', info: 'bg-slate-800'
        };
        const $t = $('<div>')
            .addClass('fixed z-50 top-5 right-5 text-white px-4 py-3 rounded-lg shadow-lg text-sm ' + (colors[type] || colors.info))
            .attr('role', 'alert')
            .text(message)
            .appendTo('body');
        if (window.gsap) {
            gsap.from($t[0], { x: 40, opacity: 0, duration: 0.3 });
        }
        setTimeout(function () {
            if (window.gsap) {
                gsap.to($t[0], { x: 40, opacity: 0, duration: 0.3, onComplete: () => $t.remove() });
            } else {
                $t.remove();
            }
        }, 3500);
    };

    $(function () {
        // Auto-dismiss server flash messages.
        $('[data-flash]').each(function () {
            const $el = $(this);
            setTimeout(() => $el.fadeOut(300, () => $el.remove()), 4000);
        });

        // --- Sidebar toggle (mobile) --------------------------------------
        $('[data-sidebar-toggle]').on('click', function () {
            $('[data-sidebar]').toggleClass('-translate-x-full');
            $('[data-sidebar-backdrop]').toggleClass('hidden');
        });
        $('[data-sidebar-backdrop]').on('click', function () {
            $('[data-sidebar]').addClass('-translate-x-full');
            $(this).addClass('hidden');
        });

        // --- User dropdown ------------------------------------------------
        $('[data-dropdown-toggle]').on('click', function (e) {
            e.stopPropagation();
            const target = $(this).data('dropdown-toggle');
            $('#' + target).toggleClass('hidden');
        });
        $(document).on('click', function () {
            $('[data-dropdown]').addClass('hidden');
        });

        // --- Confirm + method spoofing for delete buttons -----------------
        $(document).on('click', '[data-confirm]', function (e) {
            const msg = $(this).data('confirm') || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });

        // --- Client-side table search (progressive; server-side is source of truth)
        $('[data-table-search]').on('keyup', function () {
            const q = $(this).val().toLowerCase();
            const target = $(this).data('table-search');
            $('#' + target + ' tbody tr').each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
            });
        });

        // --- Loading state on form submit ---------------------------------
        $('form[data-loading]').on('submit', function () {
            const $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).attr('data-original', $btn.html())
                .html('<i class="fa-solid fa-spinner fa-spin"></i> Please wait...');
        });

        // --- Entrance animations ------------------------------------------
        if (window.gsap) {
            gsap.from('[data-animate-card]', { y: 16, opacity: 0, duration: 0.4, stagger: 0.05, ease: 'power2.out' });
        }
    });
})(jQuery);

/* ==========================================================================
   Client-side validation (progressive enhancement; server remains source of
   truth). Validates required, email, phone, min/maxlength, pattern, match.
   ========================================================================== */
(function ($) {
    'use strict';

    function showError($field, msg) {
        $field.addClass('field-invalid').attr('aria-invalid', 'true');
        let $msg = $field.next('.field-error-msg');
        if (!$msg.length) { $msg = $('<p class="field-error-msg"></p>').insertAfter($field); }
        $msg.text(msg);
    }
    function clearError($field) {
        $field.removeClass('field-invalid').removeAttr('aria-invalid');
        $field.next('.field-error-msg').remove();
    }

    function validateField(field) {
        const $f = $(field);
        const val = ($f.val() || '').toString().trim();
        const type = $f.attr('type');
        clearError($f);

        if ($f.prop('required') && !val) { showError($f, 'This field is required.'); return false; }
        if (!val) { return true; }
        if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { showError($f, 'Enter a valid email address.'); return false; }
        if ($f.attr('name') && $f.attr('name').indexOf('phone') > -1 && !/^[0-9+\-\s()]{7,20}$/.test(val)) { showError($f, 'Enter a valid phone number.'); return false; }
        const min = parseInt($f.attr('minlength'), 10); if (min && val.length < min) { showError($f, 'Must be at least ' + min + ' characters.'); return false; }
        const pattern = $f.attr('pattern'); if (pattern && !(new RegExp('^(?:' + pattern + ')$').test(val))) { showError($f, 'Invalid format.'); return false; }
        if ($f.attr('name') === 'password' && type === 'password' && val && !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(val)) {
            showError($f, 'Use 8+ chars with upper, lower and a number.'); return false;
        }
        if ($f.attr('name') === 'password_confirmation') {
            const pw = $('input[name="password"]').val();
            if (pw && val !== pw) { showError($f, 'Passwords do not match.'); return false; }
        }
        return true;
    }

    $(function () {
        // Validate on blur and on submit for forms not explicitly opted out.
        $(document).on('blur', 'form:not([data-no-validate]) input, form:not([data-no-validate]) select, form:not([data-no-validate]) textarea', function () {
            validateField(this);
        });
        $(document).on('submit', 'form:not([data-no-validate])', function (e) {
            let ok = true;
            $(this).find('input, select, textarea').each(function () {
                if ($(this).attr('type') === 'hidden' || this.disabled) { return; }
                if (!validateField(this)) { ok = false; }
            });
            if (!ok) {
                e.preventDefault();
                const $first = $(this).find('.field-invalid').first();
                if ($first.length) { $first.trigger('focus'); }
                if (window.toast) { window.toast('Please correct the highlighted fields.', 'error'); }
            }
        });

        // --- QR code rendering (client-side, dependency loaded on demand) -----
        const $qr = $('[data-qr]');
        if ($qr.length) {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
            s.onload = function () {
                $qr.each(function () {
                    // eslint-disable-next-line no-new
                    new QRCode(this, { text: $(this).data('qr').toString(), width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M });
                });
            };
            document.head.appendChild(s);
        }
    });
})(jQuery);
