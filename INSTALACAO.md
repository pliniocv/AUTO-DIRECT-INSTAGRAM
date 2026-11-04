# Instalação passo a passo

Leva de 1 a 2 horas na primeira vez. A parte mais demorada é o painel da Meta, que não dá para automatizar.
Onde há segredo, está escrito "você cola": cole direto no arquivo, nunca numa conversa.

Se você usa o Claude Code, abra esta pasta nele e digite `/instalar-auto-direct`: ele faz este roteiro com você.

## 1. Conferir a hospedagem

No painel da hospedagem, veja se o plano tem: PHP 8 ou mais novo, bancos de dados MySQL e tarefas agendadas (cron).
Planos compartilhados comuns (Hostinger Premium, HostGator, Locaweb e parecidos) costumam ter os três.

## 2. Criar o subdomínio e o banco

1. Crie um subdomínio, por exemplo `ig.seudominio.com`, e ative o HTTPS (SSL) nele. A Meta só chama endereço com HTTPS.
2. Crie um banco MySQL. Anote o nome do banco, o usuário e a senha.

## 3. Preencher os seus arquivos

Dentro da pasta `site/`:

1. Copie `config.exemplo.php` para `config.php` e preencha a parte "Quem você é", o `URL_BASE`, os dados do banco
   e a `ADMIN_SENHA` (uma senha longa, só para as páginas internas). O resto você preenche nos próximos passos.
2. Copie `regras.exemplo.php` para `regras.php` e escreva as suas palavras e mensagens. Os campos estão explicados
   no topo do arquivo. Botão aceita no máximo 20 caracteres.

## 4. Subir o código

Compacte o conteúdo da pasta `site/` num zip (o conteúdo, não a pasta), envie pelo Gerenciador de Arquivos da
hospedagem para a pasta do subdomínio e extraia lá. Confira se o `.htaccess` foi junto (é um arquivo oculto).

Abra `https://ig.seudominio.com/instalar.php`, entre com a `ADMIN_SENHA` e veja "Banco conectado" e "Tabelas criadas".

## 5. Criar o app na Meta

1. developers.facebook.com → Meus apps → Criar aplicativo.
2. Nome do app **sem** a palavra "Instagram" (a Meta recusa). Ex.: "Fulano Direct".
3. Caso de uso: **"Gerenciar mensagens e conteúdo no Instagram"**.
4. Portfólio empresarial: se você tiver um verificado, conecte (ajuda na publicação).
5. Configurações do app → Básico → copie a **Chave secreta do aplicativo** para `APP_SECRET` (você cola).

## 6. Permissões

Casos de uso → API do Instagram → Permissões e recursos → "Adicionar":

- `instagram_business_basic`
- `instagram_business_manage_comments`
- `instagram_business_manage_messages`
- recurso **Business Asset User Profile Access** (para saber o nome e se a pessoa segue)

## 7. Ligar a sua conta do Instagram e gerar o token

1. Funções do app → Adicionar pessoas → **Testador do Instagram** → o @ da sua conta.
2. No Instagram, aceite o convite: instagram.com/accounts/manage_access/ → Convites de testador.
3. Casos de uso → "Configuração da API com login do Instagram":
   - copie a **Chave secreta do app do Instagram** para `IG_APP_SECRET` (você cola)
   - "Gerar tokens" → Adicionar conta → login → Gerar token → cole em `IG_TOKEN_INICIAL`
4. Invente uma frase qualquer e cole em `VERIFY_TOKEN`.
5. Suba o `config.php` atualizado e abra `instalar.php`: a linha "Token do Instagram" deve mostrar o seu @.

## 8. Webhook (o aviso de comentário e de mensagem)

1. Mesma tela → "Configurar webhooks" → URL de callback `https://ig.seudominio.com/webhook.php` e o mesmo
   `VERIFY_TOKEN` → Verificar e salvar. Certificado de cliente: deixe desligado.
2. Ligue os campos `comments`, `messages` e `messaging_postbacks`.
3. No `instalar.php`, clique em **"Assinar os avisos do Instagram"**.
4. No painel da Meta, botão "Teste" do campo `comments`: o aviso de exemplo aparece em "Últimos eventos" no `instalar.php`.

## 9. Publicar o app

1. Configurações do app → Básico: preencha
   - URL da política de privacidade: `https://ig.seudominio.com/privacidade/`
   - URL dos termos de uso: `https://ig.seudominio.com/termos/`
   - URL de exclusão de dados: `https://ig.seudominio.com/exclusao-de-dados/`
   - categoria e ícone
2. Publicar → Publicar.

Sobre a análise da Meta: a documentação diz que o aviso de comentário de outras pessoas precisa de "acesso avançado",
que passa pela análise do app. No teste do autor, com o app só publicado, o comentário de uma conta sem ligação
nenhuma com o app já chegou e o fluxo rodou inteiro. Isso pode variar de conta para conta e a Meta pode mudar.
Se o comentário de terceiros não chegar, peça a análise das três permissões do passo 6.

