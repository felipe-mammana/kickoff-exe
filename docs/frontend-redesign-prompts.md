# Prompts para refatoração visual no Stitch

Use estes prompts no Stitch para gerar propostas de UI. O objetivo e redesenhar o frontend do sistema EXE Inventario TI mantendo a aplicação operacional, densa, responsiva e adequada a uso interno.

## Direcao geral do produto

```text
Redesenhe um sistema web interno de inventario de TI chamado EXE. O produto e usado por equipe técnica para cadastrar empresas, dispositivos, fotos, auditoria e usuários. Crie uma interface moderna, responsiva e profissional, com modo claro e escuro, foco em produtividade, leitura rápida e formularios longos bem organizados. Evite visual de landing page. Não use hero marketing. Priorize layout de dashboard operacional, navegação lateral, topbar com busca, estados vazios claros, tabelas densas mas legíveis e botões com ícones. A identidade deve parecer tecnologia corporativa brasileira: confiável, limpa, objetiva e premium sem exagero.
```

## Sistema de layout e componentes

```text
Crie um design system para o sistema EXE Inventario TI. Inclua sidebar desktop, bottom navigation mobile, topbar, breadcrumbs, botões primário/secundário/perigo, icon buttons, inputs, selects, date inputs, upload áreas, cards de métricas, tabelas responsivas, chips de status, badges, modais/lightbox, empty states, alerts, filtros recolhíveis e formularios em seções. Use bordas discretas, raio de 8px ou menor, espaçamentos padronizados, tipografia sem serifa, alto contraste no modo escuro e uma versão clara equivalente. Interface deve ficar compacta e eficiente, sem cards dentro de cards. Cores são azul e branco em diferentes tons.
```

## Login

```text
Redesenhe a tela de login do sistema EXE Inventario TI. Deve ter logo EXE, formulario centralizado com email e senha, botao entrar, feedback de erro e alternancia de tema. Visual corporativo, seguro e simples. Fundo discreto, sem ilustração grande. O card de login deve ser compacto, acessível em mobile e desktop, com hierarquia clara e foco no formulario.
```

## Dashboard geral

```text
Redesenhe o dashboard principal de inventario de TI. A tela deve ter sidebar, topbar com busca global, seletor de empresa, cards de métricas para total de dispositivos, notebooks, desktops e impressoras, área de últimas alterações e tabela/listagem de dispositivos. Inclua filtros por empresa, tipo, status, etiqueta, responsável, departamento, modelo e data. A tela deve funcionar bem com poucos dados, muitos dados e sem empresas cadastradas. O estilo deve ser operacional, denso, organizado e responsivo.
```

## Empresas - listagem

```text
Redesenhe a tela de listagem de empresas. Deve mostrar empresas ativas/inativas em tabela ou lista responsiva, com nome, padrão de etiqueta, status, data de atualização e ações de ver/editar. Inclua botao "Cadastrar nova empresa", busca/filtro simples e empty state quando não houver empresas. Visual consistente com o dashboard, adequado para administradores.
```

## Empresas - formulario

```text
Redesenhe o formulario de criação/edicao de empresa. Campos: nome, padrão de etiqueta e status ativo. Inclua breadcrumbs, titulo contextual, botões salvar/cancelar e mensagens de validação. Layout compacto, responsivo, com boa hierarquia. Não deve parecer uma landing page; deve parecer ferramenta interna profissional.
```

## Empresas - detalhe

```text
Redesenhe a tela de detalhe da empresa. Exiba nome, padrão de etiqueta, status, datas, usuário criador/atualizador quando disponível e ações para voltar, editar e desativar. Inclua área de resumo e link para ver dispositivos da empresa. Deve ter bom estado visual para empresa ativa e inativa, com alerta discreto para desativação.
```

## Dispositivos - formulario

