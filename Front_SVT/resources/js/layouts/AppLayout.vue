<template>
  <div class="svt-shell">
    <a class="sgp-skip-link" href="#conteudo-principal">Ir para o conteúdo</a>

    <aside class="svt-rail">
      <router-link to="/app/painel" class="svt-brand" :title="ehAdmin ? 'Dashboard' : 'Início'">
        <img :src="logoSenac" alt="Senac" />
      </router-link>

      <nav class="svt-nav" aria-label="Seções">
        <template v-for="item in itens" :key="item.rota + item.label">
          <div v-if="item.filhos" class="svt-nav-group">
            <p class="svt-nav-group-label">
              <span class="svt-nav-icon" aria-hidden="true" v-html="icons[item.icon]" />
              {{ item.label }}
            </p>
            <router-link v-for="filho in item.filhos" :key="filho.rota" :to="filho.path">
              {{ filho.label }}
            </router-link>
          </div>
          <router-link v-else :to="item.path">
            <span class="svt-nav-icon" aria-hidden="true" v-html="icons[item.icon || item.rota]" />
            {{ item.label }}
          </router-link>
        </template>
      </nav>

      <div class="svt-rail-foot">
        <div v-if="usuario" class="svt-who">
          <span class="svt-avatar" aria-hidden="true">
            <img v-if="usuario.foto" :src="usuario.foto" alt="" />
            <template v-else>{{ iniciais }}</template>
          </span>
          <p class="svt-who-name">{{ usuario.nome }}</p>
        </div>
        <button type="button" class="svt-sair" @click="logout">Sair</button>
      </div>
    </aside>

    <div class="svt-top">
      <img :src="logoSenac" alt="Senac" class="svt-top-logo" />
      <div v-if="usuario" class="svt-top-user">
        <span class="svt-avatar" aria-hidden="true">
          <img v-if="usuario.foto" :src="usuario.foto" alt="" />
          <template v-else>{{ iniciais }}</template>
        </span>
        <span class="svt-top-id">
          <strong>{{ nomeTopo }}</strong>
          <span class="svt-top-perfil">{{ usuario.perfil }}</span>
        </span>
      </div>
      <button type="button" class="svt-sair" @click="logout">Sair</button>
    </div>

    <main id="conteudo-principal" class="svt-main" tabindex="-1">
      <router-view />
    </main>

    <nav class="svt-dock" aria-label="Seções">
      <router-link
        v-for="item in dock"
        :key="`m-${item.rota}`"
        :to="item.path"
        :class="{ 'is-active': rotaAtiva(item) }"
      >
        <span class="svt-nav-icon" aria-hidden="true" v-html="icons[item.icon || item.rota]" />
        {{ item.curto || item.label }}
      </router-link>
    </nav>

    <AcessibilidadeFlutuante />
  </div>
</template>

<script src="../scripts/AppLayout.js"></script>
