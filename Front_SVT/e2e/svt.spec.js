import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

const relatorioExemplo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), 'fixtures/relatorio-exemplo.pdf');

const senhaPerfil = 'svt2026';
const larguras = [320, 360, 375, 390, 430, 768, 1024, 1366, 1440];

async function entrar(page, email, senha) {
    await page.goto('/login');
    await page.evaluate(() => localStorage.clear());
    await page.reload();
    await page.getByLabel('E-mail institucional', { exact: true }).fill(email);
    await page.locator('#senha').fill(senha);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await page.waitForURL(/\/app\//);
}

async function sair(page) {
    await page.getByRole('button', { name: 'Sair' }).click();
    await page.waitForURL(/\/login/);
}

async function token(page) {
    return page.evaluate(() => localStorage.getItem('sgp_token'));
}

async function medir(page) {
    return page.evaluate(() => {
        const main = document.querySelector('.svt-main');
        if (main) main.scrollTop = main.scrollHeight;
        const dock = document.querySelector('.svt-dock');
        const dockVisivel = dock && getComputedStyle(dock).display !== 'none';
        const dockCaixa = dockVisivel ? dock.getBoundingClientRect() : null;
        const alvos = [...document.querySelectorAll('.svt-main button, .svt-main input, .svt-main select, .svt-main textarea')].filter((el) => {
            const caixa = el.getBoundingClientRect();
            return caixa.width > 0 && caixa.height > 0;
        });
        const cobertos = alvos.filter((el) => {
            if (!dockCaixa) return false;
            const caixa = el.getBoundingClientRect();
            return caixa.bottom > dockCaixa.top + 1 && caixa.top < dockCaixa.bottom - 1;
        }).map((el) => (el.innerText || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 40));
        return {
            largura: window.innerWidth,
            estouro: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            cobertos,
        };
    });
}

test.describe.serial('ciclo da visita', () => {
    let erros = [];

    test.beforeEach(async ({ page }) => {
        erros = [];
        page.on('pageerror', (erro) => erros.push(String(erro)));
        page.on('console', (msg) => {
            if (msg.type() === 'error' && !msg.location().url.includes('favicon')) {
                erros.push(msg.text() + ' :: ' + msg.location().url);
            }
        });
        page.on('response', (resposta) => {
            if (resposta.status() === 404 && !resposta.url().includes('favicon')) {
                erros.push('404 ' + resposta.url());
            }
        });
    });

    test('os seis perfis entram e o instrutor não abre usuários', async ({ page }) => {
        const contas = [
            ['instrutor@df.senac.br', senhaPerfil, 'Início'],
            ['nucleo@df.senac.br', senhaPerfil, 'Pendências'],
            ['cped.tecnologia@df.senac.br', senhaPerfil, 'Pendências'],
            ['direcao@df.senac.br', senhaPerfil, 'Pendências'],
            ['nulog@df.senac.br', senhaPerfil, 'Transporte'],
            ['administrador@df.senac.br', 'senac2025', 'Usuários e Acessos'],
        ];

        for (const [email, senha, menu] of contas) {
            await entrar(page, email, senha);
            await expect(page.getByRole('link', { name: menu, exact: true })).toBeVisible();
            if (email.startsWith('nulog@')) {
                await expect(page.getByRole('heading', { name: /Núcleo de Logística/ })).toBeVisible();
                await expect(page.getByRole('link', { name: 'Visitas Autorizadas', exact: true })).toBeVisible();
                await expect(page.getByRole('link', { name: 'Relatórios Gerenciais', exact: true })).toHaveCount(0);
            }
            if (email.startsWith('cped.')) {
                await expect(page.getByRole('link', { name: 'Visitas do Eixo', exact: true })).toBeVisible();
            }
            if (email.startsWith('direcao@')) {
                await expect(page.getByRole('link', { name: 'Todas as Visitas', exact: true })).toBeVisible();
            }
            if (email.startsWith('administrador@')) {
                await expect(page.getByRole('link', { name: 'Relatórios Gerenciais', exact: true })).toBeVisible();
                await expect(page.getByRole('link', { name: 'Relatórios de Visita', exact: true })).toHaveCount(0);
                await expect(page.getByRole('link', { name: 'Visitas Técnicas', exact: true })).toBeVisible();
                await expect(page.getByRole('link', { name: 'Auditoria', exact: true })).toBeVisible();
                await expect(page.getByRole('link', { name: 'Dados Institucionais', exact: true })).toHaveCount(0);
                await expect(page.getByRole('link', { name: 'Configurações', exact: true })).toHaveCount(0);
                await page.goto('/app/auditoria');
                await expect(page.getByRole('heading', { name: 'Auditoria' })).toBeVisible();
                for (const largura of larguras) {
                    await page.setViewportSize({ width: largura, height: 800 });
                    const medida = await medir(page);
                    expect(medida.estouro, 'auditoria em ' + largura).toBeLessThanOrEqual(1);
                    expect(medida.cobertos, JSON.stringify(medida)).toEqual([]);
                }
                await page.setViewportSize({ width: 1440, height: 900 });
            }
            if (email.startsWith('instrutor@')) {
                await expect(page.getByRole('link', { name: 'Relatórios de Visita', exact: true })).toBeVisible();
                await expect(page.getByRole('link', { name: 'Minhas Visitas', exact: true })).toBeVisible();
            }
            if (email.startsWith('instrutor@')) {
                await page.goto('/app/usuarios');
                await expect(page).toHaveURL(/\/app\/painel/);
                await page.goto('/app/auditoria');
                await expect(page).toHaveURL(/\/app\/painel/);
            }
            await sair(page);
        }

        expect(erros).toEqual([]);
    });

    test('a decisão cabe nas larguras sem ficar sob o menu', async ({ page, request }) => {
        await entrar(page, 'instrutor@df.senac.br', senhaPerfil);
        const autorizacao = { Authorization: 'Bearer ' + await token(page), Accept: 'application/json' };
        const turmas = await request.get('/api/svt/turmas', { headers: autorizacao });
        expect(turmas.ok()).toBeTruthy();
        const turma = (await turmas.json()).data.find((item) => String(item.nome).startsWith('BEL-'));
        expect(turma).toBeTruthy();
        const criada = await request.post('/api/svt/solicitacoes', {
            headers: { ...autorizacao, 'Content-Type': 'application/json' },
            data: {
                enviar: true,
                turma_id: turma.id,
                local_visita: 'Layout E2E',
                data_visita_prevista: '2026-12-20',
                horario_pretendido: '10:00',
                atividade: 'Observar o atendimento.',
                objetivo: 'Relacionar a prática com o curso.',
                relacao_curricular: 'Unidade curricular em andamento.',
            },
        });
        expect(criada.status()).toBe(201);
        const id = (await criada.json()).visita.id;
        await sair(page);

        await entrar(page, 'nucleo@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes?id=' + id);
        await expect(page.getByRole('button', { name: 'Recusar' })).toBeVisible();

        for (const largura of larguras) {
            await page.setViewportSize({ width: largura, height: largura >= 768 ? 900 : 720 });
            const medida = await medir(page);
            expect(medida.largura, 'largura ' + largura).toBe(largura);
            expect(medida.estouro, 'estouro em ' + largura).toBeLessThanOrEqual(1);
            expect(medida.cobertos, 'cobertos em ' + largura).toEqual([]);
        }

        await page.setViewportSize({ width: 1440, height: 900 });
        await expect(page.getByRole('link', { name: 'Visitas da Unidade', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Relatórios de Visita', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Relatórios Gerenciais', exact: true })).toBeVisible();
        await page.goto('/app/relatorios');
        await expect(page.getByRole('heading', { name: 'Relatórios Gerenciais' })).toBeVisible();
        await page.goto('/app/relatorios-visita');
        await expect(page.getByRole('heading', { name: 'Relatórios de Visita' })).toBeVisible();
        for (const largura of [320, 1440]) {
            await page.setViewportSize({ width: largura, height: largura >= 768 ? 900 : 720 });
            const medida = await medir(page);
            expect(medida.estouro, 'relatório em ' + largura).toBeLessThanOrEqual(1);
            expect(medida.cobertos, 'relatório coberto em ' + largura).toEqual([]);
        }
        expect(erros).toEqual([]);
    });

    test('o ciclo completo volta ao instrutor com o relatório', async ({ page, request }) => {
        await entrar(page, 'instrutor@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes');
        await page.getByRole('button', { name: 'Pedir uma visita' }).click();
        const turmaTec = page.getByLabel('Turma vinculada a você');
        await turmaTec.selectOption(await turmaTec.locator('option', { hasText: 'TEC-2026-A' }).getAttribute('value'));
        await page.getByLabel('Local da visita').fill('Laboratorio Playwright');
        await page.getByLabel('Data pretendida').fill('2026-12-18');
        await page.getByLabel('Horário pretendido').fill('09:15');
        await page.getByLabel('O que a turma vai fazer').fill('Observar a rotina do laboratorio.');
        await page.getByLabel('Objetivo pedagógico').fill('Relacionar a pratica com a unidade.');
        await page.getByLabel('Relação com o curso ou a unidade curricular').fill('Conteudo em andamento.');
        const extra = page.getByLabel('Por que esta visita extra é necessária');
        if (await extra.count()) {
            await extra.fill('Atividade extra prevista no plano e ainda nao realizada.');
        }
        const resposta = page.waitForResponse((item) => item.url().includes('/api/svt/solicitacoes') && item.request().method() === 'POST');
        await page.getByRole('button', { name: 'Enviar ao núcleo' }).click();
        const corpo = await (await resposta).json();
        const id = corpo.visita.id;
        expect(corpo.visita.etapa).toBe('nucleo');
        await expect(page.getByText('Pedido enviado ao núcleo da unidade.')).toBeVisible();
        await sair(page);

        await entrar(page, 'nucleo@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes?id=' + id);
        await page.getByRole('button', { name: 'Aprovar e seguir' }).click();
        await expect(page.getByText('Pedido encaminhado.')).toBeVisible();
        await sair(page);

        const foraDoEixo = [
            'cped.gastronomia@df.senac.br',
            'cped.ambiente@df.senac.br',
            'cped.gestao@df.senac.br',
            'cped.beleza@df.senac.br',
        ];
        for (const email of foraDoEixo) {
            const outro = await request.post('/api/login', {
                data: { email, senha: senhaPerfil },
            });
            const tokenOutro = (await outro.json()).token;
            const consulta = await request.get('/api/svt/solicitacoes/' + id, {
                headers: { Authorization: 'Bearer ' + tokenOutro, Accept: 'application/json' },
            });
            const decisao = await request.post('/api/svt/solicitacoes/' + id + '/decisao', {
                headers: { Authorization: 'Bearer ' + tokenOutro, Accept: 'application/json' },
                data: { aprovar: true },
            });
            expect(consulta.status(), email).toBe(404);
            expect(decisao.status(), email).toBe(422);
        }

        await entrar(page, 'cped.tecnologia@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes?id=' + id);
        await page.getByRole('button', { name: 'Aprovar e seguir' }).click();
        await expect(page.getByText('Pedido encaminhado.')).toBeVisible();
        await sair(page);

        await entrar(page, 'direcao@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes?id=' + id);
        await page.getByRole('button', { name: 'Aprovar e seguir' }).click();
        await expect(page.getByText('Pedido encaminhado.')).toBeVisible();
        await sair(page);

        await entrar(page, 'nulog@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes?id=' + id);
        await page.getByLabel('Tipo').selectOption('Ônibus');
        await page.getByLabel('Veículo').fill('Micro Playwright');
        await page.getByLabel('Placa').fill('E2E1A23');
        await page.getByLabel('Data', { exact: true }).fill('2026-12-18');
        await page.getByLabel('Horário', { exact: true }).fill('08:00');
        await page.getByLabel('Horário de retorno').fill('12:00');
        await page.getByLabel('Motorista').fill('Motorista Playwright');
        await page.getByRole('button', { name: 'Registrar transporte' }).click();
        await expect(page.getByText('Transporte registrado.')).toBeVisible();
        await sair(page);

        await entrar(page, 'instrutor@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes?id=' + id);
        await expect(page.getByText('Motorista Playwright')).toBeVisible();
        await expect(page.getByText('E2E1A23')).toBeVisible();
        await page.getByRole('button', { name: 'Marcar visita como realizada' }).click();
        await expect(page.getByText('Visita marcada como realizada.')).toBeVisible();
        await page.getByLabel('O que foi realizado e o que a turma aprendeu').fill('A turma observou o laboratorio.');
        await page.getByLabel('Relatório da visita, em Word ou PDF').setInputFiles(relatorioExemplo);
        await page.getByRole('button', { name: 'Anexar relatório' }).click();
        await expect(page.getByText('Relatório anexado.')).toBeVisible();

        const autorizacao = { Authorization: 'Bearer ' + await token(page), Accept: 'application/json' };
        const arquivo = await request.get('/api/svt/solicitacoes/' + id + '/relatorio/arquivo', { headers: autorizacao });
        expect(arquivo.status()).toBe(200);
        expect(arquivo.headers()['content-type']).toContain('application/pdf');
        expect(Buffer.from(await arquivo.body()).equals(readFileSync(relatorioExemplo))).toBe(true);
        const anonimo = await request.get('/api/svt/solicitacoes/' + id + '/relatorio/arquivo');
        expect(anonimo.status()).toBe(401);

        const detalhe = await request.get('/api/svt/solicitacoes/' + id, { headers: autorizacao });
        const visita = (await detalhe.json()).visita;
        expect(visita.etapa).toBe('relatorio');
        expect(visita.status).toBe('Relatório entregue');
        expect(erros.filter((item) => !item.includes('favicon'))).toEqual([]);
    });

    test('recusar sem motivo não encerra a visita', async ({ page }) => {
        await entrar(page, 'instrutor@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes');
        await page.getByRole('button', { name: 'Pedir uma visita' }).click();
        const turmaGas = page.getByLabel('Turma vinculada a você');
        await turmaGas.selectOption(await turmaGas.locator('option', { hasText: 'GAS-2026' }).getAttribute('value'));
        await page.getByLabel('Local da visita').fill('Cozinha Playwright');
        await page.getByLabel('Data pretendida').fill('2026-12-19');
        await page.getByLabel('O que a turma vai fazer').fill('Observar a cozinha.');
        await page.getByLabel('Objetivo pedagógico').fill('Relacionar com o curso.');
        await page.getByLabel('Relação com o curso ou a unidade curricular').fill('Unidade em andamento.');
        const extra = page.getByLabel('Por que esta visita extra é necessária');
        if (await extra.count()) {
            await extra.fill('Segunda saída justificada para o teste.');
        }
        const resposta = page.waitForResponse((item) => item.url().includes('/api/svt/solicitacoes') && item.request().method() === 'POST');
        await page.getByRole('button', { name: 'Enviar ao núcleo' }).click();
        const id = (await (await resposta).json()).visita.id;
        await sair(page);

        await entrar(page, 'nucleo@df.senac.br', senhaPerfil);
        await page.goto('/app/solicitacoes?id=' + id);
        await page.getByRole('button', { name: 'Recusar' }).click();
        await expect(page.getByText('Informe o motivo da recusa.')).toBeVisible();
        await page.getByLabel('Se for recusar, escreva o motivo').fill('Sem relação suficiente com a unidade curricular.');
        await page.getByRole('button', { name: 'Recusar' }).click();
        await expect(page.getByText('Pedido recusado.')).toBeVisible();
        expect(erros.filter((item) => !item.includes('favicon') && !item.includes('/decisao'))).toEqual([]);
    });
});
