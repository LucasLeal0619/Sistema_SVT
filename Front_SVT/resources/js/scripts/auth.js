/**
 * Perfis do SVT (espelha config/permissoes.php).
 *
 * Cinco funções movem a visita.
 * O administrador só cuida de quem entra, com unidade e área certas.
 */

export const PERFIS = {
  ADMINISTRADOR: 'Administrador',
  EDITOR: 'Editor',
  CONSULTOR: 'Consultor',
  INSTRUTOR: 'Instrutor',
  NUCLEO: 'Núcleo Pedagógico',
  CPED: 'Coordenação CPED',
  DIRECAO: 'Direção Pedagógica',
  NULOG: 'NULOG',
};

export const AREAS_CPED = [
  'Coordenação de Gastronomia e Turismo',
  'Coordenação de Ambiente e Saúde',
  'Coordenação de Gestão',
  'Coordenação de Tecnologia',
  'Coordenação de Beleza',
];

export const PAPEL = {
  [PERFIS.ADMINISTRADOR]: {
    titulo: 'Administrador',
    frase: 'Você não pede visita, não aprova e não marca transporte. Você cria o acesso das cinco funções e corrige unidade e área quando o pedido não chega em ninguém.',
    agora: 'Gerenciar usuários',
    para: '/app/usuarios',
  },
  [PERFIS.INSTRUTOR]: {
    titulo: 'Instrutor',
    frase: 'Você abre o pedido da turma: para onde vão, em que dia e o que vão fazer lá.',
    agora: 'Pedir uma visita',
    para: '/app/solicitacoes',
  },
  [PERFIS.NUCLEO]: {
    titulo: 'Núcleo pedagógico',
    frase: 'Você é o primeiro a olhar o pedido, só da sua unidade. Aprova para a coordenação ou recusa com um motivo.',
    agora: 'Ver pendências',
    para: '/app/fila',
  },
  [PERFIS.CPED]: {
    titulo: 'Coordenação',
    frase: 'Você vê visitas e relatórios só do seu eixo. Na sua vez, aprova para a direção ou recusa com um motivo.',
    agora: 'Ver pendências',
    para: '/app/fila',
  },
  [PERFIS.DIRECAO]: {
    titulo: 'Direção pedagógica',
    frase: 'Você vê as visitas de todos os eixos. É a última análise pedagógica: se aprovar, o pedido segue para o NULOG marcar o transporte.',
    agora: 'Ver pendências',
    para: '/app/fila',
  },
  [PERFIS.NULOG]: {
    titulo: 'NULOG',
    frase: 'A visita já foi liberada. Você registra veículo, placa, data, horário e motorista.',
    agora: 'Registrar transporte',
    para: '/app/fila',
  },
};

const OPERACAO = [
  PERFIS.ADMINISTRADOR,
  PERFIS.EDITOR,
  PERFIS.CONSULTOR,
  PERFIS.INSTRUTOR,
  PERFIS.NUCLEO,
  PERFIS.CPED,
  PERFIS.DIRECAO,
  PERFIS.NULOG,
];
const FILA = [PERFIS.NUCLEO, PERFIS.CPED, PERFIS.DIRECAO, PERFIS.NULOG];
const DOCUMENTOS = [PERFIS.EDITOR, PERFIS.CONSULTOR, PERFIS.INSTRUTOR, PERFIS.NUCLEO, PERFIS.CPED, PERFIS.DIRECAO];
const GERENCIAL = [PERFIS.ADMINISTRADOR, PERFIS.EDITOR, PERFIS.CONSULTOR, PERFIS.NUCLEO, PERFIS.CPED, PERFIS.DIRECAO];

export const MENU_POR_PERFIL = {
  painel: OPERACAO,
  solicitacoes: OPERACAO,
  fila: FILA,
  relatoriosVisita: DOCUMENTOS,
  relatorios: GERENCIAL,
  usuarios: [PERFIS.ADMINISTRADOR],
  auditoria: [PERFIS.ADMINISTRADOR],
};

/** null = ainda não validou; true/false = resultado da última checagem */
let sessaoValida = null;
let validacaoEmAndamento = null;
let saindo = false;

export function marcarSaida() {
  saindo = true;
}

export function prepararLogin() {
  saindo = false;
}

export function sessaoEmSaida() {
  return saindo;
}

