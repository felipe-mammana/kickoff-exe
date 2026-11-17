# Matriz comparativa de preservação funcional

Auditoria baseada nas rotas de `public/index.php`, controllers e templates PHP existentes. O protótipo não executa regras de negócio: as ações marcadas como “simulado” representam o ponto de integração futuro.

| Tela | Elemento/funcionalidade atual | Representação no protótipo | Preservado? |
| --- | --- | --- | --- |
| Login | E-mail, senha, mostrar senha, CSRF, login | `login.html`, formulário e estados de acesso | Sim, visual |
| Login | 2FA por código, envio por e-mail, cancelar | Documentado como fluxo futuro no inventário | Parcial — pendência visual |
| Dashboard | Empresa selecionada | Dashboard com contexto de empresa | Sim, visual |
| Dashboard | Filtros: empresa, tipo, status, etiqueta, colaborador, departamento, modelo, data | Barra de filtros e busca; combinação avançada documentada | Parcial — ampliar painel avançado |
| Dashboard | Galeria, lightbox, navegação de fotos | Estado de galeria representado no detalhe do dispositivo | Parcial — interação visual pendente |
| Empresas | Busca, status, cadastrar em modal, abrir detalhe | `companies.html`, busca local e links de cadastro/detalhe | Sim, visual |
| Empresas | Nome, padrão de etiqueta, categorias do cofre, ativo/inativo | Formulário base; categorias do cofre indicadas na documentação | Parcial — adicionar árvore de categorias ao formulário |
| Empresa detalhe | Editar, desativar, reativar, exclusão total | `company-detail.html` com ações e confirmação simulada | Sim, visual |
| Empresa detalhe | Upload, descrição, download e remoção de anexos | Área de anexos e ação simulada | Parcial — fluxo de upload visual pendente |
| Dispositivo formulário | Tipos: notebook, CPU, roteador, access point, modem, impressora | Seletor e seção condicional | Sim, visual |
| Dispositivo formulário | Etiqueta automática/livre, hostname, colaborador, departamento, modelo, local | Campos principais e seção condicional | Sim, visual |
| Dispositivo formulário | Senhas protegidas, IP, gateway, operadora, admin, conexão USB/rede | Campos condicionais e aviso de proteção | Sim, visual |
| Dispositivo formulário | TFlux, antivírus, solicitante, compartilhada | Checkboxes na seção de segurança | Sim, visual |
| Dispositivo formulário | Fotos galeria/câmera, preview, remoção, limite | Área de fotos e opções descritas | Parcial — preview local pendente |
| Dispositivo detalhe | Dados técnicos por tipo, status, editar, desativar | `machine-detail.html` com resumo e proteção | Sim, visual |
| Dispositivo detalhe | Revelar credencial, auditoria, checklist | Alertas e checklist visual; revelação simulada | Parcial — modal de confirmação pendente |
| Dispositivo detalhe | Fotos gerais/rede, lightbox, download | Galeria representada | Parcial — interação visual pendente |
| Cofre | Busca, modo de busca, empresa, categoria, credencial | `vault.html` com tabela e proteção | Parcial — filtros de categoria e modal pendentes |
| Cofre | Criar/editar/desativar, gerador, copiar, revelar | Ações preservadas como pontos de interação simulada | Parcial — detalhar em segunda iteração |
| Cofre importação | DOCX/XLSX/CSV, prévia, seleção, duplicidade, campos, cancelar/confirmar | Documentado no inventário; tela dedicada pendente | Não — pendência explícita |
| Auditoria | Usuário, empresa, ação, módulo, criticidade, data inicial/final | Filtros de usuário/tipo/período e exportação | Parcial — incluir todos os selects na próxima revisão |
| Auditoria | Detalhes de alteração, exportar CSV/JSON | Tabela e botão de exportar | Sim, visual |
| Usuários | Lista, nome, e-mail, perfil, status, criação, ações | `users.html` com tabela e ações | Sim, visual |
| Usuários | Criar, atualizar, resetar senha, status | Ações agrupadas em menu/feedback simulado | Parcial — modal CRUD pendente |
| Sessões | Lista, online/offline, IP, navegador, última atividade, encerrar, auditoria | `sessions.html` | Sim, visual |
| Configurações | Conta, senha, e-mail/confirmar, preferências, tema, sidebar, tabela, data | Accordions em `settings.html` | Sim, visual |
| Configurações | 2FA autenticador/e-mail | Accordion dedicado | Sim, visual |
| Configurações | Segurança, sessões, API tokens | Accordion dedicado e ações simuladas | Sim, visual |
| Configurações | Auditoria, arquivos protegidos, dispositivos, cofre, categorias/campos | Accordions por categoria | Sim, visual |
| Configurações | Microsoft Mail | Categoria de integração e ação simulada | Sim, visual |
| Erros | 403, 404, 500 sem exposição de detalhes | `errors.html` com três estados | Sim, visual |

## Pendências controladas

As funcionalidades de backend continuam fora do escopo por segurança. A próxima iteração deve detalhar os modais de cofre/importação, upload/lightbox de fotos, categorias do cofre e todos os filtros avançados mantendo os mesmos nomes e combinações do PHP.
