<?php

/**
 * Regras do Sistema de Visitas Técnicas (ata de 30/09/2026).
 * A relação completa de cursos ainda será fornecida; estes limites são os da reunião.
 */
return [
    'tipos' => [
        'tecnico' => ['label' => 'Curso técnico', 'limite' => 4],
        'qualificacao' => ['label' => 'Qualificação', 'limite' => 2],
        'aperfeicoamento' => ['label' => 'Aperfeiçoamento', 'limite' => 1],
        'aprendizagem' => ['label' => 'Aprendizagem', 'limite' => 4],
    ],

    'etapas' => [
        'rascunho' => 'Rascunho',
        'nucleo' => 'Aguardando Núcleo Pedagógico',
        'recusada_nucleo' => 'Reprovada pelo Núcleo Pedagógico',
        'cped' => 'Aguardando Coordenação do Eixo',
        'recusada_cped' => 'Reprovada pela Coordenação',
        'direcao' => 'Aguardando Direção Pedagógica',
        'recusada_direcao' => 'Reprovada pela Direção',
        'nulog' => 'Aguardando Logística',
        'aprovada' => 'Transporte definido',
        'realizada' => 'Realizada',
        'relatorio' => 'Relatório entregue',
        'cancelada' => 'Cancelada',
        'recusada' => 'Recusada',
    ],

    'ordem' => ['rascunho', 'nucleo', 'cped', 'direcao', 'nulog', 'aprovada', 'realizada', 'relatorio'],

    // Provisório, até o cliente confirmar o que consome a cota.
    'cota' => [
        'criterio' => 'realizadas_e_programadas',
        'consomem' => ['aprovada', 'realizada', 'relatorio'],
        'pendentes' => ['nucleo', 'cped', 'direcao', 'nulog'],
        'nao_consomem' => ['rascunho', 'recusada', 'recusada_nucleo', 'recusada_cped', 'recusada_direcao', 'cancelada'],
    ],

    'responsaveis' => [
        'Gastronomia e Turismo' => 'Coordenação de Gastronomia e Turismo',
        'Ambiente e Saúde' => 'Coordenação de Ambiente e Saúde',
        'Gestão e Moda' => 'Coordenação de Gestão',
        'Tecnologia e Economia Criativa' => 'Coordenação de Tecnologia',
        'Beleza e Cuidado Pessoal' => 'Coordenação de Beleza',
    ],

    'unidades' => [
        'Asa Norte',
        'Taguatinga',
        'Ceilândia',
        'Gama',
        'Sobradinho',
        'DEP/CPED',
        'NULOG',
    ],

    'transportes' => ['Ônibus', 'Van', 'Carro', 'Outro'],
];
