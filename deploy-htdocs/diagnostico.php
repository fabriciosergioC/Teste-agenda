<?php
/**
 * Diagnostico rápido para hospedagem (ByetHost etc.).
 * Suba este arquivo dentro de htdocs e abra no navegador:
 *   https://SEU-DOMINIO/diagnostico.php
 * Depois apague-o do servidor (contém informações do ambiente).
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Diagnostico Agenda Estetica ===\n\n";

// 1. Versao do PHP (precisa ser >= 8.1 por causa de enums)
echo 'PHP: ' . PHP_VERSION . ' — '
   . (version_compare(PHP_VERSION, '8.1.0') ? 'OK (>= 8.1)' : 'PROBLEMA: precisa de PHP 8.1 ou superior')
   . "\n\n";

// 2. Estrutura de arquivos esperada dentro de htdocs
$verificacoes = [
    'index.php'                 => 'controlador (versao de htdocs, que chama src/iniciar.php)',
    'src/iniciar.php'           => 'bootstrap',
    'src/Servico/ServicoAgenda.php' => 'servico',
    'src/Dominio/StatusAgendamento.php' => 'enum (exige PHP 8.1+)',
    'data/banco.json'           => 'banco JSON',
    'assets/estilo.css'         => 'estilos',
];
$raiz = __DIR__;
echo "=== Arquivos esperados em htdocs ===\n";
foreach ($verificacoes as $caminho => $descricao) {
    $existe = is_file($raiz . '/' . $caminho);
    echo ($existe ? '[OK]      ' : '[FALTANDO] ') . $caminho . ' (' . $descricao . ")\n";
}
echo "\n";

// 3. O index.php carregado e a versao correta (require __DIR__)?
if (is_file($raiz . '/index.php')) {
    $conteudoIndex = (string) file_get_contents($raiz . '/index.php');
    $ehVersaoHtdocs = strpos($conteudoIndex, "require __DIR__ . '/src/iniciar.php'") !== false;
    $ehVersaoPublic = strpos($conteudoIndex, 'dirname(__DIR__)') !== false;
    echo "=== index.php ===\n";
    if ($ehVersaoPublic) {
        echo "[PROBLEMA] O index.php em htdocs e a versao de public/ (usa dirname(__DIR__)).\n";
        echo "           Substitua pelo index.php da pasta deploy-htdocs.\n";
    } elseif ($ehVersaoHtdocs) {
        echo "[OK] index.php e a versao correta para htdocs.\n";
    } else {
        echo "??? index.php nao reconheceu nenhum dos dois padroes de require.\n";
    }
    echo "\n";
}

// 4. Permissoes de escrita no banco (necessario para criar/editar agendamentos)
echo "=== Escrita no banco ===\n";
$arquivoBanco = $raiz . '/data/banco.json';
if (is_file($arquivoBanco)) {
    echo (is_writable($arquivoBanco) ? '[OK]      data/banco.json gravavel' : '[PROBLEMA] data/banco.json SEM permissao de escrita (defina 644/755)') . "\n";
}
$pastaData = $raiz . '/data';
echo (is_dir($pastaData) && is_writable($pastaData) ? '[OK]      pasta data/ gravavel' : '[PROBLEMA] pasta data/ sem escrita') . "\n\n";

// 5. Tentativa real de carregar o bootstrap (falha aqui = erro 500)
echo "=== Teste do bootstrap ===\n";
if (version_compare(PHP_VERSION, '8.1.0') && is_file($raiz . '/src/iniciar.php')) {
    try {
        require $raiz . '/src/iniciar.php';
        $base = new Agenda\Nucleo\BaseJson(Agenda\CaminhoBancoJson);
        $servico = new Agenda\Servico\ServicoAgenda($base);
        $total = count($servico->listarTodos());
        echo "[OK] Aplicacao carrou. Agendamentos no banco: {$total}\n";
    } catch (Throwable $erro) {
        echo '[PROBLEMA] ' . get_class($erro) . ': ' . $erro->getMessage() . "\n";
        echo 'Linha: ' . $erro->getLine() . ' em ' . $erro->getFile() . "\n";
    }
} else {
    echo 'Pulado (PHP < 8.1 ou bootstrap ausente — veja itens acima).' . "\n";
}
