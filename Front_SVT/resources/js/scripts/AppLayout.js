import logoSenac from '../../images/Logo-Senac-branco.png';
import AcessibilidadeFlutuante from '../components/ui/AcessibilidadeFlutuante.vue';
import { PERFIS, clearSessao, getUsuario, marcarSaida, podeAcessarMenu } from './auth';

const ARQUIVO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>';

const ICONS = {
  painel: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
  inicio: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7"/><path d="M5 10v10h14V10"/></svg>',
  fila: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>',
  transporte: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h8a2 2 0 0 1 2 2v7H6V8a2 2 0 0 1 2-2Z"/><path d="M6 12h12"/><path d="M8 6V4h8v2"/><circle cx="8.5" cy="17.5" r="1.5"/><circle cx="15.5" cy="17.5" r="1.5"/></svg>',
  solicitacoes: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>',
  relatorios: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19H2"/></svg>',
  usuarios: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  cadastros: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h10"/></svg>',
  auditoria: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4.2 2.8 7.4 7 9 4.2-1.6 7-4.8 7-9V6Z"/><path d="m9 12 2 2 4-4"/></svg>',
  arquivo: ARQUIVO,
};

export default {
  name: 'AppLayout',
  components: { AcessibilidadeFlutuante },
  data() {
    return {
      logoSenac,
      icons: ICONS,
    };
  },
  computed: {
    usuario() {
      return getUsuario();
    },
    papel() {
      return PAPEL[this.usuario?.perfil] || {
        titulo: this.usuario?.perfil || 'Acesso',
        frase: '',
      };
    },
    primeiroNome() {
      return (this.usuario?.nome || 'SVT').split(' ')[0];
    },
    iniciais() {
      const partes = (this.usuario?.nome || '').trim().split(/\s+/).filter(Boolean);
      if (partes.length === 0) return '?';
      if (partes.length === 1) return partes[0].slice(0, 2).toUpperCase();
      return `${partes[0][0]}${partes[partes.length - 1][0]}`.toUpperCase();
    },
    ehAdmin() {
      return this.usuario?.perfil === PERFIS.ADMINISTRADOR;
    },
    nomeTopo() {
      const nome = this.usuario?.nome || '';
      if (this.usuario?.perfil === PERFIS.NULOG && nome.startsWith('Núcleo')) {
        return 'Núcleo de Logística';
      }
      return this.primeiroNome;
    },
    itens() {
      return this.montarMenu().filter((item) => item.filhos?.length || item.path);
    },
    dock() {
      return this.itens.map((item) => {
        if (!item.filhos?.length) {
          return { ...item, curto: item.curto || item.label };
        }
        return {
          rota: item.rota,
          label: item.label,
          curto: item.curto || item.label,
          icon: item.icon,
          path: item.filhos[0].path,
          rotas: item.filhos.map((filho) => filho.rota),
        };
      });
    },
  },
  methods: {
    montarMenu() {
      const perfil = this.usuario?.perfil;
      const item = (rota, label, path, icon, curto) => (
        podeAcessarMenu(rota) ? { rota, label, path, icon, curto } : null
      );
      const visitas = perfil === PERFIS.ADMINISTRADOR
        ? ['Visitas Técnicas', 'Visitas']
        : ({
          [PERFIS.INSTRUTOR]: ['Minhas Visitas', 'Visitas'],
          [PERFIS.NUCLEO]: ['Visitas da Unidade', 'Visitas'],
          [PERFIS.CPED]: ['Visitas do Eixo', 'Visitas'],
          [PERFIS.NULOG]: ['Visitas Autorizadas', 'Visitas'],
        }[perfil] || ['Todas as Visitas', 'Visitas']);
      const documentos = item('relatoriosVisita', 'Relatórios de Visita', '/app/relatorios-visita', 'arquivo', 'Relatórios');
      const gerencial = item('relatorios', 'Relatórios Gerenciais', '/app/relatorios', 'relatorios', this.ehAdmin ? 'Relat.' : 'Relatórios');
      const filhos = [documentos, gerencial].filter(Boolean);
      const relatorios = filhos.length > 1
        ? { rota: 'relatorios', label: 'Relatórios', icon: 'relatorios', curto: 'Relatórios', filhos }
        : (filhos[0] || null);
      return [
        item('painel', this.ehAdmin ? 'Dashboard' : 'Início', '/app/painel', this.ehAdmin ? 'painel' : 'inicio', this.ehAdmin ? 'Painel' : 'Início'),
        item('fila', perfil === PERFIS.NULOG ? 'Transporte' : 'Pendências', '/app/fila', perfil === PERFIS.NULOG ? 'transporte' : 'fila', perfil === PERFIS.NULOG ? 'Transporte' : 'Pendências'),
        item('solicitacoes', visitas[0], '/app/solicitacoes', 'solicitacoes', visitas[1]),
        relatorios,
        item('usuarios', 'Usuários e Acessos', '/app/usuarios', 'usuarios', 'Acessos'),
        item('auditoria', 'Auditoria', '/app/auditoria', 'auditoria', 'Audit.'),
      ].filter(Boolean);
    },
    rotaAtiva(item) {
      const nome = this.$route.name;
      if (item.rotas?.length) return item.rotas.includes(nome);
      if (item.filhos?.length) return item.filhos.some((filho) => filho.rota === nome);
      return nome === item.rota;
    },
    async logout() {
      marcarSaida();
      try {
        await window.axios.post('/api/logout', null, { skipAuthRedirect: true });
      } catch {
        // A sessão local sai mesmo se o token já expirou.
      }

      clearSessao();
      this.$router.replace('/login');
    },
  },
};
