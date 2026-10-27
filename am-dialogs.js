/**
 * Diálogos AUTOMANAGER — amAlert / amConfirm e ligação a formulários.
 */
(function (global) {
    'use strict';

    var root = null;
    var titleEl = null;
    var msgEl = null;
    var iconEl = null;
    var btnOk = null;
    var btnCancel = null;
    var backdrop = null;
    var pendingResolve = null;
    var pendingMode = 'alert';

    function ensureDom() {
        if (root) {
            return;
        }
        root = document.createElement('div');
        root.id = 'amDialogRoot';
        root.className = 'am-dialog is-hidden';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-hidden', 'true');
        root.innerHTML =
            '<button type="button" class="am-dialog__backdrop" aria-label="Fechar"></button>' +
            '<div class="am-dialog__panel">' +
            '<div class="am-dialog__icon" aria-hidden="true"></div>' +
            '<h3 class="am-dialog__title"></h3>' +
            '<p class="am-dialog__msg"></p>' +
            '<div class="am-dialog__actions">' +
            '<button type="button" class="btn btn-secondary am-dialog__btn-cancel">Cancelar</button>' +
            '<button type="button" class="btn btn-primary am-dialog__btn-ok">OK</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(root);
        titleEl = root.querySelector('.am-dialog__title');
        msgEl = root.querySelector('.am-dialog__msg');
        iconEl = root.querySelector('.am-dialog__icon');
        btnOk = root.querySelector('.am-dialog__btn-ok');
        btnCancel = root.querySelector('.am-dialog__btn-cancel');
        backdrop = root.querySelector('.am-dialog__backdrop');

        btnOk.addEventListener('click', function () {
            closeDialog(true);
        });
        btnCancel.addEventListener('click', function () {
            closeDialog(false);
        });
        backdrop.addEventListener('click', function () {
            closeDialog(pendingMode === 'alert');
        });
        document.addEventListener('keydown', function (e) {
            if (!root || root.classList.contains('is-hidden')) {
                return;
            }
            if (e.key === 'Escape') {
                closeDialog(pendingMode === 'alert');
            }
        });
    }

    function setVariant(variant) {
        root.classList.remove('am-dialog--confirm', 'am-dialog--danger', 'am-dialog--success');
        var v = variant || 'info';
        if (v === 'confirm' || v === 'warn') {
            root.classList.add('am-dialog--confirm');
            iconEl.textContent = '!';
        } else if (v === 'danger') {
            root.classList.add('am-dialog--danger');
            iconEl.textContent = '×';
        } else if (v === 'success') {
            root.classList.add('am-dialog--success');
            iconEl.textContent = '✓';
        } else {
            iconEl.textContent = 'i';
        }
    }

    function openDialog(opts) {
        ensureDom();
        opts = opts || {};
        pendingMode = opts.mode || 'alert';

        setVariant(opts.variant || (pendingMode === 'confirm' ? 'confirm' : 'info'));

        if (titleEl) {
            titleEl.textContent = opts.title || (pendingMode === 'confirm' ? 'Confirmar' : 'Aviso');
        }
        if (msgEl) {
            msgEl.textContent = opts.message || '';
        }
        if (btnOk) {
            btnOk.textContent = opts.okText || (pendingMode === 'confirm' ? 'Sim, confirmar' : 'OK');
            btnOk.className = 'btn ' + (opts.okClass || (pendingMode === 'confirm' ? 'btn-danger' : 'btn-primary')) + ' am-dialog__btn-ok';
        }
        if (btnCancel) {
            btnCancel.textContent = opts.cancelText || 'Cancelar';
            btnCancel.style.display = pendingMode === 'confirm' ? '' : 'none';
        }

        root.classList.remove('is-hidden');
        root.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (btnOk) {
            btnOk.focus();
        }
    }

    function closeDialog(result) {
        if (!root) {
            return;
        }
        root.classList.add('is-hidden');
        root.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (typeof pendingResolve === 'function') {
            var fn = pendingResolve;
            pendingResolve = null;
            fn(result);
        }
    }

    function amAlert(message, opts) {
        return new Promise(function (resolve) {
            pendingResolve = function () {
                resolve();
            };
            openDialog({
                mode: 'alert',
                message: message,
                title: opts && opts.title,
                variant: (opts && opts.variant) || 'info',
                okText: (opts && opts.okText) || 'OK',
                okClass: (opts && opts.okClass) || 'btn-primary'
            });
        });
    }

    function amConfirm(message, opts) {
        return new Promise(function (resolve) {
            pendingResolve = resolve;
            openDialog({
                mode: 'confirm',
                message: message,
                title: opts && opts.title,
                variant: (opts && opts.variant) || 'confirm',
                okText: (opts && opts.okText) || 'Sim, confirmar',
                cancelText: (opts && opts.cancelText) || 'Cancelar',
                okClass: (opts && opts.okClass) || 'btn-danger'
            });
        });
    }

    function bindConfirmForms(selector) {
        document.querySelectorAll(selector).forEach(function (form) {
            if (form.dataset.amConfirmBound === '1') {
                return;
            }
            form.dataset.amConfirmBound = '1';
            if (!form.getAttribute('action') && form.classList.contains('cfg-confirm-form')) {
                form.setAttribute('action', 'configuracoes.php?seccao=veiculos');
            }
            form.setAttribute('method', 'post');
            var bypass = false;
            form.addEventListener('submit', function (e) {
                if (bypass) {
                    bypass = false;
                    return;
                }
                var title = form.getAttribute('data-confirm-title') || 'Confirmar';
                var msg = form.getAttribute('data-confirm-msg') || form.getAttribute('data-confirm') || 'Tem a certeza?';
                e.preventDefault();
                amConfirm(msg, { title: title }).then(function (ok) {
                    if (ok) {
                        bypass = true;
                        form.submit();
                    }
                });
            });
        });
    }

    function bindSubmitButtons() {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.amSubmitConfirmBound === '1') {
                return;
            }
            var hasBtn = form.querySelector('button[type="submit"][data-am-confirm], input[type="submit"][data-am-confirm]');
            if (!hasBtn) {
                return;
            }
            form.dataset.amSubmitConfirmBound = '1';
            var bypass = false;
            form.addEventListener('submit', function (e) {
                if (bypass) {
                    bypass = false;
                    return;
                }
                var sub = e.submitter;
                if (!sub || !sub.getAttribute('data-am-confirm')) {
                    return;
                }
                e.preventDefault();
                var title = sub.getAttribute('data-am-confirm-title') || 'Confirmar';
                var msg = sub.getAttribute('data-am-confirm');
                amConfirm(msg, { title: title }).then(function (ok) {
                    if (ok) {
                        bypass = true;
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit(sub);
                        } else {
                            form.submit();
                        }
                    }
                });
            });
        });
    }

    function bindConfirmLinks() {
        document.querySelectorAll('a[data-am-confirm]').forEach(function (link) {
            if (link.dataset.amConfirmBound === '1') {
                return;
            }
            link.dataset.amConfirmBound = '1';
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var href = link.getAttribute('href') || '#';
                amConfirm(link.getAttribute('data-am-confirm'), {
                    title: link.getAttribute('data-am-confirm-title') || 'Confirmar'
                }).then(function (ok) {
                    if (ok && href !== '#') {
                        global.location.href = href;
                    }
                });
            });
        });
    }

    function init() {
        bindConfirmForms('.cfg-confirm-form');
        bindSubmitButtons();
        bindConfirmLinks();
    }

    global.amAlert = amAlert;
    global.amConfirm = amConfirm;
    global.amBindConfirmForms = bindConfirmForms;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
