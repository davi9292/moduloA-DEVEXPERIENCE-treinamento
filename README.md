# CEON — Plataforma Escolar

Sistema de gestão escolar desenvolvido para a **Escola CESI**, com três perfis de acesso (aluno, professor e administrador).

Projeto desenvolvido para o **SENAI DEV EXPERIENCE – Etapa Seletiva – Módulo A: Programação de Aplicações Web**.

---

## Tecnologias utilizadas

| Camada | Tecnologia |
|---|---|
| Back-end | PHP 8+ (sem framework), PDO |
| Banco de dados | MySQL / MariaDB |
| Front-end | HTML5, CSS3, JavaScript, Bootstrap 5 |
| Gráficos | Chart.js 4 |
| Calendário | FullCalendar 6 |
| Ícones | Bootstrap Icons |

---

## Instalação no XAMPP

### 1. Instalar o XAMPP
Baixe em [apachefriends.org](https://www.apachefriends.org) e instale normalmente.

### 2. Iniciar os serviços
Abra o **Painel de Controle do XAMPP** e clique em **Start** em:
- **Apache**
- **MySQL**

### 3. Copiar o projeto
Coloque a pasta `ceon` dentro do diretório `htdocs` do XAMPP:

```
Windows:  C:\xampp\htdocs\ceon
Linux:    /opt/lampp/htdocs/ceon
macOS:    /Applications/XAMPP/htdocs/ceon
```

### 4. Abrir o phpMyAdmin
No navegador, acesse:

```
http://localhost/phpmyadmin
```

### 5. Importar o banco de dados
1. Clique na aba **Importar**
2. Clique em **Escolher arquivo** e selecione `ceon/database.sql`
3. Role até o final e clique em **Executar**

> O arquivo `database.sql` já cria o banco `ceon`, todas as tabelas e os dados de demonstração.
> Não é necessário criar o banco manualmente.

### 6. Acessar o sistema
No navegador, acesse:

```
http://localhost/ceon
```

---

## Credenciais de acesso

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | `admin@ceon.com` | `admin123` |
| Professor | `professor@ceon.com` | `professor123` |
| Aluno | `aluno@ceon.com` | `aluno123` |

Usuários adicionais para teste: `professor2@ceon.com`, `professor3@ceon.com` (senha `professor123`) e `aluno2@ceon.com` até `aluno10@ceon.com` (senha `aluno123`).

Todas as senhas estão armazenadas com hash **bcrypt** (`password_hash`).

---

## Configuração do banco (se necessário)

Por padrão o sistema conecta com usuário `root` e senha vazia, que é o padrão do XAMPP.
Caso sua instalação use outra configuração, edite o arquivo `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ceon');
define('DB_USER', 'root');
define('DB_PASS', '');
```

---

## Estrutura de pastas

```
ceon/
├── index.php                   Redireciona para login ou dashboard
├── login.php                   Autenticação
├── logout.php                  Encerra a sessão
├── database.sql                Script do banco + dados de demonstração
│
├── config/
│   └── database.php            Conexão PDO
│
├── includes/
│   ├── auth.php                Sessão e controle de permissões
│   ├── functions.php           Funções utilitárias (XSS, upload, médias)
│   ├── professor_helpers.php   Consultas do módulo professor
│   ├── acesso_negado.php       Página 403
│   ├── header.php              Cabeçalho comum
│   ├── sidebar.php             Menu lateral dinâmico por perfil
│   └── footer.php              Rodapé comum
│
├── assets/
│   ├── css/style.css           Identidade visual do sistema
│   ├── js/script.js            Menu mobile, confirmações, validações
│   └── img/
│
├── aluno/                      Dashboard, calendário, tarefas, notas,
│                               faltas, materiais, comunicados,
│                               cardápio, perfil
│
├── professor/                  Dashboard, calendário, turmas, tarefas,
│                               eventos, comunicados, materiais,
│                               notas, faltas, perfil
│
├── admin/                      Dashboard, usuários, alunos, professores,
│                               turmas, disciplinas, eventos, comunicados,
│                               cardápio, relatórios, perfil
│
└── uploads/
    └── materiais/              Arquivos enviados pelos professores
```

---

## Funcionalidades por perfil

### Aluno
- Dashboard com disciplinas, tarefas pendentes, próximos eventos, comunicados e média geral
- Calendário com aulas, eventos e prazos de entrega (mês / semana / dia)
- Tarefas separadas em pendentes, atrasadas e concluídas, com marcação de conclusão
- Notas por disciplina com média ponderada calculada automaticamente
- Faltas por disciplina com percentual de presença
- Download de materiais de apoio da sua turma
- Comunicados gerais e da sua turma
- Cardápio da semana
- Perfil com alteração de dados e senha

### Professor
- Dashboard com turmas, disciplinas, tarefas criadas e próximos eventos
- Calendário das próprias aulas, eventos e entregas
- Listagem dos alunos de cada turma com a média de cada um
- CRUD de tarefas, eventos, comunicados e materiais
- Lançamento e exclusão de notas (com peso)
- Registro e exclusão de faltas
- Perfil com alteração de dados e senha

### Administrador
- Dashboard com totais e quatro gráficos (alunos por turma, média por disciplina, distribuição de usuários e eventos por mês)
- CRUD completo de usuários, alunos, professores, turmas, disciplinas, eventos, comunicados e cardápio
- Todos os módulos com listagem, pesquisa, filtros, cadastro, edição e exclusão com confirmação
- Relatórios com desempenho por turma, faltas por disciplina e ranking dos alunos
- Perfil com alteração de dados e senha

---

## Segurança implementada

| Proteção | Como foi implementada |
|---|---|
| SQL Injection | PDO com prepared statements em **todas** as consultas; `ATTR_EMULATE_PREPARES` desativado |
| Senhas | `password_hash()` na gravação e `password_verify()` na autenticação — nunca em texto puro |
| Sessões | `session_regenerate_id(true)` no login, cookies `HttpOnly`, `use_strict_mode` ativo, destruição completa no logout |
| Controle de acesso | `exigirTipoUsuario()` no topo de cada página protegida; acesso indevido retorna **403** |
| XSS | Função `e()` com `htmlspecialchars()` aplicada em toda saída dinâmica |
| Upload | Validação de extensão, **MIME type real** via `finfo`, tamanho máximo (10 MB) e renomeação do arquivo; `.htaccess` impede execução de scripts na pasta de uploads |
| Validação | Todos os formulários validados no servidor, independente da validação do navegador |
| Autorização de dados | Professores só editam os próprios registros; alunos só baixam materiais da própria turma |

---

## Testes realizados

O projeto foi testado com PHP 8.3 e MariaDB. Resultados:

- Todas as 33 páginas retornam HTTP 200 sem warnings, notices ou erros
- Login funcional para os três perfis
- CRUDs testados com gravação confirmada no banco
- Tentativa de SQL Injection no login (`' OR '1'='1`) bloqueada
- Payload XSS (`<script>alert()</script>`) escapado corretamente na exibição
- Upload de `.php` recusado pela extensão
- Upload de PHP renomeado para `.pdf` recusado pela validação de MIME type
- Matriz de permissões: todo acesso cruzado entre perfis retorna 403
- Acesso sem autenticação redireciona para o login
- Download de material de turma alheia negado

---

## Responsividade

O layout se adapta a desktop, notebook, tablet e celular. Em telas menores que 992px a sidebar recolhe e passa a ser aberta pelo botão de menu no cabeçalho, com overlay para fechar.

---

## Acessibilidade

- HTML semântico (`<header>`, `<main>`, `<nav>`, `<aside>`, `<footer>`, `<fieldset>`, `<legend>`)
- Todos os inputs possuem `<label>` associado
- Botões de ícone com `aria-label` descritivo
- Botão de menu com `aria-expanded` e `aria-controls`
- Contraste adequado entre texto e fundo
- Navegação por teclado preservada
