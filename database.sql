-- =====================================================================
-- CEON - Plataforma Escolar
-- Script de criação do banco de dados e dados de demonstração
-- Escola CESI - SENAI DEV EXPERIENCE - Etapa Seletiva
-- =====================================================================

-- Garante que a importação interprete os acentos corretamente (UTF-8)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

DROP DATABASE IF EXISTS ceon;
CREATE DATABASE ceon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ceon;

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- TABELA: usuarios
-- =====================================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('aluno', 'professor', 'administrador') NOT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuarios_tipo (tipo_usuario),
    INDEX idx_usuarios_status (status)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: turmas
-- =====================================================================
CREATE TABLE turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    ano_letivo YEAR NOT NULL,
    turno ENUM('manha', 'tarde', 'noite') NOT NULL,
    UNIQUE KEY uq_turma (nome, ano_letivo)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: disciplinas
-- =====================================================================
CREATE TABLE disciplinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: professores (extensão de usuarios)
-- =====================================================================
CREATE TABLE professores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    CONSTRAINT fk_professores_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: alunos (extensão de usuarios)
-- =====================================================================
CREATE TABLE alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    turma_id INT NOT NULL,
    data_nascimento DATE NOT NULL,
    CONSTRAINT fk_alunos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_alunos_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE RESTRICT,
    INDEX idx_alunos_turma (turma_id)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: professor_disciplinas (N:N)
-- =====================================================================
CREATE TABLE professor_disciplinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    CONSTRAINT fk_pd_professor FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_disciplina FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_prof_disc (professor_id, disciplina_id)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: eventos
-- =====================================================================
CREATE TABLE eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    data DATE NOT NULL,
    hora TIME NULL,
    local VARCHAR(150) NULL,
    publico_alvo ENUM('todos', 'alunos', 'professores', 'turma') NOT NULL DEFAULT 'todos',
    turma_id INT NULL,
    criado_por INT NOT NULL,
    CONSTRAINT fk_eventos_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_eventos_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_eventos_data (data)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: aulas
-- =====================================================================
CREATE TABLE aulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turma_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    professor_id INT NOT NULL,
    data DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    sala VARCHAR(50) NULL,
    CONSTRAINT fk_aulas_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_aulas_disciplina FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    CONSTRAINT fk_aulas_professor FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    INDEX idx_aulas_data (data),
    INDEX idx_aulas_turma (turma_id)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: tarefas
-- =====================================================================
CREATE TABLE tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disciplina_id INT NOT NULL,
    turma_id INT NOT NULL,
    professor_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    data_entrega DATE NOT NULL,
    CONSTRAINT fk_tarefas_disciplina FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    CONSTRAINT fk_tarefas_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_tarefas_professor FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    INDEX idx_tarefas_entrega (data_entrega)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: tarefas_concluidas (controle de conclusão por aluno)
-- =====================================================================
CREATE TABLE tarefas_concluidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tarefa_id INT NOT NULL,
    aluno_id INT NOT NULL,
    data_conclusao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tc_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tarefa_aluno (tarefa_id, aluno_id)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: notas
-- =====================================================================
CREATE TABLE notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    valor DECIMAL(4,2) NOT NULL,
    peso DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    data_lancamento DATE NOT NULL,
    CONSTRAINT fk_notas_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    CONSTRAINT fk_notas_disciplina FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    INDEX idx_notas_aluno (aluno_id)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: faltas
-- =====================================================================
CREATE TABLE faltas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    data DATE NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_faltas_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    CONSTRAINT fk_faltas_disciplina FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    INDEX idx_faltas_aluno (aluno_id)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: comunicados
-- =====================================================================
CREATE TABLE comunicados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    data_publicacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    publico_alvo ENUM('todos', 'alunos', 'professores', 'turma') NOT NULL DEFAULT 'todos',
    turma_id INT NULL,
    autor_id INT NOT NULL,
    CONSTRAINT fk_comunicados_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_comunicados_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_comunicados_data (data_publicacao)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: materiais
-- =====================================================================
CREATE TABLE materiais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    turma_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    arquivo VARCHAR(255) NOT NULL,
    data_upload DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_materiais_professor FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    CONSTRAINT fk_materiais_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_materiais_disciplina FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA: cardapio
