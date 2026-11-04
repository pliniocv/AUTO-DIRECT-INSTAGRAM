<?php
// Funções comuns: banco, chamadas à Meta, regras e registro de eventos.

if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    exit('Falta o config.php: copie o config.exemplo.php para config.php e preencha. Veja INSTALACAO.md.');
}
require_once __DIR__ . '/config.php';

// O servidor roda em UTC. Datas do PHP e do banco em horário de Brasília (sem horário de verão desde 2019).
date_default_timezone_set('America/Sao_Paulo');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NOME . ';charset=utf8mb4',
            DB_USUARIO,
            DB_SENHA,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec("SET time_zone = '-03:00'");
    }
    return $pdo;
}

function kv_get(string $chave): ?string
{
    $st = db()->prepare('SELECT valor FROM kv WHERE chave = ?');
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function kv_set(string $chave, string $valor): void
{
    db()->prepare('INSERT INTO kv (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
        ->execute([$chave, $valor]);
}

function registrar(string $tipo, $detalhe = ''): void
{
    try {
        db()->prepare('INSERT INTO eventos (tipo, detalhe) VALUES (?, ?)')
            ->execute([$tipo, is_string($detalhe) ? $detalhe : json_encode($detalhe, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) {
        error_log("auto-direct: $tipo " . $e->getMessage());
    }
}

// Página do Facebook (opcional). FB_APP_ID e FB_PAGINA ficam no config.php.
const GRAPH_FB = 'https://graph.facebook.com/v26.0';

// Canal da conversa em andamento: 'ig' (Instagram) ou 'fb' (Página do Facebook, pelo Messenger).
// Decide o endereço da API, o token e a conta que envia. Quem recebe o aviso troca o canal antes de tratar.
function canal(?string $novo = null): string
{
    static $c = 'ig';
    if ($novo !== null) {
        $c = $novo === 'fb' ? 'fb' : 'ig';
    }
    return $c;
}

function token(): string
{
    if (canal() === 'fb') {
        // Token da Página, gerado no instalar.php a partir do FB_TOKEN_INICIAL. Não vence.
        return kv_get('fb_token') ?? '';
    }
    return kv_get('ig_token') ?? IG_TOKEN_INICIAL;
}

// Conta que manda as mensagens no canal atual: o perfil do Instagram ou a Página do Facebook.
function conta_id(): string
{
    return canal() === 'fb' ? (string) kv_get('fb_page_id') : ig_id();
}

// "Direct" vira "Messenger" nos textos que vão para quem veio do Facebook.
function no_canal(string $texto): string
{
    return canal() === 'fb' ? str_replace('Direct', 'Messenger', $texto) : $texto;
}

// Chamada à API do Instagram ou do Facebook, conforme o canal. Devolve [status HTTP, corpo decodificado].
function api(string $metodo, string $caminho, array $dados = []): array
{
    $url = (canal() === 'fb' ? GRAPH_FB : GRAPH) . $caminho;
    $ch = curl_init();
    $cab = ['Authorization: Bearer ' . token()];
    if ($metodo === 'GET') {
        if ($dados) {
            $url .= '?' . http_build_query($dados);
        }
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($dados) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados, JSON_UNESCAPED_UNICODE));
            $cab[] = 'Content-Type: application/json';
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        }
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $cab,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $corpo = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string) $corpo, true) ?? ['bruto' => $corpo];
    if ($status >= 400 || isset($json['error'])) {
        registrar('erro_api', ['canal' => canal(), 'caminho' => $caminho, 'status' => $status, 'resposta' => $json]);
    }
    return [$status, $json];
}

function ig_id(): string
{
    $id = kv_get('ig_user_id');
    if (!$id) {
        [, $me] = api('GET', '/me', ['fields' => 'user_id,username']);
        $id = (string) ($me['user_id'] ?? '');
        if ($id) {
            kv_set('ig_user_id', $id);
            kv_set('ig_username', (string) ($me['username'] ?? ''));
        }
    }
    return (string) $id;
}

function regras(): array
{
    static $r = null;
    // Sem regras.php, vale o exemplo: o sistema já funciona para testar antes de você escrever as suas palavras.
    return $r ??= require __DIR__ . (is_file(__DIR__ . '/regras.php') ? '/regras.php' : '/regras.exemplo.php');
}

// Língua de uma palavra: 'en' para as regras de quem comenta de fora (PROFILE), 'pt' para o resto.
// É assim que os leads de fora ficam separados, sem tabela própria: pela palavra.
function idioma(string $palavra): string
{
    return regras()[$palavra]['idioma'] ?? 'pt';
}

// Link do WhatsApp: número do Brasil gravado sem o 55 ganha o 55; número de fora já vem com o código do país.
function link_whats(string $numero, string $idioma = 'pt'): string
{
    return 'https://wa.me/' . ($idioma === 'pt' && strlen($numero) <= 11 ? '55' . $numero : $numero);
}

// Link do WhatsApp com a mensagem já escrita, para você chamar o lead com um toque.
// Texto da regra no campo 'whats' (opcional); sem ele vai o padrão abaixo. {nome}, {material} e {rede} são trocados.
// $modo: 'wa' (wa.me, celular e e-mail), 'app' (whatsapp://, abre no aplicativo do Windows já aberto, sem aba nova)
// ou 'web' (WhatsApp Web direto). Aba reaproveitada não dá: o WhatsApp Web manda Cross-Origin-Opener-Policy.
function link_whats_mensagem(array $l, string $modo = 'wa'): string
{
    $r = regras()[$l['palavra']] ?? [];
    $en = idioma($l['palavra']) === 'en';
    $padrao = $en
        ? 'Hi{nome}! It\'s ' . MARCA . '. You asked for "{material}" on {rede}, and I wanted to check: did you get a chance to look at it?'
        : 'Oi{nome}! Aqui é ' . MARCA . '. Você pediu "{material}" lá no {rede} e eu queria saber: conseguiu dar uma olhada?';
    $texto = str_replace(
        ['{material}', '{rede}'],
        [$r['titulo_form'] ?? $l['palavra'], ($l['canal'] ?? 'ig') === 'fb' ? 'Facebook' : 'Instagram'],
        personalizar($r['whats'] ?? $padrao, limpar_nome((string) $l['nome']))
    );
    $link = link_whats($l['whatsapp'], $en ? 'en' : 'pt');
    if ($modo !== 'wa') {
        return ($modo === 'app' ? 'whatsapp://send' : 'https://web.whatsapp.com/send')
            . '?phone=' . basename($link) . '&text=' . rawurlencode($texto);
    }
    return $link . '?text=' . rawurlencode($texto);
}

function normalizar(string $t): string
{
    $t = mb_strtoupper($t, 'UTF-8');
    return strtr($t, ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I',
        'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ç' => 'C']);
}

