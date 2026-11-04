<?php
// Lista de leads, com download em planilha (CSV).

require __DIR__ . '/lib.php';
exigir_admin();

// Quem já foi chamado no WhatsApp: guardado no kv como chamado_<id do lead> = data. O clique no botão grava sozinho;
// "marcar" e "desmarcar" corrigem na mão. Sem coluna nova no banco, então não precisa rodar o instalar.php.
if (isset($_POST['chamado'])) {
    $id = (int) $_POST['chamado'];
    if (!empty($_POST['desfazer'])) {
        db()->prepare('DELETE FROM kv WHERE chave = ?')->execute(['chamado_' . $id]);
        $quando = '';
    } else {
        $quando = date('Y-m-d H:i:s');
        kv_set('chamado_' . $id, $quando);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'quando' => $quando ? date('d/m H:i', strtotime($quando)) : '']);
    exit;
}
$chamados = [];
foreach (db()->query("SELECT chave, valor FROM kv WHERE chave LIKE 'chamado\\_%'") as $k) {
    $chamados[(int) substr($k['chave'], 8)] = $k['valor'];
}

$todos = db()->query('SELECT id, criado_em, canal, palavra, nome, email, whatsapp, username FROM leads ORDER BY id DESC')->fetchAll();
// O botão abre o WhatsApp com a mensagem pronta: deixe o seu número logado no WhatsApp Web ou no aplicativo.
$rede = fn(?string $c): string => $c === 'fb' ? 'Facebook' : 'Instagram';
// Duas abas, sem tabela separada no banco: a palavra diz se o lead é de fora (regra com 'idioma' => 'en').
$aba = ($_GET['aba'] ?? '') === 'fora' ? 'fora' : 'brasil';
$leads = array_values(array_filter($todos, fn($l) => (idioma($l['palavra']) === 'en') === ($aba === 'fora')));
$qtd_fora = count(array_filter($todos, fn($l) => idioma($l['palavra']) === 'en'));
$qtd_chamados = count(array_filter($leads, fn($l) => isset($chamados[$l['id']])));
$so_falta = isset($_GET['falta']);

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . ($aba === 'fora' ? 'de-fora-' : '') . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // acento certo no Excel
    fputcsv($out, ['data', 'rede', 'palavra', 'nome', 'email', 'whatsapp', 'instagram', 'chamado no whatsapp'], ';');
    foreach ($leads as $l) {
        $c = $chamados[$l['id']] ?? null;
        unset($l['id']);
        $l['criado_em'] = date('d/m/Y H:i', strtotime($l['criado_em']));
        $l['canal'] = $rede($l['canal']);
        $l['chamado'] = $c ? date('d/m/Y H:i', strtotime($c)) : '';
        fputcsv($out, array_values($l), ';');
    }
    exit;
}

