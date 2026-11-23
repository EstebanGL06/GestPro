<?php
// Renombra este archivo a config.php y completa con tus credenciales locales.
// Nunca subas config.php al repositorio.

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: 'gestpro_verifactu',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'mail' => [
        'host'       => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
        'username'   => getenv('MAIL_USER') ?: 'tu_correo@gmail.com',
        'password'   => getenv('MAIL_PASS') ?: 'tu_app_password_aqui',
        'port'       => (int) (getenv('MAIL_PORT') ?: 465),
        'from_email' => getenv('MAIL_FROM') ?: 'tu_correo@gmail.com',
        'from_name'  => getenv('MAIL_FROM_NAME') ?: 'GestPro VeriFactu',
    ],
];
