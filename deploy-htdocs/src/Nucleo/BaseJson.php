<?php

/**
 * Camada de persistência: banco de dados em arquivo JSON.
 *
 * O arquivo inteiro é lido/gravado como um array associativo.
 * Gravações usam lock exclusivo para evitar corrupção com acessos simultâneos.
 */

declare(strict_types=1);

namespace Agenda\Nucleo;

use JsonException;
use RuntimeException;

final class BaseJson
{
    public function __construct(
        private readonly string $caminhoArquivo,
    ) {
    }

    /**
     * Lê todo o banco. Retorna array vazio se o arquivo ainda não existe.
     *
     * @return array<string, mixed>
     */
    public function lerTudo(): array
    {
        if (!is_file($this->caminhoArquivo)) {
            return [];
        }

        $conteudo = file_get_contents($this->caminhoArquivo);
        if ($conteudo === false || trim($conteudo) === '') {
            return [];
        }

        try {
            $dados = json_decode($conteudo, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $excecao) {
            throw new RuntimeException(
                'Arquivo do banco JSON está corrompido: ' . $excecao->getMessage(),
            );
        }

        return is_array($dados) ? $dados : [];
    }

    /**
     * Grava o banco inteiro de forma atômica (lock + escrita única).
     *
     * @param array<string, mixed> $dados
     */
    public function gravarTudo(array $dados): void
    {
        $diretorio = dirname($this->caminhoArquivo);
        if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
            throw new RuntimeException("Não foi possível criar o diretório: {$diretorio}");
        }

        $json = json_encode(
            $dados,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($json === false) {
            throw new RuntimeException('Falha ao codificar os dados em JSON.');
        }

        $gravado = file_put_contents($this->caminhoArquivo, $json . "\n", LOCK_EX);
        if ($gravado === false) {
            throw new RuntimeException("Não foi possível gravar em: {$this->caminhoArquivo}");
        }
    }

    public function caminhoArquivo(): string
    {
        return $this->caminhoArquivo;
    }
}
