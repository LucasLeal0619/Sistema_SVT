<template>
  <section v-if="dados" class="svt-bloco svt-historico-ano">
    <header class="svt-historico-topo">
      <div>
        <h2 class="svt-section-title">Histórico da turma</h2>
        <p class="svt-muted">Visitas de {{ dados.ano }}. Troque o ano para consultar outro período desta mesma turma.</p>
      </div>
      <label class="svt-field svt-ano">Ano
        <select :value="dados.ano" aria-label="Ano do histórico" @change="$emit('trocar', Number($event.target.value))">
          <option v-for="ano in dados.anos" :key="ano" :value="ano">{{ ano }}</option>
        </select>
      </label>
    </header>

    <div class="svt-faixa" aria-label="Resumo do ano">
      <p class="svt-faixa-item"><strong>{{ dados.resumo.solicitacoes }}</strong><span>Solicitações</span></p>
      <p class="svt-faixa-item"><strong>{{ dados.resumo.realizadas }}</strong><span>Realizadas</span></p>
      <p class="svt-faixa-item"><strong>{{ dados.resumo.tramitacao }}</strong><span>Em tramitação</span></p>
      <p class="svt-faixa-item"><strong>{{ dados.resumo.reprovadas }}</strong><span>Reprovadas</span></p>
      <p class="svt-faixa-item"><strong>{{ dados.resumo.canceladas }}</strong><span>Canceladas</span></p>
      <p class="svt-faixa-item"><strong>{{ dados.resumo.relatorios_entregues }}</strong><span>Relatórios entregues</span></p>
    </div>

    <p v-if="!dados.itens.length" class="svt-muted">Nenhuma visita desta turma em {{ dados.ano }}.</p>
    <div v-else class="svt-rola">
      <p class="svt-rola-aviso">Deslize a tabela para o lado para ver o restante.</p>
      <table class="svt-analitica">
        <thead>
          <tr>
            <th>Pedido</th>
            <th>Local</th>
            <th>Data</th>
            <th>Situação</th>
            <th>Relatório</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in dados.itens" :key="item.id" :class="{ 'is-atual': Number(item.id) === Number(atual) }">
            <td>
              <button type="button" class="svt-linkish" @click="$emit('abrir', item.id)">#{{ item.id }}</button>
              · {{ item.turma }}
            </td>
            <td>{{ item.local_visita || '—' }}</td>
            <td>{{ formatar(item.data_visita_prevista) }}</td>
            <td>
              {{ item.etapa_label }}
              <template v-if="item.motivo_decisao"> · {{ item.motivo_decisao }}</template>
            </td>
            <td>{{ item.etapa === 'relatorio' || item.tem_relatorio ? 'Entregue' : 'Pendente' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script>
export default {
  name: 'HistoricoTurma',
  props: {
    dados: { type: Object, default: null },
    atual: { type: Number, default: null },
  },
  emits: ['trocar', 'abrir'],
  methods: {
    formatar(valor) {
      if (!valor) return '—';
      return new Date(`${String(valor).slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR');
    },
  },
};
</script>
