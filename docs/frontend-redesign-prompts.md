# Prompts para refatoracao visual no Stitch

Use estes prompts no Stitch para gerar propostas de UI. O objetivo e redesenhar o frontend do sistema EXE Inventario TI mantendo a aplicacao operacional, densa, responsiva e adequada a uso interno.

## Direcao geral do produto

```text
Redesenhe um sistema web interno de inventario de TI chamado EXE. O produto e usado por equipe tecnica para cadastrar empresas, dispositivos, fotos, auditoria e usuarios. Crie uma interface moderna, responsiva e profissional, com modo claro e escuro, foco em produtividade, leitura rapida e formularios longos bem organizados. Evite visual de landing page. Nao use hero marketing. Priorize layout de dashboard operacional, navegacao lateral, topbar com busca, estados vazios claros, tabelas densas mas legiveis e botoes com icones. A identidade deve parecer tecnologia corporativa brasileira: confiavel, limpa, objetiva e premium sem exagero.
```

## Sistema de layout e componentes

```text
Crie um design system para o sistema EXE Inventario TI. Inclua sidebar desktop, bottom navigation mobile, topbar, breadcrumbs, botoes primario/secundario/perigo, icon buttons, inputs, selects, date inputs, upload areas, cards de metricas, tabelas responsivas, chips de status, badges, modais/lightbox, empty states, alerts, filtros recolhiveis e formularios em secoes. Use bordas discretas, raio de 8px ou menor, espaçamentos padronizados, tipografia sem serifa, alto contraste no modo escuro e uma versao clara equivalente. Interface deve ficar compacta e eficiente, sem cards dentro de cards.
```

## Login

```text
Redesenhe a tela de login do sistema EXE Inventario TI. Deve ter logo EXE, formulario centralizado com email e senha, botao entrar, feedback de erro e alternancia de tema. Visual corporativo, seguro e simples. Fundo discreto, sem ilustracao grande. O card de login deve ser compacto, acessivel em mobile e desktop, com hierarquia clara e foco no formulario.
```

## Dashboard geral

```text
Redesenhe o dashboard principal de inventario de TI. A tela deve ter sidebar, topbar com busca global, seletor de empresa, cards de metricas para total de dispositivos, notebooks, desktops e impressoras, area de ultimas alteracoes e tabela/listagem de dispositivos. Inclua filtros por empresa, tipo, status, etiqueta, responsavel, departamento, modelo e data. A tela deve funcionar bem com poucos dados, muitos dados e sem empresas cadastradas. O estilo deve ser operacional, denso, organizado e responsivo.
```

## Empresas - listagem

```text
Redesenhe a tela de listagem de empresas. Deve mostrar empresas ativas/inativas em tabela ou lista responsiva, com nome, padrao de etiqueta, status, data de atualizacao e acoes de ver/editar. Inclua botao "Cadastrar nova empresa", busca/filtro simples e empty state quando nao houver empresas. Visual consistente com o dashboard, adequado para administradores.
```

## Empresas - formulario

```text
Redesenhe o formulario de criacao/edicao de empresa. Campos: nome, padrao de etiqueta e status ativo. Inclua breadcrumbs, titulo contextual, botoes salvar/cancelar e mensagens de validacao. Layout compacto, responsivo, com boa hierarquia. Nao deve parecer uma landing page; deve parecer ferramenta interna profissional.
```

## Empresas - detalhe

```text
Redesenhe a tela de detalhe da empresa. Exiba nome, padrao de etiqueta, status, datas, usuario criador/atualizador quando disponivel e acoes para voltar, editar e desativar. Inclua area de resumo e link para ver dispositivos da empresa. Deve ter bom estado visual para empresa ativa e inativa, com alerta discreto para desativacao.
```

## Dispositivos - formulario

