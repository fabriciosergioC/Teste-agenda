<?php

/**
 * Serviço: regras de negócio dos agendamentos sobre o banco JSON.
 *
 * Estrutura do banco:
 * {
 *   "agendamentos": [ {id, cliente, telefone, ...}, ... ],
 *   "proximoId": 12
 * }
 */

declare(strict_types=1);

namespace Agenda\Servico;

use Agenda\Dominio\Agendamento;
use Agenda\Dominio\StatusAgendamento;
use Agenda\Nucleo\BaseJson;
use Agenda\Nucleo\ExcecaoValidacao;
use DateTimeImmutable;

final class ServicoAgenda
{
    private const Tabela = 'agendamentos';
    private const Sequencia = 'proximoId';

    private const DuracaoMinima = 15;
    private const DuracaoMaxima = 480;

    public function __construct(
        private readonly BaseJson $base,
    ) {
    }

    // ------------------------------------------------------------------
    // Leitura
    // ------------------------------------------------------------------

    /**
     * Todos os agendamentos, ordenados por data e horário.
     *
     * @return Agendamento[]
     */
    public function listarTodos(): array
    {
        $lista = $this->carregarRegistros();
        usort(
            $lista,
            static fn(Agendamento $a, Agendamento $b): int
                => [$a->data, $a->inicioEmMinutos()] <=> [$b->data, $b->inicioEmMinutos()],
        );

        return $lista;
    }

    /**
     * Agendamentos de um dia específico, em ordem de horário.
     *
     * @return Agendamento[]
     */
    public function listarPorData(string $data): array
    {
        return array_values(array_filter(
            $this->listarTodos(),
            static fn(Agendamento $a): bool => $a->data === $data
                && $a->status !== StatusAgendamento::Cancelado,
        ));
    }

    public function buscar(int $id): ?Agendamento
    {
        foreach ($this->carregarRegistros() as $agendamento) {
            if ($agendamento->id === $id) {
                return $agendamento;
            }
        }

        return null;
    }

    /**
     * Quantidade de agendamentos ativos por dia do mês (para o calendário).
     *
     * @return array<string, int> data ISO => quantidade
     */
    public function contagemPorDiaDoMes(int $ano, int $mes): array
    {
        $contagem = [];
        $prefixo = sprintf('%04d-%02d', $ano, $mes);

        foreach ($this->carregarRegistros() as $agendamento) {
            if ($agendamento->status === StatusAgendamento::Cancelado) {
                continue;
            }
            if (str_starts_with($agendamento->data, $prefixo)) {
                $contagem[$agendamento->data] = ($contagem[$agendamento->data] ?? 0) + 1;
            }
        }

        return $contagem;
    }

    /**
     * Profissionais já cadastrados (para sugestões no formulário).
     *
     * @return string[]
     */
    public function profissionaisConhecidos(): array
    {
        $nomes = [];
        foreach ($this->carregarRegistros() as $agendamento) {
            if ($agendamento->profissional !== '') {
                $nomes[$agendamento->profissional] = true;
            }
        }
        $lista = array_keys($nomes);
        sort($lista);

        return $lista;
    }

    // ------------------------------------------------------------------
    // Escrita
    // ------------------------------------------------------------------

    /**
     * Valida e grava (cria ou atualiza) um agendamento.
     *
     * @param array<string, mixed> $entrada Dados brutos do formulário.
     * @param int|null $id Id a atualizar; null cria um novo registro.
     *
     * @throws ExcecaoValidacao Com as mensagens de erro em português.
     */
    public function salvar(array $entrada, ?int $id = null): Agendamento
    {
        $dados = $this->limparEntrada($entrada);
        $erros = $this->validar($dados);

        $agendamento = new Agendamento(
            id: $id ?? 0,
            cliente: $dados['cliente'],
            telefone: $dados['telefone'],
            procedimento: $dados['procedimento'],
            profissional: $dados['profissional'],
            data: $dados['data'],
            horaInicio: $dados['horaInicio'],
            duracaoMinutos: $dados['duracaoMinutos'],
            status: StatusAgendamento::deString($dados['status']),
            observacoes: $dados['observacoes'],
        );

        if ($agendamento->status !== StatusAgendamento::Cancelado) {
            $conflito = $this->encontrarConflito($agendamento);
            if ($conflito !== null) {
                $erros[] = sprintf(
                    'Conflito de horário: %s já está agendada para %s em %s (%s às %s).',
                    $conflito->procedimento,
                    $conflito->cliente,
                    \Agenda\Nucleo\formatarDataBrasileira($conflito->data),
                    $conflito->profissional,
                    $conflito->horaInicio,
                );
            }
        }

        if ($erros !== []) {
            throw new ExcecaoValidacao($erros);
        }

        $agendamentos = $this->carregarRegistros();
        $agora = date('c');

        if ($id === null) {
            $banco = $this->base->lerTudo();
            $novoId = (int) ($banco[self::Sequencia] ?? 1);
            $agendamento->id = $novoId;
            $agendamento->criadoEm = $agora;
            $agendamento->atualizadoEm = $agora;
            $agendamentos[] = $agendamento;

            $this->base->gravarTudo([
                self::Tabela => array_map(
                    static fn(Agendamento $a): array => $a->paraRegistro(),
                    $agendamentos,
                ),
                self::Sequencia => $novoId + 1,
            ]);

            return $agendamento;
        }

        $posicao = $this->localizar($agendamentos, $id);
        $agendamento->criadoEm = $agendamentos[$posicao]->criadoEm;
        $agendamento->atualizadoEm = $agora;
        $agendamentos[$posicao] = $agendamento;

        $this->gravarLista($agendamentos);

        return $agendamento;
    }

