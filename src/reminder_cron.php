<?php

require_once __DIR__.'/../config.php';

$plugin = EasyCertificatePlugin::create();
$plugin->sendExpirationReminder();
