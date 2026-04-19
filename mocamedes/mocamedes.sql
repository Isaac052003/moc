-- =========================================
-- BASE DE DADOS
-- =========================================
CREATE DATABASE IF NOT EXISTS mocamedes;
USE mocamedes;

-- =========================================
-- TABELA DE USUÁRIOS
-- =========================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    bi VARCHAR(20) UNIQUE NOT NULL,
    telefone VARCHAR(20),
    senha VARCHAR(255) NOT NULL,
    
    tipo ENUM(
        'cidadao',
        'funcionario',
        'admin_municipal',
        'admin_sistema'
    ) DEFAULT 'cidadao',
    
    estado ENUM('ativo','inativo') DEFAULT 'ativo',
    
    foto VARCHAR(255),
    
    data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_login DATETIME,
    
    INDEX idx_tipo (tipo),
    INDEX idx_estado (estado)
);

-- =========================================
-- TABELA DE SERVIÇOS
-- =========================================
CREATE TABLE servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO servicos (nome, descricao) VALUES
('Regularização de Terreno', 'Solicitação para regularização de propriedades e terrenos municipais'),
('Autorização para Evento', 'Pedido de autorização para realização de eventos públicos ou privados'),
('Licença de Construção', 'Solicitação de licença para construção, reforma ou ampliação');

-- =========================================
-- TABELA DE SOLICITAÇÕES
-- =========================================
CREATE TABLE solicitacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_processo VARCHAR(30) UNIQUE,
    usuario_id INT NOT NULL,
    servico_id INT NOT NULL,
    descricao TEXT NOT NULL,
    
    estado ENUM(
        'Pendente',
        'Rececao',
        'Area_tecnica',
        'Vistoria',
        'INOTU',
        'Pagamento',
        'Levantamento'
    ) DEFAULT 'Pendente',

    data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE RESTRICT,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_servico (servico_id),
    INDEX idx_estado (estado),
    INDEX idx_numero_processo (numero_processo)
);

-- =========================================
-- TABELA DE DOCUMENTOS / ANEXOS
-- =========================================
CREATE TABLE documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id INT NOT NULL,
    tipo_documento VARCHAR(100) NOT NULL,
    arquivo VARCHAR(255) NOT NULL,
    tamanho INT, -- tamanho em bytes
    mime_type VARCHAR(100),
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
    
    INDEX idx_solicitacao (solicitacao_id)
);

-- =========================================
-- TABELA DE TRAMITAÇÃO (MOVIMENTO DO PROCESSO)
-- =========================================
CREATE TABLE tramitacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id INT NOT NULL,
    funcionario_id INT NOT NULL,
    estado_anterior VARCHAR(50),
    estado_novo VARCHAR(50) NOT NULL,
    comentario TEXT,
    data_movimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    
    INDEX idx_solicitacao (solicitacao_id),
    INDEX idx_funcionario (funcionario_id),
    INDEX idx_data (data_movimento)
);

-- =========================================
-- TABELA DE PEDIDOS DE CORREÇÃO
-- =========================================
CREATE TABLE correcoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id INT NOT NULL,
    funcionario_id INT NOT NULL,
    mensagem TEXT NOT NULL,
    resolvido BOOLEAN DEFAULT FALSE,
    data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_resolucao DATETIME,
    
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    
    INDEX idx_solicitacao (solicitacao_id),
    INDEX idx_resolvido (resolvido)
);

-- =========================================
-- TABELA DE NOTIFICAÇÕES
-- =========================================
CREATE TABLE notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    remetente_id INT,
    tipo ENUM('info', 'sucesso', 'aviso', 'erro') DEFAULT 'info',
    titulo VARCHAR(100),
    mensagem TEXT NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    link VARCHAR(255), -- link para redirecionamento

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (remetente_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_lida (lida),
    INDEX idx_data (data_envio)
);

-- =========================================
-- TABELA DE LOGS DO SISTEMA
-- =========================================
CREATE TABLE logs_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    acao VARCHAR(100) NOT NULL,
    descricao TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_log TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_acao (acao),
    INDEX idx_data (data_log)
);

-- =========================================
-- TABELA DE CONFIGURAÇÕES DO SISTEMA
-- =========================================
CREATE TABLE configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao VARCHAR(255),
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO configuracoes (chave, valor, descricao) VALUES
('sistema_nome', 'Portal do Moçamedense', 'Nome do sistema'),
('sistema_versao', '1.0.0', 'Versão do sistema'),
('email_contato', 'geral@mocamedes.gov.ao', 'Email de contato'),
('telefone_contato', '+244 000 000 000', 'Telefone de contato'),
('endereco', 'Moçâmedes, Namibe, Angola', 'Endereço da administração');

-- =========================================
-- TABELA DE MENSAGENS ENTRE USUÁRIOS
-- =========================================
CREATE TABLE mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    assunto VARCHAR(200),
    mensagem TEXT NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_leitura DATETIME,
    
    FOREIGN KEY (remetente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    
    INDEX idx_remetente (remetente_id),
    INDEX idx_destinatario (destinatario_id),
    INDEX idx_lida (lida)
);

