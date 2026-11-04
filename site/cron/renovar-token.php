<?php
// Tarefa agendada (cron do painel da hospedagem, todo dia à 00:00): renova o token, que vence em 60 dias,
// e apaga eventos com mais de 30 dias.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/lib.php';

$ch = curl_init('https://graph.instagram.com/refresh_access_token?' . http_build_query([
    'grant_type' => 'ig_refresh_token',
    'access_token' => token(),
]));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
$r = json_decode((string) curl_exec($ch), true) ?: [];
curl_close($ch);

if (!empty($r['access_token'])) {
    kv_set('ig_token', $r['access_token']);
    kv_set('ig_token_vence', date('Y-m-d H:i:s', time() + (int) ($r['expires_in'] ?? 0)));
    registrar('token_renovado', ['vence' => kv_get('ig_token_vence')]);
    echo "token renovado\n";
} else {
    registrar('token_falhou', $r);
    echo "falhou\n";
}

db()->exec('DELETE FROM eventos WHERE criado_em < NOW() - INTERVAL 30 DAY');
