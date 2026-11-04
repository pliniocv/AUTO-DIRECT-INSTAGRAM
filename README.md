# Auto Direct do Instagram

Alguém comenta uma palavra no seu post e recebe o material no Direct, sozinho. Sem mensalidade de ferramenta:
roda na hospedagem que você já tem, com a API oficial da Meta.

## O que faz

1. A pessoa comenta uma palavra (ex.: GUIA) num post.
2. O sistema responde no comentário ("Te mandei no Direct!") e manda um Direct com um botão.
3. A pessoa toca no botão. O sistema confere se ela segue o perfil; se não segue, pede para seguir.
4. Quem segue recebe o link de um formulário (nome, e-mail e WhatsApp).
5. Preencheu: o material abre, chega no Direct e no e-mail, e você recebe um aviso de lead novo.
6. Quem parou no meio recebe um lembrete único perto de 24 horas depois.

Também tem: a mesma palavra mandada direto no Direct (bom para Stories), página de leads com planilha e botão
"Chamar no WhatsApp", funil por post, resposta a comentários antigos, versão em inglês para quem comenta de fora,
Página do Facebook pelo Messenger (opcional) e exclusão de dados automática pelo Direct.

## Do que precisa

| Peça | Custo |
|---|---|
| Conta profissional no Instagram (comercial ou criador) | 0 |
| Conta de desenvolvedor na Meta (developers.facebook.com) | 0 |
| Hospedagem com PHP 8, MySQL e tarefas agendadas (cron) | a que você já paga |
| Um subdomínio com HTTPS (ex.: `ig.seudominio.com`) | 0 |
| Serviço de e-mail para os avisos (Brevo, plano gratuito) | 0 |

## Instalar

**Com o Claude Code (mais fácil):** baixe este repositório, abra a pasta no Claude Code e digite:

```
/instalar-auto-direct
```

Ele pergunta o seu @, o domínio e as palavras, monta os seus arquivos e te guia em cada tela da Meta e da hospedagem.

**Sem o Claude Code:** siga o [INSTALACAO.md](INSTALACAO.md), passo a passo.

## Os seus dados ficam com você

- Cada pessoa cria o **próprio** app na Meta e roda o sistema na **própria** hospedagem. Ninguém mais vê os seus leads,
  nem o autor deste projeto.
- As senhas ficam no `site/config.php` e as suas palavras no `site/regras.php`. Os dois estão no `.gitignore`:
  não sobem para o GitHub se você fizer um fork.
- Nunca cole o conteúdo do `config.php` em conversa nenhuma, nem com o Claude. Preencha direto no arquivo.

## O que a API da Meta não deixa

- Mandar mensagem para quem só começou a seguir, sem interagir.
- Começar conversa: só dá para responder a um comentário (uma vez, até 7 dias depois) ou a quem escreveu (até 24 horas).
- Comentário que abre Direct no TikTok.

## Aviso

Projeto independente, sem ligação com a Meta, o Instagram ou qualquer ferramenta comercial de automação.
Use dentro das regras da plataforma. Sem garantia: a Meta muda a API de tempos em tempos.

Licença MIT. Feito por [Plinio Ventavoli](https://instagram.com/plinioventavoli).
