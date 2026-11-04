<?php
require dirname(__DIR__, 2) . '/paginas.php';
pagina_inicio('Privacy policy', 'en');
$fb = tem_facebook();
?>
  <p><a href="/privacidade/">Versão em português</a></p>

  <h2>Who handles your data</h2>
  <p><?= e(MARCA) ?>, owner of the Instagram profile @<?= e(INSTAGRAM_USUARIO) ?><?= $fb ? ' and the Facebook page facebook.com/' . e(FB_PAGINA) : '' ?>. Contact: <?= e(EMAIL_CONTATO) ?>.</p>

  <h2>What is collected</h2>
  <ul>
    <li>When you comment a keyword on a post or send it by Instagram DM: your username, your Instagram account ID, the text of the comment and whether you follow the profile.</li>
    <?php if ($fb): ?>
    <li>When you comment a keyword on a Facebook page post or send it on Messenger: your name, your Facebook account ID and the text of the comment.</li>
    <?php endif; ?>
    <li>When you fill in the form: name, email, WhatsApp, date and IP address of the submission.</li>
  </ul>

  <h2>What it is used for</h2>
  <ul>
    <li>Replying to your comment and sending the material you asked for by DM<?= $fb ? ' or Messenger' : '' ?>.</li>
    <li>Sending content and offers by email and WhatsApp, only if you tick the consent box on the form.</li>
  </ul>
  <p>The legal basis is your consent.</p>

  <h2>Who it is shared with</h2>
  <p>No one. The data is not sold or transferred. It is stored on the hosting server hired by the owner, and messages go through Meta, owner of Instagram<?= $fb ? ' and Facebook' : '' ?>, under its own rules.</p>

  <h2>How long it is kept</h2>
  <p>Until you ask for deletion. Technical message logs are deleted automatically after 30 days.</p>

  <h2>Your rights</h2>
  <p>You can ask to access, correct or delete your data at any time. See how on the <a href="/exclusao-de-dados/en/">data deletion</a> page.</p>
<?php pagina_fim();
