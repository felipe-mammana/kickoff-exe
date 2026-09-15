# Deploy no servidor

Consulte o [guia tecnico e operacional](guia-tecnico-operacional.md#publicacao-em-servidor).

As instrucoes antigas de publicar todo o pacote na raiz publica e importar
administrador com senha predefinida foram substituidas.

- DocumentRoot deve apontar exclusivamente para public/.
- Criar administrador com seed_admin.php somente na instalacao inicial.
- Configuracoes e chaves devem ser privadas; preservar APP_KEY ao migrar dados.
- Nao publicar sessoes, backups, scripts SQL e pacotes antigos sem revisao.
- Homologar migracoes e recuperacao antes de alterar a producao.
