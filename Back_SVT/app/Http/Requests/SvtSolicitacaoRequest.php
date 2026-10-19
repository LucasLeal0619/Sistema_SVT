<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SvtSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $enviar = $this->boolean('enviar', ! $this->isMethod('PUT'));

        return [
            'enviar' => ['sometimes', 'boolean'],
            'turma_id' => ['required', 'integer', 'exists:turmas,id'],
            'local_visita' => [$enviar ? 'required' : 'nullable', 'string', 'max:180'],
            'data_visita_prevista' => [$enviar ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'horario_pretendido' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'atividade' => [$enviar ? 'required' : 'nullable', 'string', 'max:2000'],
            'objetivo' => [$enviar ? 'required' : 'nullable', 'string', 'max:2000'],
            'relacao_curricular' => [$enviar ? 'required' : 'nullable', 'string', 'max:2000'],
            'justificativa' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'turma_id.required' => 'Selecione a turma.',
            'turma_id.exists' => 'A turma selecionada não está cadastrada.',
            'data_visita_prevista.after_or_equal' => 'A data pretendida não pode ser anterior a hoje.',
            'horario_pretendido.regex' => 'Informe o horário no formato HH:MM.',
            'objetivo.required' => 'Informe o objetivo pedagógico.',
            'relacao_curricular.required' => 'Informe a relação com o curso ou a unidade curricular.',
        ];
    }
}
