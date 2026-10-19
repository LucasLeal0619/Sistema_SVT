export async function hidratarUnidadesSelect(vm, props = ['unidades']) {
  const { data } = await window.axios.get('/api/svt/catalogo');
  const nomes = Array.isArray(data.unidades) ? data.unidades : [];
  const alvos = Array.isArray(props) ? props : [props];

  alvos.forEach((prop) => {
    if (prop in vm) {
      vm[prop] = nomes;
    }
  });

  return nomes;
}
