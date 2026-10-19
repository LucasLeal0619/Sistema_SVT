import { createRouter, createWebHistory } from 'vue-router';
import { PERFIS, destinoInterno, garantirSessao, getPerfil, podeAcessarMenu } from '../scripts/auth';

const appChildren = [
  { path: 'painel', name: 'painel', component: () => import('../pages/SvtPainel.vue'), meta: { menu: 'painel' } },
  { path: 'solicitacoes', name: 'solicitacoes', component: () => import('../pages/SvtSolicitacoes.vue'), meta: { menu: 'solicitacoes' } },
  { path: 'fila', name: 'fila', component: () => import('../pages/SvtSolicitacoes.vue'), props: { fila: true }, meta: { menu: 'fila' } },
  { path: 'relatorios', name: 'relatorios', component: () => import('../pages/SvtRelatorios.vue'), meta: { menu: 'relatorios' } },
  { path: 'relatorios-visita', name: 'relatoriosVisita', component: () => import('../pages/SvtRelatoriosVisita.vue'), meta: { menu: 'relatoriosVisita' } },
  { path: 'usuarios', name: 'usuarios', component: () => import('../pages/Usuarios.vue'), meta: { menu: 'usuarios' } },
  { path: 'auditoria', name: 'auditoria', component: () => import('../pages/Auditoria.vue'), meta: { menu: 'auditoria' } },
  { path: 'configuracoes/:secao?', redirect: '/app/painel' },
  { path: 'cadastros', redirect: '/app/painel' },
  { path: 'inicio', redirect: '/app/painel' },
  { path: 'visitas-tecnicas', redirect: '/app/solicitacoes' },
  { path: ':pathMatch(.*)*', redirect: '/app/painel' },
];

const routes = [
  { path: '/', redirect: '/login' },
  {
    path: '/login',
    name: 'login',
    component: () => import('../pages/Login.vue'),
  },
  {
    path: '/app',
    component: () => import('../layouts/AppLayout.vue'),
    redirect: '/app/painel',
    children: appChildren,
  },
  { path: '/:pathMatch(.*)*', redirect: '/login' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const normalizedPath = to.path.toLowerCase();

  if (to.path !== normalizedPath) {
    return { path: normalizedPath, query: to.query, hash: to.hash };
  }

  const requiresAuth = to.path.startsWith('/app');
  const sessaoOk = await garantirSessao();

  if (requiresAuth && !sessaoOk) {
    return {
      name: 'login',
      query: to.fullPath !== '/app/painel' && to.fullPath !== '/app'
        ? { redirect: to.fullPath }
        : {},
    };
  }

  if (to.name === 'login' && sessaoOk) {
    const pedido = typeof to.query.redirect === 'string' ? to.query.redirect : '';
    return destinoInterno(pedido, '/app/painel');
  }

  if (requiresAuth && sessaoOk) {
    const menuKey = to.meta?.menu;
    if (menuKey && !podeAcessarMenu(menuKey)) {
      return { path: '/app/painel' };
    }
  }
});

const TITULOS_PAGINA = {
  login: 'Login',
  painel: 'Dashboard',
  solicitacoes: 'Solicitações',
  fila: 'Pendências',
  relatorios: 'Relatórios gerenciais',
  relatoriosVisita: 'Relatórios de visita',
  usuarios: 'Usuários e acessos',
  auditoria: 'Auditoria',
};

router.afterEach((to) => {
  let titulo = TITULOS_PAGINA[to.name] || 'SVT';
  if (to.name === 'painel' && getPerfil() !== PERFIS.ADMINISTRADOR) {
    titulo = 'Início';
  }
  if (to.name === 'fila' && getPerfil() === PERFIS.NULOG) {
    titulo = 'Transporte';
  }
  document.title = `${titulo} · SVT`;
});

export default router;
