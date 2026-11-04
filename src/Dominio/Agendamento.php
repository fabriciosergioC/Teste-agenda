<?php

/**
 * Domínio: entidade Agendamento.
 *
 * Representa um horário reservado na agenda da clínica.
 * Campos em português, sem acento nos identificadores (padrão do projeto).
 */

declare(strict_types=1);

namespace Agenda\Dominio;

final class Agendamento
{
    public function __construct(
        public int $id,
        public string $cliente = '',
        public string $telefone = '',
        public string $procedimento = '',
        public string $profissional = '',
        public string $data = '',           // YYYY-MM-DD
        public string $horaInicio = '',     // HH:MM
        public int $duracaoMinutos = 60,
        public StatusAgendamento $status = StatusAgendamento::Pendente,
        public string $observacoes = '',
        public string $criadoEm = '',       // datetime ISO
        public string $atualizadoEm = '',   // datetime ISO
    ) {
    }

    /**
     * Monta a entidade a partir de um registro lido do banco JSON.
     *
     * @param array<string, mixed> $registro
     */
    public static function deRegistro(array $registro): self
    {
        return new self(
            id: (int) ($registro['id'] ?? 0),
            cliente: (string) ($registro['cliente'] ?? ''),
            telefone: (string) ($registro['telefone'] ?? ''),
            procedimento: (string) ($registro['procedimento'] ?? ''),
            profissional: (string) ($registro['profissional'] ?? ''),
            data: (string) ($registro['data'] ?? ''),
            horaInicio: (string) ($registro['horaInicio'] ?? ''),
            duracaoMinutos: (int) ($registro['duracaoMinutos'] ?? 60),
            status: StatusAgendamento::deString($registro['status'] ?? null),
            observacoes: (string) ($registro['observacoes'] ?? ''),
            criadoEm: (string) ($registro['criadoEm'] ?? ''),
            atualizadoEm: (string) ($registro['atualizadoEm'] ?? ''),
        );
    }

    /**
     * Converte a entidade em registro para o banco JSON.
     *
     * @return array<string, mixed>
     */
    public function paraRegistro(): array
    {
        return [
            'id' => $this->id,
            'cliente' => $this->cliente,
            'telefone' => $this->telefone,
            'procedimento' => $this->procedimento,
            'profissional' => $this->profissional,
            'data' => $this->data,
            'horaInicio' => $this->horaInicio,
            'duracaoMinutos' => $this->duracaoMinutos,
            'status' => $this->status->value,
            'observacoes' => $this->observacoes,
            'criadoEm' => $this->criadoEm,
            'atualizadoEm' => $this->atualizadoEm,
        ];
    }

    public function horaTermino(): string
    {
        return \Agenda\Nucleo\somarMinutos($this->horaInicio, $this->duracaoMinutos);
    }

    /**
     * Início em minutos desde 00:00 (para comparações e ordenação).
     */
    public function inicioEmMinutos(): int
    {
        [$h, $m] = array_map('intval', explode(':', $this->horaInicio));

        return $h * 60 + $m;
    }

    /**
     * Fim em minutos desde 00:00.
     */
    public function terminoEmMinutos(): int
    {
        return $this->inicioEmMinutos() + $this->duracaoMinutos;
    }

    /**
     * Dois agendamentos colidem quando ocupam o mesmo profissional no mesmo
     * dia com horários sobrepostos. Cancelados não bloqueiam horário.
     */
    public function colideCom(self $outro): bool
    {
        if ($this->data !== $outro->data) {
            return false;
        }
        if ($this->status === StatusAgendamento::Cancelado || $outro->status === StatusAgendamento::Cancelado) {
            return false;
        }
        if ($this->profissional !== $outro->profissional) {
            return false;
        }

        return $this->inicioEmMinutos() < $outro->terminoEmMinutos()
            && $outro->inicioEmMinutos() < $this->terminoEmMinutos();
    }
}