```text
Redesenhe o formulario de cadastro/edicao de dispositivo de inventario de TI. O formulario e longo e muda conforme tipo de dispositivo: notebook, CPU/desktop, roteador, access point, modem, impressora e outros. Organize em secoes claras: identificacao, usuario/local, hardware, rede/acesso, checklist, observacoes e fotos. Inclua upload de fotos com duas opcoes no celular: escolher da galeria e tirar foto na hora. Inclua preview de fotos, remocao de fotos existentes, campos obrigatorios visiveis e botoes salvar/cancelar fixos ou bem acessiveis. Precisa ser excelente no mobile.
```

## Dispositivos - detalhe

```text
Redesenhe a tela de detalhe do dispositivo. Mostre tipo, etiqueta, status, empresa, modelo, hostname, responsavel/local, rede/acesso, checklist, observacoes, datas e galeria de fotos com lightbox. Inclua acoes de editar e desativar. Senhas e dados sensiveis devem ter tratamento visual discreto e protegido. A tela deve ser facil de escanear por um tecnico em campo, especialmente no celular.
```

## Galeria e upload de fotos

```text
Redesenhe os componentes de fotos do dispositivo. Inclua grid de miniaturas, legenda com nome do arquivo, separacao entre fotos gerais e fotos de configuracao de rede, lightbox para ampliar, botao remover e area de upload. No mobile, mostre claramente duas opcoes: galeria e camera. Use estados de carregamento, erro de formato e limite de 5MB. Interface deve ser objetiva e confiavel.
```

## Auditoria / logs

```text
Redesenhe a tela de auditoria/logs do sistema. Deve ter filtros por usuario, empresa, dispositivo, tipo de acao, data inicial e data final. Remova qualquer foco visual desnecessario em dispositivos no topo; mantenha dashboard/sidebar e registros recentes. A listagem deve ter evento, usuario, origem/IP, registro relacionado e dados alterados. Padronize espacamentos, alinhamentos e densidade. Inclua chips por tipo de evento, exportar CSV/JSON e empty state. A tela deve parecer uma ferramenta de compliance/operacao, nao uma tela decorativa.
```

## Usuarios

```text
Redesenhe a tela de usuarios do sistema. Mostre usuarios cadastrados com nome, email, perfil admin/comum e data de criacao. Inclua acao para ir aos logs. O layout deve ser simples, administrativo, responsivo e consistente com o restante. Se nao houver CRUD completo de usuarios ainda, deixe a tela preparada para futuras acoes sem parecer incompleta.
```

## Configuracoes

```text
Redesenhe a tela de configuracoes do sistema EXE. Mostre informacoes do ambiente, seguranca, uploads, sessoes, API e links para auditoria. Organize em secoes compactas, com cards informativos discretos e status chips. A tela deve transmitir confianca e clareza tecnica, sem excesso visual.
```

## Erros 403, 404 e 500

```text
Redesenhe as telas de erro 403, 404 e 500. Mantenha layout do sistema com sidebar/topbar quando fizer sentido, mensagem clara, botao voltar ao inicio e descricao curta. Para erro 500, nao exponha detalhes tecnicos para usuario final. Visual limpo, calmo e consistente com modo claro/escuro.
```

## Navegacao responsiva

```text
Redesenhe a navegacao do sistema EXE para desktop e mobile. Desktop deve ter sidebar fixa/recolhivel com Dashboard, Empresas, Auditoria, Usuarios e Configuracoes. Mobile deve ter topbar compacta, botao menu e bottom navigation com acoes principais. Inclua busca global, alternancia de tema e menu de usuario/logout. Evite sobreposicao de textos e garanta que tudo caiba em telas pequenas.
```

## Plano para implementacao depois do Stitch

1. Consolidar tokens visuais no CSS: cores, espacamento, radius, sombras, tipografia e estados.
2. Refatorar layout base: sidebar, topbar, mobile nav, conteudo e estados globais.
3. Refatorar dashboard e empresas.
4. Refatorar formulario/detalhe de dispositivos.
5. Refatorar fotos e lightbox.
6. Refatorar auditoria/logs.
7. Refatorar usuarios, configuracoes e erros.
8. Validar responsivo em desktop e mobile.
9. Testar fluxo completo: login, CRUD de empresas, dispositivos, fotos, auditoria e API.