    /**
     * Remove definitivamente um agendamento.
     */
    public function excluir(int $id): void
    {
        $agendamentos = $this->carregarRegistros();
        $filtrados = array_values(array_filter(
            $agendamentos,
            static fn(Agendamento $a): bool => $a->id !== $id,
        ));

        if (count($filtrados) === count($agendamentos)) {
            return; // não existia; nada a fazer
        }

        $this->gravarLista($filtrados);
    }

    /**
     * Altera apenas o status de um agendamento.
     */
    public function alterarStatus(int $id, StatusAgendamento $novo): void
    {
        $agendamentos = $this->carregarRegistros();
        $posicao = $this->localizar($agendamentos, $id);

        $agendamentos[$posicao]->status = $novo;
        $agendamentos[$posicao]->atualizadoEm = date('c');

        $this->gravarLista($agendamentos);
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /**
     * @param Agendamento[] $lista
     */
    private function gravarLista(array $lista): void
    {
        $banco = $this->base->lerTudo();
        $sequencia = max(
            (int) ($banco[self::Sequencia] ?? 1),
            ...array_map(static fn(Agendamento $a): int => $a->id + 1, $lista ?: [new Agendamento(id: 1)]),
        );

        $this->base->gravarTudo([
            self::Tabela => array_map(
                static fn(Agendamento $a): array => $a->paraRegistro(),
                array_values($lista),
            ),
            self::Sequencia => $sequencia,
        ]);
    }

    /**
     * @return Agendamento[]
     */
    private function carregarRegistros(): array
    {
        $banco = $this->base->lerTudo();
        $registros = $banco[self::Tabela] ?? [];

        $lista = [];
        foreach ((array) $registros as $registro) {
            if (is_array($registro)) {
                $lista[] = Agendamento::deRegistro($registro);
            }
        }

        return $lista;
    }

    /**
     * @param Agendamento[] $lista
     */
    private function localizar(array $lista, int $id): int
    {
        foreach ($lista as $posicao => $agendamento) {
            if ($agendamento->id === $id) {
                return $posicao;
            }
        }

        throw new ExcecaoValidacao(['Agendamento não encontrado.']);
    }

    /**
     * @return Agendamento|null
     */
    private function encontrarConflito(Agendamento $novo): ?Agendamento
    {
        foreach ($this->carregarRegistros() as $existente) {
            if ($existente->id === $novo->id) {
                continue;
            }
            if ($novo->colideCom($existente)) {
                return $existente;
            }
        }

        return null;
    }

    /**
     * Normaliza os valores brutos do formulário.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array<string, mixed>
     */
    private function limparEntrada(array $entrada): array
    {
        return [
            'cliente' => trim((string) ($entrada['cliente'] ?? '')),
            'telefone' => trim((string) ($entrada['telefone'] ?? '')),
            'procedimento' => trim((string) ($entrada['procedimento'] ?? '')),
            'profissional' => trim((string) ($entrada['profissional'] ?? '')),
            'data' => trim((string) ($entrada['data'] ?? '')),
            'horaInicio' => trim((string) ($entrada['horaInicio'] ?? '')),
            'duracaoMinutos' => (int) ($entrada['duracaoMinutos'] ?? 0),
            'status' => trim((string) ($entrada['status'] ?? 'pendente')),
            'observacoes' => trim((string) ($entrada['observacoes'] ?? '')),
        ];
    }

    /**
     * Regras de validação. Retorna mensagens em português.
     *
     * @param array<string, mixed> $dados Já normalizados por limparEntrada().
     *
     * @return string[]
     */
    private function validar(array $dados): array
    {
        $erros = [];

        if (\Agenda\Nucleo\contarCaracteres($dados['cliente']) < 2) {
            $erros[] = 'Informe o nome do cliente.';
        }
        if ($dados['telefone'] === '') {
            $erros[] = 'Informe o telefone de contato.';
        }
        if ($dados['procedimento'] === '') {
            $erros[] = 'Informe o procedimento estético.';
        }
        if ($dados['profissional'] === '') {
            $erros[] = 'Informe o profissional responsável.';
        }

        $data = DateTimeImmutable::createFromFormat('Y-m-d', $dados['data']);
        if ($data === false || $data->format('Y-m-d') !== $dados['data']) {
            $erros[] = 'Data inválida.';
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $dados['horaInicio']) !== 1) {
            $erros[] = 'Horário de início inválido.';
        }

        $duracao = $dados['duracaoMinutos'];
        if ($duracao < self::DuracaoMinima || $duracao > self::DuracaoMaxima) {
            $erros[] = sprintf(
                'A duração deve ser entre %d minutos e %d horas.',
                self::DuracaoMinima,
                intdiv(self::DuracaoMaxima, 60),
            );
        }

        return $erros;
    }
}
