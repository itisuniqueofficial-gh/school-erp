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
        if (window.ERPAnim) {
            window.ERPAnim.toastEnter($t[0]);
        }
        setTimeout(function () {
            if (window.ERPAnim) {
                window.ERPAnim.toastExit($t[0], function () { $t.remove(); });
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

        // --- Sidebar drawer (mobile) --------------------------------------
        // Off-canvas drawer with body scroll-lock, focus management, overlay
        // click and Escape support. Desktop (lg+) keeps the sidebar static, so
        // these handlers are no-ops there (the toggle button is hidden by CSS).
        const $sidebar = $('[data-sidebar]');
        const $backdrop = $('[data-sidebar-backdrop]');
        const $sidebarToggle = $('[data-sidebar-toggle]');

        function sidebarIsOpen() {
            return $sidebar.length && !$sidebar.hasClass('-translate-x-full');
        }
        function openSidebar() {
            $sidebar.removeClass('-translate-x-full');
            $backdrop.removeClass('hidden');
            $('body').addClass('overflow-hidden');
            $sidebarToggle.attr('aria-expanded', 'true');
            // Move focus into the drawer for keyboard/screen-reader users.
            $sidebar.find('a, button').filter(':visible').first().trigger('focus');
        }
        function closeSidebar(restoreFocus) {
            $sidebar.addClass('-translate-x-full');
            $backdrop.addClass('hidden');
            $('body').removeClass('overflow-hidden');
            $sidebarToggle.attr('aria-expanded', 'false');
            if (restoreFocus !== false) { $sidebarToggle.trigger('focus'); }
        }

        $sidebarToggle.attr('aria-expanded', 'false');
        $sidebarToggle.on('click', function () {
            if (sidebarIsOpen()) { closeSidebar(); } else { openSidebar(); }
        });
        $backdrop.on('click', function () { closeSidebar(); });

        // --- User dropdown ------------------------------------------------
        $('[data-dropdown-toggle]').on('click', function (e) {
            e.stopPropagation();
            const target = $(this).data('dropdown-toggle');
            const $menu = $('#' + target);
            const willOpen = $menu.hasClass('hidden');
            $('[data-dropdown]').addClass('hidden');
            $('[data-dropdown-toggle]').attr('aria-expanded', 'false');
            $menu.toggleClass('hidden', !willOpen);
            $(this).attr('aria-expanded', willOpen ? 'true' : 'false');
        });
        $(document).on('click', function () {
            $('[data-dropdown]').addClass('hidden');
            $('[data-dropdown-toggle]').attr('aria-expanded', 'false');
        });

        // --- Escape key: close open overlays ------------------------------
        $(document).on('keydown', function (e) {
            if (e.key !== 'Escape' && e.keyCode !== 27) { return; }
            if (sidebarIsOpen()) { closeSidebar(); }
            $('[data-dropdown]').addClass('hidden');
            $('[data-dropdown-toggle]').attr('aria-expanded', 'false');
        });

        // --- Password visibility toggle (app-wide) ------------------------
        // Any `<button data-toggle-password="inputId">` with an <i> icon works.
        $(document).on('click', '[data-toggle-password]', function () {
            const input = document.getElementById($(this).data('toggle-password'));
            if (!input) { return; }
            const $icon = $(this).find('i');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            $icon.toggleClass('fa-eye', !show).toggleClass('fa-eye-slash', show);
            $(this).attr('aria-pressed', show ? 'true' : 'false');
        });

        // --- Copy-to-clipboard for 12-digit IDs and other tokens ----------
        $(document).on('click', '[data-copy]', function (e) {
            e.preventDefault();
            var text = String($(this).data('copy'));
            var done = function () { if (window.toast) { window.toast('Copied: ' + text, 'success'); } };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, done);
            } else {
                var $t = $('<textarea>').val(text).css({ position: 'fixed', left: '-9999px' }).appendTo('body');
                $t[0].select();
                try { document.execCommand('copy'); } catch (err) {}
                $t.remove();
                done();
            }
        });

        // --- Confirm + method spoofing for delete buttons -----------------
        // Uses SweetAlert2 when available, with a graceful fallback to the
        // native confirm() so destructive actions are never left unguarded.
        $(document).on('click', '[data-confirm]', function (e) {
            const el = this;
            if (el.dataset.confirmed === '1') { return; } // already confirmed -> let it through
            const msg = $(el).data('confirm') || 'Are you sure?';
            e.preventDefault();

            const proceed = function () {
                el.dataset.confirmed = '1';
                const $form = $(el).closest('form');
                if (el.tagName === 'A' && el.getAttribute('href')) {
                    window.location.href = el.getAttribute('href');
                } else if ($form.length) {
                    const form = $form.get(0);
                    if (form.requestSubmit && el.tagName === 'BUTTON') { form.requestSubmit(el); }
                    else { form.submit(); }
                } else {
                    el.click();
                }
            };

            if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire({
                    title: 'Please confirm',
                    text: msg,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, continue',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    reverseButtons: true,
                    focusCancel: true
                }).then(function (result) { if (result.isConfirmed) { proceed(); } });
            } else if (window.confirm(msg)) {
                proceed();
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

        // --- Reusable Excel export (SheetJS, lazy-loaded on first use) ----
        function notifyError(msg) {
            if (window.Swal && Swal.fire) { Swal.fire({ icon: 'error', title: 'Export unavailable', text: msg }); }
            else { window.alert(msg); }
        }
        function ensureSheetJS(cb) {
            if (window.XLSX) { cb(); return; }
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
            s.onload = function () { cb(); };
            s.onerror = function () { notifyError('Could not load the export library. Check your connection and try again.'); };
            document.head.appendChild(s);
        }
        function exportTableToXlsx(table, filename, sheetName) {
            var headerCells = Array.prototype.slice.call(table.querySelectorAll('thead th'));
            var skip = headerCells.map(function (th) { return th.hasAttribute('data-noexport'); });
            var rows = [];
            rows.push(headerCells.filter(function (_, i) { return !skip[i]; }).map(function (th) { return th.textContent.trim(); }));
            Array.prototype.slice.call(table.querySelectorAll('tbody tr')).forEach(function (tr) {
                if (tr.offsetParent === null && tr.style.display === 'none') { return; } // skip client-side-hidden rows
                var cells = Array.prototype.slice.call(tr.children);
                // ignore full-width placeholder rows (e.g. "No records found")
                if (cells.length <= 1) { return; }
                rows.push(cells.filter(function (_, i) { return !skip[i]; }).map(function (td) {
                    return (td.getAttribute('data-export') || td.textContent).trim();
                }));
            });
            var ws = XLSX.utils.aoa_to_sheet(rows);
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, (sheetName || 'Sheet1').substring(0, 31));
            XLSX.writeFile(wb, (filename || 'export') + '.xlsx');
        }
        $(document).on('click', '[data-export-xlsx]', function (e) {
            e.preventDefault();
            var btn = this;
            var table = document.querySelector(btn.getAttribute('data-export-target') || '');
            if (!table) { return; }
            var filename = btn.getAttribute('data-export-filename') || 'export';
            var sheet = btn.getAttribute('data-export-sheet') || 'Export';
            ensureSheetJS(function () {
                try { exportTableToXlsx(table, filename, sheet); }
                catch (err) { console.error(err); notifyError('Export failed. Please try again.'); }
            });
        });

        // --- Loading state on form submit ---------------------------------
        $('form[data-loading]').on('submit', function () {
            const $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).attr('data-original', $btn.html())
                .html('<i class="fa-solid fa-spinner fa-spin"></i> Please wait...');
        });

        // ==================================================================
        // Reusable, lazy-loaded, OPT-IN client enhancements. Each activates
        // only on elements explicitly marked with a data-attribute, so pages
        // that don't opt in behave exactly as before (no regressions). All
        // libraries load from CDN on demand and fail safe (the underlying
        // native element keeps working if a CDN is unavailable).
        // ==================================================================
        window.ERP = window.ERP || {};
        var loadedCss = {};
        function ensureCss(href) {
            if (loadedCss[href]) { return; }
            loadedCss[href] = true;
            var l = document.createElement('link');
            l.rel = 'stylesheet'; l.href = href;
            document.head.appendChild(l);
        }
        function ensureLib(test, src, cb) {
            if (test()) { cb(); return; }
            var existing = document.querySelector('script[data-src="' + src + '"]');
            if (existing) { existing.addEventListener('load', function () { if (test()) cb(); }); return; }
            var s = document.createElement('script');
            s.src = src; s.setAttribute('data-src', src);
            s.onload = function () { if (test()) { cb(); } };
            s.onerror = function () { /* fail safe: native element still works */ };
            document.head.appendChild(s);
        }

        // Tom Select — searchable selects: <select data-tomselect ...>
        ERP.initTomSelect = function (root) {
            var nodes = (root || document).querySelectorAll('select[data-tomselect]:not([data-ts-init])');
            if (!nodes.length) { return; }
            ensureCss('https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css');
            ensureLib(function () { return !!window.TomSelect; }, 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js', function () {
                nodes.forEach(function (el) {
                    el.setAttribute('data-ts-init', '1');
                    try { new window.TomSelect(el, { allowEmptyOption: true, maxOptions: 1000 }); } catch (e) { console.error('TomSelect', e); }
                });
            });
        };

        // DataTables — client-side for SMALL, fully-rendered tables: <table data-datatable>
        ERP.initDataTables = function (root) {
            var nodes = (root || document).querySelectorAll('table[data-datatable]:not([data-dt-init])');
            if (!nodes.length) { return; }
            ensureCss('https://cdn.datatables.net/1.13.8/css/dataTables.dataTables.min.css');
            ensureLib(function () { return !!(window.jQuery && $.fn && $.fn.dataTable); }, 'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', function () {
                nodes.forEach(function (t) {
                    t.setAttribute('data-dt-init', '1');
                    try {
                        $(t).DataTable({
                            pageLength: parseInt(t.getAttribute('data-dt-page') || '25', 10),
                            order: [], // preserve server order by default
                            columnDefs: [{ targets: 'no-sort', orderable: false }]
                        });
                    } catch (e) { console.error('DataTables', e); }
                });
            });
        };

        // QRCode — client-side QR for non-sensitive identifiers: <div data-qr="value" data-qr-size="110">
        ERP.initQRCodes = function (root) {
            var nodes = (root || document).querySelectorAll('[data-qr]:not([data-qr-init])');
            if (!nodes.length) { return; }
            ensureLib(function () { return !!window.QRCode; }, 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js', function () {
                nodes.forEach(function (el) {
                    el.setAttribute('data-qr-init', '1');
                    var val = el.getAttribute('data-qr') || '';
                    var size = parseInt(el.getAttribute('data-qr-size') || '104', 10);
                    if (val === '') { return; }
                    try { new window.QRCode(el, { text: val, width: size, height: size, correctLevel: window.QRCode.CorrectLevel.M }); } catch (e) { console.error('QRCode', e); }
                });
            });
        };

        // Day.js — lazy date utility (never force-reformats server-rendered dates).
        ERP.formatDate = function (value, fmt, cb) {
            fmt = fmt || 'DD MMM YYYY';
            ensureLib(function () { return !!window.dayjs; }, 'https://cdn.jsdelivr.net/npm/dayjs@1.11.10/dayjs.min.js', function () {
                var d = window.dayjs(value);
                var out = d.isValid() ? d.format(fmt) : '';
                if (typeof cb === 'function') { cb(out); }
            });
        };

        // Auto-initialize opt-in elements present on load.
        ERP.initTomSelect();
        ERP.initDataTables();
        ERP.initQRCodes();


        // --- Entrance animations (safe: content is visible by default) ----
        if (window.ERPAnim) {
            window.ERPAnim.staggerCards('[data-animate-card]');
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
        // Disable browser-native validation bubbles so there is ONE consistent
        // validation system (custom). Fields still keep `required` for a11y.
        $('form:not([data-no-validate])').attr('novalidate', 'novalidate');

        // Do NOT show errors before submit. Only re-validate a field that is
        // already in the error state, so the message clears the moment it's fixed.
        $(document).on('input change', 'form:not([data-no-validate]) .field-invalid', function () {
            validateField(this);
        });

        // Validate everything on submit; block and focus the first invalid field.
        $(document).on('submit', 'form:not([data-no-validate])', function (e) {
            let ok = true;
            $(this).find('input, select, textarea').each(function () {
                if ($(this).attr('type') === 'hidden' || this.disabled) { return; }
                if (!validateField(this)) { ok = false; }
            });
            if (!ok) {
                e.preventDefault();
                e.stopImmediatePropagation();
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

/* ==========================================================================
   Cascading dependent dropdowns (Academic Year -> Class -> Section -> Subject
   -> Teacher). Progressive enhancement: forms still work without JS because
   the server renders the full option lists too.
   ========================================================================== */
(function ($) {
    'use strict';

    var OPTIONS_URL = (window.ERP_BASE || '') + '/admin/academic/options';

    function fill($select, rows, placeholder) {
        if (!$select.length) { return; }
        var current = $select.val();
        var ph = $select.data('placeholder') || placeholder;
        $select.empty();
        if (ph) {
            $select.append($('<option>').val('').text(ph));
        }
        (rows || []).forEach(function (r) {
            $select.append($('<option>').val(r.id).text(r.name));
        });
        // Preserve previous selection when still present (else it resets — the
        // desired behaviour when a parent changes and old value is now invalid).
        if (current) { $select.val(current); }
    }

    function load(type, params, cb) {
        $.getJSON(OPTIONS_URL, $.extend({ type: type }, params))
            .done(function (res) { cb((res && res.data) || []); })
            .fail(function () { cb([]); });
    }

    $(function () {
        // Class -> Section (element carries data-target-section="#selector").
        $(document).on('change', 'select[data-cascade="class"][data-target-section]', function () {
            var classId = $(this).val();
            var $section = $($(this).data('target-section'));
            var $subject = $($(this).data('target-subject') || '');
            var $wrap = $section.closest('div');
            if (!classId) {
                fill($section, [], null);
                $wrap.show(); $section.prop('disabled', false);
                if ($subject.length) { fill($subject, [], null); }
                return;
            }
            // Respect the class's section-usage flag: no-section classes hide the
            // Section field entirely and submit without a section.
            $.getJSON(OPTIONS_URL, { type: 'class-config', class_id: classId }).done(function (cfg) {
                var uses = (cfg && cfg.uses_sections !== undefined) ? Number(cfg.uses_sections) : 1;
                if (uses === 0) {
                    $section.val('').prop('disabled', true);
                    $wrap.hide();
                } else {
                    $section.prop('disabled', false);
                    $wrap.show();
                    load('sections', { class_id: classId }, function (rows) { fill($section, rows, null); });
                }
            }).fail(function () {
                $section.prop('disabled', false); $wrap.show();
                load('sections', { class_id: classId }, function (rows) { fill($section, rows, null); });
            });
            if ($subject.length) {
                load('subjects', { class_id: classId }, function (rows) { fill($subject, rows, null); });
            }
        });

        // Section change -> refresh teacher list where a target is declared.
        $(document).on('change', 'select[data-cascade="section"][data-target-teacher]', function () {
            var $form = $(this).closest('form');
            var classId = $form.find('[name="class_id"]').val();
            var sectionId = $(this).val();
            var subjectId = $form.find('[name="subject_id"]').val();
            var $teacher = $($(this).data('target-teacher'));
            if (!classId || !sectionId) { fill($teacher, [], 'Teacher'); return; }
            load('teachers', { class_id: classId, section_id: sectionId, subject_id: subjectId || 0 }, function (rows) {
                fill($teacher, rows, 'Teacher');
            });
        });
    });

    // Live image preview for profile-picture upload inputs.
    $(document).on('change', '[data-image-preview]', function () {
        var target = document.querySelector($(this).data('image-preview'));
        var file = this.files && this.files[0];
        if (!target) { return; }
        if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) {
            target.classList.add('hidden');
            target.removeAttribute('src');
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) { target.src = e.target.result; target.classList.remove('hidden'); };
        reader.readAsDataURL(file);
    });
})(jQuery);
