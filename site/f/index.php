<?php
// Formulário: nome, e-mail e WhatsApp antes de liberar o material.
// Em português ou em inglês, conforme a palavra (campo 'idioma' da regra).

require dirname(__DIR__) . '/lib.php';

$tk = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$st = db()->prepare('SELECT * FROM interacoes WHERE token = ?');
$st->execute([$tk]);
$i = $tk !== '' ? $st->fetch() : null;
$r = $i ? (regras()[$i['palavra']] ?? null) : null;
if ($i) {
    canal($i['canal'] ?? 'ig'); // a entrega volta pelo mesmo canal: Direct ou Messenger
}
$en = $i && idioma($i['palavra']) === 'en';

$t = $en ? [
    'lang' => 'en',
    'link_morto' => 'This link is no longer valid. Comment the word again on the post and I will send you a new one.',
    'expirado' => 'Link expired',
    'nome' => 'Please write your name.',
    'email' => 'Please check your email.',
    'whats' => 'Please add your WhatsApp with the country code.',
    'aceite' => 'Please tick the box to continue.',
    'copiar' => 'Copy',
    'copiado' => 'Copied',
    'tambem' => 'I also sent it to your DMs.',
    'falta' => 'Just one more step. Fill this in and the step by step opens right away.',
    'rot_nome' => 'Name',
    'rot_email' => 'Email',
    'rot_whats' => 'WhatsApp with country code',
    'exemplo' => '+1 555 123 4567',
    'aceite_txt' => 'I agree to receive content from ' . MARCA . ' by email and WhatsApp. I can opt out anytime.',
    'privacidade' => 'Privacy',
    'link_privacidade' => '/privacidade/en/',
    'enviar' => 'Get it now',
] : [
    'lang' => 'pt-BR',
    'link_morto' => 'Este link não vale mais. Comente a palavra de novo no post que eu te mando outro.',
    'expirado' => 'Link expirado',
    'nome' => 'Escreva o seu nome.',
    'email' => 'Confira o e-mail.',
    'whats' => 'Coloque o WhatsApp com DDD.',
    'aceite' => 'Marque a caixinha para continuar.',
    'copiar' => 'Copiar',
    'copiado' => 'Copiado',
    'tambem' => no_canal('Também te mandei no Direct.'),
    'falta' => 'Falta só isso. Preencha e o material abre na hora.',
    'rot_nome' => 'Nome',
    'rot_email' => 'E-mail',
    'rot_whats' => 'WhatsApp com DDD',
    'exemplo' => WHATSAPP_EXEMPLO,
    'aceite_txt' => 'Aceito receber conteúdos de ' . MARCA . ' por e-mail e WhatsApp. Posso pedir para sair quando quiser.',
    'privacidade' => 'Privacidade',
    'link_privacidade' => '/privacidade/',
    'enviar' => 'Liberar agora',
];
if ($en && canal() === 'fb') {
    $t['tambem'] = 'I also sent it to you on Messenger.';
}

if (!$i || !$r) {
    http_response_code(404);
    $erro_fatal = $t['link_morto'];
}

// Com 'mostrar_entrega', a página mostra o texto da entrega e um botão para o link, em vez de ir direto para ele.
$redireciona = !empty($r['url']) && empty($r['mostrar_entrega']);

$concluido = $i && $r && ja_e_lead((string) $i['ig_user_id'], $i['palavra']);
if ($concluido && $redireciona) {
    header('Location: ' . $r['url'], true, 303);
    exit;
}

$erros = [];
$v = ['nome' => '', 'email' => '', 'whats' => ''];

