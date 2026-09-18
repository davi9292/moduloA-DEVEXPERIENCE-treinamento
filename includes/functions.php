<?php
/**
 * CEON - Plataforma Escolar
 * Funções utilitárias gerais.
 */

/**
 * Escapa dados para exibição segura em HTML (proteção contra XSS).
 */
function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Formata uma data no padrão brasileiro (dd/mm/aaaa) a partir de uma data SQL (aaaa-mm-dd).
 */
function formatarData(?string $dataSql): string
{
    if (empty($dataSql) || $dataSql === '0000-00-00') {
        return '-';
    }
    $dt = DateTime::createFromFormat('Y-m-d', substr($dataSql, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '-';
}

/**
 * Formata data e hora no padrão brasileiro.
 */
function formatarDataHora(?string $dataHoraSql): string
{
    if (empty($dataHoraSql)) {
        return '-';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dataHoraSql);
    return $dt ? $dt->format('d/m/Y \à\s H:i') : '-';
}

/**
 * Formata hora (HH:MM).
 */
function formatarHora(?string $horaSql): string
{
    if (empty($horaSql)) {
        return '-';
    }
    return substr($horaSql, 0, 5);
}

/**
 * Grava uma mensagem flash (sucesso/erro) na sessão, exibida uma única vez.
 */
function definirMensagem(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

/**
 * Recupera (e remove) a mensagem flash da sessão, se existir.
 */
function obterMensagem(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $msg;
    }
    return null;
}

/**
 * Imprime o HTML do alerta Bootstrap com a mensagem flash, se houver.
 */
function exibirMensagem(): void
{
    $msg = obterMensagem();
    if (!$msg) {
        return;
    }
    $classe = $msg['tipo'] === 'sucesso' ? 'alert-success' : 'alert-danger';
    $icone  = $msg['tipo'] === 'sucesso' ? '✓' : '✕';
    echo '<div class="alert ' . $classe . ' alert-dismissible fade show" role="alert">'
        . '<strong>' . $icone . '</strong> ' . e($msg['texto'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>'
        . '</div>';
}

/**
 * Retorna o rótulo amigável do tipo de usuário.
 */
function rotuloTipoUsuario(string $tipo): string
{
    $mapa = [
        'aluno'         => 'Aluno',
        'professor'     => 'Professor',
        'administrador' => 'Administrador',
    ];
    return $mapa[$tipo] ?? $tipo;
}

/**
 * Retorna o rótulo amigável do público-alvo.
 */
function rotuloPublicoAlvo(string $publico): string
{
    $mapa = [
        'todos'       => 'Todos',
        'alunos'      => 'Alunos',
        'professores' => 'Professores',
        'turma'       => 'Turma específica',
    ];
    return $mapa[$publico] ?? $publico;
}

/**
 * Retorna o rótulo amigável da refeição do cardápio.
 */
function rotuloRefeicao(string $refeicao): string
{
    $mapa = [
        'cafe_da_manha'   => 'Café da Manhã',
        'almoco'          => 'Almoço',
        'lanche_da_tarde' => 'Lanche da Tarde',
    ];
    return $mapa[$refeicao] ?? $refeicao;
}

/**
 * Retorna o nome do dia da semana em português a partir de uma data SQL.
 */
function diaSemanaPt(string $dataSql): string
{
    $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $timestamp = strtotime($dataSql);
    return $dias[(int) date('w', $timestamp)];
}

/**
 * Valida e move um arquivo enviado via formulário para a pasta de uploads.
 * Retorna o nome do arquivo salvo em caso de sucesso, ou lança Exception com a mensagem de erro.
 *
 * @param array $arquivo Elemento de $_FILES['campo']
 */
function processarUpload(array $arquivo, string $pastaDestino): string
{
    $extensoesPermitidas = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt', 'zip'];
    $mimesPermitidos = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
    ];
    $tamanhoMaximo = 10 * 1024 * 1024; // 10 MB

    if (!isset($arquivo) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('Nenhum arquivo foi selecionado.');
    }
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro ao enviar o arquivo (código ' . $arquivo['error'] . ').');
    }
    if ($arquivo['size'] > $tamanhoMaximo) {
        throw new Exception('O arquivo excede o tamanho máximo permitido (10 MB).');
    }

    $nomeOriginal = $arquivo['name'];
    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        throw new Exception('Extensão de arquivo não permitida. Envie: ' . implode(', ', $extensoesPermitidas) . '.');
    }

    // Nunca confiar apenas na extensão: valida o MIME type real do arquivo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeReal, $mimesPermitidos, true)) {
        throw new Exception('Tipo de arquivo inválido ou potencialmente perigoso.');
    }

    // Gera nome de arquivo seguro e único, removendo caracteres especiais
    $nomeBase = pathinfo($nomeOriginal, PATHINFO_FILENAME);
    $nomeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nomeBase);
    $nomeFinal = $nomeBase . '_' . uniqid() . '.' . $extensao;

    $destino = rtrim($pastaDestino, '/') . '/' . $nomeFinal;
    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new Exception('Não foi possível salvar o arquivo no servidor.');
    }

    return $nomeFinal;
}

/**
 * Calcula a média ponderada a partir de um array de notas com 'valor' e 'peso'.
 */
function calcularMediaPonderada(array $notas): float
{
    $somaValores = 0.0;
    $somaPesos = 0.0;
    foreach ($notas as $nota) {
        $somaValores += ((float) $nota['valor']) * ((float) $nota['peso']);
        $somaPesos += (float) $nota['peso'];
    }
    return $somaPesos > 0 ? round($somaValores / $somaPesos, 2) : 0.0;
}
