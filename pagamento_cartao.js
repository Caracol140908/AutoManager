(function (global) {
    'use strict';

    function onlyDigits(v) {
        return String(v || '').replace(/\D/g, '');
    }

    function validateNumero(numero) {
        var d = onlyDigits(numero);
        if (!d) return 'Indique o número do cartão.';
        if (d.length < 8 || d.length > 19) {
            return 'O número do cartão deve ter entre 8 e 19 dígitos.';
        }
        return null;
    }

    function validateCvv(cvv) {
        var c = onlyDigits(cvv);
        if (!c) return 'Indique o CVV.';
        if (c.length < 3 || c.length > 4) {
            return 'O CVV deve ter 3 ou 4 dígitos.';
        }
        return null;
    }

    function validateValidade(validade) {
        var v = String(validade || '').trim();
        var m = /^(\d{2})\/(\d{2})$/.exec(v);
        if (!m) return 'Validade inválida. Use o formato MM/AA.';
        var mes = parseInt(m[1], 10);
        if (mes < 1 || mes > 12) return 'Mês de validade inválido (01 a 12).';
        return null;
    }

    function validateTitular(nome) {
        var n = String(nome || '').trim();
        if (!n || n.length < 2) return 'Indique o nome do titular.';
        return null;
    }

    function validateCartao(dados) {
        var checks = [
            validateNumero(dados.numero_cartao || dados.numero || ''),
            validateValidade(dados.validade),
            validateCvv(dados.cvv),
            validateTitular(dados.nome_titular || dados.titular)
        ];
        for (var i = 0; i < checks.length; i++) {
            if (checks[i]) return checks[i];
        }
        return null;
    }

    function formatNumeroCartao(input) {
        if (!input) return;
        var d = onlyDigits(input.value).slice(0, 19);
        input.value = d.match(/.{1,4}/g)?.join(' ') || d;
    }

    function formatValidade(input) {
        if (!input) return;
        var v = onlyDigits(input.value).slice(0, 4);
        input.value = v.length >= 3 ? v.slice(0, 2) + '/' + v.slice(2) : v;
    }

    function bindCartaoFields(root) {
        var scope = root || document;
        var num = scope.querySelector('input[name="numero_cartao"]');
        var val = scope.querySelector('input[name="validade"]');
        var cvv = scope.querySelector('input[name="cvv"]');
        if (num) {
            num.addEventListener('input', function () { formatNumeroCartao(num); });
            formatNumeroCartao(num);
        }
        if (val) {
            val.addEventListener('input', function () { formatValidade(val); });
        }
        if (cvv) {
            cvv.setAttribute('maxlength', '4');
            cvv.addEventListener('input', function () {
                cvv.value = onlyDigits(cvv.value).slice(0, 4);
            });
        }
    }

    global.AMValidacaoCartao = {
        validateCartao: validateCartao,
        bindCartaoFields: bindCartaoFields,
        formatNumeroCartao: formatNumeroCartao
    };
})(typeof window !== 'undefined' ? window : this);
