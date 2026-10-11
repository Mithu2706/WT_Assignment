/* =====================================================================
   admin.js - progressive enhancements for the PHP admin panel.
   Every feature also works without JavaScript:
     - delete links fall back to a server-rendered confirmation page
     - forms submit normally; JS only prevents accidental double submits
   ===================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var dialog = document.getElementById('confirm-dialog');
        var dialogForm = document.getElementById('confirm-form');
        var pendingForm = null;      // a <form data-confirm> waiting for confirmation

        function openDialog(message, action, id) {
            if (!dialog || typeof dialog.showModal !== 'function') return false;
            document.getElementById('confirm-message').textContent = message;   // textContent: no HTML injection
            if (action) {
                dialogForm.setAttribute('action', action);
                dialogForm.elements.id.value = id;
            }
            dialog.showModal();
            document.getElementById('confirm-submit').focus();
            return true;
        }

        // 1) "Delete student" links -> confirmation dialog that POSTs (with CSRF token)
        document.querySelectorAll('[data-confirm-delete]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                pendingForm = null;
                if (openDialog(link.dataset.message, link.dataset.confirmDelete, link.dataset.id)) {
                    e.preventDefault();
                }
            });
        });

        // 2) Forms with data-confirm (e.g. delete a file) -> confirm first
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === 'yes') return;
                e.preventDefault();
                pendingForm = form;
                if (!openDialog(form.dataset.confirm)) {
                    // <dialog> unsupported: fall back to the browser's confirm()
                    if (window.confirm(form.dataset.confirm)) { form.dataset.confirmed = 'yes'; form.submit(); }
                }
            });
        });

        if (dialogForm) {
            dialogForm.addEventListener('submit', function (e) {
                var button = document.getElementById('confirm-submit');
                button.disabled = true;                       // no double delete
                if (pendingForm) {
                    e.preventDefault();
                    pendingForm.dataset.confirmed = 'yes';
                    pendingForm.submit();
                }
            });
            dialog.querySelectorAll('[data-close-dialog]').forEach(function (btn) {
                btn.addEventListener('click', function () { dialog.close(); });
            });
            dialog.addEventListener('close', function () {
                pendingForm = null;
                document.getElementById('confirm-submit').disabled = false;
            });
        }

        // 3) Prevent accidental duplicate submissions (double clicks, Enter twice)
        document.querySelectorAll('form[data-prevent-double-submit]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.submitting === 'yes') { e.preventDefault(); return; }
                form.dataset.submitting = 'yes';
                form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                    btn.classList.add('is-loading');
                    btn.setAttribute('aria-disabled', 'true');
                    // Disable on the next tick so the form still submits normally.
                    setTimeout(function () { btn.disabled = true; }, 0);
                });
            });
        });

        // Re-enable forms when the user navigates "Back" to a cached page.
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            document.querySelectorAll('form[data-prevent-double-submit]').forEach(function (form) {
                form.dataset.submitting = '';
                form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                    btn.disabled = false;
                    btn.classList.remove('is-loading');
                    btn.removeAttribute('aria-disabled');
                });
            });
        });
    });
}());
