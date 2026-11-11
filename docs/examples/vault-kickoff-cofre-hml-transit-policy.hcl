# Politica exclusiva da homologacao. Nao aplicar a AppRoles de producao.
path "transit/datakey/plaintext/kickoff-cofre-hml" {
  capabilities = ["update"]
}

path "transit/decrypt/kickoff-cofre-hml" {
  capabilities = ["update"]
}
