<?php
// Copy to crypto.local.php. Generate separate random 32-byte base64 keys.
// Empty active keeps legacy writes until this purpose is provisioned.
return [
    'credentials' => ['active' => '', 'keys' => []],
    'totp' => ['active' => '', 'keys' => []],
    'microsoft' => ['active' => '', 'keys' => []],
];
