<?php
// Painel de instalação e diagnóstico. Pode abrir quantas vezes quiser: nada se repete errado.

require __DIR__ . '/lib.php';

$faltando = [];
// Para criar o banco bastam estes. Token e segredos aparecem como pendência na tabela.
foreach (['DB_NOME', 'DB_USUARIO', 'DB_SENHA', 'ADMIN_SENHA'] as $c) {
    if (constant($c) === 'COLE_AQUI') {
        $faltando[] = $c;
    }
}
if ($faltando) {
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="/estilo.css"><main class="caixa">'
        . '<h1>Falta preencher o config.php</h1><p>' . h(implode(', ', $faltando)) . '</p></main>';
    exit;
}

exigir_admin();

$passos = [];
function passo(string $nome, bool $ok, string $detalhe = ''): void
{
    global $passos;
    $passos[] = [$nome, $ok, $detalhe];
}

// GET direto na API do Facebook com um token passado à mão (o token() do canal ainda não existe aqui).
function fb_get(string $caminho, array $dados): array
{
    $ch = curl_init(GRAPH_FB . $caminho . '?' . http_build_query($dados));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $r = json_decode((string) curl_exec($ch), true) ?: [];
    curl_close($ch);
    return $r;
}

// Devolve [deu certo, detalhe]. Nunca põe token no detalhe nem no registro.
function ligar_pagina(string $token_usuario): array
{
    $longo = fb_get('/oauth/access_token', ['grant_type' => 'fb_exchange_token', 'client_id' => FB_APP_ID,
        'client_secret' => APP_SECRET, 'fb_exchange_token' => $token_usuario]);
    if (empty($longo['access_token'])) {
        return [false, 'troca do token recusada: ' . ($longo['error']['message'] ?? 'sem resposta')];
    }
    $alvo = fb_get('/' . FB_PAGINA, ['fields' => 'id,name', 'access_token' => $longo['access_token']]);
    $contas = fb_get('/me/accounts', ['fields' => 'id,name,access_token', 'limit' => 100, 'access_token' => $longo['access_token']]);
    if (isset($contas['error'])) {
        // Token de Página (painel do Messenger, ou Explorador com a Página escolhida), visto em 28/10/2026:
        // a Página não tem "accounts", o próprio /me já é ela. "category" só existe em Página, não em usuário.
        $eu = fb_get('/me', ['fields' => 'id,name,category', 'access_token' => $longo['access_token']]);
        if (!empty($eu['id']) && isset($eu['category'])) {
            $contas = ['data' => [['id' => $eu['id'], 'name' => $eu['name'] ?? '', 'access_token' => $longo['access_token']]]];
        }
    }
    // Se a consulta pelo endereço falhar e o token gerenciar uma página só, é ela.
    $alvo_id = $alvo['id'] ?? (count($contas['data'] ?? []) === 1 ? ($contas['data'][0]['id'] ?? '') : '');
    if ($alvo_id === '') {
        // Última tentativa: a Página que a pessoa autorizou na janela da Meta, lida do próprio token.
        $dbg = fb_get('/debug_token', ['input_token' => $longo['access_token'], 'access_token' => $longo['access_token']]);
        foreach ($dbg['data']['granular_scopes'] ?? [] as $g) {
            if (($g['scope'] ?? '') === 'pages_messaging' && !empty($g['target_ids'][0])) {
                $alvo_id = (string) $g['target_ids'][0];
            }
        }
    }
    if ($alvo_id !== '' && !in_array($alvo_id, array_column($contas['data'] ?? [], 'id'), true)) {
        // Página de portfólio empresarial (visto em 28/10/2026): /me/accounts vem vazio, mas a Página
        // pedida pelo ID devolve o token dela.
        $p = fb_get('/' . $alvo_id, ['fields' => 'id,name,access_token', 'access_token' => $longo['access_token']]);
        if (!empty($p['access_token'])) {
            $contas['data'][] = $p;
        }
    }
    foreach ($contas['data'] ?? [] as $p) {
        if ($alvo_id !== '' && ($p['id'] ?? '') === $alvo_id && !empty($p['access_token'])) {
            kv_set('fb_token', $p['access_token']);
            kv_set('fb_page_id', $p['id']);
            kv_set('fb_page_nome', (string) ($p['name'] ?? ''));
            // Validade do token guardado: 0 quer dizer que não vence.
            $dbg = fb_get('/debug_token', ['input_token' => $p['access_token'], 'access_token' => FB_APP_ID . '|' . APP_SECRET]);
            $vence = (int) ($dbg['data']['expires_at'] ?? -1);
            $validade = $vence === 0 ? 'token não vence' : ($vence > 0 ? 'token vence em ' . date('d/m/Y', $vence) : 'validade não informada');
            kv_set('fb_token_vence', $validade);
            registrar('fb_pagina_ligada', ['id' => $p['id'], 'nome' => $p['name'] ?? '', 'validade' => $validade]);
            return [true, ($p['name'] ?? '') . ' (ID ' . $p['id'] . '), ' . $validade];
        }
    }
    $nomes = implode(', ', array_map(fn($p) => $p['name'] ?? '?', $contas['data'] ?? []));
    return [false, 'a página ' . FB_PAGINA . ' não está entre as que o token gerencia: '
        . ($nomes ?: ($contas['error']['message'] ?? $alvo['error']['message'] ?? 'nenhuma'))];
}

