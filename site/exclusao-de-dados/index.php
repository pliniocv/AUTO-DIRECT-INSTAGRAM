<?php
require dirname(__DIR__) . '/paginas.php';
pagina_inicio('Como apagar os seus dados');
$fb = tem_facebook();
?>
  <p>Você pode pedir a exclusão de tudo o que foi guardado sobre você. <a href="/exclusao-de-dados/en/">English version</a></p>
  <ul>
    <li><strong>Pelo Direct:</strong> mande a mensagem "excluir meus dados" para @<?= e(INSTAGRAM_USUARIO) ?> no Instagram. A exclusão é automática e você recebe a confirmação na hora.</li>
    <?php if ($fb): ?>
    <li><strong>Pelo Messenger:</strong> mande a mesma mensagem para a página facebook.com/<?= e(FB_PAGINA) ?>. Também é automático.</li>
    <?php endif; ?>
    <li><strong>Por e-mail:</strong> escreva para <?= e(EMAIL_CONTATO) ?> com o seu nome de usuário do Instagram<?= $fb ? ' ou o seu nome no Facebook' : '' ?>. A exclusão é feita em até 7 dias.</li>
  </ul>
  <p>São apagados: nome, e-mail, WhatsApp, nome de usuário, identificador da conta e o histórico dos pedidos de material.</p>
  <p><a href="/privacidade/">Política de privacidade</a></p>
<?php pagina_fim();
