<?php

declare(strict_types=1);

class SettingsController
{
    public static function index(): void
    {
        require_admin();

        view('settings/index', [
            'title' => 'Configuracoes',
        ]);
    }
}
