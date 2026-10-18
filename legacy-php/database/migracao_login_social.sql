-- ============================================================
-- Migração: adicionar suporte a login social (Google/Facebook)
-- Executa isto SÓ SE já tinhas a base de dados criada antes desta
-- funcionalidade. Se estás a criar a base de dados pela primeira
-- vez, usa apenas o schema.sql (já inclui estas colunas).
-- ============================================================
USE loja_artesanato;

ALTER TABLE clientes
  MODIFY senha_hash VARCHAR(255) NULL,
  ADD COLUMN provider ENUM('local','google','facebook') NOT NULL DEFAULT 'local' AFTER senha_hash,
  ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER provider,
  ADD COLUMN facebook_id VARCHAR(255) NULL UNIQUE AFTER google_id,
  ADD COLUMN avatar_url VARCHAR(500) NULL AFTER facebook_id;
