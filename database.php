<?php
/* For license terms, see /license.txt */

use Doctrine\DBAL\Types\Type;

/**
 * Plugin database installation script. Can only be executed if included
 * inside another script loading global.inc.php.
 *
 * @package chamilo.plugin.easycertificate
 */
/**
 * Check if script can be called.
 */
if (!function_exists('api_get_path')) {
    die('This script must be loaded through the Chamilo plugin installer sequence');
}

$entityManager = Database::getManager();
$pluginSchema = new \Doctrine\DBAL\Schema\Schema();
$connection = $entityManager->getConnection();
$platform = $connection->getDatabasePlatform();

if ($pluginSchema->hasTable(EasyCertificatePlugin::TABLE_EASYCERTIFICATE)) {
    return;
}

//Create tables
$certificateTable = $pluginSchema->createTable(EasyCertificatePlugin::TABLE_EASYCERTIFICATE);
$certificateTable->addColumn('id', Type::INTEGER, ['autoincrement' => true, 'unsigned' => true]);
$certificateTable->addColumn('access_url_id', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('c_id', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('session_id', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('front_content', Type::TEXT);
$certificateTable->addColumn('back_content', Type::TEXT);
$certificateTable->addColumn('background_h', Type::STRING);
$certificateTable->addColumn('background_v', Type::STRING);
$certificateTable->addColumn('orientation', Type::STRING);
$certificateTable->addColumn('margin_left', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('margin_right', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('margin_top', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('margin_bottom', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('certificate_default', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('show_back', Type::INTEGER, ['unsigned' => true]);
$certificateTable->addColumn('date_change', Type::INTEGER, ['unsigned' => true]);
$certificateTable->setPrimaryKey(['id']);

$queries = $pluginSchema->toSql($platform);

foreach ($queries as $query) {
    Database::query($query);
}
