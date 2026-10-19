# SVT — Sistema de Visitas Técnicas

Sistema do Senac DF para solicitar, analisar, transportar e registrar visitas técnicas.

A interface é uma SPA Vue 3 em `Front_SVT`. A API é Laravel 12 com Sanctum em `Back_SVT`. O banco de desenvolvimento é MySQL (`SVT` no exemplo). Os testes da API usam SQLite em memória. No desenvolvimento, o Vite encaminha `/api`, `/IMG` e `/storage` para `http://127.0.0.1:8000`.

A autorização que vale é a da API. O menu só esconde atalhos.

## Fluxo da visita

A etapa fica em `etapa`. O rótulo legível fica em `status`.

```text
rascunho → núcleo pedagógico → coordenação do eixo → direção pedagógica → logística
         → transporte definido → realizada → relatório entregue
```

Recusa e cancelamento saem dessa linha. A recusa guarda quem recusou: núcleo, coordenação ou direção. O motivo é obrigatório na recusa.

| Quem | O que faz |
|---|---|
| Instrutor | Abre o pedido da própria turma, edita o rascunho, cancela enquanto está em rascunho ou no núcleo, marca a visita como realizada e anexa o relatório em PDF ou Word. |
| Núcleo Pedagógico | Aprova ou recusa só a própria unidade. |
| Coordenação de Eixo | Aprova ou recusa só a área do próprio eixo. |
| Direção Pedagógica | Última análise pedagógica, nos cinco eixos. |
| NULOG | Registra veículo, placa, data, horário e motorista. Não aprova nem recusa. |
| Administrador | Cuida dos acessos, vê a auditoria e o histórico da turma. Não analisa pedido. |

Editor e Consultor ainda existem no cadastro e consultam visitas. O formulário de usuários não oferece esses papéis.

Os cinco eixos são Gastronomia e Turismo, Ambiente e Saúde, Gestão e Moda, Tecnologia e Economia Criativa, e Beleza e Cuidado Pessoal. Cada um tem a coordenação correspondente.

## Cota

A cota em `config/svt.php` é provisória. Consomem o limite as etapas `aprovada`, `realizada` e `relatorio`. Pedido em tramitação, rascunho, recusa e cancelamento não consomem. Acima do limite o envio segue, com justificativa. Período, carga horária e a lista definitiva de cursos ainda dependem do cliente.

## O que cada perfil vê

- Instrutor: as próprias visitas e os relatórios delas.
- Núcleo: a unidade cadastrada no acesso.
- Coordenação: o eixo da própria área.
- Direção, administrador, editor e consultor: a consulta não aplica filtro extra de unidade ou eixo.
- NULOG: visitas aguardando transporte, com transporte definido, realizadas ou com relatório.

A lista de visitas abre pelo botão **Visualizar**. Editar rascunho e cancelar ficam na ficha, quando a regra permite.

O histórico por turma e por ano aparece só para o administrador, ligado ao cadastro da turma (`turma_id`), não ao nome digitado. A auditoria geral também é só do administrador: registro append-only, sem senha, token ou conteúdo de arquivo.

Relatórios gerenciais respeitam o mesmo recorte do perfil. Os indicadores separam solicitação, visita realizada e a cota provisória.

## Como rodar

Requisitos: PHP 8.2+, Composer, Node 20+, MySQL.

```cmd
git clone https://github.com/LucasLeal0619/Sistema_SVT.git
cd Sistema_SVT
local-start.cmd
```

Crie o banco `SVT` no MySQL antes do seed. A aplicação abre em http://127.0.0.1:5173/login e a API em http://127.0.0.1:8000.

Logins de demonstração, depois do seed:

| Papel | E-mail | Senha |
|---|---|---|
| Administrador | `administrador@df.senac.br` | `senac2025` |
| Instrutor | `instrutor@df.senac.br` | `svt2026` |
| Núcleo pedagógico | `nucleo@df.senac.br` | `svt2026` |
| Coordenação de Gastronomia e Turismo | `cped.gastronomia@df.senac.br` | `svt2026` |
| Coordenação de Ambiente e Saúde | `cped.ambiente@df.senac.br` | `svt2026` |
| Coordenação de Gestão | `cped.gestao@df.senac.br` | `svt2026` |
| Coordenação de Tecnologia | `cped.tecnologia@df.senac.br` | `svt2026` |
| Coordenação de Beleza | `cped.beleza@df.senac.br` | `svt2026` |
| Direção pedagógica | `direcao@df.senac.br` | `svt2026` |
| NULOG | `nulog@df.senac.br` | `svt2026` |

## Testes

Na API, a suíte que existe é a de feature. A pasta `tests/Unit` não entra no `phpunit.xml` padrão.

```cmd
cd Back_SVT
php vendor/bin/phpunit --testsuite Feature
```

No front:

```cmd
cd Front_SVT
npm run build
npx playwright test
```

O Playwright sobe a API e o Vite em portas separadas, com SQLite próprio. Não aponta para o MySQL de desenvolvimento.
