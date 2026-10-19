<template>
  <div class="login-page svt-login">
    <section class="svt-login-brand" aria-label="Identidade do Sistema de Visitas Técnicas">
      <span
        class="svt-login-logo"
        role="img"
        aria-label="Senac"
        :style="{
          maskImage: `url(${logoSenac})`,
          webkitMaskImage: `url(${logoSenac})`,
        }"
      ></span>

      <div class="svt-login-brand-copy">
        <p class="svt-login-name">SVT</p>
        <p class="svt-login-system">Sistema de Visitas Técnicas</p>
        <span class="svt-login-accent" aria-hidden="true"></span>
        <p class="svt-login-lead">
          Planeje, solicite e acompanhe visitas técnicas de forma simples e integrada.
        </p>

        <ol class="svt-login-flow">
          <li>
            <span class="svt-login-flow-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M8 13h8M8 17h5"/></svg>
            </span>
            <span>Solicitação</span>
          </li>
          <li>
            <span class="svt-login-flow-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2 4.8-5"/></svg>
            </span>
            <span>Aprovação</span>
          </li>
          <li>
            <span class="svt-login-flow-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15h13a3 3 0 0 0 0-6H8"/><path d="M7 15v2M16 15v2"/><circle cx="7.5" cy="18.5" r="1.4"/><circle cx="16.5" cy="18.5" r="1.4"/><path d="M4 12V8h3l2 4"/></svg>
            </span>
            <span>Transporte</span>
          </li>
          <li>
            <span class="svt-login-flow-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="m9 15 2 2 4-4"/></svg>
            </span>
            <span>Relatório</span>
          </li>
        </ol>
      </div>
    </section>

    <section class="svt-login-panel">
      <div class="svt-login-form-wrap">
        <h1 class="svt-login-title">Bem-vindo ao SVT</h1>
        <p class="svt-login-help">Entre com suas credenciais institucionais para continuar.</p>

        <form class="svt-login-form" novalidate @submit.prevent="handleLogin">
          <p v-if="errorMessage" id="login-erro" class="svt-login-alert" role="alert">
            {{ errorMessage }}
          </p>

          <div class="svt-login-field">
            <label class="svt-login-label" for="email">E-mail institucional</label>
            <div class="svt-login-control">
              <span class="svt-login-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
              </span>
              <input
                id="email"
                v-model="email"
                class="svt-login-input"
                type="email"
                name="email"
                inputmode="email"
                autocomplete="email"
                autocapitalize="none"
                spellcheck="false"
                placeholder="seu@email.senac.br"
                aria-required="true"
                :aria-invalid="erroEmail ? 'true' : 'false'"
                :aria-describedby="erroEmail ? 'email-erro' : undefined"
                :disabled="loading"
                @input="erroEmail = ''"
              />
            </div>
            <p v-if="erroEmail" id="email-erro" class="svt-login-field-error">{{ erroEmail }}</p>
          </div>

          <div class="svt-login-field">
            <label class="svt-login-label" for="senha">Senha</label>
            <div class="svt-login-control">
              <span class="svt-login-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
              </span>
              <input
                id="senha"
                v-model="senha"
                class="svt-login-input svt-login-input--senha"
                :type="showPassword ? 'text' : 'password'"
                name="senha"
                autocomplete="current-password"
                placeholder="Digite sua senha"
                aria-required="true"
                :aria-invalid="erroSenha ? 'true' : 'false'"
                :aria-describedby="erroSenha ? 'senha-erro' : undefined"
                :disabled="loading"
                @input="erroSenha = ''"
              />
              <button
                type="button"
                class="svt-login-toggle"
                :aria-pressed="showPassword"
                :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                :disabled="loading"
                @click="showPassword = !showPassword"
              >
                <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 6.1A10 10 0 0 1 12 6c6.5 0 10 6 10 6a18 18 0 0 1-3.2 3.8"/><path d="M6.1 6.8C3.8 8.3 2 12 2 12s3.5 7 10 7a10 10 0 0 0 4.2-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
              </button>
            </div>
            <p v-if="erroSenha" id="senha-erro" class="svt-login-field-error">{{ erroSenha }}</p>
          </div>

          <label class="svt-login-lembrar">
            <input v-model="lembrar" type="checkbox" name="lembrar" :disabled="loading" />
            <span>Lembrar meu e-mail</span>
          </label>

          <button class="svt-login-submit" type="submit" :disabled="loading" :aria-busy="loading">
            <span v-if="loading" class="svt-login-spinner" aria-hidden="true"></span>
            {{ loading ? (concluido ? 'Acesso confirmado' : 'Entrando') : 'Entrar' }}
          </button>
        </form>

        <p class="svt-login-notice">Acesso restrito a colaboradores autorizados do Senac DF.</p>
      </div>

      <footer class="svt-login-footer">
        © 2026 Senac DF · Sistema de Visitas Técnicas
        <span class="svt-login-versao">v{{ versao }}</span>
      </footer>
    </section>
  </div>
</template>

<script src="../scripts/Login.js"></script>
<style scoped src="../../css/Login.css"></style>
