<?php
require dirname(__DIR__) . '/paginas.php';
pagina_inicio('Política de privacidade');
$fb = tem_facebook();
?>
  <p><a href="/privacidade/en/">English version</a></p>

  <h2>Quem cuida dos seus dados</h2>
  <p><?= e(MARCA) ?>, responsável pelo perfil @<?= e(INSTAGRAM_USUARIO) ?> no Instagram<?= $fb ? ' e pela página facebook.com/' . e(FB_PAGINA) . ' no Facebook' : '' ?>. Contato: <?= e(EMAIL_CONTATO) ?>.</p>

  <h2>O que é coletado</h2>
  <ul>
    <li>Quando você comenta uma palavra-chave num post ou manda no Direct do Instagram: o seu nome de usuário e o identificador da sua conta no Instagram, o texto do comentário e se você segue o perfil.</li>
    <?php if ($fb): ?>
    <li>Quando você comenta uma palavra-chave num post da página no Facebook ou manda no Messenger: o seu nome, o identificador da sua conta no Facebook e o texto do comentário.</li>
    <?php endif; ?>
    <li>Quando você preenche o formulário: nome, e-mail, WhatsApp, data e endereço de IP do envio.</li>
  </ul>

  <h2>Para que é usado</h2>
  <ul>
    <li>Responder ao seu comentário e enviar no Direct<?= $fb ? ' ou no Messenger' : '' ?> o material que você pediu.</li>
    <li>Enviar conteúdos e ofertas por e-mail e WhatsApp, só se você marcar o aceite no formulário.</li>
  </ul>
  <p>A base legal é o seu consentimento (Lei Geral de Proteção de Dados, LGPD, art. 7º, I).</p>

  <h2>Com quem é compartilhado</h2>
  <p>Com ninguém. Os dados não são vendidos nem cedidos. Ficam guardados no servidor de hospedagem contratado pelo responsável, e as mensagens passam pela Meta, dona do Instagram<?= $fb ? ' e do Facebook' : '' ?>, conforme as regras dela.</p>

  <h2>Por quanto tempo</h2>
  <p>Até você pedir a exclusão. Registros técnicos das mensagens são apagados sozinhos depois de 30 dias.</p>

  <h2>Seus direitos</h2>
  <p>Você pode pedir acesso, correção ou exclusão dos seus dados a qualquer momento. Veja como em <a href="/exclusao-de-dados/">exclusão de dados</a>.</p>
<?php pagina_fim();
