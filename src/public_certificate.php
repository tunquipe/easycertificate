<?php
/* For licensing terms, see /license.txt */

/**
 * Public certificate view — no login required.
 * Access via: plugin/easycertificate/src/public_certificate.php?token=<md5>
 */

$cidReset = true;
$course_plugin = 'easycertificate';
require_once __DIR__ . '/../config.php';

$token = isset($_GET['token']) ? Database::escape_string($_GET['token']) : '';

if (empty($token)) {
    http_response_code(404);
    exit('Certificado no encontrado.');
}

$plugin = EasyCertificatePlugin::create();
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';
if (!$enable) {
    http_response_code(403);
    exit('Plugin deshabilitado.');
}

// Lookup certificate by token (md5 of "id-cat_id-user_id")
$tblCertificate = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CERTIFICATE);
$tblCategory    = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CATEGORY);

$sql = "SELECT cer.id, cer.cat_id, cer.user_id, cer.created_at,
               cat.course_code, cat.session_id
        FROM $tblCertificate cer
        INNER JOIN $tblCategory cat ON cat.id = cer.cat_id
        WHERE md5(CONCAT(cer.id,'-',cer.cat_id,'-',cer.user_id)) = '$token'
        LIMIT 1";
$rs = Database::query($sql);

if (Database::num_rows($rs) === 0) {
    http_response_code(404);
    exit('Certificado no encontrado.');
}

$cert       = Database::fetch_assoc($rs);
$userId     = (int) $cert['user_id'];
$catId      = (int) $cert['cat_id'];
$courseCode = $cert['course_code'];
$sessionId  = (int) $cert['session_id'];

$courseInfo  = api_get_course_info($courseCode);
$courseId    = $courseInfo['real_id'];
$userInfo    = api_get_user_info($userId);
$sessionInfo = $sessionId > 0 ? SessionManager::fetch($sessionId) : [];

$accessUrlId = api_get_current_access_url_id();
$path        = api_get_path(SYS_UPLOAD_PATH) . 'certificates';

// Get certificate template
$infoCertificate = EasyCertificatePlugin::getInfoCertificate($courseId, $sessionId, $accessUrlId);
if (!is_array($infoCertificate) || empty($infoCertificate)) {
    $infoCertificate = EasyCertificatePlugin::getInfoCertificateDefault($accessUrlId);
}
if (empty($infoCertificate)) {
    http_response_code(500);
    exit('No hay plantilla de certificado configurada.');
}

// Build HTML content (same logic as print_certificate.php)
$linkCertificateCSS  = '<link rel="stylesheet" type="text/css" href="' . api_get_path(WEB_PLUGIN_PATH) . 'easycertificate/resources/css/certificate.css">';
$linkCertificateCSS .= '<link rel="stylesheet" type="text/css" href="' . api_get_path(WEB_CSS_PATH) . 'document.css">';

$allUserInfo             = DocumentManager::get_all_info_to_certificate($userId, $courseCode, false);
$myContentHtml           = $infoCertificate['front_content'];
$myContentHtml           = str_replace(chr(13) . chr(10) . chr(13) . chr(10), chr(13) . chr(10), $myContentHtml);
$myContentHtml           = str_replace($allUserInfo[0], $allUserInfo[1], $myContentHtml);

// Score
$score         = GradebookUtils::get_certificate_by_user_id($catId, $userId);
$convertScore  = convertPercentageToScore($score['score_certificate']);
$myContentHtml = str_replace('((score_number))', $convertScore, $myContentHtml);
$myContentHtml = str_replace('((score_certificate))', $score['score_certificate'], $myContentHtml);

// Simple averages
$simpleAvg         = EasyCertificatePlugin::getScoreForEvaluations($courseCode, $userId, 0, $sessionId);
$simpleAvgCategory = EasyCertificatePlugin::getScoreForEvaluations($courseCode, $userId, 1, $sessionId);
$myContentHtml     = str_replace('((simple_average))', $simpleAvg, $myContentHtml);
$myContentHtml     = str_replace('((simple_average_category))', $simpleAvgCategory, $myContentHtml);

// Extra fields
$extraFieldsAll = EasyCertificatePlugin::getExtraFieldsUserAll(false);
foreach ($extraFieldsAll as $field) {
    $myContentHtml = str_replace('((' . $field . '))', EasyCertificatePlugin::getValueExtraField($field, $userId), $myContentHtml);
}

// Session dates
if ($sessionId > 0) {
    $startDate = null;
    $endDate   = null;
    switch ($infoCertificate['date_change']) {
        case 0:
            if (!empty($sessionInfo['display_start_date'])) {
                $startDate = api_format_date(strtotime(api_get_local_time($sessionInfo['display_start_date'])), DATE_FORMAT_LONG_NO_DAY);
            }
            if (!empty($sessionInfo['display_end_date'])) {
                $endDate = api_format_date(strtotime(api_get_local_time($sessionInfo['display_end_date'])), DATE_FORMAT_LONG_NO_DAY);
            }
            break;
        case 1:
            if (!empty($sessionInfo['access_start_date'])) {
                $startDate = api_format_date(strtotime(api_get_local_time($sessionInfo['access_start_date'])), DATE_FORMAT_LONG_NO_DAY);
            }
            if (!empty($sessionInfo['access_end_date'])) {
                $endDate = api_format_date(strtotime(api_get_local_time($sessionInfo['access_end_date'])), DATE_FORMAT_LONG_NO_DAY);
            }
            break;
    }
    $myContentHtml = str_replace('((session_start_date))', $startDate, $myContentHtml);
    $myContentHtml = str_replace('((session_end_date))', $endDate, $myContentHtml);
}