// Funil por post: quem comentou, quem tocou no botão, quem chegou ao formulário (ou à entrega) e quem preencheu.
$funil = db()->query("SELECT canal, media_id, palavra, COUNT(*) comentou,
        SUM(etapa NOT IN ('novo','dm1')) tocou,
        SUM(etapa IN ('link','link_lembrete','lead','entregue')) chegou,
        SUM(etapa = 'lead') preencheu,
        MAX(criado_em) ultimo
    FROM interacoes GROUP BY canal, media_id, palavra ORDER BY ultimo DESC")->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Leads do Direct</title>
<link rel="stylesheet" href="/estilo.css">
</head>
<body>
<main class="caixa larga">
  <p class="marca"><?= h(MARCA) ?></p>
  <h1>Leads do Direct</h1>
  <p><a href="/comentarios.php">Comentários e posts</a> · <a href="/conversas.php">Conversas</a> · <a href="/instalar.php">Instalação</a></p>
  <p><?= $aba === 'brasil' ? '<strong>Leads do Brasil</strong>' : '<a href="?">Leads do Brasil</a>' ?> (<?= count($todos) - $qtd_fora ?>)
    · <?= $aba === 'fora' ? '<strong>Leads de fora</strong>' : '<a href="?aba=fora">Leads de fora</a>' ?> (<?= $qtd_fora ?>)</p>
  <p><?= count($leads) ?> leads nesta aba. <a class="botao" href="?csv=1<?= $aba === 'fora' ? '&amp;aba=fora' : '' ?>">Baixar planilha</a></p>

  <h2>Funil por post</h2>
  <div class="tabela"><table>
    <tr><th>Rede</th><th>Post</th><th>Palavra</th><th>Comentou</th><th>Tocou no botão</th><th>Recebeu o link</th><th>Preencheu</th></tr>
    <?php foreach ($funil as $f): ?>
    <?php $m = $f['media_id'] ? media_info($f['media_id']) : null; ?>
    <tr>
      <td><?= h($rede($f['canal'])) ?></td>
      <td><?= $m ? '<a href="' . h($m['link']) . '">' . h($m['data'] . ' ' . ($m['legenda'] ?: 'post')) . '</a>' : ($f['canal'] === 'fb' ? 'pelo Messenger' : 'pelo Direct') ?></td>
      <td><?= h($f['palavra']) ?></td>
      <td><?= (int) $f['comentou'] ?></td>
      <td><?= (int) $f['tocou'] ?></td>
      <td><?= (int) $f['chegou'] ?></td>
      <td><?= (int) $f['preencheu'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>

  <h2>Leads</h2>
  <?php $base = $aba === 'fora' ? '?aba=fora' : '?'; ?>
  <p><span id="qtd-chamados"><?= $qtd_chamados ?></span> já chamados no WhatsApp, <span id="qtd-falta"><?= count($leads) - $qtd_chamados ?></span> para chamar.
    <?= $so_falta ? '<a href="' . $base . '">Mostrar todos</a>' : '<a href="' . $base . ($aba === 'fora' ? '&amp;' : '') . 'falta=1">Só quem falta chamar</a>' ?></p>
  <div class="tabela"><table>
    <tr><th>Data</th><th>Rede</th><th>Palavra</th><th>Nome</th><th>E-mail</th><th>WhatsApp</th><th>Instagram</th><th></th><th>Chamado</th></tr>
    <?php foreach ($leads as $l): ?>
    <?php $c = $chamados[$l['id']] ?? null; if ($so_falta && $c) continue; ?>
    <tr data-id="<?= (int) $l['id'] ?>"<?= $c ? ' class="chamado"' : '' ?>>
      <td><?= h(date('d/m/Y H:i', strtotime($l['criado_em']))) ?></td>
      <td><?= h($rede($l['canal'])) ?></td>
      <td><?= h($l['palavra']) ?></td>
      <td><?= h($l['nome']) ?></td>
      <td><?= h($l['email']) ?></td>
      <td><a href="<?= h(link_whats($l['whatsapp'], idioma($l['palavra']))) ?>"><?= h($l['whatsapp']) ?></a></td>
      <td><?= $l['username'] ? '<a href="https://instagram.com/' . h($l['username']) . '">@' . h($l['username']) . '</a>' : '' ?></td>
      <td><a class="botao chamar" href="<?= h(link_whats_mensagem($l, 'app')) ?>" data-celular="<?= h(link_whats_mensagem($l)) ?>">Chamar no WhatsApp</a>
        <a class="pelo-web" href="<?= h(link_whats_mensagem($l, 'web')) ?>" target="_blank" rel="noopener">pelo navegador</a></td>
      <td class="quando"><span><?= $c ? h(date('d/m H:i', strtotime($c))) : '' ?></span>
        <button type="button" class="marcar"><?= $c ? 'desmarcar' : 'marcar' ?></button></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</main>
<script>
// No computador, o botão abre o aplicativo do WhatsApp para Windows (whatsapp://), sem aba nova;
// "pelo navegador" é a reserva, pelo WhatsApp Web. No celular, os dois voltam para o wa.me, que abre o aplicativo.
if (/Android|iPhone|iPad/i.test(navigator.userAgent)) {
  document.querySelectorAll('a.chamar').forEach(a => { a.href = a.dataset.celular; a.target = '_blank'; });
  document.querySelectorAll('a.pelo-web').forEach(a => a.remove());
}

// Grava no banco quem já foi chamado e marca a linha na hora, sem recarregar a página.
function marcar(tr, desfazer) {
  const dados = new URLSearchParams({chamado: tr.dataset.id});
  if (desfazer) dados.set('desfazer', '1');
  fetch('leads.php', {method: 'POST', body: dados, keepalive: true})
    .then(r => r.json())
    .then(j => {
      const era = tr.classList.contains('chamado');
      tr.classList.toggle('chamado', !desfazer);
      tr.querySelector('.quando span').textContent = j.quando;
      tr.querySelector('.marcar').textContent = desfazer ? 'marcar' : 'desmarcar';
      if (era !== !desfazer) {
        const passo = desfazer ? -1 : 1;
        const qc = document.getElementById('qtd-chamados'), qf = document.getElementById('qtd-falta');
        qc.textContent = +qc.textContent + passo;
        qf.textContent = +qf.textContent - passo;
      }
    })
    .catch(() => alert('Não gravou. Recarregue a página e tente de novo.'));
}
document.querySelectorAll('a.chamar, a.pelo-web').forEach(a =>
  a.addEventListener('click', () => marcar(a.closest('tr'), false)));
document.querySelectorAll('button.marcar').forEach(b =>
  b.addEventListener('click', () => { const tr = b.closest('tr'); marcar(tr, tr.classList.contains('chamado')); }));
</script>
</body>
</html>
