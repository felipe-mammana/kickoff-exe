# Politica exclusiva da homologacao. Nao aplicar a AppRoles de producao.
path "transit/datakey/wrapped/kickoff-cofre-hml" {
  capabilities = ["update"]
}

path "transit/decrypt/kickoff-cofre-hml" {
  capabilities = ["update"]
}
