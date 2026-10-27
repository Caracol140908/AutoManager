<?php
// ai-assistant/ai-database.php
class AutoAssistAI {
    private $problemas_comuns = [];
    private $sintomas = [];
    
    public function __construct() {
        $this->carregarBancoConhecimento();
    }
    
    private function carregarBancoConhecimento() {
        // Base de conhecimento de problemas
        $this->problemas_comuns = [
            'motor' => [
                'falta_potencia' => [
                    'descricao' => 'Falta de potência/aceleração',
                    'causas' => ['Filtro de ar sujo', 'Velas de ignição gastas', 'Injetores obstruídos'],
                    'gravidade' => 'media',
                    'tempo_estimado' => '2-4 horas',
                    'custo_estimado' => '150-400€'
                ],
                'barulho_tique' => [
                    'descricao' => 'Barulho de tique-taque no motor',
                    'causas' => ['Folga nas tuchas', 'Correia de distribuição', 'Tensor da correia'],
                    'gravidade' => 'alta',
                    'tempo_estimado' => '3-6 horas',
                    'custo_estimado' => '300-800€'
                ]
            ],
            'freios' => [
                'vibracao_volante' => [
                    'descricao' => 'Vibração no volante ao travar',
                    'causas' => ['Discos empenados', 'Pastilhas desgastadas'],
                    'gravidade' => 'alta',
                    'tempo_estimado' => '2-3 horas',
                    'custo_estimado' => '200-500€'
                ]
            ],
            'suspensao' => [
                'barulho_buracos' => [
                    'descricao' => 'Barulho ao passar em buracos',
                    'causas' => ['Amortecedores gastos', 'Buchas da suspensão'],
                    'gravidade' => 'media',
                    'tempo_estimado' => '2-4 horas',
                    'custo_estimado' => '250-600€'
                ]
            ]
        ];
        
        // Mapeamento de sintomas para problemas
        $this->sintomas = [
            'vibra ao travar' => ['freios,vibracao_volante'],
            'barulho ao travar' => ['freios,ruido_agudo'],
            'puxa para um lado' => ['suspensao,barulho_buracos'],
            'barulho no motor' => ['motor,barulho_tique'],
            'fraco ao acelerar' => ['motor,falta_potencia']
        ];
    }
    
    public function analisarSintoma($texto) {
        $texto = strtolower($texto);
        $resultados = [];
        
        foreach ($this->sintomas as $sintoma => $problemas) {
            if (strpos($texto, $sintoma) !== false) {
                foreach (explode(',', $problemas) as $problema) {
                    $parts = explode(',', $problema);
                    if (count($parts) === 2) {
                        $sistema = trim($parts[0]);
                        $codigo = trim($parts[1]);
                        if (isset($this->problemas_comuns[$sistema][$codigo])) {
                            $resultados[] = $this->problemas_comuns[$sistema][$codigo];
                        }
                    }
                }
            }
        }
        
        return $resultados;
    }
}
?>