## 10. Tarefas agendadas (cron)

No painel da hospedagem, crie três tarefas (troque o caminho pelo da sua pasta; o painel costuma mostrar):

| Arquivo | Quando | Para quê |
|---|---|---|
| `cron/renovar-token.php` | uma vez por dia | o token do Instagram vence em 60 dias; isto renova |
| `cron/lembrete.php` | de hora em hora | lembrete para quem parou no meio |
| `cron/varrer-direct.php` | a cada 10 minutos | pega quem mandou a palavra no Direct e a Meta não avisou |

Comando típico: `php /home/SEU_USUARIO/domains/seudominio.com/public_html/ig/cron/lembrete.php`

## 11. E-mail (onde mais se apanha)

- Se o e-mail do seu domínio fica em outro serviço (Zoho, Google Workspace), o `mail()` da hospedagem geralmente
  não entrega, e o DMARC do domínio barra o que sai de servidor não autorizado.
- **O que funcionou: Brevo gratuito (300 e-mails por dia).** Crie a conta → Remetentes, domínios → Adicionar domínio →
  método manual → coloque no DNS os registros que ele mostra (um TXT e dois CNAME) → Verificar → Autenticar.
  Depois: SMTP e API → gerar chave SMTP → no `config.php`, `USAR_SMTP = true`, `SMTP_USUARIO` (o login SMTP que o Brevo
  mostra) e `SMTP_SENHA` (a chave). Você cola.
- O `EMAIL_AVISO` precisa ser um endereço do domínio que você autenticou.
- Teste pelo botão "Testar e-mail de aviso" no `instalar.php`. Não chegou? Use "Testar para outro endereço" com um
  Gmail e abra "Mostrar original" para ver se SPF, DKIM e DMARC passaram.

## 12. Testar

1. Comente a palavra num post com **outra** conta. Veja em `instalar.php` → Últimos eventos.
2. Toque no botão, siga, preencha. Confira o lead em `leads.php` e o e-mail.
3. Teste com a conta de um amigo, sem ligação nenhuma com o app: é o teste que vale.

## Página do Facebook (opcional)

O mesmo sistema responde comentários da sua Página pelo Messenger. No Facebook não há o passo "me segue":
a API não deixa conferir quem curte a Página.

1. No app, adicione os casos de uso "Gerenciar tudo na sua Página" e "Messenger from Meta", com as permissões
   `pages_show_list`, `pages_manage_metadata`, `pages_messaging`, `pages_read_engagement`,
   `pages_manage_engagement` e `pages_read_user_content`.
2. Webhooks, objeto **Page**: mesmo endereço e mesmo `VERIFY_TOKEN`; ligue os campos `feed`, `messages` e
   `messaging_postbacks` na lista "Campos do webhook" (além da assinatura do passo 4).
3. No `config.php`: `FB_APP_ID` (Configurações → Básico → ID do aplicativo) e `FB_PAGINA` (o pedaço depois de facebook.com/).
4. Explorador da Graph API: gere um token **de usuário** com as 6 permissões, escolhendo a sua Página, e cole em
   `FB_TOKEN_INICIAL`. Ele vence em 1 hora: siga logo para o próximo passo.
5. `instalar.php` → "Ligar a Página do Facebook" (troca por um token da Página, que não vence) →
   "Assinar os avisos da Página".

## Armadilhas e o que fazer

| Problema | Causa | Solução |
|---|---|---|
| Direct em dobro | outra ferramenta (ManyChat, CRM) respondendo a mesma palavra | desligue o fluxo antigo antes de ativar a palavra |
| Palavra comum dispara à toa ("amei seu perfil") | qualquer comentário com a palavra | `so_curto` (até 4 palavras) ou prender a palavra a um post (`posts`) |
| Nome não aparece no 1º Direct | a Meta só libera o perfil depois que a pessoa interage | normal: o nome entra a partir do toque no botão |
| Botão não aparece | texto do botão com mais de 20 caracteres | encurtar |
| Formulário dá erro 500 | banco ainda sem tabelas | abra o `instalar.php` uma vez |
| "Falta o config.php" | o arquivo não subiu ou ficou com outro nome | confira no Gerenciador de Arquivos |
| Arquivo não foi substituído | upload interrompido | confira a data e o tamanho do arquivo no gerenciador |
| Quem vem de anúncio e manda a palavra no Direct não recebe | a Meta às vezes não avisa | o `cron/varrer-direct.php` resolve; `conversas.php` mostra os casos |
| Comentário antigo, de antes da palavra existir | não houve aviso na hora | `comentarios.php` → Procurar (até 7 dias) |

## Páginas internas (com a sua `ADMIN_SENHA`)

- `leads.php`: leads, funil por post, planilha e botão "Chamar no WhatsApp"
- `comentarios.php`: comentários sem resposta e IDs dos posts
- `conversas.php`: conversas do Direct com palavra que não receberam o fluxo
- `instalar.php`: diagnóstico, botões de assinar avisos e de testar e-mail
