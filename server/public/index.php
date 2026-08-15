<?php
require_once __DIR__ . '/bootstrap.php';
header('Location: ' . (OC_Auth::check() ? 'devices.php' : 'login.php'));
