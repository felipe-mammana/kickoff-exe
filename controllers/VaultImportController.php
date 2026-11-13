<?php

declare(strict_types=1);

final class VaultImportController
{
    public static function index(): void
    {
        self::guard();
        $token = trim((string) ($_GET['token'] ?? ''));
        $userId = self::currentUserId();
        try {
            $preview = VaultImportPreview::load($token !== '' ? $token : null, $userId);
        } catch (VaultImportPreviewCryptoException) {
            flash('danger', 'O serviço criptográfico está indisponível. Tente novamente mais tarde.');
            redirect('/?route=vault.import');
        }
        if ($token !== '' && $preview === null) {
            flash('danger', 'A prévia expirou ou não pertence a esta sessão. Selecione o arquivo novamente.');
            redirect('/?route=vault.import');
        }
        if ($token !== '') {
            redirect('/?route=vault.import');
        }

        $companies = Company::all(true);
        $categories = VaultCategory::selectableForCompanies(array_column($companies, 'id'));
        view('vault/import', [
            'title' => 'Importar credenciais',
            'companies' => $companies,
            'categories' => $categories,
            'categoryFieldConfig' => self::categoryFieldConfig($categories),
            'preview' => $preview,
            'defaultCompanyId' => (int) ($_GET['company_id'] ?? ($preview['metadata']['default_company_id'] ?? 0)),
        ]);
    }

