<?php
require __DIR__ . '/paginas.php';
pagina_inicio('Atendimento do Instagram');
?>
  <p>Este endereço entrega os materiais pedidos nos comentários do perfil
    <a href="https://instagram.com/<?= e(INSTAGRAM_USUARIO) ?>">@<?= e(INSTAGRAM_USUARIO) ?></a>.</p>
  <p><a href="/privacidade/">Política de privacidade</a> · <a href="/termos/">Termos de uso</a> · <a href="/exclusao-de-dados/">Exclusão de dados</a></p>
<?php pagina_fim();