if (empty($erro_fatal) && !$concluido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['nome'] = trim((string) ($_POST['nome'] ?? ''));
    $v['email'] = trim((string) ($_POST['email'] ?? ''));
    $v['whats'] = trim((string) ($_POST['whats'] ?? ''));
    $digitos = preg_replace('/\D/', '', $v['whats']);

    if (mb_strlen($v['nome']) < 2) {
        $erros['nome'] = $t['nome'];
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = $t['email'];
    }
    // Brasil: DDD + número (10 a 13 dígitos). Fora: código do país + número (8 a 15, o máximo internacional).
    [$min, $max] = $en ? [8, 15] : [10, 13];
    if (strlen($digitos) < $min || strlen($digitos) > $max) {
        $erros['whats'] = $t['whats'];
    }
    if (empty($_POST['aceite'])) {
        $erros['aceite'] = $t['aceite'];
    }

    if (!$erros) {
        db()->prepare('INSERT INTO leads (canal, interacao_id, ig_user_id, username, palavra, nome, email, whatsapp, ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([canal(), $i['id'], $i['ig_user_id'], $i['username'], $i['palavra'], $v['nome'], $v['email'], $digitos,
                $_SERVER['REMOTE_ADDR'] ?? null]);
        mudar_etapa((int) $i['id'], 'lead');
        avisar_lead(['canal' => canal(), 'palavra' => $i['palavra'], 'nome' => $v['nome'], 'email' => $v['email'],
            'whatsapp' => $digitos, 'username' => $i['username']]);
        email_para_lead($v['email'], limpar_nome($v['nome']), $r);
        // Deixa a entrega guardada no Direct também (a janela de 24 h está aberta).
        enviar_entrega(['id' => $i['ig_user_id']], $r, limpar_nome($v['nome']));
        if ($redireciona) {
            header('Location: ' . $r['url'], true, 303);
            exit;
        }
        $concluido = true;
    }
}
?>
<!doctype html>
<html lang="<?= $t['lang'] ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= h($r['titulo_form'] ?? MARCA) ?></title>
<link rel="stylesheet" href="/estilo.css">
</head>
<body>
<main class="caixa">
  <p class="marca"><?= h(MARCA) ?></p>
<?php if (!empty($erro_fatal)): ?>
  <h1><?= h($t['expirado']) ?></h1>
  <p><?= h($erro_fatal) ?></p>
<?php elseif ($concluido): ?>
  <h1><?= h($r['titulo_form']) ?></h1>
  <?php if (!empty($r['texto_pagina'])): ?>
  <textarea class="copiar" id="copiar" readonly rows="8"><?= h($r['texto_pagina']) ?></textarea>
  <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('copiar').value);this.textContent='<?= h($t['copiado']) ?>'"><?= h($t['copiar']) ?></button>
  <p><?= h($t['tambem']) ?></p>
  <?php else: ?>
  <?php foreach (preg_split('/\n\s*\n/', personalizar($r['entrega'], '')) as $paragrafo): ?>
  <p><?= preg_replace('#(https?://\S+)#', '<a href="$1">$1</a>', h($paragrafo)) ?></p>
  <?php endforeach; ?>
  <?php if (!empty($r['url']) && !$redireciona): ?>
  <p><a class="botao" href="<?= h($r['url']) ?>"><?= h($r['entrega_botao']) ?></a></p>
  <?php endif; ?>
  <?php endif; ?>
<?php else: ?>
  <h1><?= h($r['titulo_form']) ?></h1>
  <p><?= h($t['falta']) ?></p>
  <form method="post" novalidate>
    <input type="hidden" name="t" value="<?= h($tk) ?>">
    <label><?= h($t['rot_nome']) ?>
      <input name="nome" autocomplete="name" value="<?= h($v['nome']) ?>" required>
      <?php if (isset($erros['nome'])): ?><span class="erro"><?= h($erros['nome']) ?></span><?php endif; ?>
    </label>
    <label><?= h($t['rot_email']) ?>
      <input name="email" type="email" autocomplete="email" inputmode="email" value="<?= h($v['email']) ?>" required>
      <?php if (isset($erros['email'])): ?><span class="erro"><?= h($erros['email']) ?></span><?php endif; ?>
    </label>
    <label><?= h($t['rot_whats']) ?>
      <input name="whats" type="tel" autocomplete="tel" inputmode="tel" placeholder="<?= h($t['exemplo']) ?>" value="<?= h($v['whats']) ?>" required>
      <?php if (isset($erros['whats'])): ?><span class="erro"><?= h($erros['whats']) ?></span><?php endif; ?>
    </label>
    <label class="aceite">
      <input type="checkbox" name="aceite" value="1" <?= !empty($_POST['aceite']) ? 'checked' : '' ?>>
      <span><?= h($t['aceite_txt']) ?> <a href="<?= $t['link_privacidade'] ?>" target="_blank"><?= h($t['privacidade']) ?></a></span>
    </label>
    <?php if (isset($erros['aceite'])): ?><span class="erro"><?= h($erros['aceite']) ?></span><?php endif; ?>
    <button type="submit"><?= h($t['enviar']) ?></button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
