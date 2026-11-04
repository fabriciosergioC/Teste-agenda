<?php

/**
 * Domínio: status possíveis de um agendamento.
 */

declare(strict_types=1);

namespace Agenda\Dominio;

enum StatusAgendamento: string
{
    case Pendente = 'pendente';
    case Confirmado = 'confirmado';
    case Concluido = 'concluido';
    case Faltou = 'faltou';
    case Cancelado = 'cancelado';

    /**
     * Rótulo exibido na interface.
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Confirmado => 'Confirmado',
            self::Concluido => 'Concluído',
            self::Faltou => 'Faltou',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * Classe CSS usada para colorir o selo de status.
     */
    public function classeCss(): string
    {
        return 'selo-' . $this->value;
    }

    /**
     * Converte a string vinda da requisição em um status válido.
     */
    public static function deString(?string $valor): self
    {
        return self::tryFrom((string) $valor) ?? self::Pendente;
    }

    /**
     * Lista para montar selects: valor => rótulo.
     *
     * @return array<string, string>
     */
    public static function listaParaSelecao(): array
    {
        $lista = [];
        foreach (self::cases() as $caso) {
            $lista[$caso->value] = $caso->rotulo();
        }

        return $lista;
    }
}
