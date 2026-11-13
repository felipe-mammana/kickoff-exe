# Importação assistida de credenciais

O Cofre aceita arquivos `.docx`, `.xlsx` e `.csv` com até 8 MB. Arquivos `.doc`, macros, objetos incorporados, vínculos externos, pacotes criptografados e estruturas compactadas fora dos limites de segurança são rejeitados.

## Fluxo

1. O usuário com `vault.create` seleciona uma empresa padrão e envia o arquivo.
2. O backend valida extensão, MIME real e estrutura, extrai até 200 credenciais e descarta o upload ao fim da requisição.
3. A prévia é cifrada com o mecanismo de credenciais existente, vinculada ao ID do usuário autenticado e mantida na sessão por até 15 minutos. O token fica em campo POST oculto; ele não é colocado na URL.
4. O usuário revisa nome, usuário, senha, empresa, categoria, IPs, MAC, SSID, URL, observações e campos personalizados. Registros podem ser desmarcados ou removidos.
5. Na confirmação, o backend valida novamente empresa, categoria, campos e duplicidades. Cada segredo é gravado pelo `VaultCredentialSecretService`, preservando o fluxo MySQL, Vault KV v2, envelope encryption e Transit.
6. A confirmação assume uma reivindicação exclusiva da prévia; uma segunda confirmação concorrente é recusada. A prévia é removida após confirmação, cancelamento, falha de leitura ou expiração.

Nenhuma categoria ou campo personalizado é criado pela importação. Informações sem campo personalizado compatível são preservadas em observações. Possíveis duplicidades nunca são sobrescritas: o usuário precisa autorizar explicitamente a criação do novo registro.

## Extração

- CSV: identifica delimitador e cabeçalhos equivalentes em UTF-8 ou Windows-1252.
- XLSX: lê valores armazenados nas planilhas sem executar fórmulas.
- DOCX: interpreta tabelas e blocos de texto com pares `rótulo: valor`.
- Hyperlinks HTTP/HTTPS de DOCX e XLSX são extraídos como texto simples, preservando protocolo, IP, porta e caminho. Um único link associado a um único registro preenche o campo de acesso; links múltiplos ficam nas observações para revisão manual.

Os rótulos reconhecidos incluem nome do equipamento ou serviço, usuário, administrador, senha, segredo, IP interno ou externo, MAC, SSID, URL e observações. Quando usuários e senhas aparecem em listas paralelas, a extração cria um registro para cada conta e mantém o mesmo equipamento.

## Segurança operacional

- O arquivo original não é movido para `storage` nem salvo como anexo.
- Respostas do fluxo usam `Cache-Control: no-store`.
- A sessão contém somente o payload cifrado da prévia.
- Falhas de autenticação da prévia e prévias adulteradas são tratadas como inválidas; indisponibilidade do mecanismo criptográfico recebe uma mensagem genérica separada, sem expor a causa interna.
- Logs e auditoria não recebem senha, observações ou campos personalizados em texto claro.
- Falhas de gravação não liberam uma nova tentativa automática: operações ambíguas do Vault permanecem sujeitas à reconciliação, e o usuário é orientado a não repetir o registro.
- A gravação continua sujeita a CSRF, desbloqueio do Cofre, permissão `vault.create` e categorias permitidas para a empresa.
- A importação não executa conversores, LibreOffice, macros ou fórmulas.
- Relacionamentos externos só são aceitos quando são hyperlinks HTTP/HTTPS válidos. Templates remotos, arquivos locais, conexões de dados e outros relacionamentos externos continuam bloqueados. Nenhum destino é acessado, resolvido por DNS ou baixado.

## Testes

O teste `tests/vault_import_parser.php` usa apenas dados fictícios e cobre CSV, XLSX, DOCX, múltiplas contas, campos de rede, rejeição de conteúdo ativo, cifragem/remoção da prévia, vínculo ao usuário, prévia adulterada e confirmação concorrente.
