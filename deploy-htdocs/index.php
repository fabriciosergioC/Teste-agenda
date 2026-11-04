<?php

/**
 * Controlador único da aplicação (front controller).
 *
 * Rotas via parâmetro:
 *   GET  index.php                      -> agenda do dia + calendário
 *   GET  index.php?tela=novo&dia=...    -> formulário de criação
 *   GET  index.php?tela=editar&id=N     -> formulário de edição
 *   POST index.php (acao=salvar)        -> cria/atualiza agendamento
 *   POST index.php (acao=excluir)       -> remove agendamento
 *   POST index.php (acao=status)        -> altera apenas o status
 */

declare(strict_types=1);

namespace Agenda;

use Agenda\Dominio\Agendamento;
use Agenda\Dominio\StatusAgendamento;
use Agenda\Nucleo\BaseJson;
use Agenda\Nucleo\ExcecaoValidacao;
use function Agenda\Nucleo\colherMensagens;
use function Agenda\Nucleo\dataDoRequestParam;
use function Agenda\Nucleo\guardarMensagem;
use function Agenda\Nucleo\redirecionar;
use Agenda\Servico\ServicoAgenda;

session_start();
require __DIR__ . '/src/iniciar.php';

// ---------------------------------------------------------------------
// Token anti-CSRF simples (sessão) — enviado em todo formulário.
// ---------------------------------------------------------------------
if (empty($_SESSION['tokenCsrf'])) {
    $_SESSION['tokenCsrf'] = bin2hex(random_bytes(16));
}
$tokenCsrf = $_SESSION['tokenCsrf'];

function tokenValido(): bool
{
    return isset($_POST['token'])
        && hash_equals($_SESSION['tokenCsrf'] ?? '', (string) $_POST['token']);
}

$base = new BaseJson(CaminhoBancoJson);
$agenda = new ServicoAgenda($base);

$diaSelecionado = dataDoRequestParam($_GET['dia'] ?? $_POST['dia'] ?? null);

// ---------------------------------------------------------------------
// Tratamento das ações enviadas por POST
// ---------------------------------------------------------------------
$acaoPost = $_POST['acao'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tokenValido()) {
        guardarMensagem('erro', 'Sessão expirada. Tente novamente.');
        redirecionar('index.php?dia=' . urlencode($diaSelecionado));
    }

    try {
        switch ($acaoPost) {
            case 'salvar':
                $id = (($_POST['id'] ?? '') !== '') ? (int) $_POST['id'] : null;
                $salvo = $agenda->salvar($_POST, $id);
                guardarMensagem(
                    'sucesso',
                    ($id === null ? 'Agendamento criado' : 'Agendamento atualizado')
                        . ' com sucesso.',
                );
                redirecionar('index.php?dia=' . urlencode($salvo->data));
                // @codeCoverageIgnoreStart — redirecionar() encerra a execução
                break;

            case 'excluir':
                $agenda->excluir((int) ($_POST['id'] ?? 0));
                guardarMensagem('sucesso', 'Agendamento removido da agenda.');
                redirecionar('index.php?dia=' . urlencode($diaSelecionado));
                break;

            case 'status':
                $novoStatus = StatusAgendamento::deString($_POST['status'] ?? null);
                $agenda->alterarStatus((int) ($_POST['id'] ?? 0), $novoStatus);
                guardarMensagem('sucesso', 'Status alterado para ' . $novoStatus->rotulo() . '.');
                redirecionar('index.php?dia=' . urlencode($diaSelecionado));
                break;

            default:
                guardarMensagem('erro', 'Ação desconhecida.');
                redirecionar('index.php?dia=' . urlencode($diaSelecionado));
        }
    } catch (ExcecaoValidacao $excecao) {
        if ($acaoPost !== 'salvar') {
            // Falha em excluir/status: volta para a agenda com a mensagem.
            guardarMensagem('erro', $excecao->getMessage());
            redirecionar('index.php?dia=' . urlencode($diaSelecionado));
        }

        // Reexibe o formulário com os dados digitados e as mensagens de erro.
        exibirTela('formulario', [
            'tituloPagina' => 'Novo agendamento',
            'agenda' => $agenda,
            'agendamento' => null,
            'valoresFormulario' => $_POST,
            'erros' => $excecao->erros(),
            'tokenCsrf' => $tokenCsrf,
        ]);
        exit;
    }
}

// ---------------------------------------------------------------------
// Rotas de leitura (GET)
// ---------------------------------------------------------------------
$tela = $_GET['tela'] ?? 'agenda';

switch ($tela) {
    case 'novo':
        exibirTela('formulario', [
            'tituloPagina' => 'Novo agendamento',
            'agenda' => $agenda,
            'agendamento' => null,
            'valoresFormulario' => [
                'data' => $diaSelecionado,
                'horaInicio' => '09:00',
                'duracaoMinutos' => 60,
                'status' => StatusAgendamento::Pendente->value,
            ],
            'erros' => [],
            'tokenCsrf' => $tokenCsrf,
        ]);
        break;

    case 'editar':
        $agendamento = $agenda->buscar((int) ($_GET['id'] ?? 0));
        if ($agendamento === null) {
            guardarMensagem('erro', 'Agendamento não encontrado.');
            redirecionar('index.php');
        }
        exibirTela('formulario', [
            'tituloPagina' => 'Editar agendamento',
            'agenda' => $agenda,
            'agendamento' => $agendamento,
            'valoresFormulario' => $agendamento->paraRegistro(),
            'erros' => [],
            'tokenCsrf' => $tokenCsrf,
        ]);
        break;

    case 'agenda':
    default:
        // Mês exibido no calendário (padrão: mês do dia selecionado).
        $ano = max(1970, min(2999, (int) ($_GET['ano'] ?? date('Y', strtotime($diaSelecionado)))));
        $mes = max(1, min(12, (int) ($_GET['mes'] ?? date('n', strtotime($diaSelecionado)))));

        exibirTela('agenda', [
            'tituloPagina' => 'Agenda de estética',
            'agenda' => $agenda,
            'dia' => $diaSelecionado,
            'ano' => $ano,
            'mes' => $mes,
            'contagem' => $agenda->contagemPorDiaDoMes($ano, $mes),
            'doDia' => $agenda->listarPorData($diaSelecionado),
            'mensagens' => colherMensagens(),
            'tokenCsrf' => $tokenCsrf,
        ]);
        break;
}
