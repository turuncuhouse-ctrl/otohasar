<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/portal.php';

portal_logout();
header('Location: /musteri/');
exit;