    public static function preview(): void
    {
        self::guard();
        self::requirePost();
        verify_csrf();

        $company = self::findCompany((int) ($_POST['company_id'] ?? 0));
        if ($company === null) {
            flash('danger', 'Selecione uma empresa válida para iniciar a importação.');
            redirect('/?route=vault.import');
        }
        $file = $_FILES['import_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('danger', 'Selecione um arquivo DOCX, XLSX ou CSV.');
            redirect('/?route=vault.import&company_id=' . (int) $company['id']);
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            flash('danger', 'Não foi possível receber o arquivo. Verifique o tamanho e tente novamente.');
            redirect('/?route=vault.import&company_id=' . (int) $company['id']);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = safe_original_filename((string) ($file['name'] ?? 'arquivo'));
        $size = (int) ($file['size'] ?? 0);
        if ($tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0 || $size > VaultImportParser::MAX_FILE_BYTES) {
            flash('danger', 'Upload inválido ou acima do limite de 8 MB.');
            redirect('/?route=vault.import&company_id=' . (int) $company['id']);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($tmpName) ?: 'application/octet-stream');
        try {
            $parsed = VaultImportParser::parseFile($tmpName, $originalName, $mime);
            $categories = VaultCategory::selectableForCompanies([(int) $company['id']]);
            foreach ($parsed['records'] as &$record) {
                $record['company_id'] = (int) $company['id'];
                $record['category_id'] = self::suggestCategoryId((string) ($record['category_hint'] ?? ''), $categories);
                if ((int) $record['category_id'] <= 0) {
                    $record['warnings'][] = 'Categoria não identificada; selecione antes de importar.';
                    $record['selected'] = false;
                }
                $record['custom_fields'] = self::initialCustomFields($record);
                $record['duplicate_reasons'] = self::duplicateReasons($record);
            }
            unset($record);
        } catch (Throwable $error) {
            VaultImportPreview::clear();
            flash('danger', self::safeParserMessage($error));
            redirect('/?route=vault.import&company_id=' . (int) $company['id']);
        }

        try {
            VaultImportPreview::store($parsed['records'], self::currentUserId(), [
                'format' => $parsed['format'],
                'record_count' => count($parsed['records']),
                'warnings' => $parsed['warnings'],
                'default_company_id' => (int) $company['id'],
            ]);
        } catch (VaultImportPreviewCryptoException) {
            VaultImportPreview::clear();
            flash('danger', 'O serviço criptográfico está indisponível. Nenhuma prévia foi criada.');
            redirect('/?route=vault.import&company_id=' . (int) $company['id']);
        }
        redirect('/?route=vault.import');
    }

    public static function confirm(): void
    {
        self::guard();
        self::requirePost();
        verify_csrf();

        $token = trim((string) ($_POST['preview_token'] ?? ''));
        $userId = self::currentUserId();
        try {
            $preview = VaultImportPreview::load($token !== '' ? $token : null, $userId);
        } catch (VaultImportPreviewCryptoException) {
            flash('danger', 'O serviço criptográfico está indisponível. Nenhuma credencial foi gravada.');
            redirect('/?route=vault.import');
        }
        if ($preview === null) {
            flash('danger', 'A prévia expirou. Nenhuma credencial foi gravada.');
            redirect('/?route=vault.import');
        }

        $postedRecords = is_array($_POST['records'] ?? null) ? $_POST['records'] : [];
        $sourceRecords = is_array($preview['records'] ?? null) ? $preview['records'] : [];
        $sourceIndexes = [];
        foreach ($sourceRecords as $sourceRecord) {
            $sourceIndexes[(int) ($sourceRecord['source_index'] ?? -1)] = true;
        }

        $selected = [];
        foreach (array_slice($postedRecords, 0, VaultImportParser::MAX_RECORDS, true) as $index => $record) {
            $validatedIndex = filter_var($index, FILTER_VALIDATE_INT);
            if ($validatedIndex === false || $validatedIndex < 0 || !is_array($record)
                || empty($record['selected']) || !isset($sourceIndexes[$validatedIndex])
            ) {
                continue;
            }
            $selected[$validatedIndex] = $record;
        }
        if (!$selected) {
            flash('danger', 'Selecione ao menos uma credencial para importar.');
            redirect('/?route=vault.import');
        }

        try {
            $preview = VaultImportPreview::claim($token !== '' ? $token : (string) ($preview['token'] ?? ''), $userId);
        } catch (VaultImportPreviewBusyException) {
            flash('warning', 'Esta prévia já está sendo processada. Aguarde a conclusão antes de tentar novamente.');
            redirect('/?route=vault.import');
        } catch (VaultImportPreviewCryptoException) {
            flash('danger', 'O serviço criptográfico está indisponível. Nenhuma credencial foi gravada.');
            redirect('/?route=vault.import');
        } catch (VaultImportPreviewInvalidException) {
            flash('danger', 'A prévia expirou ou não é válida. Nenhuma credencial foi gravada.');
            redirect('/?route=vault.import');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $createdCompanyIds = [];
        $auditFailures = 0;
        try {
            $service = VaultCredentialSecretService::fromApplication();
            foreach ($selected as $index => $record) {
            [$data, $secret, $recordErrors] = self::validatedRecord($record);
            if ($recordErrors) {
                $errors[] = 'Registro ' . ($index + 1) . ': ' . implode(' ', $recordErrors);
                $skipped++;
                continue;
            }

            $duplicateReasons = self::duplicateReasons($data + ['secret_value' => $secret]);
            if ($duplicateReasons && empty($record['allow_duplicate'])) {
                $errors[] = 'Registro ' . ($index + 1) . ': possível duplicidade não autorizada.';
                $skipped++;
                continue;
            }

            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
            try {
                $credentialId = $service->create($data, $secret);
            } catch (Throwable $error) {
                $errors[] = 'Registro ' . ($index + 1) . ': não foi possível confirmar a gravação segura.';
                $skipped++;
                if ($error instanceof VaultCredentialServiceException) {
                    break;
                }
                continue;
            }

            $created++;
            $createdCompanyIds[(int) $data['company_id']] = true;
            try {
                AuditLog::record([
                    'required' => true,
                    'action_type' => 'vault_credential_imported',
                    'affected_table' => 'vault_credentials',
                    'affected_record_id' => $credentialId,
                    'company_id' => (int) $data['company_id'],
                    'description' => 'Credencial importada para o cofre após revisão da prévia.',
                    'new_data' => [
                        'title' => $data['title'],
                        'username' => $data['username'],
                        'category_id' => $data['category_id'],
                        'service_url' => $data['service_url'],
                        'notes' => '[protegido]',
                        'custom_fields' => '[protegido]',
                        'secret_value' => '[protegido]',
                        'source_format' => (string) ($preview['metadata']['format'] ?? ''),
                    ],
                ]);
            } catch (Throwable) {
                $auditFailures++;
                $errors[] = 'Registro ' . ($index + 1) . ': a credencial foi criada, mas a auditoria obrigatória falhou. Não repita este registro; acione o suporte.';
                break;
            }
            }
        } catch (Throwable) {
            $errors[] = 'A gravação segura ficou indisponível. Nenhuma nova credencial foi processada.';
            $skipped = count($selected) - $created;
        } finally {
            VaultImportPreview::clear();
        }

        $skipped = max($skipped, count($selected) - $created);
        try {
            AuditLog::record([
                'required' => true,
                'action_type' => 'vault_import_completed',
                'affected_table' => 'vault_credentials',
                'description' => 'Importação assistida de credenciais concluída.',
                'new_data' => [
                    'created_count' => $created,
                    'skipped_count' => $skipped,
                    'audit_failure_count' => $auditFailures,
                    'status' => $auditFailures > 0 || $errors ? 'partial' : 'completed',
                    'source_format' => (string) ($preview['metadata']['format'] ?? ''),
                ],
            ]);
        } catch (Throwable) {
            $errors[] = 'A auditoria de resumo ficou indisponível; não repita registros já criados.';
        }

        if ($created > 0) {
            flash('success', $created . ' credencial(is) importada(s) com segurança.');
        }
        if ($skipped > 0 || $errors) {
            flash('danger', $skipped . ' registro(s) não foram importados. ' . implode(' ', array_slice($errors, 0, 5)));
        }
        if (count($createdCompanyIds) === 1) {
            redirect('/?route=vault.show&id=' . (int) array_key_first($createdCompanyIds));
        }
        redirect('/?route=vault.index');
    }

    public static function cancel(): void
    {
        self::guard();
        self::requirePost();
        verify_csrf();
        VaultImportPreview::clear();
        flash('info', 'Importação cancelada. Nenhuma credencial foi gravada.');
        redirect('/?route=vault.index');
    }

    private static function guard(): void
    {
        require_permission('vault.create');
        require_vault_access();
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
    }

    private static function requirePost(): void
    {
        if (!is_post()) {
            http_response_code(405);
            exit;
        }
    }

    private static function currentUserId(): int
    {
        $user = current_user();
        $userId = (int) ($user['id'] ?? 0);
        if ($userId < 1) {
            throw new RuntimeException('Sessão autenticada indisponível.');
        }
        return $userId;
    }

    private static function findCompany(int $companyId): ?array
    {
        if ($companyId <= 0) {
            return null;
        }
        foreach (Company::all(true) as $company) {
            if ((int) $company['id'] === $companyId) {
                return $company;
            }
        }
        return null;
    }

    /** @param array<int,array<string,mixed>> $categories */
    private static function suggestCategoryId(string $hint, array $categories): int
    {
        $hint = self::normalize($hint);
        if ($hint === '') {
            return 0;
        }
        $terms = [
            'roteador' => ['roteador', 'router'],
            'wi fi' => ['wi fi', 'wifi', 'ssid', 'access point'],
            'banco' => ['banco', 'database', 'mysql', 'sql'],
            'mail' => ['email', 'e mail', 'correio'],
            'financeiro' => ['financeiro', 'banco online'],
            'cloud' => ['cloud', 'hospedagem', 'hosting'],
            'sistemas internos' => ['sistema', 'aplicacao'],
            'rede' => ['rede', 'switch', 'servidor', 'firewall', 'vpn'],
        ];

        $bestId = 0;
        $bestScore = 0;
        foreach ($categories as $category) {
            $name = self::normalize((string) ($category['name'] ?? ''));
            $score = $name !== '' && str_contains($hint, $name) ? 10 : 0;
            foreach ($terms as $categoryTerm => $needles) {
                if (!str_contains($name, $categoryTerm)) {
                    continue;
                }
                foreach ($needles as $needle) {
                    if (str_contains($hint, self::normalize($needle))) {
                        $score = max($score, 7);
                    }
                }
            }
            if ($score > $bestScore || ($score === $bestScore && $score > 0 && !empty($category['parent_id']))) {
                $bestId = (int) $category['id'];
                $bestScore = $score;
            }
        }
        return $bestScore >= 7 ? $bestId : 0;
    }

    /** @param array<int,array<string,mixed>> $categories */
    private static function categoryFieldConfig(array $categories): array
    {
        $config = [];
        $settings = AppSetting::vaultSettings();
        foreach ($categories as $category) {
            $id = (int) ($category['id'] ?? 0);
            if ($id > 0) {
                $config[(string) $id] = AppSetting::vaultFieldsForCategory($category, $settings);
            }
        }
        return $config;
    }

    private static function initialCustomFields(array $record): array
    {
        $categoryId = (int) ($record['category_id'] ?? 0);
        $category = $categoryId > 0 ? VaultCategory::find($categoryId) : null;
        if (!$category) return [];
        $standard = [
            'ip_internal' => trim((string) ($record['ip_internal'] ?? '')),
            'ip_external' => trim((string) ($record['ip_external'] ?? '')),
            'mac' => trim((string) ($record['mac'] ?? '')),
            'ssid' => trim((string) ($record['ssid'] ?? '')),
        ];
        $values = [];
        foreach (AppSetting::vaultFieldsForCategory($category) as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if ($key === '') continue;
            [, $value] = self::mappedStandardValue($definition, $standard);
            if ($value !== '') $values[$key] = $value;
        }
        return $values;
    }

    /** @return array{0:array<string,mixed>,1:string,2:string[]} */
    private static function validatedRecord(array $record): array
    {
        $errors = [];
        $company = self::findCompany((int) ($record['company_id'] ?? 0));
        if ($company === null) {
            $errors[] = 'Empresa inválida.';
        }
        $categoryId = (int) ($record['category_id'] ?? 0);
        $category = $categoryId > 0 ? VaultCategory::find($categoryId) : null;
        if ($company !== null && (!$category || !VaultCategory::isSelectableForCompany($category, (int) $company['id']))) {
            $errors[] = 'Categoria inválida para a empresa.';
        }

        $title = trim((string) ($record['title'] ?? ''));
        $username = trim((string) ($record['username'] ?? ''));
        $secret = trim((string) ($record['secret_value'] ?? ''));
        $serviceUrl = trim((string) ($record['service_url'] ?? ''));
        $notes = trim((string) ($record['notes'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) $errors[] = 'Nome obrigatório com até 160 caracteres.';
        if ($username !== '' && mb_strlen($username) > 190) $errors[] = 'Usuário acima do limite.';
        if ($secret === '' || mb_strlen($secret) > 5000) $errors[] = 'Senha obrigatória com até 5000 caracteres.';
        if ($serviceUrl !== '' && mb_strlen($serviceUrl) > 255) $errors[] = 'URL acima do limite.';

        $definitions = $category ? AppSetting::vaultFieldsForCategory($category) : [];
        $postedCustom = is_array($record['custom_fields'] ?? null) ? $record['custom_fields'] : [];
        $standard = [
            'ip_internal' => trim((string) ($record['ip_internal'] ?? '')),
            'ip_external' => trim((string) ($record['ip_external'] ?? '')),
            'mac' => trim((string) ($record['mac'] ?? '')),
            'ssid' => trim((string) ($record['ssid'] ?? '')),
        ];
        $custom = [];
        $consumed = [];
        foreach ($definitions as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if ($key === '') continue;
            $value = trim((string) ($postedCustom[$key] ?? ''));
            if ($value === '') {
                [$mappedKey, $mappedValue] = self::mappedStandardValue($definition, $standard);
                $value = $mappedValue;
                if ($value !== '') $consumed[$mappedKey] = true;
            }
            if (!empty($definition['required']) && $value === '') {
                $errors[] = (string) ($definition['label'] ?? 'Campo personalizado') . ' é obrigatório.';
                continue;
            }
            if ($value === '') continue;
            $max = ($definition['type'] ?? 'text') === 'textarea' ? 5000 : 255;
            if (mb_strlen($value) > $max) {
                $errors[] = (string) ($definition['label'] ?? 'Campo personalizado') . ' acima do limite.';
                continue;
            }
            if (($definition['type'] ?? '') === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                $errors[] = (string) ($definition['label'] ?? 'Campo personalizado') . ' deve ser uma URL válida.';
                continue;
            }
            if (($definition['type'] ?? '') === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = (string) ($definition['label'] ?? 'Campo personalizado') . ' deve ser um e-mail válido.';
                continue;
            }
            $custom[$key] = $value;
        }

        $labels = ['ip_internal' => 'IP interno', 'ip_external' => 'IP externo', 'mac' => 'MAC', 'ssid' => 'SSID'];
        foreach ($standard as $key => $value) {
            if ($value !== '' && empty($consumed[$key])) {
                $notes = trim($notes . "\n" . $labels[$key] . ': ' . $value);
            }
        }
        if (mb_strlen($notes) > 5000) $errors[] = 'Observações acima do limite.';

        return [[
            'company_id' => (int) ($company['id'] ?? 0),
            'category_id' => $categoryId,
            'title' => $title,
            'service_url' => $serviceUrl !== '' ? $serviceUrl : null,
            'username' => $username !== '' ? $username : null,
            'notes' => $notes !== '' ? $notes : null,
            'custom_fields' => $custom ? json_encode($custom, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
        ], $secret, $errors];
    }

    /** @return array{0:string,1:string} */
    private static function mappedStandardValue(array $definition, array $standard): array
    {
        $label = self::normalize((string) ($definition['label'] ?? '') . ' ' . (string) ($definition['key'] ?? ''));
        if (str_contains($label, 'ssid') || str_contains($label, 'wifi')) return ['ssid', $standard['ssid']];
        if (str_contains($label, 'mac')) return ['mac', $standard['mac']];
        if (str_contains($label, 'ip externo') || str_contains($label, 'ip publico') || str_contains($label, 'wan')) return ['ip_external', $standard['ip_external']];
        if (str_contains($label, 'ip') || str_contains($label, 'lan')) return ['ip_internal', $standard['ip_internal']];
        return ['', ''];
    }

    /** @return string[] */
    private static function duplicateReasons(array $record): array
    {
        $companyId = (int) ($record['company_id'] ?? 0);
        if ($companyId <= 0) return [];
        $stmt = db()->prepare(
            "SELECT id, category_id, title, username, service_url, notes, custom_fields
             FROM vault_credentials
             WHERE company_id = :company_id AND is_active = 1 AND operational_state = 'ready'
             ORDER BY id DESC LIMIT 1000"
        );
        $stmt->execute(['company_id' => $companyId]);
        $title = self::normalize((string) ($record['title'] ?? ''));
        $username = self::normalize((string) ($record['username'] ?? ''));
        $url = self::normalize((string) ($record['service_url'] ?? ''));
        $categoryId = (int) ($record['category_id'] ?? 0);
        $identifiers = array_values(array_filter(array_map(
            static fn (string $value): string => self::normalize($value),
            [
                (string) ($record['ip_internal'] ?? ''),
                (string) ($record['ip_external'] ?? ''),
                (string) ($record['mac'] ?? ''),
                (string) ($record['ssid'] ?? ''),
            ]
        )));
        $reasons = [];
        foreach ($stmt->fetchAll() as $existing) {
            $existing = VaultCredential::decryptMetadata($existing);
            $sameTitle = $title !== '' && self::normalize((string) $existing['title']) === $title;
            $sameUser = self::normalize((string) ($existing['username'] ?? '')) === $username;
            $sameCategory = (int) ($existing['category_id'] ?? 0) === $categoryId;
            if ($sameTitle && $sameUser && $sameCategory) {
                $reasons[] = 'Mesmo nome, usuário e categoria.';
            }
            if ($url !== '' && self::normalize((string) ($existing['service_url'] ?? '')) === $url) {
                $reasons[] = 'Mesma URL de acesso.';
            }
            if ($identifiers) {
                $haystack = self::normalize((string) ($existing['notes'] ?? '') . ' ' . (string) ($existing['custom_fields'] ?? ''));
                foreach ($identifiers as $identifier) {
                    if ($identifier !== '' && str_contains($haystack, $identifier)) {
                        $reasons[] = 'Identificador de rede já cadastrado.';
                        break;
                    }
                }
            }
            if ($reasons) break;
        }
        return array_values(array_unique($reasons));
    }

    private static function safeParserMessage(Throwable $error): string
    {
        if ($error instanceof InvalidArgumentException || $error instanceof RuntimeException) {
            $message = trim($error->getMessage());
            if ($message !== '' && mb_strlen($message) <= 240) return $message;
        }
        return 'Não foi possível interpretar o arquivo. Verifique a estrutura e tente novamente.';
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = is_string($ascii) ? $ascii : $value;
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }
}
