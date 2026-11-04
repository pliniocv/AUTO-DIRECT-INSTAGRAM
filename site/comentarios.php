<?php
// Posts recentes (com o ID para prender palavra a post em regras.php) e comentários dos últimos 7 dias
// que têm palavra-chave e não receberam o fluxo. A Meta só deixa responder no privado até 7 dias depois.

require __DIR__ . '/fluxo.php';
exigir_admin();

$resultado = null;
if (isset($_POST['responder'])) {
    $feitos = 0;
    foreach ((array) ($_POST['c'] ?? []) as $codificado) {
        $v = json_decode((string) base64_decode($codificado), true);
        if (is_array($v) && tratar_comentario($v)) {
            $feitos++;
        }
    }
    registrar('comentarios_antigos', "$feitos respondidos");
    $resultado = "$feitos comentários receberam o fluxo.";
}

[, $r] = api('GET', '/' . ig_id() . '/media', ['fields' => 'id,caption,permalink,timestamp,comments_count', 'limit' => 15]);
$posts = $r['data'] ?? [];

$pendentes = [];
if (isset($_GET['varrer'])) {
    $limite = time() - 7 * 86400 + 3600; // uma hora de folga antes do fim do prazo
    $ja = db()->prepare('SELECT 1 FROM interacoes WHERE comment_id = ?');
    foreach ($posts as $p) {
        if (empty($p['comments_count'])) {
            continue;
        }
        [, $c] = api('GET', '/' . $p['id'] . '/comments', ['fields' => 'id,text,timestamp,from', 'limit' => 50]);
        foreach ($c['data'] ?? [] as $com) {
            if (strtotime($com['timestamp']) < $limite || ($com['from']['id'] ?? '') === ig_id()) {
                continue;
            }
            $palavra = achar_palavra((string) $com['text'], $p['id']);
            $ja->execute([$com['id']]);
            if (!$palavra || $ja->fetchColumn()) {
                continue;
            }
            $pendentes[] = [
                'palavra' => $palavra,
                'quando' => date('d/m H:i', strtotime($com['timestamp'])),
                'username' => $com['from']['username'] ?? '',
                'texto' => $com['text'],
                'post' => $p['permalink'] ?? '',
                'v' => base64_encode(json_encode([
                    'id' => $com['id'], 'text' => $com['text'],
                    'from' => ['id' => $com['from']['id'] ?? '', 'username' => $com['from']['username'] ?? null],
                    'media' => ['id' => $p['id']],
                ])),
            ];
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Comentários e posts</title>
<link rel="stylesheet" href="/estilo.css">
</head>
<body>
<main class="caixa larga">
  <p class="marca"><?= h(MARCA) ?></p>
  <h1>Comentários e posts</h1>
  <p><a href="/leads.php">Leads</a> · <a href="/conversas.php">Conversas</a> · <a href="/instalar.php">Instalação</a></p>
  <?php if ($resultado): ?><p class="ok"><?= h($resultado) ?></p><?php endif; ?>

  <h2>Comentários sem resposta (últimos 7 dias)</h2>
  <?php if (!isset($_GET['varrer'])): ?>
  <p>Procura, nos 15 posts mais recentes, comentários com palavra-chave que não receberam o fluxo (por exemplo, feitos antes de a palavra existir).</p>
  <p><a class="botao" href="?varrer=1">Procurar</a></p>
  <?php elseif (!$pendentes): ?>
  <p>Nenhum comentário pendente.</p>
  <?php else: ?>
  <p><strong>Marque só quem você ainda não atendeu à mão.</strong> Quem for marcado recebe a resposta no comentário e o fluxo no Direct.</p>
  <form method="post">
    <div class="tabela"><table>
      <tr><th></th><th>Quando</th><th>Quem</th><th>Comentário</th><th>Palavra</th></tr>
      <?php foreach ($pendentes as $c): ?>
      <tr>
        <td><input type="checkbox" name="c[]" value="<?= h($c['v']) ?>"></td>
        <td><?= h($c['quando']) ?></td>
        <td><a href="https://instagram.com/<?= h($c['username']) ?>">@<?= h($c['username']) ?></a></td>
        <td><a href="<?= h($c['post']) ?>"><?= h($c['texto']) ?></a></td>
        <td><?= h($c['palavra']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <button name="responder" value="1">Mandar o fluxo para os marcados</button>
  </form>
  <?php endif; ?>

  <h2>Posts recentes</h2>
  <p>Para uma palavra valer só em certos posts, copie o ID para <code>'posts' => ['ID']</code> na regra, em regras.php.</p>
  <div class="tabela"><table>
    <tr><th>Data</th><th>Legenda</th><th>Comentários</th><th>ID</th></tr>
    <?php foreach ($posts as $p): ?>
    <tr>
      <td><?= h(date('d/m/Y', strtotime($p['timestamp']))) ?></td>
      <td><a href="<?= h($p['permalink'] ?? '') ?>"><?= h(mb_substr((string) ($p['caption'] ?? '(sem legenda)'), 0, 70)) ?></a></td>
      <td><?= (int) ($p['comments_count'] ?? 0) ?></td>
      <td><code><?= h($p['id']) ?></code></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</main>
</body>
</html>
