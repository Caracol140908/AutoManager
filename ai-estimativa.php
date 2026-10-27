<?php
class EstimativaAI {
    private $tabela_custos = [];
    
    public function __construct() {
        $this->carregarTabelaCustos();
    }
    
    private function carregarTabelaCustos() {
        $this->tabela_custos = [
            'troca_pastilhas' => [
                'descricao' => 'Troca de pastilhas de freio',
                'custo_peças' => [80, 150], // min, max
                'custo_mao_obra' => [50, 100],
                'tempo_estimado' => '1-2 horas',
                'marcas_recomendadas' => ['Brembo', 'Bosch', 'Textar']
            ],
            'troca_discos' => [
                'descricao' => 'Troca de discos de freio',
                'custo_peças' => [120, 300],
                'custo_mao_obra' => [80, 150],
                'tempo_estimado' => '2-3 horas',
                'marcas_recomendadas' => ['Brembo', 'ATE', 'Jurid']
            ],
            'alinhamento' => [
                'descricao' => 'Alinhamento da direção',
                'custo_peças' => [0, 0],
                'custo_mao_obra' => [40, 80],
                'tempo_estimado' => '1 hora',
                'marcas_recomendadas' => []
            ],
            'diagnostico' => [
                'descricao' => 'Diagnóstico completo',
                'custo_peças' => [0, 0],
                'custo_mao_obra' => [30, 60],
                'tempo_estimado' => '30-60 minutos',
                'marcas_recomendadas' => []
            ]
        ];
    }
    
    public function estimarCusto($servico, $marca_veiculo = '', $modelo = '', $ano = '') {
        $servico_key = strtolower(str_replace(' ', '_', $servico));
        
        if (isset($this->tabela_custos[$servico_key])) {
            $dados = $this->tabela_custos[$servico_key];
            
            $custo_min = $dados['custo_peças'][0] + $dados['custo_mao_obra'][0];
            $custo_max = $dados['custo_peças'][1] + $dados['custo_mao_obra'][1];
            
            return [
                'servico' => $dados['descricao'],
                'custo_estimado' => $custo_min . '€ - ' . $custo_max . '€',
                'detalhes' => [
                    'peças' => $dados['custo_peças'][0] . '€ - ' . $dados['custo_peças'][1] . '€',
                    'mao_obra' => $dados['custo_mao_obra'][0] . '€ - ' . $dados['custo_mao_obra'][1] . '€',
                    'tempo' => $dados['tempo_estimado'],
                    'marcas' => $dados['marcas_recomendadas']
                ],
                'observacoes' => 'Preços variam conforme marca do veículo e qualidade das peças'
            ];
        }
        
        return [
            'servico' => $servico,
            'custo_estimado' => 'Consulte-nos',
            'detalhes' => [],
            'observacoes' => 'Serviço não encontrado na base de dados. Contate-nos para orçamento personalizado.'
        ];
    }
}
?>