// Devolve a palavra da regra encontrada no texto, como palavra inteira, ou null.
// $media: ID do post comentado. Regra com 'posts' só vale nesses posts; no Direct (sem post) vale sempre.
// $direct: true quando o texto veio do Direct; regra com 'so_comentario' não dispara ali.
function achar_palavra(string $texto, ?string $media = null, bool $direct = false): ?string
{
    $t = normalizar($texto);
    $palavras = count(preg_split('/\s+/u', trim($t), -1, PREG_SPLIT_NO_EMPTY));
    foreach (regras() as $palavra => $r) {
        if (!empty($r['so_curto']) && $palavras > 4) {
            continue; // "amei seu perfil, muito bom" não dispara PERFIL
        }
        if (!empty($r['so_comentario']) && $direct) {
            continue; // "quero saber o preço" no Direct é conversa, não pedido de fluxo
        }
        if (!empty($r['posts']) && $media !== null && !in_array($media, $r['posts'], true)) {
            continue;
        }
        // A palavra da regra e as variações dela ("outras"), todas levando ao mesmo fluxo.
        foreach (array_merge([$palavra], $r['outras'] ?? []) as $p) {
            if (preg_match('/(^|[^A-Z0-9])' . preg_quote($p, '/') . '([^A-Z0-9]|$)/u', $t)) {
                return $palavra;
            }
        }
    }
    return null;
}

// Direct final: com botão quando a regra tem link, em texto quando não tem.
function enviar_entrega(array $destino, array $r, string $nome = ''): bool
{
    $texto = personalizar($r['entrega'], $nome);
    if (!empty($r['url'])) {
        return enviar_botao($destino, $texto, ['type' => 'web_url', 'url' => $r['url'], 'title' => $r['entrega_botao']]);
    }
    return enviar_texto($destino, $texto);
}

