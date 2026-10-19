<template>
  <section class="svt-page svt-page--wide">
    <template v-if="tela === 'lista'">
      <header class="svt-head">
        <h1>{{ tituloLista }}</h1>
        <p class="svt-lead" v-if="fila && ehNulog">A direção já liberou estas visitas. Aqui se registra o veículo, a placa, a data, o horário e o motorista. Não há aprovação nem recusa.</p>
        <p class="svt-lead" v-else-if="fila">Pedidos parados na sua etapa, com o instrutor de cada turma.</p>
        <p class="svt-lead" v-else-if="ehNulog">Visitas já liberadas pela direção: aguardando transporte, com transporte definido, realizadas ou com relatório.</p>
        <p class="svt-lead" v-else-if="eixoFixo">Só as visitas do eixo {{ eixoFixo }}, em qualquer etapa.</p>
        <p class="svt-lead" v-else-if="ehDirecao">Todas as visitas, de todos os eixos.</p>
        <p class="svt-lead" v-else>Lista das visitas. O instrutor é quem pediu e acompanha a turma.</p>
      </header>

      <div class="svt-row-actions" v-if="!fila && podeCriar">
        <button class="svt-btn" type="button" @click="pedirNova">Pedir uma visita</button>
      </div>

      <p v-if="aviso" class="svt-alert is-ok">{{ aviso }}</p>
      <p v-if="erro" class="svt-alert">{{ erro }}</p>
      <p v-if="recorte" class="svt-muted">
        {{ recorte }}
        <button type="button" class="svt-linkish" @click="limparRecorte">Limpar recorte</button>
      </p>

      <PainelFiltros>
        <template #busca>
          <input v-model="busca" type="search" placeholder="Turma, curso ou local" aria-label="Buscar visita" @input="agendarCarga" />
        </template>
        <select v-model="unidadeFiltro" aria-label="Filtrar por unidade" @change="agendarCarga">
          <option value="">Todas as unidades</option>
          <option v-for="unidade in unidades" :key="unidade" :value="unidade">{{ unidade }}</option>
        </select>
        <select v-if="!eixoFixo" v-model="eixoFiltro" aria-label="Filtrar por eixo" @change="agendarCarga">
          <option value="">Todos os eixos</option>
          <option v-for="eixo in eixos" :key="eixo" :value="eixo">{{ eixo }}</option>
        </select>
        <select v-model="instrutorFiltro" aria-label="Filtrar por instrutor" @change="agendarCarga">
          <option value="">Todos os instrutores</option>
          <option v-for="nome in instrutores" :key="nome" :value="nome">{{ nome }}</option>
        </select>
        <select v-if="!fila" v-model="etapa" aria-label="Filtrar por etapa" @change="agendarCarga">
          <option value="">Qualquer etapa</option>
          <option v-for="(label, chave) in etapas" :key="chave" :value="chave">{{ label }}</option>
        </select>
      </PainelFiltros>

      <p v-if="carregando" class="svt-muted">Carregando...</p>
      <p v-else-if="visitas.length === 0" class="svt-muted">
        {{ fila && ehNulog ? 'Nenhuma visita esperando transporte.' : fila ? 'Nada esperando você agora.' : 'Nenhuma visita com esses filtros.' }}
      </p>
      <ListaResponsiva v-else :itens="visitas" :colunas="colunasLista">
        <template #acao="{ item }">
          <button class="svt-btn secondary" type="button" @click="abrir(item.id)">Visualizar</button>
        </template>
        <template #celula-turma="{ item }">
          {{ item.turma }}
          <span v-if="item.ficticio" class="svt-badge is-note">Fictício</span>
        </template>
        <template #celula-data="{ item }">{{ formatarData(item.data_visita_prevista) }}</template>
        <template #celula-etapa="{ item }">
          <span class="svt-badge" :class="classeEtapa(item.etapa)">{{ item.etapa_label }}</span>
        </template>
      </ListaResponsiva>
      <div v-if="meta.total > meta.per_page" class="svt-pager">
        <span>{{ meta.from }}–{{ meta.to }} de {{ meta.total }}</span>
        <div class="svt-pager-nav">
          <button class="svt-btn secondary" type="button" :disabled="meta.current_page <= 1" @click="irParaPagina(meta.current_page - 1)">Anterior</button>
          <button class="svt-btn secondary" type="button" :disabled="meta.current_page >= meta.last_page" @click="irParaPagina(meta.current_page + 1)">Próxima</button>
        </div>
      </div>
    </template>

    <form v-else-if="tela === 'pedido'" class="svt-ficha" @submit.prevent="enviar(true)">
      <button class="svt-back" type="button" @click="voltar">Voltar</button>
      <h2>Pedido da turma</h2>
      <p class="svt-muted">Isso vai primeiro para o núcleo pedagógico da unidade.</p>
      <p v-if="erro" class="svt-alert">{{ erro }}</p>
      <p v-if="cota" class="svt-alert" :class="{ 'is-ok': !cota.acima_do_limite }">
        Realizadas ou com transporte: {{ cota.consumidas }} de {{ cota.limite }}.
        Pendentes na tramitação: {{ cota.pendentes }}.
        <template v-if="cota.acima_do_limite"> Dá para seguir, mas a justificativa é obrigatória.</template>
        <template v-else> Ainda cabem {{ cota.disponiveis }}.</template>
      </p>

      <p class="svt-kicker">A turma</p>
      <label class="svt-field">Turma vinculada a você
        <select v-model="form.turma_id" required @change="aoEscolherTurma">
          <option value="">Selecione</option>
          <option v-for="turma in turmas" :key="turma.id" :value="turma.id">{{ turma.nome }} · {{ turma.curso }}</option>
        </select>
        <small v-if="turmas.length === 0">Nenhuma turma vinculada. O administrador precisa cadastrar a turma no seu nome.</small>
      </label>
      <p v-if="turmaEscolhida" class="svt-muted">
        {{ turmaEscolhida.unidade }} · {{ turmaEscolhida.eixo }} · {{ turmaEscolhida.curso }}
        <template v-if="turmaEscolhida.tipo_label"> · {{ turmaEscolhida.tipo_label }}</template>
      </p>
      <p v-if="cota" class="svt-muted">{{ cota.observacao }}</p>

      <p class="svt-kicker">A saída</p>
      <label class="svt-field">Local da visita
        <input v-model="form.local_visita" required />
      </label>
      <div class="svt-grid-2">
        <label class="svt-field">Data pretendida
          <input v-model="form.data_visita_prevista" type="date" required />
        </label>
        <label class="svt-field">Horário pretendido
          <input v-model="form.horario_pretendido" placeholder="08:30" />
        </label>
      </div>
      <label class="svt-field">O que a turma vai fazer
        <textarea v-model="form.atividade" required />
      </label>
      <label class="svt-field">Objetivo pedagógico
        <textarea v-model="form.objetivo" required />
      </label>
      <label class="svt-field">Relação com o curso ou a unidade curricular
        <textarea v-model="form.relacao_curricular" required />
      </label>
      <label v-if="cota?.acima_do_limite" class="svt-field">Por que esta visita extra é necessária
        <textarea v-model="form.justificativa" required />
      </label>
      <div class="svt-actions svt-actions--pair">
        <button class="svt-btn secondary" type="button" :disabled="enviando" @click="enviar(false)">Salvar rascunho</button>
        <button class="svt-btn" type="submit" :disabled="enviando">
          {{ enviando ? 'Enviando...' : 'Enviar ao núcleo' }}
        </button>
      </div>
    </form>

    <article v-else-if="detalhe" class="svt-ficha">
      <button class="svt-back" type="button" @click="voltar">Voltar</button>
      <p v-if="aviso" class="svt-alert is-ok">{{ aviso }}</p>
      <header class="svt-ficha-topo">
        <p class="svt-kicker">Solicitação #{{ detalhe.visita.id }}</p>
        <h2>{{ detalhe.visita.turma }}</h2>
        <p class="svt-muted">{{ detalhe.visita.curso }} · {{ detalhe.visita.unidade }} · {{ detalhe.visita.eixo }}</p>
        <p><span class="svt-badge" :class="classeEtapa(detalhe.visita.etapa)">{{ detalhe.visita.etapa_label }}</span></p>
      </header>
      <p v-if="detalhe.visita.ficticio" class="svt-alert">Dado fictício de demonstração.</p>

      <h3>A visita</h3>
      <dl class="svt-leitura">
        <dt>Local</dt><dd>{{ detalhe.visita.local_visita || '—' }}</dd>
        <dt>Data prevista</dt><dd>{{ formatarData(detalhe.visita.data_visita_prevista) }}</dd>
        <dt>Horário</dt><dd>{{ detalhe.visita.horario_pretendido || '—' }}</dd>
        <dt>Instrutor</dt><dd>{{ detalhe.visita.instrutor || '—' }}</dd>
        <dt>Atividade</dt><dd>{{ detalhe.visita.atividade || '—' }}</dd>
        <dt>Objetivo</dt><dd>{{ detalhe.visita.objetivo || '—' }}</dd>
        <dt>Relação curricular</dt><dd>{{ detalhe.visita.relacao_curricular || '—' }}</dd>
      </dl>
      <p v-if="detalhe.visita.acima_do_limite" class="svt-alert">
        Passou do limite ({{ detalhe.visita.visitas_utilizadas }}/{{ detalhe.visita.limite }}).
        {{ detalhe.visita.justificativa }}
      </p>
      <p v-if="detalhe.visita.motivo_decisao"><strong>Registro:</strong> {{ detalhe.visita.motivo_decisao }}</p>

      <h3>Transporte</h3>
      <dl v-if="detalhe.visita.motorista" class="svt-leitura">
        <dt>Tipo</dt><dd>{{ detalhe.visita.transporte_tipo }}</dd>
        <dt>Veículo</dt><dd>{{ detalhe.visita.veiculo }}</dd>
        <dt>Placa</dt><dd>{{ detalhe.visita.placa }}</dd>
        <dt>Motorista</dt><dd>{{ detalhe.visita.motorista }}</dd>
        <dt>Saída</dt><dd>{{ formatarData(detalhe.visita.data_transporte) }} {{ detalhe.visita.horario_transporte }}</dd>
        <dt>Retorno</dt><dd>{{ detalhe.visita.horario_retorno || '—' }}</dd>
      </dl>
      <p v-else class="svt-muted">Transporte ainda não registrado.</p>

      <h3>Relatório</h3>
      <p v-if="detalhe.visita.relatorio_texto" class="svt-relato-texto">{{ detalhe.visita.relatorio_texto }}</p>
      <p v-if="detalhe.visita.tem_relatorio">
        <button class="svt-btn secondary" type="button" @click="baixarRelatorio(detalhe.visita)">Abrir relatório</button>
      </p>
      <p v-else-if="!detalhe.visita.relatorio_texto" class="svt-muted">Relatório ainda não entregue.</p>

      <form v-if="detalhe.visita.pode_decidir" class="svt-list" @submit.prevent="decidir(true)">
        <label class="svt-field">Se for recusar, escreva o motivo
          <textarea v-model="motivo" rows="3" />
        </label>
        <div class="svt-actions svt-actions--pair">
          <button class="svt-btn" type="submit">Aprovar e seguir</button>
          <button class="svt-btn danger" type="button" @click="decidir(false)">Recusar</button>
        </div>
      </form>

      <form v-if="detalhe.visita.pode_transporte" class="svt-list" @submit.prevent="salvarTransporte">
        <h3>Registrar o transporte</h3>
        <p class="svt-muted">A análise pedagógica já terminou. Estes dados ficam visíveis para a unidade e para o instrutor.</p>
        <label class="svt-field">Tipo
          <select v-model="transporte.transporte_tipo" required>
            <option v-for="tipo in transportes" :key="tipo" :value="tipo">{{ tipo }}</option>
          </select>
        </label>
        <div class="svt-grid-2">
          <label class="svt-field">Veículo <input v-model="transporte.veiculo" required /></label>
          <label class="svt-field">Placa <input v-model="transporte.placa" required /></label>
          <label class="svt-field">Data <input v-model="transporte.data_transporte" type="date" required /></label>
          <label class="svt-field">Horário <input v-model="transporte.horario_transporte" required placeholder="08:00" /></label>
        </div>
        <label class="svt-field">Horário de retorno
          <input v-model="transporte.horario_retorno" placeholder="12:00" />
        </label>
        <label class="svt-field">Motorista <input v-model="transporte.motorista" required /></label>
        <label class="svt-field">Informações adicionais
          <textarea v-model="transporte.info_transporte" rows="2" />
        </label>
        <button class="svt-btn block" type="submit">Registrar transporte</button>
      </form>

      <div v-if="detalhe.visita.pode_realizar" class="svt-actions">
        <button class="svt-btn" type="button" @click="marcarRealizada">Marcar visita como realizada</button>
      </div>

      <div v-if="detalhe.visita.pode_editar || detalhe.visita.pode_cancelar" class="svt-actions">
        <button v-if="detalhe.visita.pode_editar" class="svt-btn secondary" type="button" @click="editarRascunho">Editar rascunho</button>
        <button v-if="detalhe.visita.pode_cancelar" class="svt-btn danger" type="button" @click="cancelar">Cancelar solicitação</button>
      </div>

      <form v-if="detalhe.visita.pode_relatorio" class="svt-list" @submit.prevent="anexarRelatorio">
        <label class="svt-field">O que foi realizado e o que a turma aprendeu
          <textarea v-model="relatorioTexto" rows="3" />
        </label>
        <label class="svt-field">Relatório da visita, em Word ou PDF
          <input type="file" accept=".pdf,.doc,.docx,application/pdf" @change="arquivo = $event.target.files[0]" required />
        </label>
        <button class="svt-btn block" type="submit">Anexar relatório</button>
      </form>

      <div v-if="detalhe.eventos?.length">
        <h3>Tramitação</h3>
        <ol class="svt-trilha">
          <li v-for="evento in detalhe.eventos" :key="evento.id">
            <strong>{{ evento.descricao }}</strong>
            <span>{{ formatarDataHora(evento.created_at) }} · {{ evento.perfil || 'Sistema' }}</span>
            <span v-if="evento.texto">{{ evento.texto }}</span>
          </li>
        </ol>
      </div>
      <p v-if="erro" class="svt-alert">{{ erro }}</p>
    </article>

    <HistoricoTurma
      v-if="ehAdmin && historicoTurma && tela !== 'lista'"
      :dados="historicoTurma"
      :atual="detalhe?.visita?.id || null"
      @trocar="trocarAnoHistorico"
      @abrir="abrir"
    />
  </section>
