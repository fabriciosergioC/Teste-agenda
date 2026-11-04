<?php

/**
 * Funções utilitárias globais da aplicação (namespace Agenda).
 */

declare(strict_types=1);

namespace Agenda\Nucleo;

/**
 * Escapa HTML para exibição segura de dados do usuário.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Guarda uma mensagem de feedback para a próxima requisição (padrão PRG).
 *
 * @param string $tipo sucesso|erro|aviso
 */
function guardarMensagem(string $tipo, string $texto): void
{
    $_SESSION['mensagens'][] = ['tipo' => $tipo, 'texto' => $texto];
}

/**
 * Consome e retorna todas as mensagens pendentes.
 *
 * @return array<int, array{tipo: string, texto: string}>
 */
function colherMensagens(): array
{
    $mensagens = $_SESSION['mensagens'] ?? [];
    unset($_SESSION['mensagens']);

    return $mensagens;
}

/**
 * Redireciona e encerra a execução (sempre após POST — padrão PRG).
 */
function redirecionar(string $destino): never
{
    header('Location: ' . $destino);
    exit;
}

/**
 * Conta caracteres de forma segura, com ou sem a extensão mbstring.
 */
function contarCaracteres(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

/**
 * Converte data ISO (YYYY-MM-DD) para o formato brasileiro (DD/MM/YYYY).
 */
function formatarDataBrasileira(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '—';
    }
    $partes = explode('-', $iso);
    if (count($partes) !== 3) {
        return $iso;
    }
    return sprintf('%02d/%02d/%04d', (int) $partes[2], (int) $partes[1], (int) $partes[0]);
}

/**
 * Retorna o nome por extenso de um dia da semana, em português.
 */
function nomeDiaSemana(string $iso): string
{
    $nomes = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira',
              'quinta-feira', 'sexta-feira', 'sábado'];

    return $nomes[(int) date('w', strtotime($iso))] ?? '';
}

/**
 * Retorna o nome por extenso de um mês, em português.
 */
function nomeMes(int $mes): string
{
    return [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ][$mes] ?? '';
}

/**
 * Soma minutos a um horário HH:MM e devolve no mesmo formato.
 */
function somarMinutos(string $hora, int $minutos): string
{
    [$horas, $min] = array_map('intval', explode(':', $hora));
    $total = $horas * 60 + $min + $minutos;

    return sprintf('%02d:%02d', intdiv($total, 60) % 24, $total % 60);
}

/**
 * Lê e valida um parâmetro de data no formato YYYY-MM-DD; usa hoje se inválido.
 */
function dataDoRequestParam(?string $valor): string
{
    if ($valor !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
        return $valor;
    }
    return date('Y-m-d');
}
