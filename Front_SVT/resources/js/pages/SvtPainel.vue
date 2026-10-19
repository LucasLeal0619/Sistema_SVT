<template>
  <section class="svt-page svt-page--wide svt-inicio">
    <header class="svt-head">
      <div>
        <h1>{{ ehAdmin ? 'Dashboard' : `${saudacao}, ${tratamento}` }}</h1>
        <p class="svt-lead">{{ recado }}</p>
      </div>
      <router-link class="svt-btn" :to="destino">{{ acao }}</router-link>
    </header>

    <div v-if="indicadores.length" class="svt-faixa" aria-label="Indicadores">
      <router-link v-for="item in indicadores" :key="item.rotulo" class="svt-faixa-item" :to="item.para">
        <strong>{{ item.valor }}</strong>
        <span>{{ item.rotulo }}</span>
      </router-link>
    </div>

    <section v-if="mostrarEixos && painel.por_eixo.length" class="svt-bloco">
      <h2 class="svt-section-title">{{ tituloEixos }}</h2>
      <div class="svt-rola">
        <p class="svt-rola-aviso">Deslize a tabela para o lado para ver o restante.</p>
        <table class="svt-analitica">
          <thead><tr><th>Eixo</th><th>Solicitações</th><th>Realizadas</th></tr></thead>
          <tbody>
            <tr v-for="item in painel.por_eixo" :key="item.nome">
              <td><router-link :to="{ name: 'solicitacoes', query: { eixo: item.nome } }">{{ item.nome }}</router-link></td>
              <td>{{ item.total }}</td>
              <td>{{ item.realizadas || 0 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="meusItens.length" class="svt-bloco">
      <h2 class="svt-section-title">Para acompanhar</h2>
      <ul class="svt-linhas">
        <li v-for="item in meusItens" :key="item.id">
          <button type="button" class="svt-linkish" @click="abrir(item.id)">{{ item.turma }}</button>
          <span>{{ item.local_visita }} · {{ item.etapa_label }}</span>
        </li>
      </ul>
    </section>

    <section v-if="ehAdmin && painel.equipe.length" class="svt-bloco">
      <h2 class="svt-section-title">Quem está com pendência</h2>
      <ul class="svt-linhas">
        <li v-for="pessoa in painel.equipe" :key="pessoa.id">
          <span>{{ pessoa.nome }} · {{ pessoa.perfil }}</span>
          <strong>{{ pessoa.pendencias }}</strong>
        </li>
      </ul>
    </section>
  </section>
</template>

<script>
import { PAPEL, PERFIS, getUsuario } from '../scripts/auth';

export default {
  name: 'SvtPainel',
  data() {
    return {
      painel: {
        em_fluxo: 0,
        realizadas: 0,
        recusadas: 0,
        por_etapa: { nucleo: 0, cped: 0, direcao: 0, nulog: 0 },
        equipe: [],
        minha_fila: 0,
        recentes: [],
        itens: [],
        resumo: null,
        por_eixo: [],
      },
    };
  },
  computed: {
    usuario() {
      return getUsuario();
    },
    ehAdmin() {
      return this.usuario?.perfil === PERFIS.ADMINISTRADOR;
    },
    primeiroNome() {
      return (this.usuario?.nome || 'oi').split(' ')[0];
    },
    eu() {
      return this.painel.equipe.find((pessoa) => pessoa.id === this.usuario?.id) || null;
    },
    meusItens() {
      if (this.eu?.itens?.length) return this.eu.itens;
      return this.painel.itens || [];
    },
    tratamento() {
      const nome = this.usuario?.nome || '';
      if (this.usuario?.perfil === PERFIS.NULOG && nome.startsWith('Núcleo')) {
        return 'Núcleo de Logística';
      }
      return this.primeiroNome;
    },
    mostrarEixos() {
      return this.usuario?.perfil === PERFIS.DIRECAO || this.usuario?.perfil === PERFIS.CPED;
    },
    tituloEixos() {
      return this.usuario?.perfil === PERFIS.CPED ? 'Eixo autorizado' : 'Cinco eixos';
    },
    indicadores() {
      const resumo = this.painel.resumo;
      if (!resumo) return [];
      const perfil = this.usuario?.perfil;
      const lista = (query) => ({ name: 'solicitacoes', query });
      const fila = { name: 'fila' };
      if (this.ehAdmin) {
        return [
          { rotulo: 'Solicitações', valor: resumo.solicitacoes ?? 0, para: lista() },
          { rotulo: 'Em tramitação', valor: this.painel.em_fluxo, para: lista({ situacao: 'tramitacao' }) },
          { rotulo: 'No núcleo', valor: this.painel.por_etapa.nucleo, para: lista({ etapa: 'nucleo' }) },
          { rotulo: 'Na coordenação', valor: this.painel.por_etapa.cped, para: lista({ etapa: 'cped' }) },
          { rotulo: 'Na direção', valor: this.painel.por_etapa.direcao, para: lista({ etapa: 'direcao' }) },
          { rotulo: 'Aguardando transporte', valor: this.painel.por_etapa.nulog, para: lista({ situacao: 'logistica' }) },
          { rotulo: 'Realizadas', valor: this.painel.realizadas, para: lista({ situacao: 'realizadas' }) },
          { rotulo: 'Relatórios entregues', valor: resumo.relatorios_entregues ?? 0, para: lista({ situacao: 'relatorio_entregue' }) },
          { rotulo: 'Reprovadas', valor: this.painel.recusadas, para: lista({ situacao: 'reprovadas' }) },
        ];
      }
      if (perfil === PERFIS.INSTRUTOR) {
        return [
          { rotulo: 'Em tramitação', valor: resumo.pendentes, para: lista({ situacao: 'tramitacao' }) },
          { rotulo: 'Transporte definido', valor: resumo.transportes, para: lista({ situacao: 'transporte' }) },
          { rotulo: 'Realizadas', valor: resumo.realizadas, para: lista({ situacao: 'realizadas' }) },
          { rotulo: 'Relatórios pendentes', valor: resumo.relatorios_pendentes, para: lista({ situacao: 'relatorio_pendente' }) },
          { rotulo: 'Relatórios entregues', valor: resumo.relatorios_entregues ?? 0, para: lista({ situacao: 'relatorio_entregue' }) },
          { rotulo: 'Reprovadas', valor: resumo.reprovadas, para: lista({ situacao: 'reprovadas' }) },
        ];
      }
      if (perfil === PERFIS.NULOG) {
        return [
          { rotulo: 'Aguardando transporte', valor: resumo.aguardando_transporte, para: fila },
          { rotulo: 'Transporte definido', valor: resumo.transportes, para: lista({ situacao: 'transporte' }) },
          { rotulo: 'Realizadas', valor: resumo.realizadas, para: lista({ situacao: 'realizadas' }) },
        ];
      }
      if (perfil === PERFIS.NUCLEO) {
        return [
          { rotulo: 'Aguardando análise', valor: resumo.pendentes, para: fila },
          { rotulo: 'Reprovadas', valor: resumo.reprovadas, para: lista({ situacao: 'reprovadas' }) },
          { rotulo: 'Realizadas', valor: resumo.realizadas, para: lista({ situacao: 'realizadas' }) },
        ];
      }
      if (perfil === PERFIS.CPED) {
        return [
          { rotulo: 'Pendências do eixo', valor: resumo.pendentes, para: fila },
          { rotulo: 'Encaminhadas à direção', valor: resumo.direcao ?? 0, para: lista({ etapa: 'direcao' }) },
          { rotulo: 'Reprovadas', valor: resumo.reprovadas, para: lista({ situacao: 'reprovadas' }) },
          { rotulo: 'Realizadas', valor: resumo.realizadas, para: lista({ situacao: 'realizadas' }) },
        ];
      }
      if (perfil === PERFIS.DIRECAO) {
        return [
          { rotulo: 'Aguardando decisão', valor: resumo.pendentes, para: fila },
          { rotulo: 'Reprovadas', valor: resumo.reprovadas, para: lista({ situacao: 'reprovadas' }) },
          { rotulo: 'Realizadas', valor: resumo.realizadas, para: lista({ situacao: 'realizadas' }) },
        ];
      }
      return [];
    },
    saudacao() {
      const hora = new Date().getHours();
      if (hora < 12) return 'Bom dia';
      if (hora < 18) return 'Boa tarde';
      return 'Boa noite';
    },
    recado() {
      const n = this.eu?.pendencias ?? this.painel.minha_fila ?? 0;
      const perfil = this.usuario?.perfil;
      if (this.ehAdmin) return 'Resumo do sistema. A auditoria registra quem fez o quê, sem carregar todas as pessoas nesta tela.';
      if (perfil === PERFIS.INSTRUTOR) {
        const andando = this.painel.resumo?.pendentes ?? n;
        return andando
          ? `Você tem ${andando} visita${andando > 1 ? 's' : ''} andando. Se a turma for sair de novo, é só pedir.`
          : 'Nenhuma visita sua andando agora. Se a turma for sair, pede por aqui.';
      }
      if (perfil === PERFIS.NULOG) {
        if (!n) return 'Nenhuma visita esperando transporte. Quando a direção aprovar, ela aparece aqui.';
        if (n === 1) return 'Tem 1 visita já aprovada. Falta registrar veículo, placa, horário e motorista.';
        return `Tem ${n} visitas já aprovadas esperando o transporte.`;
      }
      if (!n) return 'Nada na sua vez agora. Quando chegar um pedido, ele aparece aqui.';
      if (n === 1) return 'Tem 1 pedido esperando você.';
      return `Tem ${n} pedidos esperando você.`;
    },
    acao() {
      if (this.ehAdmin) return 'Abrir auditoria';
      if (this.usuario?.perfil === PERFIS.INSTRUTOR) return 'Solicitar visita';
      if (this.usuario?.perfil === PERFIS.DIRECAO) return 'Relatórios gerenciais';
      return PAPEL[this.usuario?.perfil]?.agora || 'Abrir visitas';
    },
    destino() {
      if (this.ehAdmin) return { name: 'auditoria' };
      if (this.usuario?.perfil === PERFIS.INSTRUTOR) return { name: 'solicitacoes', query: { novo: '1' } };
      if (this.usuario?.perfil === PERFIS.DIRECAO) return { name: 'relatorios' };
      return PAPEL[this.usuario?.perfil]?.para || '/app/solicitacoes';
    },
  },
  mounted() {
    this.carregar();
  },
  methods: {
    async carregar() {
      const { data } = await window.axios.get('/api/svt/painel');
      this.painel = {
        ...this.painel,
        ...data,
        por_etapa: { ...this.painel.por_etapa, ...(data.por_etapa || {}) },
        equipe: data.equipe || [],
      };
    },
    abrir(id) {
      this.$router.push({ name: 'solicitacoes', query: { id } });
    },
  },
};
</script>
