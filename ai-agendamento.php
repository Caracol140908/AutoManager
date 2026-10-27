<?php
class AgendamentoAI {
    
    public function sugerirHorarios($urgencia = 'normal', $duracao_estimada = 2) {
        $horarios_disponiveis = [];
        $agora = new DateTime();
        
        switch ($urgencia) {
            case 'urgente':
                // Próximas 24 horas
                for ($i = 1; $i <= 8; $i++) {
                    $data = clone $agora;
                    $data->modify('+' . ($i * 3) . ' hours');
                    if ($data->format('H') >= 8 && $data->format('H') <= 18) {
                        $horarios_disponiveis[] = $data->format('d/m H:i');
                    }
                }
                break;
                
            case 'normal':
                // Próximos 3 dias
                for ($i = 1; $i <= 6; $i++) {
                    $data = clone $agora;
                    $data->modify('+' . $i . ' days');
                    $data->setTime(9, 0); // 9:00
                    $horarios_disponiveis[] = $data->format('d/m H:i');
                    
                    $data->setTime(14, 0); // 14:00
                    $horarios_disponiveis[] = $data->format('d/m H:i');
                }
                break;
                
            default:
                // Semana atual
                for ($i = 1; $i <= 5; $i++) {
                    $data = clone $agora;
                    $data->modify('next Monday +' . ($i - 1) . ' days');
                    $data->setTime(10, 0);
                    $horarios_disponiveis[] = $data->format('d/m H:i');
                }
        }
        
        return $horarios_disponiveis;
    }
    
    public function criarAgendamento($dados_cliente, $servico, $horario) {
        // Aqui você integraria com seu sistema de agendamento real
        return [
            'sucesso' => true,
            'codigo_agendamento' => 'AG' . date('Ymd') . rand(1000, 9999),
            'data' => $horario,
            'servico' => $servico,
            'observacoes' => 'Chegar 15 minutos antes. Trazer documentação do veículo.'
        ];
    }
}
?>