-- =====================================================================
CREATE TABLE cardapio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data DATE NOT NULL,
    refeicao ENUM('cafe_da_manha', 'almoco', 'lanche_da_tarde') NOT NULL,
    descricao TEXT NOT NULL,
    UNIQUE KEY uq_cardapio (data, refeicao)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DADOS DE DEMONSTRAÇÃO
-- =====================================================================

-- Senhas com hash bcrypt real, gerado via password_hash() com PASSWORD_DEFAULT:
-- admin123     -> $2y$10$iso5iq5nyqngbgO9rboAGO36TnMsEPAzK5tREgrCEC.1AAN85RBJ2
-- professor123 -> $2y$10$arYCH5PugF9UKT1qS/R78.ns1kdT2FyhIZFmcDh6KgMjT42KGDQlO
-- aluno123     -> $2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta

INSERT INTO usuarios (nome, email, senha, tipo_usuario, status) VALUES
('Ana Beatriz Souza', 'admin@ceon.com', '$2y$10$iso5iq5nyqngbgO9rboAGO36TnMsEPAzK5tREgrCEC.1AAN85RBJ2', 'administrador', 'ativo'),
('Carlos Eduardo Lima', 'professor@ceon.com', '$2y$10$arYCH5PugF9UKT1qS/R78.ns1kdT2FyhIZFmcDh6KgMjT42KGDQlO', 'professor', 'ativo'),
('Mariana Alves Costa', 'professor2@ceon.com', '$2y$10$arYCH5PugF9UKT1qS/R78.ns1kdT2FyhIZFmcDh6KgMjT42KGDQlO', 'professor', 'ativo'),
('Roberto Nunes Silva', 'professor3@ceon.com', '$2y$10$arYCH5PugF9UKT1qS/R78.ns1kdT2FyhIZFmcDh6KgMjT42KGDQlO', 'professor', 'ativo'),
('João Pedro Martins', 'aluno@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Laura Fernandes Rocha', 'aluno2@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Gabriel Henrique Dias', 'aluno3@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Isabela Cristina Melo', 'aluno4@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Lucas Gabriel Pereira', 'aluno5@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Beatriz Oliveira Santos', 'aluno6@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Thiago Almeida Ramos', 'aluno7@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Camila Rodrigues Farias', 'aluno8@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Pedro Henrique Barros', 'aluno9@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo'),
('Sofia Cardoso Teixeira', 'aluno10@ceon.com', '$2y$10$WDMyU3gClXl4rGnDfvWksePlyzt1UMqLa2VpgvG/06FxxtXS1/gta', 'aluno', 'ativo');

INSERT INTO turmas (nome, ano_letivo, turno) VALUES
('1º Ano A - Ensino Médio', 2026, 'manha'),
('2º Ano B - Ensino Médio', 2026, 'tarde'),
('3º Ano C - Ensino Médio', 2026, 'manha');

INSERT INTO disciplinas (nome, descricao) VALUES
('Matemática', 'Álgebra, geometria e funções'),
('Português', 'Gramática, literatura e redação'),
('Física', 'Mecânica, termologia e eletricidade'),
('Química', 'Química geral e orgânica'),
('História', 'História do Brasil e geral');

INSERT INTO professores (usuario_id) VALUES
(2), -- Carlos Eduardo Lima
(3), -- Mariana Alves Costa
(4); -- Roberto Nunes Silva

INSERT INTO professor_disciplinas (professor_id, disciplina_id) VALUES
(1, 1), -- Carlos -> Matemática
(1, 3), -- Carlos -> Física
(2, 2), -- Mariana -> Português
(2, 5), -- Mariana -> História
(3, 4); -- Roberto -> Química

-- Alunos (turma_id: 1, 2 ou 3 distribuídos)
INSERT INTO alunos (usuario_id, turma_id, data_nascimento) VALUES
(5, 1, '2009-03-12'),
(6, 1, '2009-07-25'),
(7, 1, '2009-01-30'),
(8, 2, '2008-11-05'),
(9, 2, '2008-05-18'),
(10, 2, '2008-09-22'),
(11, 3, '2007-02-14'),
(12, 3, '2007-06-09'),
(13, 3, '2007-10-27'),
(14, 1, '2009-04-03');