$banco_ok = false;
try {
    db();
    passo('Banco conectado', true);
    foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/banco.sql')))) as $sql) {
        if (stripos($sql, 'CREATE') !== false) {
            db()->exec(preg_replace('/^--.*$/m', '', $sql));
        }
    }
    passo('Tabelas criadas', true);
    $banco_ok = true;
} catch (Throwable $e) {
    passo('Banco', false, $e->getMessage());
}

foreach (['APP_SECRET', 'VERIFY_TOKEN', 'IG_TOKEN_INICIAL'] as $c) {
    passo($c . ' no config.php', constant($c) !== 'COLE_AQUI', constant($c) === 'COLE_AQUI' ? 'falta preencher' : 'preenchido');
}

if ($banco_ok && (IG_TOKEN_INICIAL !== 'COLE_AQUI' || kv_get('ig_token'))) {
    db()->exec("DELETE FROM kv WHERE chave = 'ig_user_id'"); // busca de novo, caso o token tenha mudado
    $id = ig_id();
    passo('Token do Instagram', (bool) $id, $id ? '@' . kv_get('ig_username') . ' (ID ' . $id . ')' : 'Token recusado. Veja os eventos abaixo.');

    if ($id && isset($_POST['assinar'])) {
        [$st, $r] = api('POST', '/' . $id . '/subscribed_apps?subscribed_fields=comments,messages,messaging_postbacks');
        passo('Assinatura dos avisos (comments, messages, messaging_postbacks)', $st < 400 && !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
    }
    if (isset($_POST['testar_email'])) {
        $via = defined('USAR_SMTP') && USAR_SMTP && defined('SMTP_SENHA') && SMTP_SENHA !== 'COLE_AQUI'
            ? 'SMTP ' . (defined('SMTP_HOST') ? SMTP_HOST : 'smtp-relay.brevo.com') : 'mail() da hospedagem';
        // Um segundo endereço (um Gmail seu, por exemplo) ajuda a ler o cabeçalho Authentication-Results
        // (SPF, DKIM, DMARC) quando o e-mail do domínio barra a mensagem.
        $outro = trim((string) ($_POST['outro_email'] ?? ''));
        $para = ($_POST['testar_email'] ?? '') === 'outro' && filter_var($outro, FILTER_VALIDATE_EMAIL) ? $outro : AVISO_EMAIL;
        $ok = enviar_email($para, 'Direct do Instagram', 'Teste do aviso de lead', "Se este e-mail chegou, os avisos de lead estão funcionando.\n\nEnviado por: $via\nQuando: " . date('d/m/Y H:i'));
        passo("E-mail de teste para $para via $via", $ok, $ok ? 'enviado: confira a caixa de entrada e o spam' : 'falhou: veja os eventos abaixo');
    }
    if ($id) {
        [$st, $r] = api('GET', '/' . $id . '/subscribed_apps');
        passo('Avisos assinados hoje', $st < 400, json_encode($r['data'] ?? $r, JSON_UNESCAPED_UNICODE));
    }
    passo('Palavras ativas', true, implode(', ', array_keys(regras())));
    $vence = kv_get('ig_token_vence');
    passo('Token vence em', true, $vence ? date('d/m/Y', strtotime($vence)) : 'ainda não renovado pelo cron (o inicial dura 60 dias)');
}

// Página do Facebook. O FB_TOKEN_INICIAL é um token de usuário (Explorador da Graph API) e vence em 1 hora:
// "Ligar a Página" troca por um de longa duração e, com ele, pega o token da Página, que não vence.
$fb_inicial = defined('FB_TOKEN_INICIAL') && FB_TOKEN_INICIAL !== 'COLE_AQUI' ? FB_TOKEN_INICIAL : '';
if ($banco_ok) {
    if (isset($_POST['ligar_fb'])) {
        [$ok, $detalhe] = FB_APP_ID === '' || FB_PAGINA === '' ? [false, 'falta FB_APP_ID ou FB_PAGINA no config.php']
            : ($fb_inicial ? ligar_pagina($fb_inicial) : [false, 'falta FB_TOKEN_INICIAL no config.php']);
        passo('Ligar a Página do Facebook', $ok, $detalhe);
    }
    canal('fb');
    $pagina = kv_get('fb_page_id');
    passo('Página do Facebook', (bool) $pagina, $pagina ? kv_get('fb_page_nome') . " (ID $pagina), " . kv_get('fb_token_vence') : 'ainda não ligada');
    if ($pagina && isset($_POST['assinar_fb'])) {
        [$st, $r] = api('POST', '/' . $pagina . '/subscribed_apps?subscribed_fields=feed,messages,messaging_postbacks');
        passo('Assinatura dos avisos da Página (feed, messages, messaging_postbacks)', $st < 400 && !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
    }
    if ($pagina) {
        [$st, $r] = api('GET', '/' . $pagina . '/subscribed_apps');
        passo('Avisos da Página assinados hoje', $st < 400, json_encode($r['data'] ?? $r, JSON_UNESCAPED_UNICODE));
    }
    canal('ig');
    $eventos = db()->query('SELECT criado_em, tipo, LEFT(detalhe, 600) d FROM eventos ORDER BY id DESC LIMIT 25')->fetchAll();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Instalação do Direct</title>
<link rel="stylesheet" href="/estilo.css">
</head>
<body>
<main class="caixa larga">
  <p class="marca"><?= h(MARCA) ?></p>
  <h1>Instalação do Direct</h1>
  <p><a href="/leads.php">Leads</a> · <a href="/comentarios.php">Comentários</a> · <a href="/conversas.php">Conversas</a></p>
  <div class="tabela"><table>
    <?php foreach ($passos as [$nome, $ok, $detalhe]): ?>
    <tr><td class="<?= $ok ? 'ok' : 'falha' ?>"><?= $ok ? 'OK' : 'FALHOU' ?></td><td><?= h($nome) ?></td><td><?= h($detalhe) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <form method="post"><button name="assinar" value="1">Assinar os avisos do Instagram</button></form>
  <form method="post" style="margin-top:12px"><button name="ligar_fb" value="1">Ligar a Página do Facebook</button>
    <button name="assinar_fb" value="1">Assinar os avisos da Página</button></form>
  <form method="post" style="margin-top:12px"><button name="testar_email" value="1">Testar e-mail de aviso</button></form>
  <form method="post" style="margin-top:12px"><label>Testar para outro endereço (diagnóstico)
    <input type="email" name="outro_email" placeholder="seuemail@gmail.com"></label>
    <button name="testar_email" value="outro">Enviar teste</button></form>

  <?php if (!empty($eventos)): ?>
  <h2>Últimos eventos</h2>
  <div class="tabela"><table>
    <tr><th>Quando</th><th>Tipo</th><th>Detalhe</th></tr>
    <?php foreach ($eventos as $e): ?>
    <tr><td><?= h(date('d/m H:i:s', strtotime($e['criado_em']))) ?></td><td><?= h($e['tipo']) ?></td><td><?= h($e['d']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</main>
</body>
</html>
