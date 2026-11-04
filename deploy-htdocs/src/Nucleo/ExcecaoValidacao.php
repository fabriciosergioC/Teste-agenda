<?php

/**
 * Exceção lançada quando os dados de um agendamento são inválidos.
 *
 * Carrega a lista de mensagens de erro em português, prontas para exibição.
 */

declare(strict_types=1);

namespace Agenda\Nucleo;

use RuntimeException;

final class ExcecaoValidacao extends RuntimeException
{
    /** @var string[] */
    private array $erros;

    /**
     * @param string[] $erros
     */
    public function __construct(array $erros)
    {
        $this->erros = $erros;
        parent::__construct(implode(' | ', $erros));
    }

    /**
     * @return string[]
     */
    public function erros(): array
    {
        return $this->erros;
    }
}