INSERT INTO eventos (titulo, descricao, data, hora, local, publico_alvo, turma_id, criado_por) VALUES
('Reunião de Pais e Mestres', 'Reunião geral para apresentação do desempenho do 3º bimestre.', '2026-09-25', '19:00:00', 'Auditório Principal', 'todos', NULL, 1),
('Feira de Ciências CESI', 'Exposição dos projetos desenvolvidos pelos alunos.', '2026-10-10', '08:30:00', 'Quadra Poliesportiva', 'todos', NULL, 1),
('Prova Bimestral de Matemática', 'Avaliação referente ao 3º bimestre.', '2026-09-30', '08:00:00', 'Sala 101', 'turma', 1, 2),
('Palestra sobre Mercado de Trabalho', 'Palestra voltada aos alunos do 3º ano.', '2026-10-05', '14:00:00', 'Auditório Principal', 'turma', 3, 1),
('Capacitação Pedagógica', 'Formação continuada para o corpo docente.', '2026-09-28', '18:00:00', 'Sala dos Professores', 'professores', NULL, 1),
('Entrega de Boletins', 'Entrega dos boletins referentes ao 3º bimestre.', '2026-10-15', '10:00:00', 'Secretaria', 'todos', NULL, 1);

INSERT INTO aulas (turma_id, disciplina_id, professor_id, data, hora_inicio, hora_fim, sala) VALUES
(1, 1, 1, '2026-09-21', '08:00:00', '09:30:00', 'Sala 101'),
(1, 3, 1, '2026-09-22', '10:00:00', '11:30:00', 'Sala 101'),
(2, 2, 2, '2026-09-21', '14:00:00', '15:30:00', 'Sala 102'),
(2, 5, 2, '2026-09-23', '14:00:00', '15:30:00', 'Sala 102'),
(3, 4, 3, '2026-09-22', '08:00:00', '09:30:00', 'Sala 103'),
(1, 1, 1, '2026-09-24', '08:00:00', '09:30:00', 'Sala 101'),
(2, 2, 2, '2026-09-24', '14:00:00', '15:30:00', 'Sala 102');

INSERT INTO tarefas (disciplina_id, turma_id, professor_id, titulo, descricao, data_entrega) VALUES
(1, 1, 1, 'Lista de Exercícios - Funções', 'Resolver os exercícios 1 a 20 do capítulo 4.', '2026-09-25'),
(3, 1, 1, 'Relatório de Laboratório', 'Relatório sobre o experimento de queda livre.', '2026-09-28'),
(2, 2, 2, 'Redação Dissertativa', 'Produzir texto dissertativo-argumentativo sobre tecnologia e educação.', '2026-09-26'),
(5, 2, 2, 'Trabalho sobre Revolução Industrial', 'Pesquisa em grupo com apresentação.', '2026-10-02'),
(4, 3, 3, 'Exercícios de Estequiometria', 'Resolver lista de exercícios do módulo 3.', '2026-09-20'),
(1, 1, 1, 'Prova Simulada', 'Simulado preparatório para avaliação bimestral.', '2026-09-18');

INSERT INTO notas (aluno_id, disciplina_id, valor, peso, data_lancamento) VALUES
(1, 1, 8.5, 2.0, '2026-08-20'), (1, 2, 7.5, 1.0, '2026-08-22'), (1, 3, 9.0, 2.0, '2026-08-25'),
(2, 1, 7.0, 2.0, '2026-08-20'), (2, 2, 8.0, 1.0, '2026-08-22'), (2, 3, 6.5, 2.0, '2026-08-25'),
(3, 1, 9.5, 2.0, '2026-08-20'), (3, 2, 8.5, 1.0, '2026-08-22'), (3, 3, 7.5, 2.0, '2026-08-25'),
(4, 2, 7.0, 1.0, '2026-08-21'), (4, 5, 8.0, 1.5, '2026-08-23'),
(5, 2, 8.5, 1.0, '2026-08-21'), (5, 5, 9.0, 1.5, '2026-08-23'),
(6, 2, 6.5, 1.0, '2026-08-21'), (6, 5, 7.0, 1.5, '2026-08-23'),
(7, 4, 8.0, 2.0, '2026-08-24'),
(8, 4, 7.5, 2.0, '2026-08-24'),
(9, 4, 9.0, 2.0, '2026-08-24'),
(10, 1, 6.0, 2.0, '2026-08-20');

