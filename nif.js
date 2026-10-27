/**
 * Validação de NIF português (cliente) — alinhada com inc_nif.php
 */
(function (global) {
    'use strict';

    function amNormalizarNif(nif) {
        return String(nif || '').replace(/\D+/g, '').slice(0, 9);
    }

    function amValidarNifPortugues(nif) {
        const nifLimpo = amNormalizarNif(nif);

        if (nifLimpo.length === 0) {
            return { ok: true, message: '' };
        }

        if (nifLimpo.length !== 9) {
            return { ok: false, message: 'O NIF deve ter exatamente 9 dígitos.' };
        }

        if (/^(\d)\1{8}$/.test(nifLimpo)) {
            return { ok: false, message: 'O NIF não pode ter todos os dígitos iguais.' };
        }

        if (!/^[1235689]/.test(nifLimpo)) {
            return { ok: false, message: 'O NIF tem um prefixo inválido.' };
        }

        let soma = 0;
        for (let i = 0; i < 8; i++) {
            soma += Number(nifLimpo[i]) * (9 - i);
        }
        let controlo = 11 - (soma % 11);
        if (controlo >= 10) {
            controlo = 0;
        }

        if (Number(nifLimpo[8]) !== controlo) {
            return { ok: false, message: 'NIF inválido (dígito de controlo incorreto).' };
        }

        return { ok: true, message: '' };
    }

    function amMarcarNifInput(inp, resultado) {
        if (!inp) {
            return;
        }

        let hint = inp.parentElement ? inp.parentElement.querySelector('.am-nif-hint') : null;
        if (!hint && inp.parentElement) {
            hint = document.createElement('small');
            hint.className = 'am-nif-hint';
            inp.parentElement.appendChild(hint);
        }

        if (!resultado.ok) {
            inp.classList.add('am-nif-invalid');
            inp.setCustomValidity(resultado.message);
            if (hint) {
                hint.textContent = resultado.message;
                hint.classList.add('is-error');
            }
        } else {
            inp.classList.remove('am-nif-invalid');
            inp.setCustomValidity('');
            if (hint) {
                hint.textContent = inp.value ? '' : 'Opcional. 9 dígitos, com dígito de controlo válido.';
                hint.classList.remove('is-error');
            }
        }
    }

    function amValidarNifInput(inp) {
        const valor = amNormalizarNif(inp.value);
        inp.value = valor;
        const resultado = amValidarNifPortugues(valor);
        amMarcarNifInput(inp, resultado);
        return resultado.ok;
    }

    async function amVerificarIdentificadorUnico(tipo, valor, inp) {
        const body = new URLSearchParams();
        body.set('tipo', tipo);
        body.set('valor', valor);
        if (inp && inp.dataset.excluirUtilizador) {
            body.set('excluir_utilizador', inp.dataset.excluirUtilizador);
        }
        if (inp && inp.dataset.excluirMecanicos) {
            body.set('excluir_mecanicos', inp.dataset.excluirMecanicos);
        }
        if (inp && inp.dataset.excluirChefesOficina) {
            body.set('excluir_chefes_oficina', inp.dataset.excluirChefesOficina);
        }

        const res = await fetch('api_verificar_identificador.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });

        if (!res.ok) {
            return { ok: true, mensagem: '' };
        }

        return res.json();
    }

    async function amValidarNifInputCompleto(inp) {
        if (!inp.value) {
            amMarcarNifInput(inp, { ok: true, message: '' });
            return true;
        }
        if (!amValidarNifInput(inp)) {
            return false;
        }

        try {
            const remoto = await amVerificarIdentificadorUnico('nif', amNormalizarNif(inp.value), inp);
            if (!remoto.ok) {
                amMarcarNifInput(inp, { ok: false, message: remoto.mensagem || 'Este NIF já está registado.' });
                return false;
            }
        } catch (err) {
            // Sem rede: validação local mantém-se
        }

        amMarcarNifInput(inp, { ok: true, message: '' });
        return true;
    }

    function amInitNifInputs(root) {
        const scope = root || document;
        const seletor = [
            '.am-nif-input',
            'input[name="nif"]',
            'input[name="nif_mecanico"]',
            'input[name="nif_chefe_oficina"]',
            'input[name="nif_utilizador"]'
        ].join(',');

        scope.querySelectorAll(seletor).forEach((inp) => {
            if (inp.dataset.amNifInit === '1') {
                return;
            }
            inp.dataset.amNifInit = '1';
            inp.classList.add('am-nif-input');
            inp.setAttribute('inputmode', 'numeric');
            inp.setAttribute('maxlength', '9');
            inp.setAttribute('autocomplete', 'off');
            if (!inp.getAttribute('placeholder')) {
                inp.setAttribute('placeholder', '123456789');
            }
            if (!inp.getAttribute('title')) {
                inp.setAttribute('title', 'NIF português válido (9 dígitos)');
            }

            inp.addEventListener('input', () => {
                inp.value = amNormalizarNif(inp.value);
                if (inp.classList.contains('am-nif-invalid')) {
                    amMarcarNifInput(inp, amValidarNifPortugues(inp.value));
                } else {
                    inp.setCustomValidity('');
                }
            });

            inp.addEventListener('blur', () => {
                amValidarNifInputCompleto(inp);
            });

            if (!inp.value) {
                amMarcarNifInput(inp, { ok: true, message: '' });
            }
        });
    }

    function amValidarNifsNoFormulario(form) {
        if (!form) {
            return true;
        }

        let valido = true;
        form.querySelectorAll('.am-nif-input').forEach((inp) => {
            if (!inp.value) {
                amMarcarNifInput(inp, { ok: true, message: '' });
                return;
            }
            if (!amValidarNifInput(inp)) {
                valido = false;
            }
        });

        if (!valido) {
            const primeiro = form.querySelector('.am-nif-input.am-nif-invalid');
            if (primeiro) {
                primeiro.reportValidity();
                primeiro.focus();
            }
        }

        return valido;
    }

    global.amNormalizarNif = amNormalizarNif;
    global.amValidarNifPortugues = amValidarNifPortugues;
    global.amVerificarIdentificadorUnico = amVerificarIdentificadorUnico;
    global.amValidarNifInputCompleto = amValidarNifInputCompleto;
    global.amInitNifInputs = amInitNifInputs;
    global.amValidarNifsNoFormulario = amValidarNifsNoFormulario;

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            amInitNifInputs(document);

            document.querySelectorAll('form').forEach((form) => {
                if (!form.querySelector('.am-nif-input')) {
                    return;
                }
                form.addEventListener('submit', (ev) => {
                    if (!amValidarNifsNoFormulario(form)) {
                        ev.preventDefault();
                    }
                });
            });
        });
    }
})(typeof window !== 'undefined' ? window : globalThis);
