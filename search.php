<?php
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__ . '/config.php';

$htmlHeadXtra[] = '<link rel="stylesheet" type="text/css" href="'.api_get_path(
        WEB_PLUGIN_PATH
    ).'easycertificate/resources/css/certificate.css"/>';


$type = isset($_GET['type']) ? (int)$_GET['type'] : null;

$plugin = EasyCertificatePlugin::create();
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';
$tblProperty = Database::get_course_table(TABLE_ITEM_PROPERTY);
$content =[];
if ($type == 'view') {
    $codCertificate = isset($_GET['c_cert']) ? (string)$_GET['c_cert'] : null;
    $certificate_Validate = EasyCertificatePlugin::getGenerateInfoCertificate(true, $codCertificate);
    $template = new Template($plugin->get_lang('CertificateInformation'));
    $template->assign('certificate', $certificate_Validate);
    $content = $template->fetch('easycertificate/template/certificate_info.tpl');
    $template->assign('content', $content);
    $template->display_blank_template();
}
