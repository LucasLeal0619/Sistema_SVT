<template>
  <section class="svt-page svt-page--wide">
    <header>
      <h1>{{ secao === 'regras' ? 'Regras de Visitas' : 'Dados Institucionais' }}</h1>
      <p v-if="secao === 'regras'" class="svt-lead">
        Limites, carga horária e vigência usados pelo SVT. Os limites da ata continuam provisórios e podem ser alterados aqui.
        Desativar uma regra não apaga o histórico das solicitações.
      </p>
      <p v-else class="svt-lead">
        Unidades, eixos, cursos e turmas. Desativar não apaga o histórico das solicitações nem substitui esses dados por integração.
        A ligação com o SIG depende de identificação oficial, documentação técnica e autorização do cliente.
      </p>
    </header>

    <nav class="svt-subnav" aria-label="Configurações">
      <router-link to="/app/configuracoes/dados">Dados Institucionais</router-link>
      <router-link to="/app/configuracoes/regras">Regras de Visitas</router-link>
    </nav>

    <div class="svt-filters">
      <select v-model="recurso" aria-label="Tipo de cadastro" @change="carregar">
        <option v-for="opcao in opcoes" :key="opcao.value" :value="opcao.value">{{ opcao.label }}</option>
      </select>
      <button class="svt-btn" type="button" @click="novo">Novo</button>
    </div>

    <p v-if="aviso" class="svt-alert is-ok">{{ aviso }}</p>
    <p v-if="erro" class="svt-alert">{{ erro }}</p>
    <p v-if="carregando" class="svt-muted">Carregando...</p>

    <form v-if="editando" class="svt-ficha" @submit.prevent="salvar">
      <h2>{{ form.id ? 'Editar' : 'Novo' }}</h2>
      <label v-if="recurso !== 'regras'" class="svt-field">Nome
        <input v-model="form.nome" required maxlength="180" />
      </label>
      <label v-if="recurso === 'cursos' || recurso === 'regras'" class="svt-field">Tipo de curso
        <select v-model="form.tipo_curso">
          <option value="">Selecione</option>
          <option v-for="(tipo, chave) in tipos" :key="chave" :value="chave">{{ tipo.label }}</option>
        </select>
      </label>
      <label v-if="recurso === 'cursos'" class="svt-field">Eixo
        <select v-model="form.eixo_id" required>
          <option value="">Selecione</option>
          <option v-for="eixo in eixos" :key="eixo.id" :value="eixo.id">{{ eixo.nome }}</option>
        </select>
      </label>
      <label v-if="recurso === 'cursos'" class="svt-field">Carga horária
        <input v-model="form.carga_horaria" type="number" min="1" />
      </label>
      <template v-if="recurso === 'turmas'">
        <label class="svt-field">Curso
          <select v-model="form.curso_id" required>
            <option value="">Selecione</option>
            <option v-for="curso in cursos" :key="curso.id" :value="curso.id">{{ curso.nome }}</option>
          </select>
        </label>
        <label class="svt-field">Unidade
          <select v-model="form.unidade_id" required>
            <option value="">Selecione</option>
            <option v-for="unidade in unidades" :key="unidade.id" :value="unidade.id">{{ unidade.nome }}</option>
          </select>
        </label>
        <label class="svt-field">Instrutor
          <select v-model="form.instrutor_id">
            <option value="">Sem vínculo</option>
            <option v-for="pessoa in instrutores" :key="pessoa.id" :value="pessoa.id">{{ pessoa.nome }}</option>
          </select>
        </label>
      </template>
      <template v-if="recurso === 'regras'">
        <label class="svt-field">Limite de visitas
          <input v-model="form.limite" type="number" min="0" required />
        </label>
        <label class="svt-field">Vigência inicial
          <input v-model="form.vigencia_inicio" type="date" required />
        </label>
        <label class="svt-field">Vigência final
          <input v-model="form.vigencia_fim" type="date" />
        </label>
        <label class="svt-field">Observação
          <textarea v-model="form.observacao" rows="3" />
        </label>
      </template>
      <div class="svt-actions">
        <button class="svt-btn" type="submit">Salvar</button>
        <button class="svt-btn secondary" type="button" @click="editando = false">Fechar</button>
      </div>
    </form>

    <ListaResponsiva v-if="!carregando && itens.length" :itens="itens" :colunas="colunasLista">
      <template #celula-nome="{ item }">{{ rotulo(item) }}</template>
      <template #celula-ativo="{ item }">{{ item.ativo ? 'Ativo' : 'Desativado' }}</template>
      <template #acao="{ item }">
        <button class="svt-btn secondary" type="button" @click="editar(item)">Editar</button>
        <button v-if="item.ativo" class="svt-btn danger" type="button" @click="desativar(item)">Desativar</button>
      </template>
    </ListaResponsiva>
    <p v-else-if="!carregando" class="svt-muted">Nenhum cadastro neste grupo.</p>
  </section>
</template>

<script>
import ListaResponsiva from '../components/ui/ListaResponsiva.vue';

