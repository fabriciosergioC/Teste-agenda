<?php

/**
 * Ponto de partida da aplicação.
 *
 * Padronização do projeto (vale para todo o código-fonte):
 * - Idioma: português (pt-BR). Identificadores sem acento, strings com acento.
 * - Classes em PascalCase, métodos/variáveis em camelCase, constantes em UPPER_CASE.
 * - declare(strict_types=1) em todos os arquivos.
 * - Namespaces: Agenda\Nucleo, Agenda\Dominio, Agenda\Servico.
 */

declare(strict_types=1);

namespace Agenda;

use RuntimeException;

const Versao = '1.0.0';
const CaminhoRaiz = __DIR__ . '/..';
const CaminhoBancoJson = CaminhoRaiz . '/data/banco.json';
const CaminhoVistas = CaminhoRaiz . '/src/Vista';

// Fuso horário padrão do negócio (clínica de estética no Brasil).
date_default_timezone_set('America/Sao_Paulo');

/**
 * Autoload simples: Agenda\Nucleo\BaseJson -> src/Nucleo/BaseJson.php.
 */
spl_autoload_register(static function (string $classe): void {
    $prefixo = 'Agenda\\';
    if (!str_starts_with($classe, $prefixo)) {
        return;
    }
    $relativa = substr($classe, strlen($prefixo));
    $arquivo = __DIR__ . '/' . str_replace('\\', '/', $relativa) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

require __DIR__ . '/Nucleo/FuncoesComuns.php';

/**
 * Tratamento global de erros: exibe página amigável em vez de stack trace crua.
 * Exceções de validação são tratadas localmente no controlador.
 */
set_exception_handler(static function (\Throwable $erro): void {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Erro: ' . $erro->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<title>Erro interno</title></head><body style="font-family:sans-serif;'
        . 'display:grid;place-items:center;min-height:100vh;margin:0">'
        . '<div style="text-align:center;max-width:520px;padding:24px;border:1px solid #eadfe0;'
        . 'border-radius:14px;background:#fff"><h1 style="color:#b56576;font-size:20px">'
        . 'Algo deu errado na agenda</h1><p style="color:#8a7f83">'
        . htmlspecialchars($erro->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</p><a href="index.php" style="color:#b56576">Voltar para a agenda</a>'
        . '</div></body></html>';
});

/**
 * Carrega um arquivo de vista dentro da plantilha geral.
 *
 * @param array<string, mixed> $dados Variáveis disponíveis na vista.
 */
function exibirTela(string $nome, array $dados = []): void
{
    $dados['tituloPagina'] = $dados['tituloPagina'] ?? 'Agenda Estética';
    extract($dados, EXTR_SKIP);

    $caminhoVista = CaminhoVistas . '/' . $nome . '.php';
    if (!is_file($caminhoVista)) {
        throw new RuntimeException("Vista não encontrada: {$nome}");
    }

    // Renderiza a vista em buffer e injeta o resultado na plantilha.
    ob_start();
    require $caminhoVista;
    $conteudoVista = ob_get_clean();

    require CaminhoVistas . '/plantilha.php';
}
