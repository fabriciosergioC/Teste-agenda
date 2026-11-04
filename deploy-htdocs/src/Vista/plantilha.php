<?php

/**
 * Vista: plantilha geral (cabeçalho, mensagens, rodapé).
 *
 * Variáveis esperadas: $tituloPagina, $conteudoVista,
 *                     $mensagens (opcional), $tokenCsrf (opcional).
 */

declare(strict_types=1);

use function Agenda\Nucleo\colherMensagens;
use function Agenda\Nucleo\e;

$mensagens = $mensagens ?? colherMensagens();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tituloPagina) ?> · Agenda Estética</title>
    <link rel="stylesheet" href="assets/estilo.css">
</head>
<body>
<header class="topo">
    <div class="topo-conteudo">
        <a class="marca" href="index.php">
            <span class="marca-simbolo">✦</span>
            <span class="marca-texto">
                <strong>Agenda Estética</strong>
                <small>gestão de horários</small>
            </span>
        </a>
        <nav class="menu">
            <a href="index.php">Agenda</a>
            <a class="botao botao-primario" href="index.php?tela=novo">+ Novo agendamento</a>
        </nav>
    </div>
</header>

<main class="conteudo">
    <?php if ($mensagens !== []): ?>
        <div class="avisos">
            <?php foreach ($mensagens as $mensagem): ?>
                <div class="aviso aviso-<?= e($mensagem['tipo']) ?>"><?= e($mensagem['texto']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $conteudoVista ?>
</main>

<footer class="rodape">
    Agenda Estética v<?= e(\Agenda\Versao) ?> · banco de dados em JSON · PHP <?= e(PHP_VERSION) ?>
</footer>
</body>
</html>
