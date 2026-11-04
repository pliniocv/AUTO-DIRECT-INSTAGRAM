<?php
// Configuração do Auto Direct.
//
// 1. Copie este arquivo para config.php (no servidor, na mesma pasta).
// 2. Troque cada COLE_AQUI pelo valor de verdade.
// 3. Nunca envie o config.php para o GitHub nem cole o conteúdo dele em conversa: ele tem as suas senhas.
//    O .gitignore do projeto já deixa o config.php de fora.

// ---------------------------------------------------------------------------
// Quem você é (aparece nas mensagens, no formulário e nas páginas de privacidade)
// ---------------------------------------------------------------------------
const MARCA = 'Seu Nome';                     // nome que assina as mensagens e aparece no topo das páginas
const INSTAGRAM_USUARIO = 'seuperfil';        // o @ do Instagram, sem o @
const EMAIL_CONTATO = 'voce@seudominio.com';  // e-mail público para pedidos de privacidade e exclusão de dados
const EMAIL_AVISO = 'voce@seudominio.com';    // recebe o aviso de lead novo e é o remetente dos e-mails
                                              // (precisa ser de um domínio autenticado no serviço de e-mail)
const WHATSAPP_EXEMPLO = '(11) 99999-9999';   // exemplo mostrado no campo de WhatsApp do formulário

// ---------------------------------------------------------------------------
// Endereço do sistema (o subdomínio onde estes arquivos estão), com https e sem barra no fim
// ---------------------------------------------------------------------------
const URL_BASE = 'https://ig.seudominio.com';

// ---------------------------------------------------------------------------
// Banco de dados MySQL (painel da hospedagem → Bancos de dados)
// ---------------------------------------------------------------------------
const DB_HOST = 'localhost';
const DB_NOME = 'COLE_AQUI';
const DB_USUARIO = 'COLE_AQUI';
const DB_SENHA = 'COLE_AQUI';

// ---------------------------------------------------------------------------
// App da Meta (developers.facebook.com)
// ---------------------------------------------------------------------------
// Configurações do app → Básico → Chave secreta do aplicativo
const APP_SECRET = 'COLE_AQUI';
// Casos de uso → API do Instagram → Configuração da API com login do Instagram → Chave secreta do app do Instagram
const IG_APP_SECRET = 'COLE_AQUI';
// Mesma tela → "Gerar tokens" → token da sua conta. O sistema renova sozinho a cada dia (cron/renovar-token.php).
const IG_TOKEN_INICIAL = 'COLE_AQUI';
// Uma frase qualquer inventada por você. É a mesma que você digita na tela de webhooks da Meta.
const VERIFY_TOKEN = 'COLE_AQUI';
const GRAPH = 'https://graph.instagram.com/v26.0';

// ---------------------------------------------------------------------------
// Página do Facebook (opcional). Deixe como está se for usar só o Instagram.
// ---------------------------------------------------------------------------
const FB_APP_ID = '';               // Configurações do app → Básico → ID do aplicativo
const FB_PAGINA = '';               // o endereço da Página: facebook.com/ESTE_PEDAÇO
const FB_TOKEN_INICIAL = 'COLE_AQUI'; // token de usuário do Explorador da Graph API (vence em 1 hora: use logo)

// ---------------------------------------------------------------------------
// Senha das páginas internas (leads.php, instalar.php, comentarios.php, conversas.php)
// Use uma senha longa, só para isto.
// ---------------------------------------------------------------------------
const ADMIN_SENHA = 'COLE_AQUI';

// ---------------------------------------------------------------------------
// E-mail. Com USAR_SMTP = false, o sistema usa o mail() da hospedagem, que costuma cair no spam
// ou nem sair. Recomendado: SMTP do Brevo (gratuito, 300 por dia). Veja INSTALACAO.md, passo 11.
// ---------------------------------------------------------------------------
const USAR_SMTP = false;
const SMTP_HOST = 'smtp-relay.brevo.com';
const SMTP_USUARIO = 'COLE_AQUI';   // login SMTP mostrado no Brevo (não é o seu e-mail)
const SMTP_SENHA = 'COLE_AQUI';     // chave SMTP gerada no Brevo