// Expedition date
if (!empty($cert['created_at'])) {
    $createdAt = api_format_date(strtotime(api_get_local_time($cert['created_at'])), DATE_FORMAT_LONG_NO_DAY);
}
$myContentHtml = str_replace('((expedition_date))', $createdAt ?? '', $myContentHtml);

// Certificate code & QR
$codeCertificate = EasyCertificatePlugin::getCodeCertificate($catId, $userId);
$myContentHtml   = str_replace('((code_certificate))', strtoupper($codeCertificate['code_certificate_md5']), $myContentHtml);

$certificateQR = EasyCertificatePlugin::getGenerateUrlImg($userId, $catId, $codeCertificate['code_certificate_md5']);
$myContentHtml = str_replace('((qr-code))', '<img src="data:image/png;base64,' . $certificateQR . '">', $myContentHtml);

$generator     = new Picqer\Barcode\BarcodeGeneratorPNG();
$codCertificate = $codeCertificate['code_certificate'];
if (!empty($codCertificate)) {
    $myContentHtml = str_replace(
        '((bar_code))',
        '<img src="data:image/png;base64,' . base64_encode($generator->getBarcode($codCertificate, $generator::TYPE_CODE_128)) . '">',
        $myContentHtml
    );
}

$myContentHtml = strip_tags(
    $myContentHtml,
    '<p><b><strong><table><tr><td><th><tbody><span><i><li><ol><ul>
    <dd><dt><dl><br><hr><img><a><div><h1><h2><h3><h4><h5><h6>'
);

// Orientation & margins
$orientation     = $infoCertificate['orientation'];
$format          = ($orientation == 'h') ? 'A4-L' : 'A4';
$pageOrientation = ($orientation == 'h') ? 'L' : 'P';

$marginLeft   = ($infoCertificate['margin_left']   > 0) ? $infoCertificate['margin_left']   . 'cm' : 0;
$marginRight  = ($infoCertificate['margin_right']  > 0) ? $infoCertificate['margin_right']  . 'cm' : 0;
$marginTop    = ($infoCertificate['margin_top']    > 0) ? $infoCertificate['margin_top']    . 'cm' : 0;
$marginBottom = ($infoCertificate['margin_bottom'] > 0) ? $infoCertificate['margin_bottom'] . 'cm' : 0;
$margin       = $marginTop . ' ' . $marginRight . ' ' . $marginBottom . ' ' . $marginLeft;

// Build HTML via template
$urlBackgroundHorizontal = $path . $infoCertificate['background_h'];
$urlBackgroundVertical   = $path . $infoCertificate['background_v'];

$templateName = $plugin->get_lang('ExportCertificate');
$template     = new Template($templateName);
$template->assign('css_certificate', $linkCertificateCSS);
$template->assign('orientation', $orientation);
$template->assign('background_h', $urlBackgroundHorizontal);
$template->assign('background_v', $urlBackgroundVertical);
$template->assign('margin', $margin);
$template->assign('front_content', $myContentHtml);
$template->assign('show_back', $infoCertificate['show_back']);

$laterContent  = '<table width="100%" class="contents-learnpath"><tr><td>';
$laterContent .= strip_tags(
    $infoCertificate['back_content'],
    '<p><b><strong><table><tr><td><th><span><i><li><ol><ul><dd><dt><dl><br><hr><img><a><div><h1><h2><h3><h4><h5><h6>'
);
$laterContent .= '</td></tr></table>';
$template->assign('back_content', $laterContent);

$htmlContent = $template->fetch('easycertificate/template/certificate.tpl');

$currentLocalTime = api_get_local_time();
$fileName = api_replace_dangerous_char('certificate_' . $courseCode . '_' . $userInfo['complete_name'] . '_' . $currentLocalTime);

$pdfTitle = 'Certificado - ' . $userInfo['complete_name'] . ' - ' . $courseInfo['title'];

$params = [
    'filename'        => $fileName,
    'pdf_title'       => $pdfTitle,
    'pdf_description' => '',
    'format'          => $format,
    'orientation'     => $pageOrientation,
    'left'  => 0,
    'top'   => 0,
    'bottom'=> 0,
    'right' => 0,
];

$pdf = new PDF($params['format'], $params['orientation'], $params);
$pdf->content_to_pdf($htmlContent, '', $fileName, null, 'I', false, null, false, false, false);
exit;

function convertPercentageToScore($p): string
{
    $p    = max(0, min(100, floatval($p)));
    $nota = ($p < 60) ? 1 + $p / 20 : 4 + ($p - 60) * 3 / 40;
    return number_format(max(1, min(7, $nota)), 1, '.', '');
}
