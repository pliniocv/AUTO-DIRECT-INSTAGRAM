<?php
// Tarefa agendada (cron do painel da hospedagem, de hora em hora): lembrete para quem parou no formulário
// ou no "me segue". Regras do envio em mandar_lembretes(), no fluxo.php.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/fluxo.php';

$n = mandar_lembretes();
if ($n) {
    registrar('lembretes', "$n enviados");
}
echo "$n lembretes\n";