</template>

<script>
import HistoricoTurma from '../components/HistoricoTurma.vue';
import ListaResponsiva from '../components/ui/ListaResponsiva.vue';
import PainelFiltros from '../components/ui/PainelFiltros.vue';
import { PERFIS, getUsuario } from '../scripts/auth';

const FORM_VAZIO = () => ({
  id: null,
  turma_id: '',
  local_visita: '',
  data_visita_prevista: '',
  horario_pretendido: '',
  atividade: '',
  objetivo: '',
  relacao_curricular: '',
  justificativa: '',
});

export default {
  name: 'SvtSolicitacoes',
  components: { HistoricoTurma, ListaResponsiva, PainelFiltros },
  props: {
    fila: { type: Boolean, default: false },
  },
  data() {
    return {
      tela: 'lista',
      visitas: [],
      turmas: [],
      pagina: 1,
      meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0, per_page: 15 },
      carregando: false,
      timer: null,
      relatorioTexto: '',
      busca: '',
      etapa: '',
      situacao: '',
      cursoFiltro: '',
      turmaFiltro: '',
      de: '',
      ate: '',
      unidadeFiltro: '',
      eixoFiltro: '',
      instrutorFiltro: '',
      instrutores: [],
      etapas: {},
      ordem: [],
      tipos: {},
      responsaveis: {},
      eixos: [],
      unidades: [],
      transportes: [],
      podeCriar: false,
      form: FORM_VAZIO(),
      cota: null,
      historicoTurma: null,
      detalhe: null,
      motivo: '',
      arquivo: null,
      transporte: {
        transporte_tipo: 'Ônibus',
        veiculo: '',
        placa: '',
        data_transporte: '',
        horario_transporte: '',
        horario_retorno: '',
        info_transporte: '',
        motorista: '',
      },
      erro: '',
      aviso: '',
      enviando: false,
    };
  },
  computed: {
    colunasLista() {
      return [
        { chave: 'turma', rotulo: 'Turma', papel: 'titulo' },
        { chave: 'etapa', rotulo: 'Etapa', papel: 'status' },
        { chave: 'instrutor', rotulo: 'Instrutor', papel: 'destaque' },
        { chave: 'data', rotulo: 'Data', papel: 'destaque' },
        { chave: 'curso', rotulo: 'Curso', papel: 'destaque' },
        { chave: 'unidade', rotulo: 'Unidade', papel: 'destaque' },
        { chave: 'local_visita', rotulo: 'Local', papel: 'destaque' },
      ];
    },
    eixoFixo() {
      const usuario = getUsuario();
      if (usuario?.perfil !== PERFIS.CPED) return '';
      const area = usuario.area;
      return Object.keys(this.responsaveis).find((eixo) => this.responsaveis[eixo] === area) || '';
    },
    ehDirecao() {
      return getUsuario()?.perfil === PERFIS.DIRECAO;
    },
    ehNulog() {
      return getUsuario()?.perfil === PERFIS.NULOG;
    },
    ehAdmin() {
      return getUsuario()?.perfil === PERFIS.ADMINISTRADOR;
    },
    tituloLista() {
      if (this.fila && this.ehNulog) return 'Transporte';
      if (this.fila) return 'Pendências';
      const perfil = getUsuario()?.perfil;
      if (perfil === PERFIS.INSTRUTOR) return 'Minhas Visitas';
      if (perfil === PERFIS.NUCLEO) return 'Visitas da Unidade';
      if (perfil === PERFIS.CPED) return 'Visitas do Eixo';
      if (perfil === PERFIS.NULOG) return 'Visitas Autorizadas';
      if (perfil === PERFIS.ADMINISTRADOR) return 'Visitas Técnicas';
      return 'Todas as Visitas';
    },
    recorte() {
      const nomes = {
        tramitacao: 'Solicitações em tramitação',
        aprovacao: 'Solicitações aguardando aprovação',
        reprovadas: 'Solicitações reprovadas',
        transporte: 'Visitas com transporte definido',
        realizadas: 'Visitas realizadas',
        relatorio_pendente: 'Visitas com relatório pendente',
        relatorio_entregue: 'Relatórios entregues',
        logistica: 'Solicitações aguardando transporte',
      };
      return nomes[this.situacao] || '';
    },
    turmaEscolhida() {
      return this.turmas.find((turma) => String(turma.id) === String(this.form.turma_id)) || null;
    },
  },
  async mounted() {
    await this.carregarCatalogo();
    this.aplicarQuery();
  },
  watch: {
    fila() {
      this.pagina = 1;
      this.situacao = '';
      this.voltar();
      this.carregar();
    },
    '$route.query': {
      deep: true,
      handler() {
        this.aplicarQuery();
      },
    },
  },
  methods: {
    aplicarQuery() {
      const query = this.$route.query || {};
      if (query.id) {
        this.carregar().then(() => this.abrir(query.id));
        return;
      }
      this.etapa = typeof query.etapa === 'string' ? query.etapa : '';
      this.situacao = typeof query.situacao === 'string' ? query.situacao : '';
      this.unidadeFiltro = typeof query.unidade === 'string' ? query.unidade : '';
      this.eixoFiltro = typeof query.eixo === 'string' ? query.eixo : '';
      this.cursoFiltro = typeof query.curso === 'string' ? query.curso : '';
      this.turmaFiltro = typeof query.turma === 'string' ? query.turma : '';
      this.de = typeof query.de === 'string' ? query.de : '';
      this.ate = typeof query.ate === 'string' ? query.ate : '';
      this.pagina = 1;
      this.carregar().then(() => {
        if (query.novo === '1' && this.podeCriar && !query.id) this.pedirNova();
      });
    },
    limparRecorte() {
      this.$router.replace({ name: this.fila ? 'fila' : 'solicitacoes' });
    },
    async carregarCatalogo() {
      const { data } = await window.axios.get('/api/svt/catalogo');
      this.etapas = data.etapas;
      this.ordem = data.ordem;
      this.tipos = data.tipos;
      this.responsaveis = data.responsaveis || {};
      this.eixos = data.eixos;
      this.unidades = data.unidades;
      this.transportes = data.transportes;
      if (this.podeCriar || getUsuario()?.perfil === PERFIS.INSTRUTOR) {
        const turmas = await window.axios.get('/api/svt/turmas');
        this.turmas = turmas.data.data;
      }
    },
    agendarCarga() {
      this.pagina = 1;
      clearTimeout(this.timer);
      this.timer = setTimeout(() => this.carregar(), 300);
    },
    irParaPagina(pagina) {
      this.pagina = pagina;
      this.carregar();
    },
    async carregar() {
      this.carregando = true;
      try {
        const { data } = await window.axios.get('/api/svt/solicitacoes', {
          params: {
            fila: this.fila ? 1 : 0,
            busca: this.busca || undefined,
            etapa: this.etapa || undefined,
            situacao: this.situacao || undefined,
            unidade: this.unidadeFiltro || undefined,
            eixo: this.eixoFiltro || this.eixoFixo || undefined,
            curso: this.cursoFiltro || undefined,
            turma: this.turmaFiltro || undefined,
            de: this.de || undefined,
            ate: this.ate || undefined,
            instrutor: this.instrutorFiltro || undefined,
            page: this.pagina,
            per_page: 15,
          },
        });
        this.visitas = data.data;
        this.meta = data.meta || this.meta;
        this.podeCriar = data.pode_criar;
        const nomes = [...new Set(data.data.map((visita) => visita.instrutor).filter(Boolean))];
        if (!this.instrutorFiltro) this.instrutores = nomes.sort((a, b) => a.localeCompare(b, 'pt-BR'));
      } catch (error) {
        if (error?.code === 'ERR_CANCELED') return;
        throw error;
      } finally {
        this.carregando = false;
      }
    },
    pedirNova() {
      this.detalhe = null;
      this.erro = '';
      this.aviso = '';
      this.form = FORM_VAZIO();
      this.cota = null;
      this.historicoTurma = null;
      this.tela = 'pedido';
    },
    voltar() {
      this.detalhe = null;
      this.erro = '';
      this.tela = 'lista';
      this.limparQuery();
    },
    limparQuery() {
      if (this.$route.query.id) {
        this.$router.replace({ name: this.fila ? 'fila' : 'solicitacoes' });
      }
    },
    async aoEscolherTurma() {
      if (!this.form.turma_id) {
        this.cota = null;
        this.historicoTurma = null;
        return;
      }
      const { data } = await window.axios.get('/api/svt/cota', { params: { turma_id: this.form.turma_id } });
      this.cota = data;
      await this.carregarHistorico(this.form.turma_id);
    },
    async enviar(enviarAoNucleo) {
      this.erro = '';
      this.enviando = true;
      const payload = { ...this.form, enviar: enviarAoNucleo };
      try {
        if (this.form.id) {
          await window.axios.put(`/api/svt/solicitacoes/${this.form.id}`, payload);
        } else {
          await window.axios.post('/api/svt/solicitacoes', payload);
        }
        this.aviso = enviarAoNucleo ? 'Pedido enviado ao núcleo da unidade.' : 'Rascunho salvo.';
        this.form = FORM_VAZIO();
        this.cota = null;
        this.historicoTurma = null;
        this.tela = 'lista';
        await this.carregar();
      } catch (error) {
        this.erro = this.mensagem(error);
      } finally {
        this.enviando = false;
      }
    },
    async carregarHistorico(turmaId, ano) {
      if (!this.ehAdmin || !turmaId) {
        this.historicoTurma = null;
        return;
      }
      try {
        const { data } = await window.axios.get(`/api/svt/turmas/${turmaId}/historico`, {
          params: ano ? { ano } : {},
        });
        this.historicoTurma = data;
      } catch {
        this.historicoTurma = null;
      }
    },
    trocarAnoHistorico(ano) {
      const turmaId = this.tela === 'pedido' ? this.form.turma_id : this.detalhe?.visita?.turma_id;
      this.carregarHistorico(turmaId, ano);
    },
    editarRascunho() {
      const visita = this.detalhe.visita;
      this.form = {
        ...FORM_VAZIO(),
        id: visita.id,
        turma_id: visita.turma_id || '',
        local_visita: visita.local_visita || '',
        data_visita_prevista: String(visita.data_visita_prevista || '').slice(0, 10),
        horario_pretendido: visita.horario_pretendido || '',
        atividade: visita.atividade || '',
        objetivo: visita.objetivo || '',
        relacao_curricular: visita.relacao_curricular || '',
        justificativa: visita.justificativa || '',
      };
      this.tela = 'pedido';
      this.aoEscolherTurma();
    },
    async cancelar() {
      this.erro = '';
      try {
        await window.axios.post(`/api/svt/solicitacoes/${this.detalhe.visita.id}/cancelar`, {});
        this.aviso = 'Solicitação cancelada.';
        this.tela = 'lista';
        this.detalhe = null;
        await this.carregar();
      } catch (error) {
        this.erro = this.mensagem(error);
      }
    },
    async marcarRealizada() {
      this.erro = '';
      try {
        await window.axios.post(`/api/svt/solicitacoes/${this.detalhe.visita.id}/realizar`);
        this.aviso = 'Visita marcada como realizada.';
        await this.abrir(this.detalhe.visita.id);
      } catch (error) {
        this.erro = this.mensagem(error);
      }
    },
    async abrir(id) {
      this.erro = '';
      const turmaAntes = this.detalhe?.visita?.turma_id;
      const ano = this.historicoTurma?.ano;
      const { data } = await window.axios.get(`/api/svt/solicitacoes/${id}`);
      this.detalhe = data;
      this.tela = 'ficha';
      const mesmaTurma = turmaAntes && String(turmaAntes) === String(data.visita.turma_id);
      await this.carregarHistorico(data.visita.turma_id, mesmaTurma ? ano : null);
    },
    async decidir(aprovar) {
      this.erro = '';
      try {
        await window.axios.post(`/api/svt/solicitacoes/${this.detalhe.visita.id}/decisao`, {
          aprovar,
          motivo: this.motivo,
        });
        this.motivo = '';
        this.aviso = aprovar ? 'Pedido encaminhado.' : 'Pedido recusado.';
        await this.carregar();
        this.tela = 'lista';
        this.detalhe = null;
        this.limparQuery();
      } catch (error) {
        this.erro = this.mensagem(error);
      }
    },
    async salvarTransporte() {
      this.erro = '';
      try {
        await window.axios.post(`/api/svt/solicitacoes/${this.detalhe.visita.id}/transporte`, this.transporte);
        this.aviso = 'Transporte registrado.';
        await this.carregar();
        this.tela = 'lista';
        this.detalhe = null;
        this.limparQuery();
      } catch (error) {
        this.erro = this.mensagem(error);
      }
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
      } catch (error) {
        this.erro = 'Não foi possível abrir o relatório.';
      }
    },
    async anexarRelatorio() {
      this.erro = '';
      const dados = new FormData();
      dados.append('arquivo', this.arquivo);
      if (this.relatorioTexto) dados.append('relatorio_texto', this.relatorioTexto);
      try {
        await window.axios.post(`/api/svt/solicitacoes/${this.detalhe.visita.id}/relatorio`, dados);
        this.aviso = 'Relatório anexado.';
        await this.carregar();
        this.tela = 'lista';
        this.detalhe = null;
        this.limparQuery();
      } catch (error) {
        this.erro = this.mensagem(error);
      }
    },
    formatarData(valor) {
      if (!valor) return 'sem data';
      return new Date(`${String(valor).slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR');
    },
    classeEtapa(etapa) {
      if (String(etapa).startsWith('recusada') || etapa === 'cancelada') return 'is-danger';
      if (etapa === 'realizada' || etapa === 'relatorio' || etapa === 'aprovada') return 'is-ok';
      return '';
    },
    formatarDataHora(valor) {
      if (!valor) return '';
      return new Date(valor).toLocaleString('pt-BR');
    },
    mensagem(error) {
      const erros = error.response?.data?.errors;
      if (erros) return Object.values(erros).flat()[0];
      return error.response?.data?.message || 'Não foi possível concluir.';
    },
  },
};
</script>