const VAZIO = () => ({
  id: null,
  nome: '',
  eixo_id: '',
  tipo_curso: '',
  carga_horaria: '',
  curso_id: '',
  unidade_id: '',
  instrutor_id: '',
  limite: 1,
  vigencia_inicio: '',
  vigencia_fim: '',
  observacao: '',
  ativo: true,
});

export default {
  name: 'Cadastros',
  components: { ListaResponsiva },
  props: {
    secao: { type: String, default: 'dados' },
  },
  data() {
    return {
      recurso: 'unidades',
      itens: [],
      eixos: [],
      unidades: [],
      cursos: [],
      instrutores: [],
      tipos: {},
      form: VAZIO(),
      editando: false,
      carregando: false,
      erro: '',
      aviso: '',
    };
  },
  computed: {
    colunasLista() {
      return [
        { chave: 'nome', rotulo: 'Registro', papel: 'titulo' },
        { chave: 'ativo', rotulo: 'Situação', papel: 'status' },
      ];
    },
    opcoes() {
      if (this.secao === 'regras') {
        return [{ value: 'regras', label: 'Regras de visita' }];
      }
      return [
        { value: 'unidades', label: 'Unidades' },
        { value: 'eixos', label: 'Eixos' },
        { value: 'cursos', label: 'Cursos' },
        { value: 'turmas', label: 'Turmas' },
      ];
    },
  },
  watch: {
    secao() {
      this.recurso = this.secao === 'regras' ? 'regras' : 'unidades';
      this.editando = false;
      this.carregar();
    },
  },
  mounted() {
    this.recurso = this.secao === 'regras' ? 'regras' : 'unidades';
    this.carregarApoio();
    this.carregar();
  },
  methods: {
    async carregarApoio() {
      const [catalogo, eixos, unidades, cursos, usuarios] = await Promise.all([
        window.axios.get('/api/svt/catalogo'),
        window.axios.get('/api/svt/cadastros/eixos'),
        window.axios.get('/api/svt/cadastros/unidades'),
        window.axios.get('/api/svt/cadastros/cursos'),
        window.axios.get('/api/usuarios', { params: { perfil: 'Instrutor', per_page: 100 } }),
      ]);
      this.tipos = catalogo.data.tipos;
      this.eixos = eixos.data.data;
      this.unidades = unidades.data.data;
      this.cursos = cursos.data.data;
      this.instrutores = usuarios.data.data || [];
    },
    async carregar() {
      this.carregando = true;
      this.erro = '';
      try {
        const { data } = await window.axios.get(`/api/svt/cadastros/${this.recurso}`);
        this.itens = data.data;
      } catch (error) {
        this.erro = error.response?.data?.message || 'Não foi possível carregar.';
      } finally {
        this.carregando = false;
      }
    },
    novo() {
      this.form = VAZIO();
      this.editando = true;
      this.erro = '';
    },
    editar(item) {
      this.form = {
        ...VAZIO(),
        ...item,
        eixo_id: item.eixo_id || '',
        curso_id: item.curso_id || '',
        unidade_id: item.unidade_id || '',
        instrutor_id: item.instrutor_id || '',
        vigencia_inicio: String(item.vigencia_inicio || '').slice(0, 10),
        vigencia_fim: String(item.vigencia_fim || '').slice(0, 10),
      };
      this.editando = true;
    },
    async salvar() {
      this.erro = '';
      const payload = { ...this.form };
      if (payload.instrutor_id === '') payload.instrutor_id = null;
      if (payload.carga_horaria === '') payload.carga_horaria = null;
      if (payload.vigencia_fim === '') payload.vigencia_fim = null;
      try {
        if (payload.id) {
          await window.axios.put(`/api/svt/cadastros/${this.recurso}/${payload.id}`, payload);
          this.aviso = 'Cadastro atualizado.';
        } else {
          await window.axios.post(`/api/svt/cadastros/${this.recurso}`, payload);
          this.aviso = 'Cadastro salvo.';
        }
        this.editando = false;
        await this.carregar();
        await this.carregarApoio();
      } catch (error) {
        const erros = error.response?.data?.errors;
        this.erro = erros ? Object.values(erros).flat()[0] : (error.response?.data?.message || 'Não foi possível salvar.');
      }
    },
    async desativar(item) {
      this.erro = '';
      try {
        const { data } = await window.axios.post(`/api/svt/cadastros/${this.recurso}/${item.id}/desativar`);
        this.aviso = data.message;
        await this.carregar();
      } catch (error) {
        this.erro = error.response?.data?.message || 'Não foi possível desativar.';
      }
    },
    rotulo(item) {
      if (this.recurso === 'cursos') return `${item.nome} · ${item.eixo?.nome || ''}`;
      if (this.recurso === 'turmas') return `${item.nome} · ${item.curso?.nome || ''} · ${item.unidade?.nome || ''}`;
      if (this.recurso === 'regras') return `${item.tipo_curso || 'curso específico'} · até ${item.limite}`;
      return item.nome;
    },
  },
};
</script>