// Destinatário: ['comment_id' => ...] para resposta privada, ['id' => ...] para conversa aberta.
function enviar(array $destino, array $mensagem): bool
{
    $dados = ['recipient' => $destino, 'message' => $mensagem];
    if (canal() === 'fb') {
        $dados['messaging_type'] = 'RESPONSE'; // o Messenger pede; tudo aqui é resposta dentro da janela
    }
    [$st] = api('POST', '/' . conta_id() . '/messages', $dados);
    return $st < 400;
}

function enviar_botao(array $destino, string $texto, array $botao): bool
{
    $ok = enviar($destino, ['attachment' => ['type' => 'template', 'payload' => [
        'template_type' => 'button',
        'text' => $texto,
        'buttons' => [$botao],
    ]]]);
    if ($ok) {
        return true;
    }
    // Se o modelo com botão for recusado, manda em texto simples.
    $extra = isset($botao['url']) ? "\n\n" . $botao['url'] : "\n\nResponda QUERO que eu te envio.";
    return enviar_texto($destino, $texto . $extra);
}

function enviar_texto(array $destino, string $texto): bool
{
    return enviar($destino, ['text' => $texto]);
}

// Primeiro nome do perfil, para "Oi, Fulano". Vazio se a Meta não informar ou o nome parecer apelido.
// Antes de a pessoa interagir no Direct, a Meta não manda o nome (visto em 28/10/2026): o dm1 sai sem.
function primeiro_nome(string $igsid): string
{
    if (canal() === 'fb') {
        // O Messenger recusa o perfil ("does not exist ... missing permissions", erro 100/33, visto em 28/10/2026).
        // No Facebook o nome vem do aviso do comentário e fica guardado na interação (nome_interacao()).
        return '';
    }
    [$st, $p] = api('GET', '/' . $igsid, ['fields' => 'name']);
    return $st < 400 ? limpar_nome((string) ($p['name'] ?? '')) : '';
}

// Primeiro nome guardado no comentário do Facebook, para as mensagens depois do toque no botão.
function nome_interacao(int $id): string
{
    return (string) kv_get('nome_' . $id);
}

function limpar_nome(string $nome_completo): string
{
    $nome = trim(explode(' ', trim($nome_completo))[0]);
    if ($nome === '' || mb_strlen($nome) > 20 || preg_match('/[^\p{L}\'-]/u', $nome)) {
        return '';
    }
    return mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8');
}

// Uma consulta só: [segue o perfil? (null se a consulta falhar), primeiro nome].
function perfil(string $igsid): array
{
    if (canal() === 'fb') {
        // A Página do Facebook não tem como conferir quem curte ou segue: o passo "me segue" fica de fora.
        return [true, primeiro_nome($igsid)];
    }
    [$st, $p] = api('GET', '/' . $igsid, ['fields' => 'name,username,is_user_follow_business']);
    if ($st >= 400) {
        return [null, ''];
    }
    $segue = array_key_exists('is_user_follow_business', $p) ? (bool) $p['is_user_follow_business'] : null;
    $nome = limpar_nome((string) ($p['name'] ?? ''));
    if ($nome === '') {
        // Diagnóstico: o nome não veio mesmo depois do toque no botão (28/10/2026). Guarda só o formato da resposta.
        registrar('perfil_sem_nome', ['campos' => array_keys($p), 'name_vazio' => empty($p['name'])]);
    }
    return [$segue, $nome];
}

// Troca {nome} por ", Fulano" ou some com ele quando não há nome.
function personalizar(string $texto, string $nome): string
{
    return str_replace('{nome}', $nome !== '' ? ', ' . $nome : '', $texto);
}

