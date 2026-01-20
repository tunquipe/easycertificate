<?php
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$htmlHeadXtra[] = '<link rel="stylesheet" type="text/css" href="'.api_get_path(
        WEB_PLUGIN_PATH
    ).'easycertificate/resources/css/certificate.css"/>';


$type = isset($_GET['type']) ? (int)$_GET['type'] : null;

$plugin = EasyCertificatePlugin::create();
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';
$percentage = $plugin->get('percentage');
$percentageValue = "";

$urlJson = api_get_path(WEB_PLUGIN_PATH).'easycertificate/usuarios.json';
$jsonContent = file_get_contents($urlJson);
$data = json_decode($jsonContent, true);
$template = new Template($plugin->get_lang('CertificateInformation'));
// O puedes iterar sobre las sedes:
$content = '';
foreach ($data['sedes'] as $sede) {
    $nombreSede = $sede['nombre'];
    $usuarios = $sede['usuarios'];

    $content .= "<h2 style='text-align: center;'><strong>Sede:</strong> " . $nombreSede . " - <strong>Usuarios consultados:</strong> " . count($usuarios) . "</h2>";

    foreach ($usuarios as $username) {
        $certificate_Validate = EasyCertificatePlugin::getGenerateInfoForUsername(true, $username, $plugin->get('percentage'));
        $template->assign('certificate', $certificate_Validate);
        $content .= $template->fetch('easycertificate/template/certificate_info.tpl');
        $content .= '<div style="page-break-after: always;"></div>';
    }
}
    $template->assign('content', $content);
    $template->display_blank_template();
