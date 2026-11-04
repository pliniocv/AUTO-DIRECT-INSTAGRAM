<?php
require dirname(__DIR__) . '/paginas.php';
pagina_inicio('Termos de uso');
$fb = tem_facebook();
?>
  <h2>A quem se aplicam</h2>
  <p>Estes termos valem para o envio automático de materiais pelo Direct do Instagram<?= $fb ? ' e pelo Messenger' : '' ?>, feito por <?= e(MARCA) ?> (@<?= e(INSTAGRAM_USUARIO) ?>).</p>

  <h2>Envio de materiais</h2>
  <ul>
    <li>Quem comenta uma palavra-chave num post ou manda a palavra no Direct<?= $fb ? ' ou no Messenger' : '' ?> pede, por iniciativa própria, o material divulgado naquele post.</li>
    <li>O material é gratuito e enviado como está, para fins informativos.</li>
    <li>Você pode parar de receber mensagens a qualquer momento, pedindo no próprio Direct<?= $fb ? ' ou no Messenger' : '' ?>.</li>
  </ul>

  <h2>Dados pessoais</h2>
  <p>O que é coletado, para que é usado e como pedir a exclusão está na <a href="/privacidade/">política de privacidade</a>.</p>

  <h2>Responsabilidade</h2>
  <p>O envio depende dos serviços da Meta. Se eles ficarem fora do ar ou mudarem as regras, o envio pode atrasar ou deixar de funcionar, sem aviso prévio.</p>

  <h2>Contato</h2>
  <p>Dúvidas sobre estes termos: <?= e(EMAIL_CONTATO) ?>.</p>
<?php pagina_fim();
