<template>
  <section class="svt-page svt-page--wide">
    <header>
      <h1>Relatórios de Visita</h1>
      <p class="svt-lead">
        Documentos enviados depois da visita. O envio continua na ficha da visita, pelo instrutor.
        Aqui dá para ver o que já foi entregue e o que ainda está pendente.
      </p>
    </header>

    <nav v-if="abas.length > 1" class="svt-subnav" aria-label="Tipos de relatório">
      <router-link v-for="aba in abas" :key="aba.to" :to="aba.to">{{ aba.label }}</router-link>
    </nav>

    <p v-if="erro" class="svt-alert">{{ erro }}</p>

    <PainelFiltros>
      <template #busca>
        <input v-model="busca" type="search" placeholder="Turma, curso ou local" aria-label="Buscar relatório" @input="aplicarFiltro" />
      </template>
      <select v-model="documento" aria-label="Situação do relatório" @change="aplicarFiltro">
        <option value="todos">Pendentes e entregues</option>
        <option value="pendente">Relatório pendente</option>
        <option value="entregue">Relatório entregue</option>
      </select>
      <select v-model="unidade" aria-label="Unidade" @change="aplicarFiltro">
        <option value="">Todas as unidades</option>
        <option v-for="item in unidades" :key="item" :value="item">{{ item }}</option>
      </select>
      <select v-if="!eixoFixo" v-model="eixo" aria-label="Eixo" @change="aplicarFiltro">
        <option value="">Todos os eixos</option>
        <option v-for="item in eixos" :key="item" :value="item">{{ item }}</option>
      </select>
    </PainelFiltros>

    <p v-if="carregando" class="svt-muted">Carregando documentos...</p>
    <p v-else-if="visitas.length === 0" class="svt-muted">Nenhum relatório neste recorte.</p>
    <ListaResponsiva v-else :itens="visitas" :colunas="colunasLista">
      <template #celula-turma="{ item }">
        {{ item.turma }}
        <span v-if="item.ficticio" class="svt-badge is-note">Fictício</span>
      </template>
      <template #celula-data="{ item }">{{ formatarData(item.data_visita_prevista) }}</template>
      <template #celula-situacao="{ item }">{{ item.etapa === 'relatorio' ? 'Entregue' : 'Pendente' }}</template>
      <template #acao="{ item }">
        <button v-if="item.tem_relatorio" type="button" class="svt-btn secondary" @click="baixarRelatorio(item)">Abrir</button>
        <router-link v-else-if="podeEnviar" class="svt-btn secondary" :to="{ name: 'solicitacoes', query: { id: item.id } }">Enviar na visita</router-link>
        <span v-else>Pendente</span>
      </template>
    </ListaResponsiva>
    <div v-if="meta.total > meta.per_page" class="svt-pager">
      <span>{{ meta.from }}–{{ meta.to }} de {{ meta.total }}</span>
      <div class="svt-pager-nav">
        <button class="svt-btn secondary" type="button" :disabled="meta.current_page <= 1" @click="irParaPagina(meta.current_page - 1)">Anterior</button>
        <button class="svt-btn secondary" type="button" :disabled="meta.current_page >= meta.last_page" @click="irParaPagina(meta.current_page + 1)">Próxima</button>
      </div>
    </div>
  </section>
</template>

<script>
import ListaResponsiva from '../components/ui/ListaResponsiva.vue';
import PainelFiltros from '../components/ui/PainelFiltros.vue';
import { PERFIS, abasRelatorio, getUsuario } from '../scripts/auth';

export default {
  name: 'SvtRelatoriosVisita',
  components: { ListaResponsiva, PainelFiltros },
  data() {
    return {
      visitas: [],
      pagina: 1,
      meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0, per_page: 15 },
      documento: 'todos',
      busca: '',
      unidade: '',
      eixo: '',
      etapas: {},
      responsaveis: {},
      eixos: [],
      unidades: [],
      erro: '',
      carregando: false,
    };
  },
  computed: {
    colunasLista() {
      return [
        { chave: 'turma', rotulo: 'Turma', papel: 'titulo' },
        { chave: 'situacao', rotulo: 'Situação', papel: 'status' },
        { chave: 'curso', rotulo: 'Curso', papel: 'destaque' },
        { chave: 'data', rotulo: 'Data', papel: 'destaque' },
        { chave: 'unidade', rotulo: 'Unidade', papel: 'destaque' },
        { chave: 'eixo', rotulo: 'Eixo', papel: 'destaque' },
        { chave: 'instrutor', rotulo: 'Instrutor', papel: 'destaque' },
      ];
    },
    abas() {
      return abasRelatorio();
    },
    podeEnviar() {
      return getUsuario()?.perfil === PERFIS.INSTRUTOR;
    },
    eixoFixo() {
      const usuario = getUsuario();
      if (usuario?.perfil !== PERFIS.CPED) return '';
      return Object.keys(this.responsaveis).find((eixo) => this.responsaveis[eixo] === usuario.area) || '';
    },
  },
  async mounted() {
    try {
      const { data } = await window.axios.get('/api/svt/catalogo');
      this.etapas = data.etapas || {};
      this.responsaveis = data.responsaveis || {};
      this.eixos = data.eixos || [];
      this.unidades = data.unidades || [];
    } catch (error) {
      this.erro = error.response?.data?.message || 'Não foi possível carregar o catálogo.';
    }
    await this.carregar();
  },
  methods: {
    aplicarFiltro() {
      this.pagina = 1;
      this.carregar();
    },
    async carregar() {
      this.erro = '';
      this.carregando = true;
      try {
        const { data } = await window.axios.get('/api/svt/solicitacoes', {
          params: {
            documento: this.documento,
            busca: this.busca || undefined,
            unidade: this.unidade || undefined,
            eixo: this.eixoFixo || this.eixo || undefined,
            page: this.pagina,
            per_page: 15,
          },
        });
        this.visitas = data.data;
        this.meta = data.meta || this.meta;
      } catch (error) {
        this.erro = error.response?.data?.message || 'Não foi possível carregar os relatórios.';
        this.visitas = [];
      } finally {
        this.carregando = false;
      }
    },
    irParaPagina(pagina) {
      this.pagina = pagina;
      this.carregar();
    },
    async baixarRelatorio(visita) {
      this.erro = '';
      try {
        const resposta = await window.axios.get(`/api/svt/solicitacoes/${visita.id}/relatorio/arquivo`, {
          responseType: 'blob',
        });
        const tipo = resposta.headers['content-type'] || 'application/octet-stream';
        const url = URL.createObjectURL(new Blob([resposta.data], { type: tipo }));
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      } catch {
        this.erro = 'Não foi possível abrir o relatório.';
      }
    },
    formatarData(valor) {
      if (!valor) return 'sem data';
      return new Date(`${String(valor).slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR');
    },
  },
};
</script>
