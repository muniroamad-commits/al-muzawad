-- ============================================================
-- Loja de Artesanato — Esquema da Base de Dados (MySQL 8+)
-- ============================================================
CREATE DATABASE IF NOT EXISTS loja_artesanato
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE loja_artesanato;

-- ------------------------------------------------------------
-- Administradores (painel de gestão)
-- ------------------------------------------------------------
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Clientes (utilizadores da app)
-- ------------------------------------------------------------
CREATE TABLE clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  telefone VARCHAR(30),
  senha_hash VARCHAR(255) NULL,       -- NULL quando a conta é só por login social
  provider ENUM('local','google','facebook') NOT NULL DEFAULT 'local',
  google_id VARCHAR(255) NULL UNIQUE,
  facebook_id VARCHAR(255) NULL UNIQUE,
  avatar_url VARCHAR(500) NULL,
  endereco TEXT,
  cidade VARCHAR(100),
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Categorias de produtos
-- ------------------------------------------------------------
CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  descricao VARCHAR(255),
  ordem INT DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Produtos
-- ------------------------------------------------------------
CREATE TABLE produtos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT,
  nome VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  descricao_curta VARCHAR(255),
  descricao_longa TEXT,
  preco DECIMAL(10,2) NOT NULL,
  preco_promocional DECIMAL(10,2) DEFAULT NULL,
  stock INT NOT NULL DEFAULT 0,
  stock_minimo INT NOT NULL DEFAULT 3,
  sku VARCHAR(60) UNIQUE,
  peso_g INT DEFAULT NULL,
  activo TINYINT(1) DEFAULT 1,
  destaque TINYINT(1) DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Imagens dos produtos (vários uploads por produto)
-- ------------------------------------------------------------
CREATE TABLE produto_imagens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  caminho VARCHAR(255) NOT NULL,
  principal TINYINT(1) DEFAULT 0,
  ordem INT DEFAULT 0,
  FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Variantes do produto (ex: aroma, tamanho) — opcional
-- ------------------------------------------------------------
CREATE TABLE produto_variantes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  nome VARCHAR(80) NOT NULL,      -- ex: "Aroma"
  valor VARCHAR(80) NOT NULL,     -- ex: "Lavanda"
  preco_extra DECIMAL(10,2) DEFAULT 0,
  stock INT DEFAULT 0,
  FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Encomendas
-- ------------------------------------------------------------
CREATE TABLE encomendas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  codigo VARCHAR(20) NOT NULL UNIQUE,     -- ex: ENC-20261017-0001
  status ENUM('pendente','pago','confirmado','em_preparacao','enviado','entregue','cancelado')
         DEFAULT 'pendente',
  subtotal DECIMAL(10,2) NOT NULL,
  taxa_entrega DECIMAL(10,2) DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  metodo_pagamento ENUM('mpesa','emola','mkesh','cartao','entrega') NOT NULL,
  estado_pagamento ENUM('pendente','pago','falhado','reembolsado') DEFAULT 'pendente',
  referencia_pagamento VARCHAR(100),
  endereco_entrega TEXT NOT NULL,
  cidade_entrega VARCHAR(100),
  telefone_contacto VARCHAR(30),
  notas TEXT,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Itens de cada encomenda
-- ------------------------------------------------------------
CREATE TABLE encomenda_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  encomenda_id INT NOT NULL,
  produto_id INT NOT NULL,
  variante_id INT DEFAULT NULL,
  nome_produto VARCHAR(150) NOT NULL,   -- snapshot no momento da compra
  preco_unitario DECIMAL(10,2) NOT NULL,
  quantidade INT NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (encomenda_id) REFERENCES encomendas(id) ON DELETE CASCADE,
  FOREIGN KEY (produto_id) REFERENCES produtos(id),
  FOREIGN KEY (variante_id) REFERENCES produto_variantes(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Registo de transacções de pagamento (log do gateway)
-- ------------------------------------------------------------
CREATE TABLE pagamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  encomenda_id INT NOT NULL,
  metodo VARCHAR(30) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  estado VARCHAR(30) NOT NULL,
  resposta_gateway TEXT,          -- JSON cru devolvido pelo agregador
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (encomenda_id) REFERENCES encomendas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Carrinho persistente (opcional — sincroniza entre sessões)
-- ------------------------------------------------------------
CREATE TABLE carrinho_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  produto_id INT NOT NULL,
  variante_id INT DEFAULT NULL,
  quantidade INT NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
  FOREIGN KEY (variante_id) REFERENCES produto_variantes(id) ON DELETE CASCADE,
  UNIQUE KEY unico_item (cliente_id, produto_id, variante_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Dados iniciais de exemplo
-- ------------------------------------------------------------
INSERT INTO categorias (nome, slug, descricao, ordem) VALUES
('Sabonetes', 'sabonetes', 'Sabonetes artesanais naturais', 1),
('Velas', 'velas', 'Velas aromáticas feitas à mão', 2),
('Cosmética Natural', 'cosmetica-natural', 'Cremes, óleos e produtos de cuidado', 3);

-- Admin inicial — IMPORTANTE: mudar a senha depois do primeiro login
-- senha por omissão: "mudar123" (hash gerado com password_hash do PHP)
INSERT INTO admins (nome, email, senha_hash) VALUES
('Administrador', 'admin@lojaartesanato.co.mz', '$2y$10$w.HiZoC5TNDfYE3K2EkbLuv6XYb.VCQlwccyUbFkJTKA1HkCaaKxq');
