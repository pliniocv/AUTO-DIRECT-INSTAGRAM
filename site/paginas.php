<?php
// Molde das páginas públicas (início, privacidade, termos, exclusão de dados).
// Elas não usam o banco: só o config.php, para funcionar mesmo antes da instalação terminar.
// A Meta exige a política de privacidade e as instruções de exclusão de dados para publicar o app.

require_once __DIR__ . '/config.php';

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function tem_facebook(): bool
{
    return defined('FB_PAGINA') && FB_PAGINA !== '';
}

function pagina_inicio(string $titulo, string $lang = 'pt-BR'): void
{
    echo '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($titulo) . '</title><link rel="stylesheet" href="/estilo.css"></head><body>'
        . '<main class="caixa larga"><p class="marca">' . e(MARCA) . '</p><h1>' . e($titulo) . '</h1>';
}

function pagina_fim(): void
{
    echo '</main></body></html>';
}
