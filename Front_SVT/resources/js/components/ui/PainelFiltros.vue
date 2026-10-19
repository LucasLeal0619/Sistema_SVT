<template>
  <div class="svt-filtro-painel">
    <div class="svt-filtro-topo">
      <div v-if="$slots.busca" class="svt-filtro-busca">
        <slot name="busca" />
      </div>
      <div class="svt-filtro-comandos">
        <button
          class="svt-btn secondary svt-filtro-toggle"
          type="button"
          :aria-expanded="aberto ? 'true' : 'false'"
          @click="aberto = !aberto"
        >
          {{ aberto ? 'Ocultar filtros' : 'Filtros' }}
        </button>
        <slot name="comandos" />
      </div>
    </div>
    <div class="svt-filters svt-filters--grid" :class="{ 'is-collapsed': !aberto }">
      <slot />
    </div>
  </div>
</template>

<script>
export default {
  name: 'PainelFiltros',
  data() {
    return { aberto: false };
  },
};
</script>
