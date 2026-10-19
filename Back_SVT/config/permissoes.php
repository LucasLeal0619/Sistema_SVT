<?php

/**
 * Acesso do SVT.
 *
 * Cinco funções movem a visita: instrutor, núcleo, coordenação, direção e NULOG.
 * O administrador não pede, não aprova e não marca transporte.
 * O trabalho dele é criar esses acessos, com a unidade do núcleo e a área do eixo na coordenação.
 */
return [
    'perfis' => [
        'Administrador',
        'Editor',
        'Consultor',
        'Instrutor',
        'Núcleo Pedagógico',
        'Coordenação CPED',
        'Direção Pedagógica',
        'NULOG',
    ],

    'acoes' => [
        'gerenciar_usuarios' => ['Administrador'],
        'consultar_auditoria' => ['Administrador'],
        'editar_dados' => ['Administrador', 'Editor', 'Instrutor'],
        'consultar_dados' => ['Administrador', 'Editor', 'Consultor', 'Instrutor', 'Núcleo Pedagógico', 'Coordenação CPED', 'Direção Pedagógica', 'NULOG'],
        'ver_relatorios' => ['Administrador', 'Editor', 'Consultor', 'Instrutor', 'Núcleo Pedagógico', 'Coordenação CPED', 'Direção Pedagógica', 'NULOG'],
    ],

    'menu' => [
        'painel' => ['Administrador', 'Editor', 'Consultor', 'Instrutor', 'Núcleo Pedagógico', 'Coordenação CPED', 'Direção Pedagógica', 'NULOG'],
        'solicitacoes' => ['Administrador', 'Editor', 'Consultor', 'Instrutor', 'Núcleo Pedagógico', 'Coordenação CPED', 'Direção Pedagógica', 'NULOG'],
        'fila' => ['Núcleo Pedagógico', 'Coordenação CPED', 'Direção Pedagógica', 'NULOG'],
        'relatorios' => ['Administrador', 'Editor', 'Consultor', 'Núcleo Pedagógico', 'Coordenação CPED', 'Direção Pedagógica'],
        'relatorios_visita' => ['Editor', 'Consultor', 'Instrutor', 'Núcleo Pedagógico', 'Coordenação CPED', 'Direção Pedagógica'],
        'usuarios' => ['Administrador'],
        'cadastros' => ['Administrador'],
        'auditoria' => ['Administrador'],
    ],
];
