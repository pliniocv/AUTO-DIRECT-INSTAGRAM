<?php
// O fluxo do Direct: comentário, mensagens, botões e lembrete.
// Usado pelo webhook.php, pelo comentarios.php (comentários antigos) e pelo cron/lembrete.php.

require_once __DIR__ . '/lib.php';

// Aviso "feed" da Página do Facebook, convertido para o formato do Instagram que tratar_comentario() usa.
// Devolve null quando não é comentário novo (publicação, curtida, edição, exclusão).
function comentario_do_facebook(array $v): ?array
{
    if (($v['item'] ?? '') !== 'comment' || ($v['verb'] ?? '') !== 'add') {
        return null;
    }
    return [
        'id' => $v['comment_id'] ?? '',
        'text' => $v['message'] ?? '',
        'from' => ['id' => $v['from']['id'] ?? '', 'name' => $v['from']['name'] ?? ''],
        'media' => ['id' => $v['post_id'] ?? ''],
    ];
}

// $v no formato do aviso da Meta: id, text, from{id,username}, media{id}.
// Devolve true se mandou o fluxo.
function tratar_comentario(array $v): bool
{
    $quem = (string) ($v['from']['id'] ?? '');
    $comentario = (string) ($v['id'] ?? '');
    $media = !empty($v['media']['id']) ? (string) $v['media']['id'] : null;
    // No Facebook o "from" pode não vir: a resposta privada vai pelo ID do comentário, e o ID de quem
    // comentou entra no toque do botão (avancar). No Instagram ele sempre vem.
    if (!$comentario || ($quem === '' && canal() === 'ig') || $quem === conta_id()) {
        return false; // inclui as respostas do próprio perfil ou da Página
    }
    $palavra = achar_palavra((string) ($v['text'] ?? ''), $media);
    if (!$palavra) {
        return false;
    }
    $st = db()->prepare('INSERT IGNORE INTO interacoes (canal, comment_id, ig_user_id, username, palavra, media_id, token, etapa)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([canal(), $comentario, $quem, $v['from']['username'] ?? null, $palavra, $media, bin2hex(random_bytes(12)), 'novo']);
    if ($st->rowCount() === 0) {
        return false; // aviso repetido
    }
    $id = (int) db()->lastInsertId();
    $r = regras()[$palavra];

    // Resposta pública: no Instagram é /replies, no Facebook é /comments.
    api('POST', '/' . $comentario . (canal() === 'fb' ? '/comments' : '/replies'),
        ['message' => no_canal($r['publico'][array_rand($r['publico'])])]);

    $destino = ['comment_id' => $comentario];
    if ($quem !== '' && ja_e_lead($quem, $palavra)) {
        enviar_entrega($destino, $r);
        mudar_etapa($id, 'entregue');
        return true;
    }
    // Instagram, sem nome: antes de a pessoa interagir no Direct a Meta recusa o perfil
    // ("User consent is required to access user profile", erro 230, visto em 28/10/2026).
    // Facebook: o nome vem no próprio aviso do comentário, e fica guardado para as mensagens seguintes.
    $nome = limpar_nome((string) ($v['from']['name'] ?? ''));
    if ($nome !== '') {
        kv_set('nome_' . $id, $nome);
    }
    $dm1 = personalizar($r['dm1'], $nome);
    enviar_botao($destino, $dm1, ['type' => 'postback', 'title' => $r['dm1_botao'], 'payload' => "Q:$id"]);
    mudar_etapa($id, 'dm1');
    return true;
}

function tratar_mensagem(array $m): void
{
    $quem = (string) ($m['sender']['id'] ?? '');
    if (!$quem || $quem === conta_id() || !empty($m['message']['is_echo'])) {
        return;
    }
    $payload = $m['postback']['payload'] ?? $m['message']['quick_reply']['payload'] ?? null;
    if ($payload && preg_match('/^[QS]:(\d+)$/', $payload, $p)) {
        $i = interacao((int) $p[1]);
        if ($i) {
            avancar($i, $quem);
        }
        return;
    }

    $texto = (string) ($m['message']['text'] ?? '');
    if ($texto === '') {
        return;
    }
    $t = normalizar($texto);

    $em_ingles = strpos($t, 'DELETE MY DATA') !== false; // frase da página de exclusão em inglês
    if ($em_ingles || strpos($t, 'EXCLUIR MEUS DADOS') !== false) {
        db()->prepare('DELETE FROM leads WHERE ig_user_id = ?')->execute([$quem]);
        db()->prepare('DELETE FROM interacoes WHERE ig_user_id = ?')->execute([$quem]);
        enviar_texto(['id' => $quem], $em_ingles ? 'Done, I deleted your data from my system.' : 'Pronto, apaguei os seus dados do meu sistema.');
        registrar('exclusao', $quem);
        return;
    }

    // "QUERO" digitado, quando o botão não apareceu.
    if (preg_match('/(^|[^A-Z])QUERO([^A-Z]|$)/', $t)) {
        $st = db()->prepare("SELECT * FROM interacoes WHERE ig_user_id = ? AND etapa IN ('dm1','dm2','dm2_lembrete')
            AND criado_em > NOW() - INTERVAL 7 DAY ORDER BY id DESC LIMIT 1");
        $st->execute([$quem]);
        if ($i = $st->fetch()) {
            avancar($i, $quem);
            return;
        }
    }

    // Palavra mandada direto no Direct, sem comentário (serve para Stories: "me manda PLAYBOOK no Direct").
    $palavra = achar_palavra($texto, null, true);
    if (!$palavra) {
        return;
    }
    // A varredura das conversas (varrer_conversas) pode ter mandado o fluxo antes de o aviso da Meta chegar.
    // Mesma pessoa e mesma palavra na última hora: não manda de novo.
    $rep = db()->prepare('SELECT 1 FROM interacoes WHERE ig_user_id = ? AND palavra = ? AND criado_em > NOW() - INTERVAL 1 HOUR');
    $rep->execute([$quem, $palavra]);
    if ($rep->fetchColumn()) {
        return;
    }
    db()->prepare('INSERT INTO interacoes (canal, ig_user_id, palavra, token, etapa) VALUES (?, ?, ?, ?, ?)')
        ->execute([canal(), $quem, $palavra, bin2hex(random_bytes(12)), 'novo']);
    $i = interacao((int) db()->lastInsertId());
    $r = regras()[$palavra];
    $dm1 = personalizar($r['dm1'], primeiro_nome($quem));
    enviar_botao(['id' => $quem], $dm1, ['type' => 'postback', 'title' => $r['dm1_botao'], 'payload' => 'Q:' . $i['id']]);
    mudar_etapa((int) $i['id'], 'dm1');
}

// Depois do primeiro toque: confere se segue e manda o formulário.
function avancar(array $i, string $quem): void
{
    $id = (int) $i['id'];
    if ($i['ig_user_id'] !== $quem) {
        db()->prepare('UPDATE interacoes SET ig_user_id = ? WHERE id = ?')->execute([$quem, $id]);
    }
    $r = regras()[$i['palavra']] ?? null;
    if (!$r) {
        return;
    }
    $destino = ['id' => $quem];
    // Depois do toque no botão a Meta já libera o perfil: dá para usar o nome daqui em diante.
    [$segue, $nome] = perfil($quem);
    if ($nome !== '') {
        kv_set('nome_' . $id, $nome);
    }
    $nome = $nome !== '' ? $nome : nome_interacao($id);

    if (ja_e_lead($quem, $i['palavra'])) {
        enviar_entrega($destino, $r, $nome);
        mudar_etapa($id, 'entregue');
        return;
    }

    if ($segue === false) {
        $ja_pediu = strpos($i['etapa'], 'dm2') === 0;
        $texto = personalizar($ja_pediu ? $r['dm2_ainda_nao'] : $r['dm2'], $nome);
        enviar_botao($destino, $texto, ['type' => 'postback', 'title' => $r['dm2_botao'], 'payload' => "S:$id"]);
        mudar_etapa($id, 'dm2');
        return;
    }
    if ($segue === null) {
        registrar('segue_desconhecido', $quem); // a consulta falhou: não trava o lead
    }
    if (!empty($r['sem_form'])) {
        enviar_entrega($destino, $r, $nome);
        mudar_etapa($id, 'entregue');
        return;
    }
    enviar_botao($destino, personalizar($r['dm3'], $nome), ['type' => 'web_url', 'url' => link_form($i), 'title' => $r['dm3_botao']]);
    mudar_etapa($id, 'link');
}

// Lembrete único para quem parou no meio. A Meta só deixa mandar até 24 h depois da última mensagem
// da pessoa, então vai entre 20 e 23 h depois do último toque (o cron roda de hora em hora).
function mandar_lembretes(): int
{
    $st = db()->query("SELECT * FROM interacoes WHERE etapa IN ('link','dm2')
        AND atualizado_em <= NOW() - INTERVAL 20 HOUR AND atualizado_em > NOW() - INTERVAL 23 HOUR");
    $enviados = 0;
    foreach ($st->fetchAll() as $i) {
        canal($i['canal'] ?? 'ig');
        $r = regras()[$i['palavra']] ?? null;
        if (!$r || ja_e_lead($i['ig_user_id'], $i['palavra'])) {
            continue;
        }
        [, $nome] = perfil($i['ig_user_id']);
        $nome = $nome !== '' ? $nome : nome_interacao((int) $i['id']);
        $destino = ['id' => $i['ig_user_id']];
        if ($i['etapa'] === 'link') {
            $ok = enviar_botao($destino, personalizar($r['lembrete_link'] ?? 'Ainda dá tempo{nome}! O seu material continua separado. Leva menos de 1 minuto:', $nome),
                ['type' => 'web_url', 'url' => link_form($i), 'title' => $r['dm3_botao']]);
            mudar_etapa((int) $i['id'], 'link_lembrete');
        } else {
            $ok = enviar_botao($destino, personalizar($r['lembrete_dm2'] ?? 'Ainda dá tempo{nome}! É só seguir o perfil e tocar no botão que eu te envio na hora.', $nome),
                ['type' => 'postback', 'title' => $r['dm2_botao'], 'payload' => 'S:' . $i['id']]);
            mudar_etapa((int) $i['id'], 'dm2_lembrete');
        }
        $enviados += $ok ? 1 : 0;
    }
    return $enviados;
}

// Conversas do Direct das últimas 24 h (prazo da Meta para responder), lidas pela API, sem depender do aviso.
// Motivo: quem abre o Direct por um anúncio e manda a palavra às vezes não gera aviso nenhum da Meta, embora a API
// enxergue a conversa. Para cada conversa, pega a última mensagem da pessoa e diz se ela tem
// palavra, se já recebeu o fluxo (interacoes) e se o perfil respondeu à mão depois dela.
function varrer_conversas(): array
{
    [$st, $r] = api('GET', '/' . ig_id() . '/conversations', [
        'platform' => 'instagram',
        'fields' => 'id,updated_time,messages.limit(6){id,message,from,created_time}',
        'limit' => 40,
    ]);
    if ($st >= 400) {
        registrar('conversas_erro', $r);
        return ['erro' => json_encode($r, JSON_UNESCAPED_UNICODE), 'lista' => []];
    }
    $limite = time() - 24 * 3600 + 600; // dez minutos de folga antes do fim da janela
    $ja = db()->prepare('SELECT 1 FROM interacoes WHERE ig_user_id = ? AND palavra = ? AND criado_em > NOW() - INTERVAL 2 DAY');
    $lista = [];
    foreach ($r['data'] ?? [] as $c) {
        if (strtotime((string) ($c['updated_time'] ?? '')) < $limite) {
            continue;
        }
        // A API devolve da mais nova para a mais antiga: o que vem antes da mensagem da pessoa é resposta do perfil.
        $dela = null;
        $respondida = false;
        foreach ($c['messages']['data'] ?? [] as $msg) {
            $de = (string) ($msg['from']['id'] ?? '');
            if ($de === conta_id() || $de === ig_id()) {
                $respondida = true;
                continue;
            }
            $dela = $msg;
            break;
        }
        if (!$dela || strtotime((string) $dela['created_time']) < $limite) {
            continue;
        }
        $texto = (string) ($dela['message'] ?? '');
        $palavra = $texto !== '' ? achar_palavra($texto, null, true) : null;
        $atendida = false;
        if ($palavra) {
            $ja->execute([(string) $dela['from']['id'], $palavra]);
            $atendida = (bool) $ja->fetchColumn();
        }
        $lista[] = [
            'quando' => strtotime((string) $dela['created_time']),
            'username' => $dela['from']['username'] ?? '',
            'texto' => $texto,
            'palavra' => $palavra,
            'atendida' => $atendida,
            'respondida' => $respondida,
            'mensagem' => ['sender' => ['id' => (string) $dela['from']['id']], 'message' => ['text' => $texto]],
        ];
    }
    return ['erro' => null, 'lista' => $lista];
}

// Quem mandou palavra, não recebeu o fluxo e não foi respondido à mão.
function pendente(array $c): bool
{
    return $c['palavra'] && !$c['atendida'] && !$c['respondida'];
}