-- =========================================
-- TABELA DE ANEXOS DAS MENSAGENS
-- =========================================
CREATE TABLE anexos_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensagem_id INT NOT NULL,
    arquivo VARCHAR(255) NOT NULL,
    tipo_documento VARCHAR(100),
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (mensagem_id) REFERENCES mensagens(id) ON DELETE CASCADE
);

-- =========================================
-- INSERIR APENAS O ADMINISTRADOR DO SISTEMA (OBRIGATÓRIO)
-- =========================================
INSERT INTO usuarios (nome, email, bi, telefone, senha, tipo, estado) VALUES
('Administrador do Sistema', 'admin@portal.com', '000000000LA000', '+244 900 000 000', '123456', 'admin_sistema', 'ativo');

-- =========================================
-- NOTA: OS DADOS DE EXEMPLO FORAM REMOVIDOS
-- =========================================
-- Para testar o sistema, cadastre usuários através da interface:
-- 1. Cidadãos: usar a página de cadastro público
-- 2. Funcionários e Admins: usar o painel do admin_sistema
-- 3. Solicitações: criar através do painel do cidadão

-- =========================================
-- CRIAR TRIGGER PARA ATUALIZAR DATA DE ATUALIZAÇÃO
-- =========================================
DELIMITER $$
CREATE TRIGGER update_solicitacoes_timestamp
BEFORE UPDATE ON solicitacoes
FOR EACH ROW
BEGIN
    SET NEW.data_atualizacao = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- =========================================
-- CRIAR TRIGGER PARA REGISTRAR NOVA TRAMITAÇÃO
-- =========================================
DELIMITER $$
CREATE TRIGGER after_solicitacao_update
AFTER UPDATE ON solicitacoes
FOR EACH ROW
BEGIN
    IF OLD.estado != NEW.estado THEN
        INSERT INTO tramitacao (solicitacao_id, funcionario_id, estado_anterior, estado_novo, comentario)
        VALUES (NEW.id, @current_user_id, OLD.estado, NEW.estado, 'Estado atualizado automaticamente');
    END IF;
END$$
DELIMITER ;

-- =========================================
-- CRIAR PROCEDURE PARA ESTATÍSTICAS
-- =========================================
DELIMITER $$
CREATE PROCEDURE get_estatisticas_gerais()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM usuarios) as total_usuarios,
        (SELECT COUNT(*) FROM usuarios WHERE tipo = 'cidadao') as total_cidadaos,
        (SELECT COUNT(*) FROM usuarios WHERE tipo = 'funcionario') as total_funcionarios,
        (SELECT COUNT(*) FROM usuarios WHERE tipo IN ('admin_municipal', 'admin_sistema')) as total_admins,
        (SELECT COUNT(*) FROM solicitacoes) as total_solicitacoes,
        (SELECT COUNT(*) FROM solicitacoes WHERE estado = 'Pendente') as pendentes,
        (SELECT COUNT(*) FROM solicitacoes WHERE estado = 'Levantamento') as concluidas,
        (SELECT COUNT(*) FROM correcoes WHERE resolvido = FALSE) as correcoes_pendentes;
END$$
DELIMITER ;

-- =========================================
-- CRIAR VIEW PARA RESUMO DE SOLICITAÇÕES
-- =========================================
CREATE VIEW view_resumo_solicitacoes AS
SELECT 
    s.id,
    s.numero_processo,
    u.nome as cidadao_nome,
    u.email as cidadao_email,
    serv.nome as servico,
    s.estado,
    s.data_solicitacao,
    s.data_atualizacao,
    (SELECT COUNT(*) FROM documentos WHERE solicitacao_id = s.id) as total_documentos,
    (SELECT COUNT(*) FROM correcoes WHERE solicitacao_id = s.id AND resolvido = FALSE) as correcoes_pendentes
FROM solicitacoes s
JOIN usuarios u ON s.usuario_id = u.id
JOIN servicos serv ON s.servico_id = serv.id;

-- =========================================
-- CRIAR VIEW PARA NOTIFICAÇÕES NÃO LIDAS
-- =========================================
CREATE VIEW view_notificacoes_nao_lidas AS
SELECT 
    n.*,
    u.nome as remetente_nome
FROM notificacoes n
LEFT JOIN usuarios u ON n.remetente_id = u.id
WHERE n.lida = FALSE
ORDER BY n.data_envio DESC;

-- =========================================
-- INSERIR APENAS O ADMINISTRADOR DO SISTEMA
-- =========================================
INSERT INTO usuarios (nome, email, bi, telefone, senha, tipo, estado) 
VALUES (
    'Administrador do Sistema',
    'admin@portal.com',
    '000000000LA000',
    '+244 900 000 000',
    '123456',  -- Senha em texto simples
    'admin_sistema',
    'ativo'
);
