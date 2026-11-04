<?php

/**
 * Vista: agenda — calendário mensal + lista de horários do dia selecionado.
 *
 * Variáveis esperadas: $agenda (ServicoAgenda), $dia (ISO), $ano, $mes,
 *                     $contagem (data => qtd), $doDia (Agendamento[]), $tokenCsrf.
 */

declare(strict_types=1);

use Agenda\Dominio\StatusAgendamento;
use function Agenda\Nucleo\e;
use function Agenda\Nucleo\formatarDataBrasileira;
use function Agenda\Nucleo\nomeDiaSemana;
use function Agenda\Nucleo\nomeMes;

$hoje = date('Y-m-d');

// Navegação do calendário: mês anterior / próximo mês.
$mesAnterior = (new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes)))
    ->modify('-1 month');
$proximoMes = (new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes)))
    ->modify('+1 month');

// Grelha do mês: primeiro dia, última posição e semanas.
$primeiroDia = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes)))
    ->format('N'); // 1 = segunda ... 7 = domingo
$diasNoMes = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes)))
    ->modify('last day of this month')->format('j');

$celulas = [];
for ($i = 1; $i < $primeiroDia; $i++) {
    $celulas[] = null; // espaços antes do dia 1
}
for ($d = 1; $d <= $diasNoMes; $d++) {
    $celulas[] = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
}
while (count($celulas) % 7 !== 0) {
    $celulas[] = null;
}
?>
<div class="painel-agenda">
    <section class="cartao calendario">
        <div class="calendario-topo">
            <a class="botao botao-suave"
               href="index.php?ano=<?= $mesAnterior->format('Y') ?>&mes=<?= $mesAnterior->format('n') ?>&dia=<?= e($dia) ?>"
               aria-label="Mês anterior">‹</a>
            <h2><?= e(ucfirst(nomeMes($mes))) ?> de <?= $ano ?></h2>
            <a class="botao botao-suave"
               href="index.php?ano=<?= $proximoMes->format('Y') ?>&mes=<?= $proximoMes->format('n') ?>&dia=<?= e($dia) ?>"
               aria-label="Próximo mês">›</a>
        </div>

        <table class="grelha" role="grid" aria-label="Calendário do mês">
            <thead>
            <tr>
                <?php foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $rotulo): ?>
                    <th scope="col"><?= $rotulo ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach (array_chunk($celulas, 7) as $semana): ?>
                <tr>
                    <?php foreach ($semana as $celula): ?>
                        <?php if ($celula === null): ?>
                            <td class="vazia"></td>
                        <?php else: ?>
                            <?php $quantidade = $contagem[$celula] ?? 0; ?>
                            <td class="<?= $celula === $dia ? 'selecionado' : '' ?> <?= $celula === $hoje ? 'hoje' : '' ?>">
                                <a href="index.php?dia=<?= e($celula) ?>&ano=<?= $ano ?>&mes=<?= $mes ?>">
                                    <span class="numero"><?= (int) substr($celula, 8, 2) ?></span>
                                    <?php if ($quantidade > 0): ?>
                                        <span class="pingo"><?= $quantidade ?></span>
                                    <?php endif; ?>
                                </a>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="cartao dia">
        <div class="dia-topo">
            <div>
                <h2><?= e(ucfirst(nomeDiaSemana($dia))) ?>, <?= e(formatarDataBrasileira($dia)) ?></h2>
                <p class="subtitulo">
                    <?= count($doDia) === 0 ? 'Nenhum horário agendado'
                        : count($doDia) . (count($doDia) === 1 ? ' atendimento' : ' atendimentos') ?>
                </p>
            </div>
            <a class="botao botao-primario" href="index.php?tela=novo&dia=<?= e($dia) ?>">+ Agendar</a>
        </div>

        <?php if ($doDia === []): ?>
            <div class="vazio">
                <p>Dia livre. Aproveite para organizar os atendimentos da semana.</p>
                <a class="botao botao-primario" href="index.php?tela=novo&dia=<?= e($dia) ?>">
                    Criar primeiro agendamento
                </a>
            </div>
        <?php else: ?>
            <ul class="lista-horarios">
                <?php foreach ($doDia as $a): ?>
                    <li class="item <?= 'status-' . e($a->status->value) ?>">
                        <div class="horario">
                            <strong><?= e($a->horaInicio) ?></strong>
                            <span>até <?= e($a->horaTermino()) ?></span>
                        </div>

                        <div class="informacoes">
                            <p class="cliente"><?= e($a->cliente) ?></p>
                            <p class="detalhe">
                                <?= e($a->procedimento) ?> · <?= e($a->profissional) ?>
                                · <?= $a->duracaoMinutos ?> min
                            </p>
                            <p class="contato">📞 <?= e($a->telefone) ?></p>
                            <?php if ($a->observacoes !== ''): ?>
                                <p class="observacao"><?= e($a->observacoes) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="acoes">
                            <span class="selo <?= e($a->status->classeCss()) ?>"><?= e($a->status->rotulo()) ?></span>

                            <form method="post" action="index.php" class="form-status">
                                <input type="hidden" name="acao" value="status">
                                <input type="hidden" name="id" value="<?= (int) $a->id ?>">
                                <input type="hidden" name="dia" value="<?= e($dia) ?>">
                                <input type="hidden" name="token" value="<?= e($tokenCsrf) ?>">
                                <select name="status" title="Alterar status">
                                    <?php foreach (StatusAgendamento::listaParaSelecao() as $valor => $rotulo): ?>
                                        <option value="<?= e($valor) ?>" <?= $a->status->value === $valor ? 'selected' : '' ?>>
                                            <?= e($rotulo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="botao botao-mini" title="Aplicar status">OK</button>
                            </form>

                            <a class="botao botao-suave" href="index.php?tela=editar&id=<?= (int) $a->id ?>"
                               title="Editar">Editar</a>

                            <form method="post" action="index.php" class="form-excluir"
                                  onsubmit="return confirm('Remover o agendamento de <?= e(addslashes($a->cliente)) ?>?');">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $a->id ?>">
                                <input type="hidden" name="dia" value="<?= e($dia) ?>">
                                <input type="hidden" name="token" value="<?= e($tokenCsrf) ?>">
                                <button type="submit" class="botao botao-perigo">Excluir</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