```text
Redesenhe o formulario de cadastro/edicao de dispositivo de inventario de TI. O formulario e longo e muda conforme tipo de dispositivo: notebook, CPU/desktop, roteador, access point, modem, impressora e outros. Organize em seções claras: identificação, usuário/local, hardware, rede/acesso, checklist, observações e fotos. Inclua upload de fotos com duas opcoes no celular: escolher da galeria e tirar foto na hora. Inclua preview de fotos, remoção de fotos existentes, campos obrigatórios visiveis e botões salvar/cancelar fixos ou bem acessíveis. Precisa ser excelente no mobile.
```

## Dispositivos - detalhe

```text
Redesenhe a tela de detalhe do dispositivo. Mostre tipo, etiqueta, status, empresa, modelo, hostname, responsável/local, rede/acesso, checklist, observações, datas e galeria de fotos com lightbox. Inclua ações de editar e desativar. Senhas e dados sensíveis devem ter tratamento visual discreto e protegido. A tela deve ser facil de escanear por um técnico em campo, especialmente no celular.
```

## Galeria e upload de fotos

```text
Redesenhe os componentes de fotos do dispositivo. Inclua grid de miniaturas, legenda com nome do arquivo, separação entre fotos gerais e fotos de configuração de rede, lightbox para ampliar, botao remover e área de upload. No mobile, mostre claramente duas opcoes: galeria e camera. Use estados de carregamento, erro de formato e limite de 5MB. Interface deve ser objetiva e confiável.
```

## Auditoria / logs

```text
Redesenhe a tela de auditoria/logs do sistema. Deve ter filtros por usuário, empresa, dispositivo, tipo de ação, data inicial e data final. Remova qualquer foco visual desnecessário em dispositivos no topo; mantenha dashboard/sidebar e registros recentes. A listagem deve ter evento, usuário, origem/IP, registro relacionado e dados alterados. Padronize espaçamentos, alinhamentos e densidade. Inclua chips por tipo de evento, exportar CSV/JSON e empty state. A tela deve parecer uma ferramenta de compliance/operação, não uma tela decorativa.
```

## Usuários

```text
Redesenhe a tela de usuários do sistema. Mostre usuários cadastrados com nome, email, perfil admin/comum e data de criação. Inclua ação para ir aos logs. O layout deve ser simples, administrativo, responsivo e consistente com o restante. Se não houver CRUD completo de usuários ainda, deixe a tela preparada para futuras ações sem parecer incompleta.
```

## Configurações

```text
Redesenhe a tela de configurações do sistema EXE. Mostre informações do ambiente, segurança, uploads, sessões, API e links para auditoria. Organize em seções compactas, com cards informativos discretos e status chips. A tela deve transmitir confiança e clareza técnica, sem excesso visual.
```

## Erros 403, 404 e 500

```text
Redesenhe as telas de erro 403, 404 e 500. Mantenha layout do sistema com sidebar/topbar quando fizer sentido, mensagem clara, botao voltar ao início e descrição curta. Para erro 500, não exponha detalhes técnicos para usuário final. Visual limpo, calmo e consistente com modo claro/escuro.
```

## Navegação responsiva

```text
Redesenhe a navegação do sistema EXE para desktop e mobile. Desktop deve ter sidebar fixa/recolhivel com Dashboard, Empresas, Auditoria, Usuários e Configurações. Mobile deve ter topbar compacta, botao menu e bottom navigation com ações principais. Inclua busca global, alternancia de tema e menu de usuário/logout. Evite sobreposição de textos e garanta que tudo caiba em telas pequenas.
```

## Plano para implementação depois do Stitch

1. Consolidar tokens visuais no CSS: cores, espacamento, radius, sombras, tipografia e estados.
2. Refatorar layout base: sidebar, topbar, mobile nav, conteúdo e estados globais.
3. Refatorar dashboard e empresas.
4. Refatorar formulario/detalhe de dispositivos.
5. Refatorar fotos e lightbox.
6. Refatorar auditoria/logs.
7. Refatorar usuários, configurações e erros.
8. Validar responsivo em desktop e mobile.
9. Testar fluxo completo: login, CRUD de empresas, dispositivos, fotos, auditoria e API.
