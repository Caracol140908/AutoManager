/**
 * Validação de telemóvel português (cliente) — alinhada com inc_telemovel.php
 */
(function (global) {
    'use strict';

    function amNormalizarTelemovel(telemovel) {
        return String(telemovel || '').replace(/\D+/g, '').slice(0, 9);
    }

    function amFormatarTelemovel(telemovel) {
        const limpo = amNormalizarTelemovel(telemovel);
        if (!limpo) {
            return '';
        }
        return limpo.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
    }

    function amValidarTelemovelPortugues(telemovel, obrigatorio) {
        const limpo = amNormalizarTelemovel(telemovel);

        if (limpo.length === 0) {
            return obrigatorio
                ? { ok: false, message: 'O telemóvel é obrigatório.' }
                : { ok: true, message: '' };
        }

        if (limpo.length !== 9) {
            return { ok: false, message: 'O telemóvel deve ter exatamente 9 dígitos.' };
        }

        if (/^(\d)\1{8}$/.test(limpo)) {
            return { ok: false, message: 'Telemóvel inválido.' };
        }

        if (!/^9[1-6]\d{7}$/.test(limpo)) {
            return {
                ok: false,
                message: 'Indique um telemóvel português válido (deve começar por 91, 92, 93 ou 96).'
            };
        }

        return { ok: true, message: '' };
    }

    function amTelemovelObrigatorio(inp) {
        return inp && (inp.required || inp.dataset.amTelemovelRequired === '1');
    }

    function amMarcarTelemovelInput(inp, resultado) {
        if (!inp) {
            return;
        }

        let hint = inp.parentElement ? inp.parentElement.querySelector('.am-telemovel-hint') : null;
        if (!hint && inp.parentElement) {
            hint = document.createElement('small');
            hint.className = 'am-telemovel-hint';
            inp.parentElement.appendChild(hint);
        }

        if (!resultado.ok) {
            inp.classList.add('am-telemovel-invalid');
            inp.setCustomValidity(resultado.message);
            if (hint) {
                hint.textContent = resultado.message;
                hint.classList.add('is-error');
            }
        } else {
            inp.classList.remove('am-telemovel-invalid');
            inp.setCustomValidity('');
            if (hint) {
                hint.textContent = inp.value
                    ? ''
                    : (amTelemovelObrigatorio(inp)
                        ? '9 dígitos. Ex.: 912 345 678'
                        : 'Opcional. 9 dígitos. Ex.: 912 345 678');
                hint.classList.remove('is-error');
            }
        }
    }

    function amValidarTelemovelInput(inp) {
        const valor = amNormalizarTelemovel(inp.value);
        inp.value = amFormatarTelemovel(valor);
        const resultado = amValidarTelemovelPortugues(valor, amTelemovelObrigatorio(inp));
        amMarcarTelemovelInput(inp, resultado);
        return resultado.ok;
    }

    function amInitTelemovelInputs(root) {
        const scope = root || document;
        const seletor = [
            '.am-telemovel-input',
            'input[name="telefone"]',
            'input[name="phone"]',
            'input[name="contacto"]',
            'input[name="telefone_mecanico"]',
            'input[name="telefone_chefe_oficina"]',
            'input[name="telefone_utilizador"]'
        ].join(',');

        scope.querySelectorAll(seletor).forEach((inp) => {
            if (inp.dataset.amTelemovelInit === '1') {
                return;
            }
            inp.dataset.amTelemovelInit = '1';
            inp.classList.add('am-telemovel-input');
            inp.setAttribute('inputmode', 'numeric');
            inp.setAttribute('autocomplete', 'tel');
            if (!inp.getAttribute('placeholder')) {
                inp.setAttribute('placeholder', '912 345 678');
            }
            if (!inp.getAttribute('title')) {
                inp.setAttribute('title', 'Telemóvel português (9 dígitos)');
            }
            if (!inp.getAttribute('maxlength')) {
                inp.setAttribute('maxlength', '11');
            }

            if (inp.value) {
                inp.value = amFormatarTelemovel(inp.value);
            }

            inp.addEventListener('input', () => {
                const pos = inp.selectionStart;
                const antes = inp.value.length;
                inp.value = amFormatarTelemovel(inp.value);
                const depois = inp.value.length;
                if (typeof pos === 'number') {
                    inp.setSelectionRange(pos + (depois - antes), pos + (depois - antes));
                }
                if (inp.classList.contains('am-telemovel-invalid')) {
                    amMarcarTelemovelInput(inp, amValidarTelemovelPortugues(amNormalizarTelemovel(inp.value), amTelemovelObrigatorio(inp)));
                } else {
                    inp.setCustomValidity('');
                }
            });

            inp.addEventListener('blur', () => {
                amValidarTelemovelInput(inp);
            });

            amMarcarTelemovelInput(inp, { ok: true, message: '' });
        });
    }

    function amValidarTelemoveisNoFormulario(form) {
        if (!form) {
            return true;
        }

        let valido = true;
        form.querySelectorAll('.am-telemovel-input').forEach((inp) => {
            if (!inp.value && !amTelemovelObrigatorio(inp)) {
                amMarcarTelemovelInput(inp, { ok: true, message: '' });
                return;
            }
            if (!amValidarTelemovelInput(inp)) {
                valido = false;
            }
        });

        if (!valido) {
            const primeiro = form.querySelector('.am-telemovel-input.am-telemovel-invalid');
            if (primeiro) {
                primeiro.reportValidity();
                primeiro.focus();
            }
        }

        return valido;
    }

    global.amNormalizarTelemovel = amNormalizarTelemovel;
    global.amFormatarTelemovel = amFormatarTelemovel;
    global.amValidarTelemovelPortugues = amValidarTelemovelPortugues;
    global.amInitTelemovelInputs = amInitTelemovelInputs;
    global.amValidarTelemoveisNoFormulario = amValidarTelemoveisNoFormulario;

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            amInitTelemovelInputs(document);

            document.querySelectorAll('form').forEach((form) => {
                if (!form.querySelector('.am-telemovel-input')) {
                    return;
                }
                form.addEventListener('submit', (ev) => {
                    if (!amValidarTelemoveisNoFormulario(form)) {
                        ev.preventDefault();
                    }
                });
            });
        });
    }
})(typeof window !== 'undefined' ? window : globalThis);