function interacao(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM interacoes WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function mudar_etapa(int $id, string $etapa): void
{
    db()->prepare('UPDATE interacoes SET etapa = ? WHERE id = ?')->execute([$etapa, $id]);
}

function ja_e_lead(string $igsid, string $palavra): bool
{
    $st = db()->prepare('SELECT 1 FROM leads WHERE ig_user_id = ? AND palavra = ? LIMIT 1');
    $st->execute([$igsid, $palavra]);
    return (bool) $st->fetchColumn();
}

function link_form(array $i): string
{
    return URL_BASE . '/f/?t=' . $i['token'];
}

// Se o e-mail do domínio fica em outro serviço (Zoho, Google), o mail() da hospedagem costuma não entregar, e o DMARC
// do domínio barra o que sai de servidor não autorizado. Caminho testado: SMTP do Brevo (gratuito, 300 por dia), com o
// domínio autenticado no Brevo. No config.php: USAR_SMTP = true, SMTP_HOST, SMTP_USUARIO, SMTP_SENHA.
const AVISO_EMAIL = EMAIL_AVISO;

function enviar_email(string $para, string $nome_remetente, string $assunto, string $corpo, string $responder = AVISO_EMAIL): bool
{
    $assunto = mb_encode_mimeheader($assunto, 'UTF-8');
    $cab = [
        'From: ' . mb_encode_mimeheader($nome_remetente, 'UTF-8') . ' <' . AVISO_EMAIL . '>',
        'Reply-To: ' . $responder,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if (defined('USAR_SMTP') && USAR_SMTP && defined('SMTP_SENHA') && SMTP_SENHA !== 'COLE_AQUI') {
        return smtp_enviar($para, $assunto, $corpo, $cab);
    }
    return mail($para, $assunto, $corpo, implode("\r\n", $cab), '-f' . AVISO_EMAIL);
}

function smtp_enviar(string $para, string $assunto, string $corpo, array $cab): bool
{
    $host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp-relay.brevo.com';
    $usuario = defined('SMTP_USUARIO') ? SMTP_USUARIO : AVISO_EMAIL;
    $s = @stream_socket_client("ssl://$host:465", $errno, $errstr, 15);
    if (!$s) {
        registrar('smtp_falhou', "conexão: $errstr");
        return false;
    }
    $ler = function () use ($s): string {
        $resp = '';
        while (($linha = fgets($s, 515)) !== false) {
            $resp .= $linha;
            if (strlen($linha) < 4 || $linha[3] === ' ') {
                break;
            }
        }
        return $resp;
    };
    // $passo é só um nome para o registro: nunca registrar o comando, porque o de AUTH leva a senha.
    $falar = function (string $cmd, string $espera, string $passo) use ($s, $ler): bool {
        if ($cmd !== '') {
            fwrite($s, $cmd . "\r\n");
        }
        $r = $ler();
        if (strpos($r, $espera) !== 0) {
            registrar('smtp_falhou', ['passo' => $passo, 'resposta' => trim($r)]);
            return false;
        }
        return true;
    };
    $dados = implode("\r\n", array_merge(["To: $para", "Subject: $assunto", 'Date: ' . date('r')], $cab))
        . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $corpo));
    $ok = $falar('', '220', 'conexão')
        && $falar('EHLO ' . (parse_url(URL_BASE, PHP_URL_HOST) ?: 'localhost'), '250', 'apresentação')
        && $falar('AUTH LOGIN', '334', 'pedido de login')
        && $falar(base64_encode($usuario), '334', 'usuário')
        && $falar(base64_encode(SMTP_SENHA), '235', 'senha')
        && $falar('MAIL FROM:<' . AVISO_EMAIL . '>', '250', 'remetente')
        && $falar("RCPT TO:<$para>", '250', 'destinatário')
        && $falar('DATA', '354', 'início da mensagem')
        && $falar($dados . "\r\n.", '250', 'envio da mensagem');
    fwrite($s, "QUIT\r\n");
    fclose($s);
    return $ok;
}

function avisar_lead(array $l): void
{
    $de_fora = idioma($l['palavra']) === 'en';
    $origem = ($l['canal'] ?? 'ig') === 'fb' ? 'Messenger do Facebook' : 'Direct do Instagram';
    $corpo = "Lead novo pelo $origem" . ($de_fora ? ' (de fora do Brasil, fluxo em inglês)' : '') . ".\n\n"
        . "Palavra: {$l['palavra']}\n"
        . "Nome: {$l['nome']}\n"
        . "WhatsApp: {$l['whatsapp']}  " . link_whats($l['whatsapp'], idioma($l['palavra'])) . "\n"
        . "Chamar com a mensagem pronta: " . link_whats_mensagem($l) . "\n"
        . ($l['email'] !== '' ? "E-mail: {$l['email']}\n" : '')
        . ($l['username'] ? "Instagram: @{$l['username']}  https://instagram.com/{$l['username']}\n" : '')
        . 'Quando: ' . date('d/m/Y H:i') . "\n\n"
        . 'Todos os leads: ' . URL_BASE . "/leads.php\n";
    // Responder o e-mail já escreve para o lead.
    $prefixo = $de_fora ? 'Lead de fora: ' : 'Lead novo: ';
    if (!enviar_email(AVISO_EMAIL, $origem, $prefixo . "{$l['palavra']} - {$l['nome']}", $corpo, $l['email'] !== '' ? $l['email'] : AVISO_EMAIL)) {
        registrar('aviso_email_falhou', $l['email']);
    }
}

