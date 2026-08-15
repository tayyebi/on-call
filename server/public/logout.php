<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::logout();
header('Location: login.php');