export function getUsuario() {
  const raw = localStorage.getItem('sgp_usuario');

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function getPerfil() {
  return getUsuario()?.perfil ?? null;
}

export function destinoInterno(valor, fallback = '/app/painel') {
  if (typeof valor !== 'string') return fallback;
  const texto = valor.trim();
  const interno = texto === '/app' || texto.startsWith('/app/');
  const perigoso = texto.startsWith('//')
    || texto.includes('\\')
    || texto.includes('://')
    || texto.includes('..');
  if (!interno || perigoso) return fallback;
  return texto;
}

export function clearSessao() {
  localStorage.removeItem('sgp_token');
  localStorage.removeItem('sgp_usuario');
  delete window.axios?.defaults?.headers?.common?.Authorization;
  sessaoValida = false;
  validacaoEmAndamento = null;
}

export function marcarSessao(token, usuario) {
  saindo = false;
  localStorage.setItem('sgp_token', token);
  localStorage.setItem('sgp_usuario', JSON.stringify({
    id: usuario.id,
    nome: usuario.nome,
    email: usuario.email,
    perfil: usuario.perfil,
    unidade: usuario.unidade ?? null,
    area: usuario.area ?? null,
    foto: usuario.foto ?? null,
  }));
  window.axios.defaults.headers.common.Authorization = `Bearer ${token}`;
  sessaoValida = true;
  validacaoEmAndamento = null;
}

/** Atualiza campos do usuário logado no localStorage (ex.: foto). */
export function atualizarUsuarioSessao(parcial) {
  const atual = getUsuario();
  if (!atual) {
    return;
  }

  localStorage.setItem('sgp_usuario', JSON.stringify({
    ...atual,
    ...parcial,
  }));
}

/**
 * Confirma se o token local ainda é válido no backend.
 * Evita abrir /app com token expirado e só depois cair no login.
 */
export async function garantirSessao() {
  const token = localStorage.getItem('sgp_token');

  if (!token) {
    sessaoValida = false;
    return false;
  }

  if (sessaoValida === true) {
    return true;
  }

  if (validacaoEmAndamento) {
    return validacaoEmAndamento;
  }

  window.axios.defaults.headers.common.Authorization = `Bearer ${token}`;

  validacaoEmAndamento = window.axios
    .get('/api/user', { skipAuthRedirect: true })
    .then(({ data }) => {
      const usuario = data.usuario ?? data;

      if (!usuario?.perfil) {
        clearSessao();
        return false;
      }

      localStorage.setItem('sgp_usuario', JSON.stringify({
        id: usuario.id,
        nome: usuario.nome,
        email: usuario.email,
        perfil: usuario.perfil,
        unidade: usuario.unidade ?? null,
        area: usuario.area ?? null,
        foto: usuario.foto ?? null,
      }));
      sessaoValida = true;
      return true;
    })
    .catch(() => {
      clearSessao();
      return false;
    })
    .finally(() => {
      validacaoEmAndamento = null;
    });

  return validacaoEmAndamento;
}

export function isAdministrador() {
  return getPerfil() === PERFIS.ADMINISTRADOR;
}

export function isEditor() {
  return getPerfil() === PERFIS.EDITOR;
}

export function isConsultor() {
  return getPerfil() === PERFIS.CONSULTOR;
}

export function podeAcessarMenu(rota) {
  const perfil = getPerfil();
  const permitidos = MENU_POR_PERFIL[rota] ?? [];

  return perfil && permitidos.includes(perfil);
}

export function abasRelatorio() {
  return [
    podeAcessarMenu('relatoriosVisita')
      ? { to: '/app/relatorios-visita', label: 'Relatórios de Visita' }
      : null,
    podeAcessarMenu('relatorios')
      ? { to: '/app/relatorios', label: 'Relatórios Gerenciais' }
      : null,
  ].filter(Boolean);
}

export function podeGerenciarUsuarios() {
  return isAdministrador();
}

export function podeEditarDados() {
  const perfil = getPerfil();

  return perfil === PERFIS.ADMINISTRADOR || perfil === PERFIS.EDITOR;
}

export function podeConsultarDados() {
  return Boolean(getPerfil());
}

export function podeImportarDados() {
  const perfil = getPerfil();

  return perfil === PERFIS.ADMINISTRADOR || perfil === PERFIS.EDITOR;
}

export function podeConsultarAuditoria() {
  return isAdministrador();
}
