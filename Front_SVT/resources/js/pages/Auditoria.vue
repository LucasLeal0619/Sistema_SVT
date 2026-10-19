<template>
  <section class="svt-page svt-page--wide">
    <header>
      <h1>Auditoria</h1>
      <p class="svt-lead">
        Registro das operações do SVT. A consulta é somente leitura e o histórico não é alterado por esta tela.
      </p>
    </header>

    <p v-if="erro" class="svt-alert" role="alert">{{ erro }}</p>
    <p v-if="carregando" class="svt-muted">Carregando a auditoria...</p>

    <div class="svt-audit-stats" aria-label="Indicadores do recorte">
      <article class="svt-stat">
        <span>Eventos</span>
        <strong>{{ indicadores.total }}</strong>
      </article>
      <article class="svt-stat">
        <span>Sucesso</span>
        <strong>{{ indicadores.sucesso }}</strong>
      </article>
      <article class="svt-stat">
        <span>Falha</span>
        <strong>{{ indicadores.falha }}</strong>
      </article>
      <article class="svt-stat">
        <span>Acesso negado</span>
        <strong>{{ indicadores.negado }}</strong>
      </article>
    </div>

    <form @submit.prevent="aplicar">
      <PainelFiltros>
      <template #busca>
        <input v-model="filtros.busca" type="search" aria-label="Busca por responsável, ação ou identificador" placeholder="Responsável, ação ou identificador" />
      </template>
      <template #comandos>
        <button class="svt-btn" type="submit">Filtrar</button>
        <button class="svt-btn secondary" type="button" @click="limpar">Limpar</button>
      </template>
      <input v-model="filtros.de" type="date" aria-label="Data inicial" />
      <input v-model="filtros.ate" type="date" aria-label="Data final" />
      <select v-model="filtros.perfil" aria-label="Perfil">
        <option value="">Todos os perfis</option>
        <option v-for="perfil in perfis" :key="perfil" :value="perfil">{{ perfil }}</option>
      </select>
      <select v-model="filtros.unidade" aria-label="Unidade">
        <option value="">Todas as unidades</option>
        <option v-for="unidade in unidades" :key="unidade.id" :value="unidade.nome">{{ unidade.nome }}</option>
      </select>
      <select v-model="filtros.eixo" aria-label="Eixo">
        <option value="">Todos os eixos</option>
        <option v-for="eixo in eixos" :key="eixo.id" :value="eixo.nome">{{ eixo.nome }}</option>
      </select>
      <select v-model="filtros.categoria" aria-label="Módulo">
        <option value="">Todos os módulos</option>
        <option v-for="item in categorias" :key="item.valor" :value="item.valor">{{ item.rotulo }}</option>
      </select>
      <input v-model="filtros.acao" type="search" aria-label="Tipo de evento" placeholder="Tipo de evento" />
      <select v-model="filtros.resultado" aria-label="Resultado">
        <option value="">Qualquer resultado</option>
        <option value="sucesso">Sucesso</option>
        <option value="falha">Falha</option>
        <option value="negado">Acesso negado</option>
      </select>
      </PainelFiltros>
    </form>

    <ListaResponsiva v-if="!carregando && eventos.length" :itens="eventos" :colunas="colunasLista">
      <template #celula-quando="{ item }">{{ quando(item.ocorrido_em) }}</template>
      <template #celula-usuario_nome="{ item }">{{ item.usuario_nome || 'Sem usuário identificado' }}</template>
      <template #celula-categoria="{ item }">{{ rotuloCategoria(item.categoria) }}</template>
      <template #celula-registro="{ item }">{{ item.entidade_tipo || '—' }} {{ item.entidade_id || '' }}</template>
      <template #celula-resultado="{ item }">{{ rotuloResultado(item.resultado) }}</template>
      <template #celula-unidade="{ item }">{{ item.unidade || 'Sem unidade' }}</template>
      <template #celula-eixo="{ item }">{{ item.eixo || 'Sem eixo' }}</template>
      <template #acao="{ item }">
        <button class="svt-btn secondary" type="button" @click="abrir(item)">Detalhes</button>
      </template>
    </ListaResponsiva>

    <p v-if="!carregando && !erro && !eventos.length" class="svt-muted">Nenhum evento neste recorte.</p>

    <div v-if="meta.total" class="svt-pager">
      <span class="svt-muted">{{ meta.from || 0 }}–{{ meta.to || 0 }} de {{ meta.total || 0 }}</span>
      <div class="svt-pager-nav">
        <button class="svt-btn secondary" type="button" :disabled="meta.current_page <= 1 || carregando" @click="ir(meta.current_page - 1)">Anterior</button>
        <button class="svt-btn secondary" type="button" :disabled="meta.current_page >= meta.last_page || carregando" @click="ir(meta.current_page + 1)">Próxima</button>
      </div>
    </div>

    <article v-if="detalhe" class="svt-ficha svt-audit-detalhe">
      <h2>Evento {{ detalhe.id }}</h2>
      <button class="svt-btn secondary" type="button" @click="detalhe = null">Fechar</button>
      <dl>
        <div><dt>Quando</dt><dd>{{ quando(detalhe.ocorrido_em) }} ({{ detalhe.fuso }})</dd></div>
        <div><dt>Responsável</dt><dd>{{ detalhe.usuario_nome || 'Sem usuário identificado' }} · {{ detalhe.usuario_email || 'sem e-mail' }} · id {{ detalhe.usuario_id || 'não vinculado' }}</dd></div>
        <div><dt>Perfil no momento</dt><dd>{{ detalhe.perfil || '—' }}</dd></div>
        <div><dt>Módulo e ação</dt><dd>{{ rotuloCategoria(detalhe.categoria) }} · {{ detalhe.acao }}</dd></div>
        <div><dt>Registro</dt><dd>{{ detalhe.entidade_tipo || '—' }} {{ detalhe.entidade_id || '' }}</dd></div>
        <div><dt>Unidade e eixo</dt><dd>{{ detalhe.unidade || 'Sem unidade' }} · {{ detalhe.eixo || 'Sem eixo' }}</dd></div>
        <div><dt>Resultado</dt><dd>{{ rotuloResultado(detalhe.resultado) }}</dd></div>
        <div><dt>Correlação</dt><dd>{{ detalhe.correlacao }}</dd></div>
        <div><dt>Antes</dt><dd><pre>{{ texto(detalhe.anterior) }}</pre></dd></div>
        <div><dt>Depois</dt><dd><pre>{{ texto(detalhe.novo) }}</pre></dd></div>
      </dl>
    </article>
  </section>
