<template>
  <section class="svt-page svt-page--wide">
    <header>
      <h1>Relatórios Gerenciais</h1>
      <p class="svt-lead">Acompanhe as solicitações e visitas técnicas por unidade, curso, eixo e período.</p>
    </header>

    <nav v-if="abas.length > 1" class="svt-subnav" aria-label="Tipos de relatório">
      <router-link v-for="aba in abas" :key="aba.to" :to="aba.to">{{ aba.label }}</router-link>
    </nav>

    <p v-if="erro" class="svt-alert">{{ erro }}</p>

    <PainelFiltros>
      <template #busca>
        <input v-model="busca" type="search" placeholder="Buscar turma, curso, local ou instrutor" aria-label="Busca" @change="aplicarFiltro" />
      </template>
      <select v-model="unidade" aria-label="Unidade" @change="aplicarFiltro">
        <option value="">Todas as unidades</option>
        <option v-for="item in unidades" :key="item" :value="item">{{ item }}</option>
      </select>
      <select v-if="!eixoFixo" v-model="eixo" aria-label="Eixo" @change="aplicarFiltro">
        <option value="">Todos os eixos</option>
        <option v-for="item in eixos" :key="item" :value="item">{{ item }}</option>
      </select>
      <input v-model="turma" type="search" placeholder="Turma" aria-label="Turma" @change="aplicarFiltro" />
      <select v-model="curso" aria-label="Curso" @change="aplicarFiltro">
        <option value="">Todos os cursos</option>
        <option v-for="item in cursos" :key="item" :value="item">{{ item }}</option>
      </select>
      <select v-model="etapa" aria-label="Situação" @change="aplicarFiltro">
        <option value="">Qualquer situação</option>
        <option v-for="(label, chave) in etapas" :key="chave" :value="chave">{{ label }}</option>
      </select>
      <input v-model="de" type="date" aria-label="Data inicial" @change="aplicarFiltro" />
      <input v-model="ate" type="date" aria-label="Data final" @change="aplicarFiltro" />
    </PainelFiltros>

    <p v-if="carregando" class="svt-muted">Carregando indicadores...</p>

    <div v-if="totais" class="svt-faixa" aria-label="Indicadores">
      <router-link class="svt-faixa-item" :to="destino()"><strong>{{ totais.solicitacoes }}</strong><span>Solicitações</span></router-link>
      <router-link class="svt-faixa-item" :to="destino({ situacao: 'tramitacao' })"><strong>{{ totais.em_andamento }}</strong><span>Em andamento</span></router-link>
      <router-link class="svt-faixa-item" :to="destino({ situacao: 'realizadas' })"><strong>{{ totais.realizadas }}</strong><span>Visitas realizadas</span></router-link>
      <router-link class="svt-faixa-item" :to="destino({ situacao: 'reprovadas' })"><strong>{{ totais.reprovadas }}</strong><span>Reprovadas</span></router-link>
      <router-link class="svt-faixa-item" :to="destino({ situacao: 'logistica' })"><strong>{{ totais.aguardando_transporte }}</strong><span>Aguardando transporte</span></router-link>
      <router-link class="svt-faixa-item" :to="destino({ situacao: 'transporte' })"><strong>{{ totais.transporte_definido }}</strong><span>Transporte definido</span></router-link>
      <router-link class="svt-faixa-item" :to="destino({ situacao: 'relatorio_entregue' })"><strong>{{ totais.relatorios_entregues }}</strong><span>Relatórios entregues</span></router-link>
      <router-link class="svt-faixa-item" :to="destino({ situacao: 'relatorio_pendente' })"><strong>{{ totais.relatorios_pendentes }}</strong><span>Relatórios pendentes</span></router-link>
    </div>

    <section v-if="totais?.por_eixo?.length" class="svt-bloco">
      <h2 class="svt-section-title">Cinco eixos</h2>
      <div class="svt-rola">
        <p class="svt-rola-aviso">Deslize a tabela para o lado para ver o restante.</p>
        <table class="svt-analitica">
          <thead><tr><th>Eixo</th><th>Solicitações</th><th>Realizadas</th></tr></thead>
          <tbody>
            <tr v-for="item in totais.por_eixo" :key="item.nome">
              <td><router-link :to="destino({ eixo: item.nome })">{{ item.nome }}</router-link></td>
              <td>{{ item.total }}</td>
              <td>{{ item.realizadas }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="totais?.por_unidade?.length" class="svt-bloco">
      <h2 class="svt-section-title">Por unidade</h2>
      <div class="svt-rola">
        <p class="svt-rola-aviso">Deslize a tabela para o lado para ver o restante.</p>
        <table class="svt-analitica">
          <thead><tr><th>Unidade</th><th>Solicitações</th><th>Realizadas</th></tr></thead>
          <tbody>
            <tr v-for="item in totais.por_unidade" :key="item.nome">
              <td><router-link :to="destino({ unidade: item.nome })">{{ item.nome }}</router-link></td>
              <td>{{ item.total }}</td>
              <td>{{ item.realizadas }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="totais?.por_curso?.length" class="svt-bloco">
      <h2 class="svt-section-title">Por curso</h2>
      <div class="svt-rola">
        <p class="svt-rola-aviso">Deslize a tabela para o lado para ver o restante.</p>
        <table class="svt-analitica">
          <thead><tr><th>Curso</th><th>Solicitações</th><th>Realizadas</th></tr></thead>
          <tbody>
            <tr v-for="item in totais.por_curso" :key="item.nome">
              <td><router-link :to="destino({ curso: item.nome })">{{ item.nome }}</router-link></td>
              <td>{{ item.total }}</td>
              <td>{{ item.realizadas }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="totais?.por_etapa?.length" class="svt-bloco">
      <h2 class="svt-section-title">Situação das solicitações</h2>
      <ul class="svt-linhas">
        <li v-for="item in totais.por_etapa" :key="item.nome">
          <router-link :to="destino({ etapa: item.nome })">{{ etapas[item.nome] || item.nome }}</router-link>
          <strong>{{ item.total }}</strong>
        </li>
      </ul>
    </section>

    <section v-if="totais?.por_mes?.length" class="svt-bloco">
      <h2 class="svt-section-title">Utilização no período</h2>
      <p class="svt-muted">A cota provisória conta transporte definido, visita realizada e relatório entregue. O cliente ainda não confirmou essa regra.</p>
      <div class="svt-rola">
        <p class="svt-rola-aviso">Deslize a tabela para o lado para ver o restante.</p>
        <table class="svt-analitica">
          <thead><tr><th>Mês</th><th>Solicitações criadas</th><th>Visitas realizadas</th><th>Cota provisória</th></tr></thead>
          <tbody>
            <tr v-for="item in totais.por_mes" :key="item.mes">
              <td>{{ item.mes }}</td>
              <td>{{ item.solicitacoes }}</td>
              <td>{{ item.realizadas }}</td>
              <td>{{ item.cota_provisoria }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>

<script>
import PainelFiltros from '../components/ui/PainelFiltros.vue';
import { PERFIS, abasRelatorio, getUsuario } from '../scripts/auth';

export default {
  name: 'SvtRelatorios',
  components: { PainelFiltros },
  data() {
    return {
      busca: '',
      unidade: '',
      eixo: '',
      curso: '',
      turma: '',
      etapa: '',
      de: '',
      ate: '',
      etapas: {},
      responsaveis: {},
      eixos: [],
      unidades: [],
      cursos: [],
      erro: '',
      carregando: false,
      totais: null,
    };
  },
  computed: {
    usuario() {
      return getUsuario();
    },
    abas() {
      return abasRelatorio();
    },
    ehNucleo() {
      return this.usuario?.perfil === PERFIS.NUCLEO;
    },
    eixoFixo() {
      if (this.usuario?.perfil !== PERFIS.CPED) return '';
      const area = this.usuario.area;
      return Object.keys(this.responsaveis).find((eixo) => this.responsaveis[eixo] === area) || '';
    },
  },
  async mounted() {
    try {
      await this.carregarCatalogo();
    } catch (error) {
      this.erro = error.response?.data?.message || 'Não foi possível carregar o catálogo.';
    }
    await this.carregar();
  },
  methods: {
    parametros() {
      return {
        busca: this.busca || undefined,
        unidade: this.unidade || undefined,
        eixo: this.eixoFixo || this.eixo || undefined,
        curso: this.curso || undefined,
        turma: this.turma || undefined,
        etapa: this.etapa || undefined,
        de: this.de || undefined,
        ate: this.ate || undefined,
      };
    },
    destino(extra = {}) {
      const query = { ...this.parametros(), ...extra };
      Object.keys(query).forEach((chave) => {
        if (!query[chave]) delete query[chave];
      });
      return { name: 'solicitacoes', query };
    },
    aplicarFiltro() {
      this.carregar();
    },
    async carregarCatalogo() {
      const { data } = await window.axios.get('/api/svt/catalogo');
      this.etapas = data.etapas;
      this.responsaveis = data.responsaveis || {};
      this.eixos = data.eixos;
      this.unidades = data.unidades;
    },
    async carregar() {
      this.erro = '';
      this.carregando = true;
      try {
        const { data } = await window.axios.get('/api/svt/relatorios', { params: this.parametros() });
        this.totais = data;
        if (!this.curso) {
          this.cursos = (data.por_curso || []).map((item) => item.nome).filter((nome) => nome && nome !== 'Sem informação');
        }
      } catch (error) {
        this.erro = error.response?.data?.message || 'Não foi possível carregar o relatório.';
        this.totais = null;
      } finally {
        this.carregando = false;
      }
    },
  },
};
</script>
