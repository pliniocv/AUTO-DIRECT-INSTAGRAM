---
name: instalar-auto-direct
description: Instala o Auto Direct do Instagram (comentário com palavra-chave vira Direct com o material) na hospedagem da própria pessoa. Use quando a pessoa pedir para instalar, configurar, criar palavras novas, ou quando algo do Auto Direct não estiver funcionando.
---

# Instalar o Auto Direct do Instagram

Você vai conduzir a instalação deste projeto com uma pessoa que, muito provavelmente, não é programadora.
O roteiro completo está em `INSTALACAO.md`, na raiz do repositório. Leia esse arquivo inteiro antes de começar:
ele é a fonte, e esta skill diz **como** conduzir.

## Regras que não se quebram

1. **Nunca peça, aceite ou repita um segredo na conversa.** Senha do banco, `APP_SECRET`, `IG_APP_SECRET`,
   tokens, `ADMIN_SENHA` e chave SMTP são colados **pela pessoa, direto no arquivo**. Você escreve `COLE_AQUI` e diz
   em qual linha ela cola. Se ela colar um segredo na conversa mesmo assim, avise que esse valor agora está no
   histórico e recomende gerar um novo no painel de origem.
2. **Nunca leia o `site/config.php` depois que a pessoa preencheu.** Para conferir se algo foi preenchido, procure
   só pelo texto `COLE_AQUI` (por exemplo, `grep -c COLE_AQUI site/config.php`), sem mostrar os valores.
3. **Um passo por vez.** Diga exatamente onde clicar, espere a pessoa confirmar e só então siga. Painel da Meta muda
   de nome de botão com frequência: se ela não achar, peça para descrever o que vê na tela.
4. **Fale da função, sem jargão.** "Aviso de comentário" em vez de "webhook" na primeira vez; explique sigla por
   extenso quando aparecer.
5. **Não prometa o que a API não deixa** (lista no fim do `README.md`).

## Roteiro

### Etapa 1: perguntas (antes de mexer em arquivo)

Pergunte, em uma mensagem só:

- nome que vai assinar as mensagens (a marca ou o nome da pessoa)
- o @ do Instagram (precisa ser conta profissional: comercial ou criador de conteúdo)
- o domínio próprio e qual hospedagem usa
- e-mail público de contato (vai na página de privacidade) e e-mail que recebe o aviso de lead
- as palavras-chave: para cada uma, o que a pessoa vai entregar (link ou texto para copiar) e se a palavra é comum
  demais (aí entra `so_curto`)
- se quer o passo "me segue antes de receber" (padrão: sim)
- se quer também a Página do Facebook (padrão: não, deixar para depois)

### Etapa 2: montar os arquivos dela

1. Copie `site/config.exemplo.php` para `site/config.php` e preencha **só** a parte "Quem você é", `URL_BASE`
   (sugira `https://ig.DOMINIO`) e `DB_HOST`. Os segredos ficam `COLE_AQUI`.
2. Copie `site/regras.exemplo.php` para `site/regras.php` e escreva as regras dela, no mesmo formato dos exemplos.
   Confira: chave em MAIÚSCULAS e sem acento, todo `*_botao` com no máximo 20 caracteres, `{nome}` grudado na palavra
   anterior ("Oi{nome}!"), e cada regra com `url` ou `texto_pagina`.
3. Se tiver PHP instalado, rode `php -l` nos dois arquivos. Se não tiver, releia o arquivo procurando aspas e vírgulas.

Os dois arquivos estão no `.gitignore`. Se a pessoa fez um fork e vai dar `git push`, confirme com
`git status --ignored` que eles aparecem como ignorados.

### Etapa 3: hospedagem

Siga os passos 1 e 2 do `INSTALACAO.md`. Depois, a pessoa preenche no `config.php` a senha do banco e a `ADMIN_SENHA`.
Confira com a contagem de `COLE_AQUI` (regra 2).

Monte o pacote para subir, **incluindo o `.htaccess`**:

- Windows: `tar -a -c -f auto-direct.zip -C site .`
- Mac ou Linux: `cd site && zip -r ../auto-direct.zip . && cd ..`

Ela envia o zip pelo Gerenciador de Arquivos, extrai na pasta do subdomínio e abre `instalar.php`.

### Etapa 4: Meta

Passos 5 a 9 do `INSTALACAO.md`, um por vez. Pontos onde a pessoa costuma travar:

- nome do app com "Instagram" é recusado
- o convite de testador precisa ser aceito **no Instagram**, em instagram.com/accounts/manage_access/
- depois de colar o token no `config.php`, é preciso **subir o arquivo de novo** (ou editar direto no
  Gerenciador de Arquivos, que é o mais simples)
- além de salvar o webhook, tem que clicar em "Assinar os avisos do Instagram" no `instalar.php`

### Etapa 5: cron e e-mail

Passos 10 e 11. No cron, ajude a achar o caminho completo da pasta (o painel da hospedagem costuma mostrar o modelo).

### Etapa 6: conferir de fora

Você pode testar o que é público sem senha nenhuma:

- `curl -s -o /dev/null -w "%{http_code}" https://ig.DOMINIO/webhook.php` → deve dar `403` (recusa quem não é a Meta)
- `https://ig.DOMINIO/privacidade/` e `/exclusao-de-dados/` → `200`, com o nome e o @ certos
- `https://ig.DOMINIO/config.php` e `/regras.php` → `403` (se der `200`, o `.htaccess` não subiu: pare e corrija)
- `https://ig.DOMINIO/f/?t=teste` → `404` com a página de "Link expirado", sem erro 500

Depois, o teste de verdade (passo 12): comentário de uma conta de amigo, sem ligação com o app.

## Depois de instalado

- **Palavra nova:** acrescente um bloco no `site/regras.php`, confira os 20 caracteres dos botões e suba só esse arquivo.
- **Não chegou nada:** peça para abrir `instalar.php` e ler "Últimos eventos". `assinatura_invalida` = `APP_SECRET`
  ou `IG_APP_SECRET` errado. `erro_api` com código 190 = token vencido ou errado. Nenhum evento = webhook não
  assinado ou app não publicado.
- **Direct em dobro:** outra ferramenta respondendo a mesma palavra.