INSERT INTO faltas (aluno_id, disciplina_id, data, quantidade) VALUES
(1, 1, '2026-08-10', 1),
(1, 3, '2026-08-17', 1),
(2, 1, '2026-08-10', 1),
(4, 2, '2026-08-12', 1),
(4, 2, '2026-08-19', 1),
(7, 4, '2026-08-14', 1),
(10, 1, '2026-08-10', 2);

INSERT INTO comunicados (titulo, mensagem, publico_alvo, turma_id, autor_id) VALUES
('Início do 4º Bimestre', 'Informamos que o 4º bimestre letivo terá início no dia 01/10/2026. Contamos com o empenho de todos!', 'todos', NULL, 1),
('Alteração no Calendário de Provas', 'A prova de Matemática do 1º Ano A foi remarcada para o dia 30/09/2026.', 'turma', 1, 2),
('Reunião Pedagógica', 'Convocamos todos os professores para reunião pedagógica no dia 28/09/2026 às 18h.', 'professores', NULL, 1),
('Campanha do Agasalho', 'A escola está arrecadando agasalhos até o dia 10/10/2026. Contamos com a colaboração de todos os alunos.', 'alunos', NULL, 1),
('Entrega de Trabalho Adiada', 'A entrega do trabalho de História do 2º Ano B foi adiada para 05/10/2026.', 'turma', 2, 2);

INSERT INTO materiais (professor_id, turma_id, disciplina_id, titulo, descricao, arquivo) VALUES
(1, 1, 1, 'Apostila de Funções', 'Material de apoio sobre funções do 1º grau e 2º grau.', 'apostila_funcoes.pdf'),
(1, 1, 3, 'Slides - Cinemática', 'Slides utilizados em aula sobre cinemática.', 'slides_cinematica.pdf'),
(2, 2, 2, 'Guia de Redação ENEM', 'Guia completo para redação dissertativo-argumentativa.', 'guia_redacao_enem.pdf'),
(2, 2, 5, 'Resumo - Revolução Industrial', 'Resumo com linha do tempo dos principais eventos.', 'resumo_revolucao_industrial.pdf'),
(3, 3, 4, 'Lista de Exercícios - Estequiometria', 'Lista complementar de exercícios.', 'lista_estequiometria.pdf');

INSERT INTO cardapio (data, refeicao, descricao) VALUES
('2026-09-21', 'cafe_da_manha', 'Leite, pão de queijo e frutas variadas'),
('2026-09-21', 'almoco', 'Arroz, feijão, frango grelhado, salada e fruta'),
('2026-09-21', 'lanche_da_tarde', 'Suco natural e bolo caseiro'),
('2026-09-22', 'cafe_da_manha', 'Leite, torrada integral e mamão'),
('2026-09-22', 'almoco', 'Arroz, feijão, carne moída, purê de batata e salada'),
('2026-09-22', 'lanche_da_tarde', 'Iogurte e granola'),
('2026-09-23', 'cafe_da_manha', 'Leite, cuscuz e banana'),
('2026-09-23', 'almoco', 'Arroz, feijão, peixe assado, legumes e salada'),
('2026-09-23', 'lanche_da_tarde', 'Suco natural e biscoito integral'),
('2026-09-24', 'cafe_da_manha', 'Leite, pão integral e melancia'),
('2026-09-24', 'almoco', 'Arroz, feijão, strogonoff de frango, batata palha e salada'),
('2026-09-24', 'lanche_da_tarde', 'Vitamina de frutas'),
('2026-09-25', 'cafe_da_manha', 'Leite, biscoito e maçã'),
('2026-09-25', 'almoco', 'Arroz, feijão, omelete, salada e fruta'),
('2026-09-25', 'lanche_da_tarde', 'Suco natural e pão de forma com geleia');