// E-mail para o lead com o material, logo depois do formulário.
function email_para_lead(string $email, string $nome, array $r): void
{
    if (($r['idioma'] ?? 'pt') === 'en') {
        // De fora: o texto da entrega (com os passos) e o link, em inglês.
        $rede = canal() === 'fb' ? 'Facebook' : 'Instagram';
        $corpo = ($nome !== '' ? "Hi, $nome!" : 'Hi!') . "\n\n"
            . "As promised on $rede, here it is: {$r['titulo_form']}\n\n"
            . personalizar($r['entrega'], '') . "\n\n"
            . (!empty($r['url']) ? $r['url'] . "\n\n" : '')
            . 'If you get stuck, just reply to this email or ' . ($rede === 'Facebook' ? 'message me on Messenger' : 'send me a DM') . ".\n\n"
            . MARCA . "\nhttps://instagram.com/" . INSTAGRAM_USUARIO . "\n\n"
            . "--\nYou got this email because you asked for this on $rede. To stop receiving emails, reply STOP.\n";
        if (!enviar_email($email, MARCA, $r['titulo_form'], $corpo)) {
            registrar('email_lead_falhou', $email);
        }
        return;
    }
    $conteudo = $r['texto_pagina'] ?? (!empty($r['url']) ? $r['url'] : personalizar($r['entrega'], ''));
    $saudacao = $nome !== '' ? "Oi, $nome!" : 'Oi!';
    $rede = canal() === 'fb' ? 'Facebook' : 'Instagram';
    $corpo = "$saudacao\n\n"
        . "Como prometido no $rede, aqui está: {$r['titulo_form']}\n\n"
        . "$conteudo\n\n"
        . no_canal("Se travar em algum ponto, é só responder este e-mail ou me chamar no Direct.\n\n")
        . MARCA . "\nhttps://instagram.com/" . INSTAGRAM_USUARIO . "\n\n"
        . "--\nVocê recebeu este e-mail porque pediu o material no $rede. Para não receber mais, responda SAIR.\n";
    if (!enviar_email($email, MARCA, $r['titulo_form'], $corpo)) {
        registrar('email_lead_falhou', $email);
    }
}

// Legenda e link de um post, guardados no banco para não perguntar à Meta toda vez.
function media_info(string $media_id): array
{
    $guardado = kv_get('media_' . $media_id);
    if ($guardado) {
        return json_decode($guardado, true);
    }
    // Post do Facebook vem como PAGINA_POST; o do Instagram é só número.
    $fb = strpos($media_id, '_') !== false;
    $antes = canal();
    canal($fb ? 'fb' : 'ig');
    [$st, $m] = api('GET', '/' . $media_id, ['fields' => $fb ? 'message,permalink_url,created_time' : 'caption,permalink,timestamp']);
    canal($antes);
    $quando = $m['timestamp'] ?? $m['created_time'] ?? null;
    $info = [
        'legenda' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($m['caption'] ?? $m['message'] ?? ''))), 0, 70),
        'link' => (string) ($m['permalink'] ?? $m['permalink_url'] ?? ''),
        'data' => $quando ? date('d/m/Y', strtotime($quando)) : '',
    ];
    if ($st < 400) {
        kv_set('media_' . $media_id, json_encode($info, JSON_UNESCAPED_UNICODE));
    }
    return $info;
}

// Páginas internas: pede a senha uma vez por sessão.
function exigir_admin(): void
{
    session_start();
    if (isset($_POST['senha'])) {
        if (ADMIN_SENHA !== 'COLE_AQUI' && hash_equals(ADMIN_SENHA, (string) $_POST['senha'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
        } else {
            sleep(3); // atrasa quem tenta adivinhar a senha
        }
    }
    if (!empty($_SESSION['admin'])) {
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>Entrar</title><link rel="stylesheet" href="/estilo.css">'
        . '<main class="caixa"><p class="marca">' . h(MARCA) . '</p><h1>Entrar</h1><form method="post">'
        . '<label>Senha<input type="password" name="senha" autofocus></label><button>Entrar</button></form></main>';
    exit;
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
