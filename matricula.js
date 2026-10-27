/**
 * Máscara e validação de matrículas portuguesas (cliente).
 */
(function (global) {
    'use strict';

    function formatMatriculaPT(valor) {
        const limpo = String(valor || '').replace(/[^a-z0-9]/gi, '').toUpperCase().slice(0, 6);
        const p1 = limpo.slice(0, 2);
        const p2 = limpo.slice(2, 4);
        const p3 = limpo.slice(4, 6);
        return [p1, p2, p3].filter(Boolean).join('-');
    }

    function validarMatriculaPT(valor) {
        const formatted = formatMatriculaPT(valor);
        const limpo = formatted.replace(/[^A-Z0-9]/g, '');

        if (!limpo) {
            return { ok: false, msg: 'Introduza a matrícula do veículo.', formatted };
        }
        if (limpo.length !== 6) {
            return { ok: false, msg: 'A matrícula deve ter 6 caracteres (ex.: AA-00-AA).', formatted };
        }
        if (/^(.)\1{5}$/.test(limpo)) {
            return { ok: false, msg: 'Matrícula inválida.', formatted };
        }

        const formatos = [
            /^[A-Z]{2}[0-9]{2}[A-Z]{2}$/,
            /^[0-9]{2}[A-Z]{2}[0-9]{2}$/,
            /^[0-9]{2}[0-9]{2}[A-Z]{2}$/
        ];

        const ok = formatos.some((re) => re.test(limpo));
        return {
            ok,
            msg: ok ? '' : 'Formato inválido. Use AA-00-AA, 00-AA-00 ou 00-00-AA.',
            formatted
        };
    }

    function aplicarMascara(inp) {
        if (!inp || inp.dataset.matriculaMask === '1') return;
        inp.dataset.matriculaMask = '1';
        inp.setAttribute('maxlength', '8');
        inp.setAttribute('autocapitalize', 'characters');
        inp.style.textTransform = 'uppercase';

        inp.addEventListener('input', function () {
            inp.value = formatMatriculaPT(inp.value);
            inp.setCustomValidity('');
        });

        inp.addEventListener('blur', function () {
            const r = validarMatriculaPT(inp.value);
            inp.value = r.formatted;
            if (inp.value && !r.ok) {
                inp.setCustomValidity(r.msg);
            } else {
                inp.setCustomValidity('');
            }
        });
    }

    function validarInputMatricula(inp) {
        if (!inp) return true;
        const r = validarMatriculaPT(inp.value);
        inp.value = r.formatted;
        if (!r.ok) {
            inp.setCustomValidity(r.msg);
            inp.reportValidity();
            return false;
        }
        inp.setCustomValidity('');
        return true;
    }

    function initMatriculaInputs() {
        const seletores = [
            'input[name="matricula"]',
            'input[name="plate"]',
            '#editCarroMatricula'
        ];
        document.querySelectorAll(seletores.join(',')).forEach(aplicarMascara);

        document.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.matriculaValidate === '1') return;
            const temMatricula = form.querySelector('input[name="matricula"], input[name="plate"], #editCarroMatricula');
            if (!temMatricula) return;
            form.dataset.matriculaValidate = '1';
            form.addEventListener('submit', function (e) {
                let valido = true;
                form.querySelectorAll('input[name="matricula"], input[name="plate"], #editCarroMatricula').forEach(function (inp) {
                    if (!validarInputMatricula(inp)) valido = false;
                });
                if (!valido) e.preventDefault();
            });
        });
    }

    global.formatMatriculaPT = formatMatriculaPT;
    global.validarMatriculaPT = validarMatriculaPT;
    global.validarInputMatricula = validarInputMatricula;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMatriculaInputs);
    } else {
        initMatriculaInputs();
    }
})(typeof window !== 'undefined' ? window : globalThis);
