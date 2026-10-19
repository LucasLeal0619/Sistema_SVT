<template>
  <section class="svt-page svt-page--wide">
    <template v-if="modo === 'lista'">
      <header>
        <h1>Usuários</h1>
        <p class="svt-lead">
          Cadastre quem entra. No núcleo, a unidade define os pedidos que a pessoa vê.
          Na coordenação, a área tem que ser a do eixo.
        </p>
      </header>

      <div class="svt-row-actions" v-if="podeEditar">
        <button class="svt-btn" type="button" @click="abrirNovo">Novo usuário</button>
      </div>

      <p v-if="mensagemSucesso" class="svt-alert is-ok">{{ mensagemSucesso }}</p>
      <p v-if="mensagemErro" class="svt-alert">{{ mensagemErro }}</p>

      <PainelFiltros>
        <template #busca>
          <input
            v-model="filtros.busca"
            type="search"
            placeholder="Nome ou e-mail"
            aria-label="Buscar usuários"
            @input="aplicarFiltros"
          />
        </template>
        <select v-model="filtros.perfil" aria-label="Função" @change="aplicarFiltros">
          <option value="">Todas as funções</option>
          <option v-for="papel in papeis" :key="papel.nome" :value="papel.nome">{{ papel.nome }}</option>
        </select>
        <select v-model="filtros.unidade" aria-label="Unidade" @change="aplicarFiltros">
          <option value="">Todas as unidades</option>
          <option v-for="unidade in unidades" :key="unidade.value || unidade" :value="unidade.value || unidade">
            {{ unidade.label || unidade }}
          </option>
        </select>
        <select v-model="filtros.status" aria-label="Status" @change="aplicarFiltros">
          <option value="">Ativos e inativos</option>
          <option value="true">Ativos</option>
          <option value="false">Inativos</option>
        </select>
      </PainelFiltros>

      <p v-if="carregando" class="svt-muted">Carregando...</p>
      <p v-else-if="usuarios.length === 0" class="svt-muted">Nenhum usuário com esses filtros.</p>
      <ListaResponsiva v-else :itens="usuarios" :colunas="colunasLista">
        <template #celula-status="{ item }">{{ item.status ? 'Ativo' : 'Inativo' }}</template>
        <template #acao="{ item }">
          <div class="svt-acoes">
            <button class="svt-btn secondary" type="button" @click="abrirEdicao(item)">Editar</button>
            <button
              v-if="item.status"
              class="svt-btn danger"
              type="button"
              :disabled="alternandoId === item.id"
              @click="inativarUsuario(item)"
            >Inativar</button>
            <button
              v-else
              class="svt-btn secondary"
              type="button"
              :disabled="alternandoId === item.id"
              @click="inativarUsuario(item)"
            >Ativar</button>
          </div>
        </template>
      </ListaResponsiva>

      <div class="svt-pager">
        <span class="svt-muted">
          {{ meta.from || 0 }}–{{ meta.to || 0 }} de {{ meta.total || 0 }}
        </span>
        <div class="svt-pager-nav">
          <button class="svt-btn secondary" type="button" :disabled="meta.current_page <= 1 || carregando" @click="irParaPagina(meta.current_page - 1)">Anterior</button>
          <button class="svt-btn secondary" type="button" :disabled="meta.current_page >= meta.last_page || carregando" @click="irParaPagina(meta.current_page + 1)">Próxima</button>
        </div>
      </div>
    </template>

    <form v-else class="svt-ficha" @submit.prevent="salvarUsuario">
      <button class="svt-back" type="button" @click="voltarLista">Voltar</button>
      <h2>{{ modo === 'novo' ? 'Novo acesso' : 'Ajustar acesso' }}</h2>
      <p class="svt-muted">Escolha a função antes do resto. O e-mail é o login.</p>
      <p v-if="erroFormulario" class="svt-alert">{{ erroFormulario }}</p>
      <p v-if="perfilFora" class="svt-alert">
        Este acesso está como {{ form.perfil }}, um papel que esta tela não oferece. Escolha um abaixo.
      </p>

      <label class="svt-field">Função
        <select v-model="form.perfil" required @change="escolherPapel(form.perfil)">
          <option value="">Selecione</option>
          <option v-for="papel in papeis" :key="papel.nome" :value="papel.nome">{{ papel.nome }} — {{ papel.resumo }}</option>
        </select>
      </label>
      <p v-if="papelEscolhido" class="svt-alert is-ok">{{ papelEscolhido.frase }}</p>

      <label class="svt-field">Nome
        <input id="nome" v-model="form.nome" type="text" required maxlength="100" autocomplete="name" />
      </label>
      <label class="svt-field">E-mail de login
        <input id="email" v-model="form.email" type="email" required maxlength="100" autocomplete="off" />
      </label>
      <div class="svt-grid-2">
        <label class="svt-field">Telefone
          <input id="telefone" v-model="form.telefone" type="tel" inputmode="tel" required maxlength="20" @input="formatarTelefone" />
        </label>
        <label class="svt-field">CPF
          <input id="cpf" v-model="form.cpf" type="text" inputmode="numeric" required maxlength="14" @input="formatarCpf" />
        </label>
      </div>
      <label class="svt-field">Unidade
        <select id="unidade" v-model="form.unidade" required>
          <option value="">Selecione</option>
          <option v-for="unidade in unidades" :key="unidade.value || unidade" :value="unidade.value || unidade">
            {{ unidade.label || unidade }}
          </option>
        </select>
        <small v-if="form.perfil === 'Núcleo Pedagógico'">Esta pessoa só recebe pedidos desta unidade.</small>
      </label>
      <label v-if="ehCped" class="svt-field">Área do eixo
        <select id="area" v-model="form.area" required>
          <option value="">Selecione a coordenação</option>
          <option v-for="area in areasCped" :key="area" :value="area">{{ area }}</option>
        </select>
        <small>Tem que ser exatamente a coordenação do eixo. Outro texto e o pedido não aparece.</small>
      </label>
      <label v-else class="svt-field">Área
        <input id="area" v-model="form.area" type="text" required maxlength="100" />
        <small>Serve para identificar onde a pessoa atua. Na coordenação, use a lista fechada.</small>
      </label>
      <div class="svt-grid-2">
        <label class="svt-field">Senha
          <input id="senha" v-model="form.senha" type="password" :required="modo === 'novo'" minlength="6" maxlength="100" autocomplete="new-password" />
        </label>
        <label class="svt-field">Confirmar senha
          <input id="confirmarSenha" v-model="form.confirmarSenha" type="password" :required="modo === 'novo' || !!form.senha" minlength="6" maxlength="100" autocomplete="new-password" />
        </label>
      </div>
      <label class="svt-check">
        <input v-model="form.status" type="checkbox" />
        Acesso ativo
      </label>

      <div class="svt-row-actions">
        <button class="svt-btn secondary" type="button" @click="voltarLista">Cancelar</button>
        <button class="svt-btn" type="submit" :disabled="salvando">
          {{ salvando ? 'Salvando...' : 'Salvar acesso' }}
        </button>
      </div>
      <button
        v-if="editandoId"
        class="svt-btn danger block"
        type="button"
        @click="excluirUsuario({ id: editandoId, nome: form.nome })"
      >
        Excluir este acesso
      </button>
    </form>
  </section>
</template>

<script src="../scripts/Usuarios.js"></script>
