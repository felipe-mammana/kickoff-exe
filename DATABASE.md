# Banco de Dados — EXE Inventário TI

SGBD: MySQL/MariaDB, InnoDB.

## users
id, name, email, password_hash, is_admin, created_at. E-mail único.

## companies
id, name, tag_pattern, is_active, created_by, updated_by, updated_at, created_at. Nome único.

## machines
id, company_id, device_type, equipment_name, tag, old_hostname, new_hostname, employee_name, department, brand, computer_model, operating_system, machine_password, admin_user, admin_password, install_location, modem_name, ip_address, gateway, carrier, printer_brand, printer_connection_type, printer_shared, notes, tflux_installed, antivirus_installed, requester_in_tflux, is_active, created_by, updated_by, updated_at, created_at.

Unicidade: `(company_id, tag)` e `(company_id, new_hostname)`.

## machine_photos
id, machine_id, photo_type, photo_topic, location_name, file_name, original_name, mime_type, file_size, created_at.

## audit_logs
id, user_id, user_name, user_email, action_type, affected_table, affected_record_id, company_id, machine_id, description, old_data, new_data, ip_address, session_identifier, created_at.

## api_tokens
id, user_id, name, token_hash, last_used_at, expires_at, revoked_at, created_at.

## login_attempts
id, email, ip_address, attempted_at. Usada para limitar tentativas de login por e-mail e IP.

## Mudanças futuras
Sempre usar migration, preservar dados, revisar índices, Models, API, exportações e auditoria.

## Autorização sugerida
Possível modelo: roles, permissions, user_roles, role_permissions, user_companies. Uma primeira versão simples pode usar `user_companies` com can_view/can_edit/can_export/can_view_credentials.
