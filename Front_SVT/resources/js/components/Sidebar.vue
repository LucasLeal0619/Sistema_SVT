<template>
  <nav class="sidebar" :class="{ 'sidebar--open': aberto, 'sidebar--collapsed': recolhido }">
    <div class="sidebar-header">
      <router-link to="/app/painel" class="sidebar-logo-link" title="Ir para o painel" @click="onNavigate">
        <img :src="logoSenac" alt="Senac" class="sidebar-logo" />
        <span class="sidebar-brand-copy">
          <strong>SVT</strong>
          <span>Visitas técnicas</span>
        </span>
      </router-link>
      <button
        type="button"
        class="sidebar-toggle"
        :aria-label="recolhido ? 'Expandir menu lateral' : 'Recolher menu lateral'"
        :title="recolhido ? 'Expandir menu lateral' : 'Recolher menu lateral'"
        @click="$emit('alternar-recolhimento')"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline :points="recolhido ? '9 18 15 12 9 6' : '15 18 9 12 15 6'" />
        </svg>
      </button>
    </div>

    <div class="sidebar-nav">
      <div
        v-for="(secao, index) in menuSecoes"
        :key="secao.titulo ?? `secao-${index}`"
        class="sidebar-section"
      >
        <button
          v-if="secao.titulo"
          type="button"
          class="sidebar-section-title"
          :class="{ 'is-open': secoesAbertas[secao.titulo] }"
          :aria-expanded="secoesAbertas[secao.titulo] ? 'true' : 'false'"
          @click="alternarSecao(secao.titulo)"
        >
          <span>{{ secao.titulo }}</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="6 9 12 15 18 9" />
          </svg>
        </button>

        <div v-show="!secao.titulo || secoesAbertas[secao.titulo]" class="sidebar-section-items">
          <router-link
            v-for="item in secao.itens"
            :key="item.rota"
            class="sidebar-link"
            :to="item.path"
            :title="recolhido ? item.label : undefined"
            @click="onNavigate"
          >
            <span class="sidebar-link-icon" aria-hidden="true" v-html="icons[item.icon]" />
            <span class="sidebar-link-label">{{ item.label }}</span>
          </router-link>
        </div>
      </div>
    </div>

    <div class="sidebar-footer">
      <div v-if="usuario" class="sidebar-user-card">
        <span class="sidebar-avatar" aria-hidden="true">
          <img v-if="usuario.foto" :src="usuario.foto" :alt="usuario.nome" />
          <template v-else>{{ iniciais }}</template>
        </span>
        <div class="sidebar-user-info">
          <p class="sidebar-user-nome">{{ usuario.nome }}</p>
          <p v-if="usuario.unidade" class="sidebar-user-unidade">{{ usuario.unidade }}</p>
        </div>
      </div>

      <button type="button" class="sidebar-logout" @click="logout" title="Sair da conta">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" x2="9" y1="12" y2="12" />
        </svg>
        <span class="sidebar-logout-label">Sair</span>
      </button>
    </div>
  </nav>
</template>

<script src="../scripts/Sidebar.js"></script>
<style scoped src="../../css/Sidebar.css"></style>
