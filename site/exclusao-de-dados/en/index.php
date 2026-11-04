<?php
require dirname(__DIR__, 2) . '/paginas.php';
pagina_inicio('How to delete your data', 'en');
$fb = tem_facebook();
?>
  <p>You can ask for everything stored about you to be deleted. <a href="/exclusao-de-dados/">Versão em português</a></p>
  <ul>
    <li><strong>By DM:</strong> send the message "delete my data" to @<?= e(INSTAGRAM_USUARIO) ?> on Instagram. Deletion is automatic and you get a confirmation right away.</li>
    <?php if ($fb): ?>
    <li><strong>On Messenger:</strong> send the same message to the page facebook.com/<?= e(FB_PAGINA) ?>. Also automatic.</li>
    <?php endif; ?>
    <li><strong>By email:</strong> write to <?= e(EMAIL_CONTATO) ?> with your Instagram username<?= $fb ? ' or your name on Facebook' : '' ?>. Deletion is done within 7 days.</li>
  </ul>
  <p>What is deleted: name, email, WhatsApp, username, account ID and the history of material requests.</p>
  <p><a href="/privacidade/en/">Privacy policy</a></p>
<?php pagina_fim();
