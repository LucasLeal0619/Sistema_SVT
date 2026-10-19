import axios from 'axios';
import { clearSessao, destinoInterno, sessaoEmSaida } from './scripts/auth';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common.Accept = 'application/json';

const token = localStorage.getItem('sgp_token');

if (token) {
  window.axios.defaults.headers.common.Authorization = `Bearer ${token}`;
}

window.axios.interceptors.request.use((config) => {
  const url = String(config.url || '');
  const entrando = url.includes('/login');
  if (sessaoEmSaida() && !url.includes('/logout') && !entrando) {
    return Promise.reject(new axios.CanceledError('sessao encerrada'));
  }

  return config;
});

window.axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearSessao();

      const skipRedirect = error.config?.skipAuthRedirect;
      const jaNoLogin = window.location.pathname.toLowerCase().startsWith('/login');

      if (!skipRedirect && !jaNoLogin) {
        const destino = destinoInterno(`${window.location.pathname}${window.location.search}${window.location.hash}`, '');
        window.location.replace(destino ? `/login?redirect=${encodeURIComponent(destino)}` : '/login');
      }
    }

    return Promise.reject(error);
  }
);
