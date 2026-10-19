<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'email' => 'administrador@df.senac.br',
                'nome' => 'Administrador do Sistema',
                'senha' => 'senac2025',
                'perfil' => Usuario::PERFIL_ADMINISTRADOR,
                'unidade' => 'Asa Norte',
                'area' => 'Administração do SVT',
            ],
        ];

        foreach ($usuarios as $dados) {
            Usuario::updateOrCreate(
                ['email' => $dados['email']],
                [
                    'nome' => $dados['nome'],
                    'senha' => Hash::make($dados['senha']),
                    'perfil' => $dados['perfil'],
                    'status' => true,
                    'unidade' => $dados['unidade'],
                    'area' => $dados['area'],
                ]
            );
        }

        if ($this->command) {
            $this->command->warn('Administrador demo: administrador@df.senac.br / senac2025');
        }
    }
}
