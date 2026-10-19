<?php

/**
 * Duas decisões ao mesmo tempo, em processos separados, no banco svt_e2e.
 * Não usa o banco SVT. Não passa pelo servidor embutido de um só fio.
 */

$raiz = dirname(__DIR__, 2);
$alvo = 'svt_e2e';

if (($argv[1] ?? '') === 'worker') {
    worker($alvo, (int) ($argv[2] ?? 0), (int) ($argv[3] ?? 0), (string) ($argv[4] ?? ''));
}

$ambiente = lerEnv($raiz.'/.env');
$host = $ambiente['DB_HOST'] ?? '127.0.0.1';
$porta = $ambiente['DB_PORT'] ?? '3306';
$usuario = $ambiente['DB_USERNAME'] ?? 'root';
$senha = $ambiente['DB_PASSWORD'] ?? '';

if ($alvo !== 'svt_e2e') {
    fwrite(STDERR, "Alvo recusado.\n");
    exit(2);
}

$pdo = new PDO(
    "mysql:host={$host};port={$porta}",
    $usuario,
    $senha,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$existente = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ".$pdo->quote($alvo))->fetchColumn();
if ($existente !== false && strcasecmp((string) $existente, 'SVT') === 0) {
    fwrite(STDERR, "O schema encontrado é o SVT. Nada foi apagado.\n");
    exit(2);
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$alvo}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Banco confirmado antes da limpeza: {$alvo}\n";

foreach (['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $alvo, 'DB_URL' => ''] as $chave => $valor) {
    putenv($chave.'='.$valor);
    $_ENV[$chave] = $valor;
    $_SERVER[$chave] = $valor;
}

require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ligado = (string) DB::selectOne('select database() as nome')->nome;
if ($ligado !== $alvo) {
    fwrite(STDERR, "Conexão inesperada: {$ligado}\n");
    exit(3);
}

Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);

$visita = App\Models\VisitaTecnica::query()->where('etapa', 'nucleo')->where('unidade', 'Asa Norte')->first();
if (! $visita) {
    fwrite(STDERR, "Nenhuma visita em núcleo para disputar.\n");
    exit(4);
}

$marca = $raiz.'/storage/app/e2e-concorrencia.go';
$prontos = [
    $raiz.'/storage/app/e2e-concorrencia.ready.1',
    $raiz.'/storage/app/e2e-concorrencia.ready.2',
];
foreach ([$marca, ...$prontos] as $arquivo) {
    @unlink($arquivo);
}

$processos = [];
foreach ([1, 2] as $indice) {
    $descritor = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $processo = proc_open(
        [PHP_BINARY, __FILE__, 'worker', (string) $indice, (string) $visita->id, 'nucleo@df.senac.br'],
        $descritor,
        $pipes,
        $raiz
    );
    $processos[] = ['proc' => $processo, 'out' => $pipes[1], 'err' => $pipes[2]];
}

$limite = microtime(true) + 40;
while (count(array_filter($prontos, 'is_file')) < 2) {
    if (microtime(true) > $limite) {
        fwrite(STDERR, "Os dois processos não ficaram prontos.\n");
        exit(4);
    }
    usleep(20000);
}
file_put_contents($marca, 'vai');

$saidas = [];
foreach ($processos as $item) {
    $saidas[] = trim(stream_get_contents($item['out']));
    $erro = stream_get_contents($item['err']);
    if ($erro !== '') {
        fwrite(STDERR, $erro);
    }
    fclose($item['out']);
    fclose($item['err']);
    proc_close($item['proc']);
}
foreach ([$marca, ...$prontos] as $arquivo) {
    @unlink($arquivo);
}

$eventos = [];
foreach ($saidas as $saida) {
    $evento = json_decode($saida, true);
    if (! is_array($evento)) {
        fwrite(STDERR, "Saída inválida: {$saida}\n");
        exit(5);
    }
    $eventos[] = $evento;
}
usort($eventos, fn ($a, $b) => $a['r'] <=> $b['r']);
$resultados = array_column($eventos, 'r');
$sobreposicao = $eventos[0]['i'] < $eventos[1]['f'] && $eventos[1]['i'] < $eventos[0]['f'];

$aprovacoes = DB::table('visita_eventos')->where('visita_tecnica_id', $visita->id)->where('evento', 'aprovacao')->count();
$etapa = App\Models\VisitaTecnica::query()->find($visita->id)->etapa;
echo 'Resultados: '.implode(' | ', $resultados)."\n";
echo "Etapa: {$etapa}\n";
echo "Aprovacoes: {$aprovacoes}\n";
echo 'Sobreposicao: '.($sobreposicao ? 'sim' : 'nao')."\n";

$ok = $resultados === ['OK', 'RECUSADA'] && $etapa === 'cped' && $aprovacoes === 1 && $sobreposicao;
exit($ok ? 0 : 5);

function worker(string $alvo, int $indice, int $id, string $email): void
{
    if ($alvo !== 'svt_e2e' || $indice < 1 || $id <= 0) {
        fwrite(STDERR, "Worker recusou o alvo.\n");
        exit(2);
    }

    $raiz = dirname(__DIR__, 2);
    foreach (['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $alvo, 'DB_URL' => ''] as $chave => $valor) {
        putenv($chave.'='.$valor);
        $_ENV[$chave] = $valor;
        $_SERVER[$chave] = $valor;
    }

    require $raiz.'/vendor/autoload.php';
    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $ligado = (string) DB::selectOne('select database() as nome')->nome;
    if ($ligado !== $alvo) {
        fwrite(STDERR, "Worker no banco {$ligado}\n");
        exit(3);
    }

    $usuario = App\Models\Usuario::query()->where('email', $email)->firstOrFail();
    $visita = App\Models\VisitaTecnica::query()->findOrFail($id);
    $marca = $raiz.'/storage/app/e2e-concorrencia.go';
    file_put_contents($raiz.'/storage/app/e2e-concorrencia.ready.'.$indice, '1');

    $limite = microtime(true) + 30;
    while (! is_file($marca)) {
        if (microtime(true) > $limite) {
            fwrite(STDERR, "Worker sem sinal.\n");
            exit(4);
        }
    }

    $inicio = microtime(true);
    try {
        app(App\Services\SvtFluxoService::class)->decidir($visita, $usuario, true, null);
        $resultado = 'OK';
    } catch (Illuminate\Validation\ValidationException $e) {
        $resultado = 'RECUSADA';
    }
    echo json_encode(['r' => $resultado, 'i' => $inicio, 'f' => microtime(true)]);
    exit(0);
}

function lerEnv(string $caminho): array
{
    $dados = [];
    foreach (file($caminho, FILE_IGNORE_NEW_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || ! str_contains($linha, '=')) {
            continue;
        }
        [$chave, $valor] = explode('=', $linha, 2);
        $dados[$chave] = trim($valor, " \t\"'");
    }

    return $dados;
}
