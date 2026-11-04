<?php
// As suas palavras-chave e as mensagens de cada uma.
//
// Copie este arquivo para regras.php e edite lá. Enquanto o regras.php não existir, o sistema usa este exemplo.
// É o único arquivo que muda no dia a dia: palavra nova = bloco novo aqui.
//
// A chave é a palavra sem acento, em MAIÚSCULAS. Maiúscula e minúscula no comentário não importam:
// PLAYBOOK, Playbook e playbook disparam igual.
//
// Campos de cada palavra:
//   publico         respostas no comentário (o sistema sorteia uma)
//   dm1             primeiro Direct. {nome} vira ", Fulano" ou some quando a Meta não informa o nome
//   dm1_botao       texto do botão do primeiro Direct (até 20 caracteres)
//   dm2             pedido para seguir o perfil
//   dm2_botao       botão "Já segui" (até 20 caracteres)
//   dm2_ainda_nao   quando a pessoa toca em "Já segui" sem seguir
//   dm3             Direct com o botão do formulário (não usado com sem_form)
//   dm3_botao       botão do formulário (até 20 caracteres)
//   titulo_form     título do formulário e assunto do e-mail
//   entrega         Direct final, depois do formulário. Com url, vira botão
//   entrega_botao   texto do botão da entrega (até 20 caracteres)
//   url             para onde o formulário leva e para onde o botão da entrega aponta (opcional)
//   texto_pagina    texto mostrado numa caixa de copiar depois do formulário, no lugar de url (opcional)
//
// Opcionais:
//   sem_form        true: pula o formulário e manda a entrega logo depois de seguir
//   so_curto        true: só dispara se o comentário tiver até 4 palavras (para palavra comum, como PERFIL)
//   so_comentario   true: só dispara em comentário, nunca numa mensagem no Direct
//   outras          variações que levam ao mesmo fluxo, em MAIÚSCULAS e sem acento (erro de grafia, outra língua)
//   posts           ['ID_DO_POST']: a palavra só vale nesses posts (os IDs aparecem em comentarios.php)
//   idioma          'en': formulário e e-mail em inglês, e o lead aparece na aba "Leads de fora"
//   mostrar_entrega true: depois do formulário, mostra o texto da entrega e um botão, em vez de ir direto ao url
//   lembrete_dm2    lembrete para quem parou no "me segue" (sem ele vai um texto padrão em português)
//   lembrete_link   lembrete para quem parou no formulário (idem)
//
// Atenção: se você usa outra ferramenta (ManyChat, CRM) respondendo a mesma palavra, desligue lá antes,
// ou a pessoa recebe tudo em dobro.

$publico = [
    'Te mandei no Direct! Confere lá',
    'Acabei de te enviar no Direct, dá uma olhada lá',
];

return [
    // Exemplo 1: material com link (um PDF, uma página, um vídeo).
    'GUIA' => [
        'so_curto' => true,
        'publico' => $publico,
        'dm1' => 'Oi{nome}! Vi seu comentário e já separei o seu guia. Toca no botão abaixo que eu te mando o link.',
        'dm1_botao' => 'Me envie o link',
        'dm2' => 'Antes de te mandar, me segue aqui no perfil para não perder os próximos conteúdos. Já seguiu? Toca no botão abaixo.',
        'dm2_botao' => 'Já segui',
        'dm2_ainda_nao' => 'Ainda não apareceu que você me segue. Segue o perfil e toca de novo no botão que eu te envio na hora.',
        'dm3' => 'Pronto{nome}! Deixa seu nome, e-mail e WhatsApp nesta página rápida que o guia abre na hora.',
        'dm3_botao' => 'RECEBER AGORA',
        'titulo_form' => 'O guia completo',
        'entrega' => 'Está aqui o seu guia{nome}. Se ficar alguma dúvida, me chama aqui.',
        'entrega_botao' => 'ABRIR O GUIA',
        'url' => 'https://seudominio.com/guia/',
    ],

    // Exemplo 2: texto para copiar (um prompt, uma lista), sem link.
    'PROMPT' => [
        'publico' => $publico,
        'dm1' => 'Oi{nome}! Vi seu comentário e já separei o prompt do vídeo. Toca no botão abaixo que eu te mando.',
        'dm1_botao' => 'Me envie o prompt',
        'dm2' => 'Antes de te mandar o prompt, me segue aqui no perfil. Já seguiu? Toca no botão abaixo que eu te envio.',
        'dm2_botao' => 'Já segui',
        'dm2_ainda_nao' => 'Ainda não apareceu que você me segue. Segue o perfil e toca de novo no botão que eu te envio na hora.',
        'dm3' => 'Pronto{nome}! Deixa seu contato nesta página que o prompt aparece na hora.',
        'dm3_botao' => 'RECEBER PROMPT',
        'titulo_form' => 'O prompt do vídeo',
        'entrega' => 'Aqui está o prompt: [cole aqui o seu prompt]',
        'texto_pagina' => '[cole aqui o seu prompt]',
    ],

    // Exemplo 3: a mesma ideia para quem comenta em inglês.
    'GUIDE' => [
        'idioma' => 'en',
        'so_curto' => true,
        'publico' => [
            'Sent it to your DMs! Check it out',
            'Just sent it to your DMs, take a look',
        ],
        'dm1' => 'Hi{nome}! I saw your comment and your guide is ready. Tap the button below and I will send you the link.',
        'dm1_botao' => 'Send me the link',
        'dm2' => 'Before I send it, follow me here so you don\'t miss my next posts. Already following? Tap the button below.',
        'dm2_botao' => 'I followed',
        'dm2_ainda_nao' => 'It doesn\'t show that you follow me yet. Follow the profile and tap the button again.',
        'dm3' => 'Done{nome}! Leave your name, email and WhatsApp on this quick page and the guide opens right away.',
        'dm3_botao' => 'GET IT NOW',
        'lembrete_dm2' => 'There is still time{nome}! Just follow the profile and tap the button, and I will send it right away.',
        'lembrete_link' => 'There is still time{nome}! Your guide is still waiting for you. It takes less than a minute:',
        'titulo_form' => 'The complete guide',
        'entrega' => 'Here it is{nome}! If you have any questions, just reply here.',
        'entrega_botao' => 'OPEN THE GUIDE',
        'url' => 'https://seudominio.com/guide/',
    ],
];
