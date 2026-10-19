<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use App\Rules\CpfValido;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->podeGerenciarUsuarios() === true;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Você não tem permissão para gerenciar usuários.');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cpf')) {
            $this->merge([
                'cpf' => preg_replace('/\D/', '', (string) $this->input('cpf')),
            ]);
        }

        if ($this->filled('telefone')) {
            $this->merge([
                'telefone' => preg_replace('/\D/', '', (string) $this->input('telefone')),
            ]);
        }

        if ($this->has('status')) {
            $this->merge([
                'status' => filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('remover_foto')) {
            $this->merge([
                'remover_foto' => filter_var($this->input('remover_foto'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $usuarioId = $usuario instanceof Usuario ? $usuario->id : $usuario;

        return [
            'nome' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('usuarios', 'email')->ignore($usuarioId),
            ],
            'senha' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'string',
                'min:6',
                'max:100',
            ],
            'cpf' => [
                'required',
                'string',
                'size:11',
                new CpfValido,
                Rule::unique('usuarios', 'cpf')->ignore($usuarioId),
            ],
            'perfil' => [
                'required',
                Rule::in(config('permissoes.perfis')),
            ],
            'status' => ['required', 'boolean'],
            'unidade' => ['required', 'string', 'max:100', Rule::in(config('svt.unidades'))],
            'area' => ['required', 'string', 'max:100'],
            'telefone' => ['required', 'string', 'max:20', 'regex:/^\d{10,11}$/'],
            'foto' => ['nullable', 'image', 'max:2048'],
            'remover_foto' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe se o usuário está ativo.',
            'unidade.required' => 'Informe a estrutura institucional.',
            'area.required' => 'Informe a área de atuação.',
            'cpf.required' => 'Informe o CPF.',
            'telefone.required' => 'Informe o telefone.',
            'nome.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'senha.required' => 'A senha é obrigatória no cadastro.',
            'senha.min' => 'A senha deve ter no mínimo 6 caracteres.',
            'perfil.required' => 'O perfil é obrigatório.',
            'perfil.in' => 'Selecione um perfil válido do fluxo de visitas.',
            'cpf.unique' => 'Este CPF já está cadastrado.',
            'cpf.size' => 'Informe um CPF válido.',
            'telefone.regex' => 'Informe um telefone válido com DDD.',
            'unidade.in' => 'Selecione uma unidade válida.',
            'foto.image' => 'A foto deve ser uma imagem válida.',
            'foto.max' => 'A foto deve ter no máximo 2 MB.',
        ];
    }
}
