/**
 * Validação de IBAN (cliente) — alinhada com inc_iban.php
 */
(function (global) {
    'use strict';

    const COMPRIMENTOS_PAIS = {
        PT: 25,
        ES: 24,
        FR: 27,
        DE: 22,
        IT: 27,
        BE: 16,
        NL: 18,
        LU: 20,
        IE: 22,
        AT: 20,
        CH: 21,
        GB: 22
    };

    const EXEMPLO_PT = 'PT50 0000 0000 0000 0000 0000 0';

    function amComprimentoIbanPais(limpo) {
        if (limpo.length < 2 || !/^[A-Z]{2}/.test(limpo)) {
            return 34;
        }
        const pais = limpo.slice(0, 2);
        return Object.prototype.hasOwnProperty.call(COMPRIMENTOS_PAIS, pais)
            ? COMPRIMENTOS_PAIS[pais]
            : 34;
    }

    function amNormalizarIban(iban) {
        const limpo = String(iban || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (!limpo) {
            return '';
        }
        return limpo.slice(0, amComprimentoIbanPais(limpo));
    }

    function amFormatarIban(iban) {
        const limpo = amNormalizarIban(iban);
        if (!limpo) {
            return '';
        }
        return limpo.replace(/(.{4})/g, '$1 ').trim();
    }

    function amMensagemComprimentoIban(pais, atual, esperado) {
        const exemplo = pais === 'PT' ? EXEMPLO_PT : '';
        let msg;

        if (atual > esperado) {
            msg = pais === 'PT'
                ? `IBAN português demasiado longo (${atual} caracteres, máximo ${esperado}).`
                : `IBAN de ${pais} demasiado longo (${atual} caracteres, máximo ${esperado}).`;
        } else {
            msg = pais === 'PT'
                ? `IBAN português incompleto (${atual}/${esperado} caracteres).`
                : `IBAN de ${pais} incompleto (${atual}/${esperado} caracteres).`;
        }

        if (exemplo) {
            msg += ` Formato: ${exemplo}.`;
        }

        return msg;
    }

    function amValidarIbanPortugues(limpo) {
        if (!/^PT\d{23}$/.test(limpo)) {
            return { ok: false, message: 'IBAN português inválido. Após PT50 devem seguir 23 dígitos (NIB).' };
        }

        const banco = limpo.slice(4, 8);
        if (banco === '0000' || !/^\d{4}$/.test(banco)) {
            return { ok: false, message: 'Código de banco inválido no IBAN português.' };
        }

        return { ok: true, message: '' };
    }

    function amIbanMod97(iban) {
        const limpo = amNormalizarIban(iban);
        if (limpo.length < 5) {
            return false;
        }

        const rearranged = limpo.slice(4) + limpo.slice(0, 4);
        let numeric = '';

        for (let i = 0; i < rearranged.length; i++) {
            const code = rearranged.charCodeAt(i);
            if (code >= 48 && code <= 57) {
                numeric += rearranged[i];
            } else if (code >= 65 && code <= 90) {
                numeric += String(code - 55);
            } else {
                return false;
            }
        }

        let remainder = 0;
        for (let i = 0; i < numeric.length; i++) {
            remainder = (remainder * 10 + Number(numeric[i])) % 97;
        }

        return remainder === 1;
    }

    function amValidarIban(iban, parcial) {
        const limpo = amNormalizarIban(iban);

        if (limpo.length === 0) {
            return { ok: true, message: '' };
        }

        if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/.test(limpo)) {
            return { ok: false, message: 'Formato de IBAN inválido. Comece por PT50 e use apenas letras e números.' };
        }

        const pais = limpo.slice(0, 2);
        const esperado = COMPRIMENTOS_PAIS[pais];

        if (esperado) {
            if (limpo.length < esperado) {
                if (parcial) {
                    return { ok: true, message: '', parcial: true, atual: limpo.length, esperado };
                }
                return { ok: false, message: amMensagemComprimentoIban(pais, limpo.length, esperado) };
            }
            if (limpo.length !== esperado) {
                return { ok: false, message: amMensagemComprimentoIban(pais, limpo.length, esperado) };
            }
        } else if (limpo.length < 15 || limpo.length > 34) {
            return { ok: false, message: 'Comprimento de IBAN inválido.' };
        }

        if (pais === 'PT') {
            const pt = amValidarIbanPortugues(limpo);
            if (!pt.ok) {
                return pt;
            }
        }

        if (!amIbanMod97(limpo)) {
            return { ok: false, message: 'IBAN inválido (dígitos de controlo incorretos). Verifique todos os números.' };
        }

        return { ok: true, message: '' };
    }

    function amHintIbanParcial(limpo) {
        if (!limpo || limpo.length < 2) {
            return 'Opcional. Ex.: ' + EXEMPLO_PT;
        }

        const pais = limpo.slice(0, 2);
        const esperado = COMPRIMENTOS_PAIS[pais];
        if (esperado && limpo.length < esperado) {
            return `A escrever… ${limpo.length}/${esperado} caracteres. Ex.: ${EXEMPLO_PT}`;
        }

        return '';
    }

    function amMarcarIbanInput(inp, resultado) {
        if (!inp) {
            return;
        }

        let hint = inp.parentElement ? inp.parentElement.querySelector('.am-iban-hint') : null;
        if (!hint && inp.parentElement) {
            hint = document.createElement('small');
            hint.className = 'am-iban-hint';
            inp.parentElement.appendChild(hint);
        }

        if (!resultado.ok) {
            inp.classList.add('am-iban-invalid');
            inp.setCustomValidity(resultado.message);
            if (hint) {
                hint.textContent = resultado.message;
                hint.classList.add('is-error');
                hint.classList.remove('is-progress');
            }
        } else {
            inp.classList.remove('am-iban-invalid');
            inp.setCustomValidity('');
            if (hint) {
                const limpo = amNormalizarIban(inp.value);
                if (resultado.parcial && limpo) {
                    hint.textContent = amHintIbanParcial(limpo);
                    hint.classList.remove('is-error');
                    hint.classList.add('is-progress');
                } else {
                    hint.textContent = inp.value ? '' : 'Opcional. Ex.: ' + EXEMPLO_PT;
                    hint.classList.remove('is-error', 'is-progress');
                }
            }
        }
    }

    function amValidarIbanInput(inp, parcial) {
        const valor = amNormalizarIban(inp.value);
        inp.value = amFormatarIban(valor);
        const resultado = amValidarIban(valor, Boolean(parcial));
        amMarcarIbanInput(inp, resultado);
        return resultado.ok;
    }

    async function amValidarIbanInputCompleto(inp) {
        if (!inp.value) {
            amMarcarIbanInput(inp, { ok: true, message: '' });
            return true;
        }
        if (!amValidarIbanInput(inp, false)) {
            return false;
        }

        if (typeof global.amVerificarIdentificadorUnico === 'function') {
            try {
                const remoto = await global.amVerificarIdentificadorUnico('iban', amNormalizarIban(inp.value), inp);
                if (!remoto.ok) {
                    amMarcarIbanInput(inp, { ok: false, message: remoto.mensagem || 'Este IBAN já está registado.' });
                    return false;
                }
            } catch (err) {
                // Sem rede: validação local mantém-se
            }
        }

        amMarcarIbanInput(inp, { ok: true, message: '' });
        return true;
    }

    function amInitIbanInputs(root) {
        const scope = root || document;
        const seletor = [
            '.am-iban-input',
            'input[name="iban"]',
            'input[name="iban_mecanico"]',
            'input[name="iban_chefe_oficina"]'
        ].join(',');

        scope.querySelectorAll(seletor).forEach((inp) => {
            if (inp.dataset.amIbanInit === '1') {
                return;
            }
            inp.dataset.amIbanInit = '1';
            inp.classList.add('am-iban-input');
            inp.setAttribute('autocomplete', 'off');
            inp.setAttribute('spellcheck', 'false');
            inp.setAttribute('maxlength', '34');
            if (!inp.getAttribute('placeholder')) {
                inp.setAttribute('placeholder', EXEMPLO_PT);
            }
            if (!inp.getAttribute('title')) {
                inp.setAttribute('title', 'IBAN português: PT50 + 23 dígitos (25 caracteres no total)');
            }

            if (inp.value) {
                inp.value = amFormatarIban(inp.value);
            }

            inp.addEventListener('input', () => {
                const pos = inp.selectionStart;
                const antes = inp.value.length;
                const limpo = amNormalizarIban(inp.value);
                inp.value = amFormatarIban(limpo);
                const depois = inp.value.length;

                if (typeof pos === 'number') {
                    const delta = depois - antes;
                    inp.setSelectionRange(Math.max(0, pos + delta), Math.max(0, pos + delta));
                }

                const resultado = amValidarIban(limpo, true);
                if (resultado.ok && resultado.parcial) {
                    amMarcarIbanInput(inp, resultado);
                } else if (!resultado.ok) {
                    amMarcarIbanInput(inp, resultado);
                } else {
                    inp.classList.remove('am-iban-invalid');
                    inp.setCustomValidity('');
                    const hint = inp.parentElement ? inp.parentElement.querySelector('.am-iban-hint') : null;
                    if (hint) {
                        hint.textContent = '';
                        hint.classList.remove('is-error');
                    }
                }
            });

            inp.addEventListener('blur', () => {
                amValidarIbanInputCompleto(inp);
            });

            amMarcarIbanInput(inp, { ok: true, message: '' });
        });
    }

    function amValidarIbansNoFormulario(form) {
        if (!form) {
            return true;
        }

        let valido = true;
        form.querySelectorAll('.am-iban-input').forEach((inp) => {
            if (!inp.value) {
                amMarcarIbanInput(inp, { ok: true, message: '' });
                return;
            }
            if (!amValidarIbanInput(inp, false)) {
                valido = false;
            }
        });

        if (!valido) {
            const primeiro = form.querySelector('.am-iban-input.am-iban-invalid');
            if (primeiro) {
                primeiro.reportValidity();
                primeiro.focus();
            }
        }

        return valido;
    }

    global.amNormalizarIban = amNormalizarIban;
    global.amFormatarIban = amFormatarIban;
    global.amValidarIban = amValidarIban;
    global.amInitIbanInputs = amInitIbanInputs;
    global.amValidarIbansNoFormulario = amValidarIbansNoFormulario;

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            amInitIbanInputs(document);

            document.querySelectorAll('form').forEach((form) => {
                if (!form.querySelector('.am-iban-input')) {
                    return;
                }
                form.addEventListener('submit', (ev) => {
                    if (!amValidarIbansNoFormulario(form)) {
                        ev.preventDefault();
                    }
                });
            });
        });
    }
})(typeof window !== 'undefined' ? window : globalThis);
