import ListaResponsiva from '../components/ui/ListaResponsiva.vue';
import PainelFiltros from '../components/ui/PainelFiltros.vue';
import { AREAS_CPED, PAPEL, atualizarUsuarioSessao, getUsuario, podeGerenciarUsuarios } from './auth';
import { hidratarUnidadesSelect } from './unidadesApi';
import { mixinHistoricoFormulario } from './formularioHistorico';
import {
  combinarValidacoes,
  extrairErroApi,
  mascaraCpf,
  mascaraTelefone,
  tamanhoMaximo,
  textoObrigatorio,
  validarCpf,
  validarEmail,
  validarSenha,
  somenteNumeros,
} from '../utils/validacao';

export default {
  name: 'Usuarios',
  components: { ListaResponsiva, PainelFiltros },
  mixins: [mixinHistoricoFormulario],
  data() {
    return {
      modo: 'lista',
      alternandoId: null,
      usuarios: [],
      meta: { total: 0, per_page: 10, current_page: 1, last_page: 1, from: 0, to: 0 },
      areasCped: AREAS_CPED,
      papeis: [
        { nome: 'Instrutor', resumo: 'Abre o pedido da turma.' },
        { nome: 'Núcleo Pedagógico', resumo: 'Só vê pedidos da unidade cadastrada.' },
        { nome: 'Coordenação CPED', resumo: 'A área tem que ser a coordenação do eixo.' },
        { nome: 'Direção Pedagógica', resumo: 'Última análise antes do transporte.' },
        { nome: 'NULOG', resumo: 'Registra veículo, placa, data e motorista.' },
        { nome: 'Administrador', resumo: 'Não move visita. Só cria estes acessos.' },
      ],
      carregando: false,
      salvando: false,
      editandoId: null,
      usuarioDetalhe: null,
      mensagemSucesso: '',
      mensagemErro: '',
      erroFormulario: '',
      filtros: {
        busca: '',
        perfil: '',
        unidade: '',
        status: '',
      },
      form: this.formVazio(),
      buscaTimeout: null,
      unidades: [],
    };
  },
  computed: {
    colunasLista() {
      return [
        { chave: 'nome', rotulo: 'Nome', papel: 'titulo' },
        { chave: 'status', rotulo: 'Status', papel: 'status' },
        { chave: 'email', rotulo: 'E-mail', papel: 'destaque' },
        { chave: 'perfil', rotulo: 'Função', papel: 'destaque' },
        { chave: 'unidade', rotulo: 'Unidade', papel: 'destaque' },
        { chave: 'area', rotulo: 'Área', papel: 'destaque' },
      ];
    },
    podeEditar() {
      return podeGerenciarUsuarios();
    },
    temFiltro() {
      return Object.values(this.filtros).some((valor) => valor !== '' && valor != null);
    },
    perfilFora() {
      return Boolean(this.form.perfil) && !this.papeis.some((papel) => papel.nome === this.form.perfil);
    },
    papelEscolhido() {
      return PAPEL[this.form.perfil] || null;
    },
    ehCped() {
      return this.form.perfil === 'Coordenação CPED';
    },
  },
  mounted() {
    this.carregarUsuarios();
    hidratarUnidadesSelect(this, ['unidades'], { forcar: true });
  },
  beforeUnmount() {
    this.revogarPreviewFoto();
  },
  methods: {
    limparFiltros() {
      this.filtros = { busca: '', perfil: '', unidade: '', status: '' };
      this.meta.current_page = 1;
      this.carregarUsuarios();
    },

    formVazio() {
      return {
        nome: '',
        email: '',
        senha: '',
        confirmarSenha: '',
        perfil: '',
        status: true,
        unidade: '',
        area: '',
        telefone: '',
        cpf: '',
        foto: '',
        fotoArquivo: null,
        removerFoto: false,
      };
    },

    iniciais(nome) {
      if (!nome) {
        return '?';
      }

      const partes = nome.trim().split(/\s+/);

      if (partes.length === 1) {
        return partes[0].slice(0, 2).toUpperCase();
      }

      return `${partes[0][0]}${partes[partes.length - 1][0]}`.toUpperCase();
    },

    avatarClass(perfil) {
      return {
        'avatar-admin': perfil === 'Administrador',
        'avatar-editor': perfil === 'Editor',
        'avatar-consultor': perfil === 'Consultor',
      };
    },

    badgePerfil(perfil) {
      return {
        'badge-admin': perfil === 'Administrador',
        'badge-editor': perfil === 'Editor',
        'badge-consultor': perfil === 'Consultor',
      };
    },

    async carregarUsuarios() {
      clearTimeout(this.buscaTimeout);

      this.buscaTimeout = setTimeout(async () => {
        this.carregando = true;
        this.mensagemErro = '';

        try {
          const params = { page: this.meta.current_page || 1, per_page: this.meta.per_page || 10 };

          if (this.filtros.busca) {
            params.busca = this.filtros.busca;
          }

          if (this.filtros.perfil) {
            params.perfil = this.filtros.perfil;
          }

          if (this.filtros.unidade) {
            params.unidade = this.filtros.unidade;
          }

          if (this.filtros.status !== '') {
            params.status = this.filtros.status;
          }

          const { data } = await window.axios.get('/api/usuarios', { params });
          this.usuarios = data.data ?? [];
          this.meta = { ...this.meta, ...(data.meta ?? {}) };
        } catch (error) {
          this.mensagemErro = this.extrairErro(error, 'Não foi possível carregar os usuários.');
        } finally {
          this.carregando = false;
        }
      }, 200);
    },

    aplicarFiltros() {
      clearTimeout(this.buscaTimeout);
      this.meta.current_page = 1;
      this.buscaTimeout = setTimeout(() => this.carregarUsuarios(), 200);
    },

    irParaPagina(page) {
      const pagina = Number(page);
      if (!Number.isInteger(pagina) || pagina < 1 || pagina > (this.meta.last_page || 1) || this.carregando) return;
      this.meta.current_page = pagina;
      this.carregarUsuarios();
    },

    alterarRegistrosPorPagina(perPage) {
      const quantidade = Number(perPage);
      if (!Number.isInteger(quantidade) || quantidade < 1 || this.carregando) return;
      this.meta.per_page = quantidade;
      this.meta.current_page = 1;
      this.carregarUsuarios();
    },

    abrirDetalhes(usuario) {
      this.usuarioDetalhe = { ...usuario };
    },

    fecharDetalhes() {
      this.usuarioDetalhe = null;
    },

    editarDoDetalhe() {
      const usuario = this.usuarioDetalhe;
      this.fecharDetalhes();
      this.abrirEdicao(usuario);
    },

    ativosDo(perfil) {
      return this.usuarios.filter((usuario) => usuario.perfil === perfil && usuario.status).length;
    },

    escolherPapel(nome) {
      this.form.perfil = nome;
      if (nome === 'Coordenação CPED' && !this.areasCped.includes(this.form.area)) {
        this.form.area = '';
      }
    },

    abrirNovo() {
      if (!this.podeEditar) {
        this.mensagemErro = 'Você não tem permissão para cadastrar usuários.';
        return;
      }

      this.aplicarEstadoNovoLocal();
      this.empilharHistoricoFormulario('novo');
    },

    aplicarEstadoNovoLocal() {
      this.revogarPreviewFoto();
      this.modo = 'novo';
      this.editandoId = null;
      this.usuarioDetalhe = null;
      this.form = this.formVazio();
      this.erroFormulario = '';
      this.mensagemErro = '';
    },

    abrirEdicao(usuario) {
      if (!this.podeEditar) {
        this.mensagemErro = 'Você não tem permissão para editar usuários.';
        return;
      }

      this.aplicarEstadoEdicaoLocal(usuario);
      this.empilharHistoricoFormulario('editar', usuario.id);
    },

    aplicarEstadoEdicaoLocal(usuario) {
      this.revogarPreviewFoto();
      this.modo = 'editar';
      this.editandoId = usuario.id;
      this.form = {
        nome: usuario.nome ?? '',
        email: usuario.email ?? '',
        senha: '',
        confirmarSenha: '',
        perfil: usuario.perfil ?? '',
        status: Boolean(usuario.status),
        unidade: usuario.unidade ?? '',
        area: usuario.area ?? '',
        telefone: usuario.telefone ?? '',
        cpf: usuario.cpf ?? '',
        foto: usuario.foto || '',
        fotoArquivo: null,
        removerFoto: false,
      };
      this.erroFormulario = '';
      this.mensagemErro = '';
    },

    async aplicarEstadoEdicaoPorId(id) {
      let usuario = this.usuarios.find((item) => String(item.id) === String(id));

      if (!usuario) {
        try {
          const { data } = await window.axios.get(`/api/usuarios/${id}`);
          usuario = data.usuario || data.data || null;
        } catch {
          usuario = null;
        }
      }

      if (!usuario) {
        this.aplicarEstadoListaLocal();
        this.limparHistoricoFormulario();
        return;
      }

      this.aplicarEstadoEdicaoLocal(usuario);
    },

    voltarLista() {
      this.aplicarEstadoListaLocal();
      this.limparHistoricoFormulario();
    },

    aplicarEstadoListaLocal() {
      this.revogarPreviewFoto();
      this.modo = 'lista';
      this.editandoId = null;
      this.erroFormulario = '';
      this.form = this.formVazio();
    },

    revogarPreviewFoto() {
      if (this.form?.foto && String(this.form.foto).startsWith('blob:')) {
        URL.revokeObjectURL(this.form.foto);
      }
    },

    onFotoSelecionada(evento) {
      const arquivo = evento.target.files?.[0];
      evento.target.value = '';

      if (!arquivo) {
        return;
      }

      if (!String(arquivo.type || '').startsWith('image/')) {
        this.erroFormulario = 'A foto deve ser uma imagem válida.';
        return;
      }

      if (arquivo.size > 2 * 1024 * 1024) {
        this.erroFormulario = 'A foto deve ter no máximo 2 MB.';
        return;
      }

      this.revogarPreviewFoto();
      this.form.fotoArquivo = arquivo;
      this.form.removerFoto = false;
      this.form.foto = URL.createObjectURL(arquivo);
      this.erroFormulario = '';
    },

    limparFoto() {
      this.revogarPreviewFoto();
      this.form.foto = '';
      this.form.fotoArquivo = null;
      this.form.removerFoto = true;
    },

    formatarCpf(evento) {
      this.form.cpf = mascaraCpf(evento.target.value);
    },

    formatarTelefone(evento) {
      this.form.telefone = mascaraTelefone(evento.target.value);
    },

    validarFormulario() {
      return combinarValidacoes(
        textoObrigatorio(this.form.perfil, 'O perfil é obrigatório.'),
        textoObrigatorio(this.form.nome, 'O nome é obrigatório.'),
        tamanhoMaximo(this.form.nome, 100, 'O nome deve ter no máximo 100 caracteres.'),
        validarEmail(this.form.email, { obrigatorio: true }),
        tamanhoMaximo(this.form.email, 100, 'O e-mail deve ter no máximo 100 caracteres.'),
        textoObrigatorio(this.form.telefone, 'Informe o telefone.'),
        validarCpf(this.form.cpf, { obrigatorio: true }),
        textoObrigatorio(this.form.unidade, 'Informe a estrutura institucional.'),
        textoObrigatorio(this.form.area, 'Informe a área de atuação.'),
        tamanhoMaximo(this.form.area, 100, 'A área deve ter no máximo 100 caracteres.'),
        textoObrigatorio(this.form.status, 'Informe se o usuário está ativo.'),
        this.modo === 'novo'
          ? validarSenha(this.form.senha, { obrigatorio: true })
          : validarSenha(this.form.senha),
        this.form.senha !== this.form.confirmarSenha ? 'As senhas não coincidem.' : '',
      );
    },

    montarFormData() {
      const formData = new FormData();
      formData.append('nome', this.form.nome.trim());
      formData.append('email', this.form.email.trim());
      formData.append('perfil', this.form.perfil);
      formData.append('status', this.form.status ? '1' : '0');
      formData.append('unidade', this.form.unidade || '');
      formData.append('area', this.form.area?.trim() || '');
      formData.append('cpf', this.form.cpf ? somenteNumeros(this.form.cpf) : '');
      formData.append('telefone', this.form.telefone ? somenteNumeros(this.form.telefone) : '');

      if (this.form.senha) {
        formData.append('senha', this.form.senha);
      }

      if (this.form.fotoArquivo) {
        formData.append('foto', this.form.fotoArquivo);
      }

      if (this.form.removerFoto && !this.form.fotoArquivo) {
        formData.append('remover_foto', '1');
      }

      return formData;
    },

    async salvarUsuario() {
      const erroValidacao = this.validarFormulario();

      if (erroValidacao) {
        this.erroFormulario = erroValidacao;
        return;
      }

      if (!this.podeEditar) {
        this.erroFormulario = 'Você não tem permissão para salvar usuários.';
        return;
      }

      this.salvando = true;
      this.erroFormulario = '';
      this.mensagemSucesso = '';

      try {
        const formData = this.montarFormData();
        let usuarioSalvo = null;

        if (this.editandoId) {
          formData.append('_method', 'PUT');
          const { data } = await window.axios.post(`/api/usuarios/${this.editandoId}`, formData);
          this.mensagemSucesso = data.message;
          usuarioSalvo = data.usuario;
        } else {
          const { data } = await window.axios.post('/api/usuarios', formData);
          this.mensagemSucesso = data.message;
          usuarioSalvo = data.usuario;
        }

        const logado = getUsuario();
        if (usuarioSalvo && logado && String(logado.id) === String(usuarioSalvo.id)) {
          atualizarUsuarioSessao({
            nome: usuarioSalvo.nome,
            email: usuarioSalvo.email,
            perfil: usuarioSalvo.perfil,
            unidade: usuarioSalvo.unidade ?? null,
            foto: usuarioSalvo.foto ?? null,
          });
        }

        this.voltarLista();
        await this.carregarUsuarios();
      } catch (error) {
        this.erroFormulario = this.extrairErro(error, 'Não foi possível salvar o usuário.');
      } finally {
        this.salvando = false;
      }
    },

    async inativarUsuario(usuario) {
      if (!this.podeEditar) {
        this.mensagemErro = 'Você não tem permissão para inativar usuários.';
        return;
      }

      const ativar = !usuario.status;
      const confirmar = window.confirm(
        ativar
          ? `Ativar o acesso de "${usuario.nome}"?`
          : `Inativar o acesso de "${usuario.nome}"? A pessoa deixa de entrar até alguém ativar de novo.`
      );

      if (!confirmar) {
        return;
      }

      this.alternandoId = usuario.id;
      this.mensagemErro = '';
      this.mensagemSucesso = '';

      try {
        await window.axios.put(`/api/usuarios/${usuario.id}`, {
          nome: usuario.nome,
          email: usuario.email,
          cpf: somenteNumeros(usuario.cpf || ''),
          perfil: usuario.perfil,
          status: ativar,
          unidade: usuario.unidade,
          area: usuario.area,
          telefone: somenteNumeros(usuario.telefone || ''),
        });
        this.mensagemSucesso = ativar ? 'Acesso ativado.' : 'Acesso inativado.';
        await this.carregarUsuarios();
      } catch (error) {
        this.mensagemErro = this.extrairErro(error, ativar ? 'Não foi possível ativar o usuário.' : 'Não foi possível inativar o usuário.');
      } finally {
        this.alternandoId = null;
      }
    },

    async excluirUsuario(usuario) {
      if (!this.podeEditar) {
        this.mensagemErro = 'Você não tem permissão para excluir usuários.';
        return;
      }

      const confirmar = window.confirm(
        `Excluir o acesso de "${usuario.nome}"? Esta ação não pode ser desfeita.`
      );

      if (!confirmar) {
        return;
      }

      this.mensagemErro = '';
      this.mensagemSucesso = '';

      try {
        const { data } = await window.axios.delete(`/api/usuarios/${usuario.id}`);
        this.mensagemSucesso = data.message;
        this.voltarLista();
        await this.carregarUsuarios();
      } catch (error) {
        this.mensagemErro = this.extrairErro(error, 'Não foi possível excluir o usuário.');
      }
    },

    extrairErro(error, fallback) {
      return extrairErroApi(error, fallback);
    },
  },
};