</template>

<script>
import ListaResponsiva from '../components/ui/ListaResponsiva.vue';
import PainelFiltros from '../components/ui/PainelFiltros.vue';

const VAZIO = () => ({
  busca: '',
  de: '',
  ate: '',
  perfil: '',
  unidade: '',
  eixo: '',
  categoria: '',
  acao: '',
  resultado: '',
});

export default {
  name: 'Auditoria',
  components: { ListaResponsiva, PainelFiltros },
  data() {
    return {
      filtros: VAZIO(),
      eventos: [],
      indicadores: { total: 0, sucesso: 0, falha: 0, negado: 0 },
      meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
      unidades: [],
      eixos: [],
      detalhe: null,
      carregando: false,
      erro: '',
      perfis: [
        'Administrador',
        'Editor',
        'Consultor',
        'Instrutor',
        'Núcleo Pedagógico',
        'Coordenação CPED',
        'Direção Pedagógica',
        'NULOG',
      ],
      colunasLista: [
        { chave: 'quando', rotulo: 'Quando', papel: 'titulo' },
        { chave: 'resultado', rotulo: 'Resultado', papel: 'status' },
        { chave: 'usuario_nome', rotulo: 'Responsável', papel: 'destaque' },
        { chave: 'acao', rotulo: 'Ação', papel: 'destaque' },
        { chave: 'perfil', rotulo: 'Perfil', papel: 'detalhe' },
        { chave: 'categoria', rotulo: 'Módulo', papel: 'detalhe' },
        { chave: 'registro', rotulo: 'Registro', papel: 'detalhe' },
        { chave: 'unidade', rotulo: 'Unidade', papel: 'detalhe' },
        { chave: 'eixo', rotulo: 'Eixo', papel: 'detalhe' },
      ],
      categorias: [
        { valor: 'autenticacao', rotulo: 'Autenticação' },
        { valor: 'usuarios', rotulo: 'Usuários' },
        { valor: 'visitas', rotulo: 'Visitas' },
        { valor: 'logistica', rotulo: 'NULOG' },
        { valor: 'documentos', rotulo: 'Documentos' },
        { valor: 'configuracoes', rotulo: 'Configurações' },
        { valor: 'seguranca', rotulo: 'Segurança' },
      ],
    };
  },
  mounted() {
    this.carregarApoio();
    this.carregar(1);
  },
  methods: {
    async carregarApoio() {
      try {
        const [unidades, eixos] = await Promise.all([
          window.axios.get('/api/svt/cadastros/unidades'),
          window.axios.get('/api/svt/cadastros/eixos'),
        ]);
        this.unidades = unidades.data.data || [];
        this.eixos = eixos.data.data || [];
      } catch {
        this.unidades = [];
        this.eixos = [];
      }
    },
    async carregar(pagina) {
      this.carregando = true;
      this.erro = '';
      this.detalhe = null;
      try {
        const { data } = await window.axios.get('/api/svt/auditoria', {
          params: { ...this.filtros, page: pagina, per_page: 15 },
        });
        this.eventos = data.data || [];
        this.meta = data.meta || this.meta;
        this.indicadores = data.indicadores || this.indicadores;
      } catch (error) {
        this.eventos = [];
        this.erro = error.response?.data?.message || 'Não foi possível carregar a auditoria.';
      } finally {
        this.carregando = false;
      }
    },
    aplicar() {
      this.carregar(1);
    },
    limpar() {
      this.filtros = VAZIO();
      this.carregar(1);
    },
    ir(pagina) {
      this.carregar(pagina);
    },
    async abrir(evento) {
      this.erro = '';
      try {
        const { data } = await window.axios.get(`/api/svt/auditoria/${evento.id}`);
        this.detalhe = data.evento;
      } catch (error) {
        this.erro = error.response?.data?.message || 'Não foi possível abrir o evento.';
      }
    },
    quando(valor) {
      if (!valor) return '—';
      const data = new Date(valor);
      if (Number.isNaN(data.getTime())) return valor;
      return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short',
      }).format(data);
    },
    rotuloCategoria(valor) {
      return this.categorias.find((item) => item.valor === valor)?.rotulo || valor || '—';
    },
    rotuloResultado(valor) {
      return { sucesso: 'Sucesso', falha: 'Falha', negado: 'Acesso negado' }[valor] || valor || '—';
    },
    texto(valor) {
      if (!valor || (typeof valor === 'object' && !Object.keys(valor).length)) return 'Sem alteração registrada.';
      return JSON.stringify(valor, null, 2);
    },
  },
};
</script>
