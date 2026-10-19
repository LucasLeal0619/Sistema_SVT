<template>
  <div class="svt-lista">
    <div class="svt-table-wrap svt-lista-tabela">
      <table class="svt-data">
        <thead>
          <tr>
            <th v-for="coluna in colunas" :key="coluna.chave" scope="col">{{ coluna.rotulo }}</th>
            <th v-if="temAcao" scope="col">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in itens" :key="item[chave]" :class="{ 'is-clickable': clicavel }" @click="aoClicarLinha(item)">
            <td v-for="coluna in colunas" :key="coluna.chave">
              <slot :name="'celula-' + coluna.chave" :item="item">{{ texto(item, coluna) }}</slot>
            </td>
            <td v-if="temAcao" @click.stop>
              <slot name="acao" :item="item" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="svt-lista-cards">
      <article v-for="item in itens" :key="'card-' + item[chave]" class="svt-lista-card">
        <header>
          <strong>
            <slot :name="'celula-' + titulo.chave" :item="item">{{ texto(item, titulo) }}</slot>
          </strong>
          <div v-if="status" class="svt-lista-status">
            <slot :name="'celula-' + status.chave" :item="item">{{ texto(item, status) }}</slot>
          </div>
        </header>
        <p v-for="coluna in destaque" :key="coluna.chave">
          <span>{{ coluna.rotulo }}</span>
          <slot :name="'celula-' + coluna.chave" :item="item">{{ texto(item, coluna) }}</slot>
        </p>
        <div v-if="abertos[item[chave]]" class="svt-lista-extra">
          <p v-for="coluna in detalhes" :key="coluna.chave">
            <span>{{ coluna.rotulo }}</span>
            <slot :name="'celula-' + coluna.chave" :item="item">{{ texto(item, coluna) }}</slot>
          </p>
        </div>
        <div class="svt-lista-card-acoes">
          <button
            v-if="detalhes.length"
            class="svt-btn secondary"
            type="button"
            :aria-expanded="abertos[item[chave]] ? 'true' : 'false'"
            @click="alternar(item)"
          >
            {{ abertos[item[chave]] ? 'Ocultar detalhes' : 'Ver detalhes' }}
          </button>
          <button v-if="clicavel" class="svt-btn" type="button" @click="$emit('abrir', item)">{{ rotuloAbrir }}</button>
          <slot name="acao" :item="item" />
        </div>
      </article>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ListaResponsiva',
  props: {
    itens: { type: Array, required: true },
    colunas: { type: Array, required: true },
    chave: { type: String, default: 'id' },
    clicavel: { type: Boolean, default: false },
    rotuloAbrir: { type: String, default: 'Abrir' },
  },
  emits: ['abrir'],
  data() {
    return { abertos: {} };
  },
  computed: {
    temAcao() {
      return Boolean(this.$slots.acao);
    },
    titulo() {
      return this.colunas.find((coluna) => coluna.papel === 'titulo') || this.colunas[0];
    },
    status() {
      return this.colunas.find((coluna) => coluna.papel === 'status') || null;
    },
    destaque() {
      return this.colunas.filter((coluna) => coluna.papel === 'destaque');
    },
    detalhes() {
      return this.colunas.filter((coluna) => coluna.papel === 'detalhe');
    },
  },
  methods: {
    texto(item, coluna) {
      if (!coluna) return '—';
      const valor = item?.[coluna.chave];
      return valor === null || valor === undefined || valor === '' ? '—' : valor;
    },
    aoClicarLinha(item) {
      if (this.clicavel) this.$emit('abrir', item);
    },
    alternar(item) {
      const id = item[this.chave];
      this.abertos = { ...this.abertos, [id]: !this.abertos[id] };
    },
  },
};
</script>
