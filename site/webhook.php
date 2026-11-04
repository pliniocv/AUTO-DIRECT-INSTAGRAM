<?php
// Recebe os avisos da Meta, do Instagram e da Página do Facebook: comentários, mensagens e toques em botão.
// O que fazer com cada um está em fluxo.php.

require __DIR__ . '/fluxo.php';

// 1. Verificação do endereço (a Meta chama uma vez, ao salvar o webhook no painel).
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['hub_mode'] ?? '') === 'subscribe' && hash_equals(VERIFY_TOKEN, (string) ($_GET['hub_verify_token'] ?? ''))) {
        echo $_GET['hub_challenge'] ?? '';
    } else {
        http_response_code(403);
    }
    exit;
}

// 2. Assinatura: só aceita aviso assinado pela Meta.
$corpo = file_get_contents('php://input');
$assinatura = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$valida = false;
foreach ([APP_SECRET, IG_APP_SECRET] as $segredo) {
    if ($segredo !== 'COLE_AQUI' && hash_equals('sha256=' . hash_hmac('sha256', $corpo, $segredo), $assinatura)) {
        $valida = true;
    }
}
if (!$valida) {
    registrar('assinatura_invalida', substr($corpo, 0, 500));
    http_response_code(403);
    exit;
}

// 3. Responde logo, para a Meta não reenviar, e processa depois.
echo 'EVENT_RECEIVED';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$aviso = json_decode($corpo, true) ?: [];
// "instagram" para o perfil, "page" para a Página do Facebook (comentário no feed, mensagem no Messenger).
canal(($aviso['object'] ?? '') === 'page' ? 'fb' : 'ig');

foreach ($aviso['entry'] ?? [] as $entrada) {
    foreach ($entrada['changes'] ?? [] as $mudanca) {
        $campo = $mudanca['field'] ?? '';
        if ($campo === 'comments') {
            registrar('aviso', $mudanca);
            tratar_comentario($mudanca['value'] ?? []);
        } elseif ($campo === 'feed') {
            // O feed da Página avisa de tudo (publicação, curtida, reação): só comentário novo entra no registro.
            $v = comentario_do_facebook($mudanca['value'] ?? []);
            if ($v) {
                registrar('aviso', $mudanca);
                tratar_comentario($v);
            }
        } else {
            registrar('aviso', $mudanca);
        }
    }
    foreach ($entrada['messaging'] ?? [] as $m) {
        if (isset($m['read']) || isset($m['delivery'])) {
            continue; // "visto" e "entregue" não interessam e enchiam o registro
        }
        registrar('aviso', $m);
        tratar_mensagem($m);
    }
}
