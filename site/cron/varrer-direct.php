<?php
// Tarefa agendada (cron do painel da hospedagem, a cada 10 minutos): manda o fluxo para quem mandou palavra no Direct e a Meta não
// avisou o sistema (visto em 30/10/2026 com quem abria o Direct pelo anúncio). Só para quem não recebeu o fluxo e não
// foi respondido à mão depois da mensagem. Regras em varrer_conversas() e pendente(), no fluxo.php.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/fluxo.php';

$v = varrer_conversas();
$n = 0;
foreach ($v['lista'] as $c) {
    if (pendente($c)) {
        tratar_mensagem($c['mensagem']);
        $n++;
    }
}
if ($n) {
    registrar('conversas_varridas', "$n pelo cron");
}
echo ($v['erro'] ? 'erro: ' . $v['erro'] : "$n fluxos") . "\n";
