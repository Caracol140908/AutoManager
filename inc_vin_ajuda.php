<?php

declare(strict_types=1);

/**
 * Guia rápido “Onde encontrar o VIN?” (SVG + texto), reutilizável.
 */
if (!function_exists('am_render_bloco_ajuda_vin')) {
    function am_render_bloco_ajuda_vin(string $htmlIdSuffix): void
    {
        $s = preg_replace('/[^a-zA-Z0-9_-]/', '', $htmlIdSuffix);
        if ($s === '') {
            $s = 'id';
        }
        $uid = 'vin-help-' . $s;
        ?>
        <details class="am-vin-help" id="<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>">
            <summary class="am-vin-help__summary">
                <i class="fas fa-map-pin" aria-hidden="true"></i> Onde encontrar o VIN?
            </summary>
            <div class="am-vin-help__body">
                <p class="am-vin-help__intro">O VIN (número de quadro) tem <strong>17 caracteres</strong> (não usa as letras I, O nem Q). Costuma estar gravado numa placa ou etiqueta — vê os sítios mais comuns abaixo.</p>
                <div class="am-vin-help__grid">
                    <div class="am-vin-help__card">
                        <div class="am-vin-help__fig" aria-hidden="true">
                            <svg viewBox="0 0 64 64" width="56" height="56" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="10" y="14" width="44" height="40" rx="3" stroke="#ff7a1a" stroke-width="2"/>
                                <rect x="16" y="22" width="18" height="10" rx="1" fill="rgba(255,122,26,.25)" stroke="#fdba74" stroke-width="1"/>
                                <text x="25" y="30" font-size="6" fill="#fdba74" font-family="system-ui,sans-serif">VIN</text>
                                <path d="M14 52h36" stroke="#6b7280" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h4 class="am-vin-help__card-title">Porta do condutor</h4>
                        <p class="am-vin-help__card-text">Abre a porta e verifica o <strong>montante</strong> (zona das dobradiças): muitas vezes há uma etiqueta com o VIN, pressões dos pneus e data de fabrico.</p>
                    </div>
                    <div class="am-vin-help__card">
                        <div class="am-vin-help__fig" aria-hidden="true">
                            <svg viewBox="0 0 64 64" width="56" height="56" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 40h48l-6-18H14L8 40z" stroke="#ff7a1a" stroke-width="2" fill="rgba(255,122,26,.08)"/>
                                <rect x="22" y="32" width="22" height="6" rx="1" fill="rgba(253,186,116,.35)" stroke="#fdba74"/>
                                <text x="27" y="37" font-size="5" fill="#1a1a1a" font-family="monospace">•••</text>
                            </svg>
                        </div>
                        <h4 class="am-vin-help__card-title">Para-brisas</h4>
                        <p class="am-vin-help__card-text">Olha para o <strong>canto inferior do vidro da frente</strong> (lado do condutor): o VIN costuma estar gravado no vidro — visto de fora ou de dentro do habitáculo.</p>
                    </div>
                    <div class="am-vin-help__card">
                        <div class="am-vin-help__fig" aria-hidden="true">
                            <svg viewBox="0 0 64 64" width="56" height="56" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="12" y="20" width="40" height="28" rx="2" stroke="#ff7a1a" stroke-width="2" fill="rgba(255,122,26,.06)"/>
                                <rect x="18" y="36" width="28" height="8" rx="1" fill="rgba(253,186,116,.3)" stroke="#fdba74"/>
                                <path d="M20 28h24M20 32h18" stroke="#6b7280" stroke-width="1.5"/>
                            </svg>
                        </div>
                        <h4 class="am-vin-help__card-title">Motor / chassis</h4>
                        <p class="am-vin-help__card-text">Com o capô aberto, procura uma <strong>placa metálica</strong> na torpedo ou na longarina dianteira — às vezes também duplicada junto à bateria ou à caixa de fusíveis.</p>
                    </div>
                </div>
                <p class="am-vin-help__tip"><i class="fas fa-camera" aria-hidden="true"></i> Se tiveres dúvidas, tira uma <strong>foto nítida</strong> da etiqueta e mostra na oficina — evita erros ao transcrever.</p>
            </div>
        </details>
        <?php
    }
}
