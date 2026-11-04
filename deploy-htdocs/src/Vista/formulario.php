<?php

/**
 * Vista: formulário de criação/edição de agendamento.
 *
 * Variáveis esperadas: $agenda (ServicoAgenda), $agendamento (?Agendamento),
 *                     $valoresFormulario (array), $erros (string[]), $tokenCsrf.
 */

declare(strict_types=1);

use Agenda\Dominio\StatusAgendamento;
use function Agenda\Nucleo\e;

$isEdicao = $agendamento instanceof \Agenda\Dominio\Agendamento;

/** Busca um valor de campo com fallback. */
$valor = static function (string $campo, string $padrao = '') use ($valoresFormulario): string {
    return (string) ($valoresFormulario[$campo] ?? $padrao);
};
?>
<section class="cartao formulario">
    <h1 class="titulo-form"><?= $isEdicao ? 'Editar agendamento' : 'Novo agendamento' ?></h1>

    <?php if ($erros !== []): ?>
        <div class="aviso aviso-erro">
            <strong>Corrija os itens abaixo:</strong>
            <ul>
                <?php foreach ($erros as $erro): ?>
                    <li><?= e($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="index.php" novalidate>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="token" value="<?= e($tokenCsrf) ?>">
        <?php if ($isEdicao): ?>
            <input type="hidden" name="id" value="<?= (int) $agendamento->id ?>">
        <?php endif; ?>

        <div class="grade">
            <label class="campo campo-largo">
                <span class="rotulo">Cliente *</span>
                <input type="text" name="cliente" required maxlength="120"
                       value="<?= e($valor('cliente')) ?>" placeholder="Nome completo">
            </label>

            <label class="campo">
                <span class="rotulo">Telefone *</span>
                <input type="tel" name="telefone" required maxlength="20"
                       value="<?= e($valor('telefone')) ?>" placeholder="(11) 99999-0000">
            </label>

            <label class="campo">
                <span class="rotulo">Profissional *</span>
                <input type="text" name="profissional" required maxlength="80" list="lista-profissionais"
                       value="<?= e($valor('profissional')) ?>" placeholder="Quem atende">
                <datalist id="lista-profissionais">
                    <?php foreach ($agenda->profissionaisConhecidos() as $nome): ?>
                        <option value="<?= e($nome) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="campo campo-largo">
                <span class="rotulo">Procedimento *</span>
                <input type="text" name="procedimento" required maxlength="120"
                       value="<?= e($valor('procedimento')) ?>"
                       placeholder="Ex.: limpeza de pele, depilação a laser...">
            </label>

            <label class="campo">
                <span class="rotulo">Data *</span>
                <input type="date" name="data" required
                       value="<?= e($valor('data', date('Y-m-d'))) ?>">
            </label>

            <label class="campo">
                <span class="rotulo">Horário de início *</span>
                <input type="time" name="horaInicio" required
                       value="<?= e($valor('horaInicio', '09:00')) ?>">
            </label>

            <label class="campo">
                <span class="rotulo">Duração *</span>
                <select name="duracaoMinutos">
                    <?php foreach ([15, 30, 45, 60, 90, 120, 180, 240] as $minutos): ?>
                        <?php $atual = (int) $valor('duracaoMinutos', '60'); ?>
                        <option value="<?= $minutos ?>" <?= $atual === $minutos ? 'selected' : '' ?>>
                            <?= $minutos < 60 ? $minutos . ' min' : rtrim(rtrim(number_format($minutos / 60, 1, ',', ''), '0'), ',') . ' h' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="campo">
                <span class="rotulo">Status</span>
                <select name="status">
                    <?php foreach (StatusAgendamento::listaParaSelecao() as $opcao => $rotulo): ?>
                        <option value="<?= e($opcao) ?>" <?= $valor('status', 'pendente') === $opcao ? 'selected' : '' ?>>
                            <?= e($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="campo campo-largo">
                <span class="rotulo">Observações</span>
                <textarea name="observacoes" rows="3" maxlength="500"
                          placeholder="Alergias, preferências, lembretes..."><?= e($valor('observacoes')) ?></textarea>
            </label>
        </div>

        <div class="formulario-rodape">
            <a class="botao botao-suave" href="index.php?dia=<?= e($valor('data')) ?>">Cancelar</a>
            <button type="submit" class="botao botao-primario">
                <?= $isEdicao ? 'Salvar alterações' : 'Agendar' ?>
            </button>
        </div>
    </form>
</section>
