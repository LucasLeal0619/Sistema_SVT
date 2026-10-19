import logoSenac from '../../images/Logo-Senac-branco.png';
import { destinoInterno, marcarSessao, prepararLogin } from './auth';
import { SVT_VERSAO } from './versao';

const STORAGE_EMAIL = 'svt_login_email';
const STORAGE_LEGADO = 'sgp_login_lembrado';

export default {
  name: 'Login',
  data() {
    return {
      logoSenac,
      versao: SVT_VERSAO,
      email: '',
      senha: '',
      lembrar: false,
      showPassword: false,
      loading: false,
      concluido: false,
      errorMessage: '',
      erroEmail: '',
      erroSenha: '',
    };
  },
  mounted() {
    prepararLogin();
    this.carregarEmailSalvo();
  },
  methods: {
    carregarEmailSalvo() {
      try {
        const legado = localStorage.getItem(STORAGE_LEGADO);
        if (legado) {
          const salvo = JSON.parse(legado);
          if (salvo?.email && !localStorage.getItem(STORAGE_EMAIL)) {
            localStorage.setItem(STORAGE_EMAIL, String(salvo.email));
          }
          localStorage.removeItem(STORAGE_LEGADO);
        }
      } catch {
        localStorage.removeItem(STORAGE_LEGADO);
      }

      const email = localStorage.getItem(STORAGE_EMAIL) || '';
      if (!email) return;

      this.email = email;
      this.lembrar = true;
    },

    guardarEmail() {
      const email = this.email.trim();
      if (this.lembrar && email) {
        localStorage.setItem(STORAGE_EMAIL, email);
        return;
      }

      localStorage.removeItem(STORAGE_EMAIL);
    },

    validar() {
      this.erroEmail = '';
      this.erroSenha = '';
      this.email = this.email.trim();

      if (!this.email) {
        this.erroEmail = 'Informe o e-mail institucional.';
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
        this.erroEmail = 'Informe um e-mail válido.';
      }

      if (!this.senha) {
        this.erroSenha = 'Informe a senha.';
      }

      return !this.erroEmail && !this.erroSenha;
    },

    async handleLogin() {
      if (this.loading) return;

      this.errorMessage = '';
      this.concluido = false;
      if (!this.validar()) return;

      this.loading = true;

      try {
        const { data } = await window.axios.post('/api/login', {
          email: this.email,
          senha: this.senha,
        });

        this.concluido = true;
        this.guardarEmail();
        marcarSessao(data.token, data.usuario);

        const pedido = typeof this.$route.query.redirect === 'string' ? this.$route.query.redirect : '';
        this.$router.replace(destinoInterno(pedido, '/app/inicio'));
      } catch (error) {
        const raw = typeof error.response?.data === 'string' ? error.response.data : '';
        if (error.response?.status === 429) {
          this.errorMessage = 'Muitas tentativas de login. Aguarde cerca de 1 minuto e tente novamente.';
        } else if (error.response?.status === 422) {
          const errors = error.response.data.errors;
          this.errorMessage = errors?.email?.[0] || errors?.senha?.[0] || error.response.data.message || 'Dados inválidos.';
        } else if (
          error.response?.status === 500
          && /No space left on device|errno=28|SQLITE_FULL/i.test(raw)
        ) {
          this.errorMessage = 'O disco do computador está cheio. Libere espaço em C: e tente entrar novamente.';
        } else if (error.response?.status === 500) {
          this.errorMessage = error.response?.data?.message
            || 'Erro interno no servidor (HTTP 500). Verifique o back-end e o espaço em disco.';
        } else if (!error.response) {
          this.errorMessage = 'Não foi possível falar com o servidor. Confira sua conexão e tente novamente.';
        } else {
          this.errorMessage = error.response?.data?.message
            || 'Não foi possível entrar. Confira o e-mail e a senha.';
        }
      } finally {
        if (!this.concluido) {
          this.loading = false;
        }
      }
    },
  },
};
