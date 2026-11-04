<?php
// Conversas do Direct das últimas 24 h com palavra-chave que não receberam o fluxo.
// Motivo: quem abre o Direct por um anúncio e manda a palavra nem sempre gera aviso da Meta, embora a API enxergue a
// conversa. A varredura também roda sozinha (cron/varrer-direct.php).
// A Meta só deixa responder no Direct até 24 h depois da última mensagem da pessoa.

require __DIR__ . '/fluxo.php';
exigir_admin();

$resultado = null;
if (isset($_POST['responder'])) {
    $feitos = 0;
    foreach ((array) ($_POST['m'] ?? []) as $codificado) {
        $m = json_decode((string) base64_decode($codificado), true);
        if (is_array($m) && !empty($m['sender']['id'])) {
            tratar_mensagem($m);
            $feitos++;
        }
    }
    registrar('conversas_varridas', "$feitos respondidas à mão pela página");
    $resultado = "$feitos conversas receberam o fluxo.";
}

$v = isset($_GET['varrer']) ? varrer_conversas() : ['erro' => null, 'lista' => []];
$situacao = function (array $c): string {
    if (!$c['palavra']) {
        return 'sem palavra';
    }
    if ($c['atendida']) {
        return 'já recebeu o fluxo';
    }
    return $c['respondida'] ? 'você respondeu à mão' : '<strong>sem fluxo</strong>';
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Conversas do Direct</title>
<link rel="stylesheet" href="/estilo.css">
</head>
<body>
<main class="caixa larga">
  <p class="marca"><?= h(MARCA) ?></p>
  <h1>Conversas do Direct</h1>
  <p><a href="/leads.php">Leads</a> · <a href="/comentarios.php">Comentários</a> · <a href="/instalar.php">Instalação</a></p>
  <?php if ($resultado): ?><p class="ok"><?= h($resultado) ?></p><?php endif; ?>
  <?php if ($v['erro']): ?><p class="erro">A Meta recusou a leitura das conversas: <code><?= h($v['erro']) ?></code></p><?php endif; ?>

  <?php if (!isset($_GET['varrer'])): ?>
  <p>Lista as conversas das últimas 24 horas e marca quem mandou palavra-chave e não recebeu o fluxo (por exemplo, quem
  abriu o Direct pelo anúncio e a Meta não avisou o sistema). A mesma varredura roda sozinha a cada 10 minutos.</p>
  <p><a class="botao" href="?varrer=1">Procurar</a></p>
  <?php elseif (!$v['lista'] && !$v['erro']): ?>
  <p>Nenhuma conversa nas últimas 24 horas.</p>
  <?php elseif ($v['lista']): ?>
  <p><strong>Marcados: quem mandou palavra, não recebeu o fluxo e não foi respondido à mão.</strong> Quem ficar marcado
  recebe o primeiro Direct do fluxo.</p>
  <form method="post">
    <div class="tabela"><table>
      <tr><th></th><th>Quando</th><th>Quem</th><th>Última mensagem</th><th>Palavra</th><th>Situação</th></tr>
      <?php foreach ($v['lista'] as $c): ?>
      <tr>
        <td><?php if ($c['palavra'] && !$c['atendida']): ?><input type="checkbox" name="m[]" value="<?= h(base64_encode(json_encode($c['mensagem']))) ?>"<?= pendente($c) ? ' checked' : '' ?>><?php endif; ?></td>
        <td><?= h(date('d/m H:i', $c['quando'])) ?></td>
        <td><a href="https://instagram.com/<?= h($c['username']) ?>">@<?= h($c['username']) ?></a></td>
        <td><?= h(mb_substr($c['texto'] !== '' ? $c['texto'] : '(sem texto: anexo, figurinha ou resposta a anúncio sem texto)', 0, 90)) ?></td>
        <td><?= h($c['palavra'] ?? '') ?></td>
        <td><?= $situacao($c) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <button name="responder" value="1">Mandar o fluxo para os marcados</button>
  </form>
  <?php endif; ?>
</main>
</body>
</html>
