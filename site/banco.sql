-- Tabelas do Auto Direct. O instalar.php roda isto sozinho, e o arquivo fica aqui para consulta.
-- Não usar ponto e vírgula em comentário: o instalar.php separa os comandos por ele.
-- canal: ig (Instagram) ou fb (Página do Facebook).

CREATE TABLE IF NOT EXISTS interacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  canal CHAR(2) NOT NULL DEFAULT 'ig',
  comment_id VARCHAR(64) NULL UNIQUE,
  ig_user_id VARCHAR(64) NOT NULL,
  username VARCHAR(100) NULL,
  palavra VARCHAR(40) NOT NULL,
  media_id VARCHAR(64) NULL,
  token CHAR(24) NOT NULL UNIQUE,
  etapa VARCHAR(20) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_usuario (ig_user_id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  canal CHAR(2) NOT NULL DEFAULT 'ig',
  interacao_id INT UNSIGNED NULL,
  ig_user_id VARCHAR(64) NOT NULL,
  username VARCHAR(100) NULL,
  palavra VARCHAR(40) NOT NULL,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  whatsapp VARCHAR(20) NOT NULL,
  ip VARCHAR(45) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_usuario_palavra (ig_user_id, palavra)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kv (
  chave VARCHAR(40) PRIMARY KEY,
  valor TEXT NOT NULL,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eventos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(40) NOT NULL,
  detalhe MEDIUMTEXT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_criado (criado_em)
) DEFAULT CHARSET=utf8mb4;
