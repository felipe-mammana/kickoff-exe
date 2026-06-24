<?php

declare(strict_types=1);

class UserController
{
    public static function index(): void
    {
        require_admin();

        view('users/index', [
            'title' => 'Usuarios',
            'users' => User::all(),
        ]);
    }
}
