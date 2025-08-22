<?php
/* For licensing terms, see /license.txt */

$default = isset($_GET['default']) ? (int)$_GET['default'] : null;
$type = isset($_GET['type']) ? (int)$_GET['type'] : null;
$demo = isset($_GET['demo']) ? (bool)$_GET['demo'] : null;

if ($default === 1) {
    $cidReset = true;
}

$course_plugin = 'easycertificate';
require_once __DIR__ . '/../config.php';

api_block_anonymous_users();
$plugin = EasyCertificatePlugin::create();
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';
$tblProperty = Database::get_course_table(TABLE_ITEM_PROPERTY);
$categoryId = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;
$content = [];

if (!$enable) {
    api_not_allowed(true, $plugin->get_lang('ToolDisabled'));
}

if ($default == 1) {
    $courseId = 0;
    $courseCode = '';
    $sessionId = 0;
    $enableCourse = false;
    $useDefault = true;
} else {
    $courseId = api_get_course_int_id();
    $courseCode = api_get_course_id();
    $sessionId = api_get_session_id();
    $enableCourse = api_get_course_setting('easycertificate_course_enable') == 1;
    $useDefault = api_get_course_setting('use_certificate_default') == 1;
}

if (empty($courseCode)) {
    $courseCode = isset($_REQUEST['course_code']) ? Database::escape_string($_REQUEST['course_code']) : '';
    $courseInfo = api_get_course_info($courseCode);
    if (!empty($courseInfo)) {
        $courseId = $courseInfo['real_id'];
    }
} else {
    $courseInfo = api_get_course_info($courseCode);
}

if (empty($sessionId)) {
    $sessionId = isset($_REQUEST['session_id']) ? (int)$_REQUEST['session_id'] : 0;
}
$catId = isset($_REQUEST['cat_id']) ? (int)$_REQUEST['cat_id'] : 0;
$accessUrlId = api_get_current_access_url_id();

$userList = [];
$exportZip = false;
$exportAllInOne = false;
if (empty($_GET['export_all']) && empty($_GET['export_all_in_one'])) {
    if (!isset($_GET['student_id'])) {
        $studentId = api_get_user_id();
    } else {
        $studentId = intval($_GET['student_id']);
    }
    $userList[] = getUserInfo($studentId);
} else {

    if (!empty($_GET['export_all'])) {
        $exportZip = true;
    }
    if (!empty($_GET['export_all_in_one'])) {
        $exportAllInOne = true;
    }

    $certificate_list = GradebookUtils::get_list_users_certificates($categoryId);
    foreach ($certificate_list as $index => $value) {
        $userList[] = getUserInfo($value['user_id']);
    }
}

$sessionInfo = [];
if ($sessionId > 0) {
    $sessionInfo = SessionManager::fetch($sessionId);
}

$table = Database::get_main_table(EasyCertificatePlugin::TABLE_EASYCERTIFICATE);
$useDefault = false;
$path = api_get_path(SYS_UPLOAD_PATH) . 'certificates';

// Get info certificate
$infoCertificate = EasyCertificatePlugin::getInfoCertificate($courseId, $sessionId, $accessUrlId);

if (!is_array($infoCertificate)) {
    $infoCertificate = [];
}

if (empty($infoCertificate)) {
    $infoCertificate = EasyCertificatePlugin::getInfoCertificateDefault($accessUrlId);

    if (empty($infoCertificate)) {
        Display::display_header($plugin->get_lang('PrintCertificate'));
        echo Display::return_message($plugin->get_lang('ErrorTemplateCertificate'), 'error');
        Display::display_footer();
        exit;
    } else {
        $useDefault = true;
    }
}
$linkCertificateCSS = $urlBackgroundHorizontal = $urlBackgroundVertical = $myContentHtml = $nameUser = null;
$workSpace = intval(297 - $infoCertificate['margin_left'] - $infoCertificate['margin_right']);
$widthCell = intval($workSpace / 6);
$htmlList = [];

$currentLocalTime = api_get_local_time();

$orientation = $infoCertificate['orientation'];
$format = 'A4-L';
$pageOrientation = 'L';
if ($orientation != 'h') {
    $format = 'A4';
    $pageOrientation = 'P';
}

$marginLeft = ($infoCertificate['margin_left'] > 0) ? $infoCertificate['margin_left'] . 'cm' : 0;
$marginRight = ($infoCertificate['margin_right'] > 0) ? $infoCertificate['margin_right'] . 'cm' : 0;
$marginTop = ($infoCertificate['margin_top'] > 0) ? $infoCertificate['margin_top'] . 'cm' : 0;
$marginBottom = ($infoCertificate['margin_bottom'] > 0) ? $infoCertificate['margin_bottom'] . 'cm' : 0;
$margin = $marginTop . ' ' . $marginRight . ' ' . $marginBottom . ' ' . $marginLeft;

$templateName = $plugin->get_lang('ExportCertificate');
$template = new Template($templateName);

$fileList = [];
$archivePath = api_get_path(SYS_ARCHIVE_PATH) . 'certificates/';
$linkCertificateCSS = '
        <link rel="stylesheet"
            type="text/css"
            href="' . api_get_path(WEB_PLUGIN_PATH) . 'easycertificate/resources/css/certificate.css">';
$linkCertificateCSS .= '
        <link rel="stylesheet"
            type="text/css"
            href="' . api_get_path(WEB_CSS_PATH) . 'document.css">';
$starPage = '<!DOCTYPE html>
<head>'.$linkCertificateCSS.'
</head>
<body style="margin: 0; padding: 0;">';
$endPage = '</body></html>';
$htmlText = null;
foreach ($userList as $userInfo) {
    $studentId = $userInfo['user_id'];
    $urlBackgroundHorizontal = $path . $infoCertificate['background_h'];
    $urlBackgroundVertical = $path . $infoCertificate['background_v'];
    $allUserInfo = DocumentManager::get_all_info_to_certificate(
        $studentId,
        $courseCode,
        false
    );

    $myContentHtml = $infoCertificate['front_content'];
    $myContentHtml = str_replace(chr(13) . chr(10) . chr(13) . chr(10), chr(13) . chr(10), $myContentHtml);
    $infoToBeReplacedInContentHtml = $allUserInfo[0];
    $infoToReplaceInContentHtml = $allUserInfo[1];
    $myContentHtml = str_replace(
        $infoToBeReplacedInContentHtml,
        $infoToReplaceInContentHtml,
        $myContentHtml
    );

    //Score Certificate

    $score = GradebookUtils::get_certificate_by_user_id(
        $catId,
        $userInfo['user_id']
    );

    $myContentHtml = str_replace(
        '((username))',
        $userInfo['username'],
        $myContentHtml
    );

    $myContentHtml = str_replace(
        '((score_certificate))',
        $score['score_certificate'],
        $myContentHtml
    );

    //simple average with category
    $simpleAverageNotCategory = EasyCertificatePlugin::getScoreForEvaluations($courseInfo['code'], $studentId, 0, $sessionId);

    $myContentHtml = str_replace(
        '((simple_average))',
        $simpleAverageNotCategory,
        $myContentHtml
    );

    //simple average with category
    $simpleAverageCategory = EasyCertificatePlugin::getScoreForEvaluations($courseInfo['code'], $studentId, 1, $sessionId);
    $myContentHtml = str_replace(
        '((simple_average_category))',
        $simpleAverageCategory,
        $myContentHtml
    );

    //ExtraField
    $extraFieldsAll = EasyCertificatePlugin::getExtraFieldsUserAll(false);
    if ($extraFieldsAll) {
        foreach ($extraFieldsAll as $field) {
            $valueExtraField = EasyCertificatePlugin::getValueExtraField($field, $studentId);
            $myContentHtml = str_replace(
                '((' . $field . '))',
                $valueExtraField,
                $myContentHtml
            );
        }
    }

    //Get Category GradeBook
    $myCertificate = GradebookUtils::get_certificate_by_user_id(
        $catId,
        $studentId
    );

    //Session Date.
    $startDate = null;
    $endDate = null;
    if ($sessionId > 0) {

        switch (intval($infoCertificate['date_change'])) {
            case 1:
                if (!empty($sessionInfo['display_start_date'])) {
                    $startDate = api_get_local_time($sessionInfo['display_start_date'],null,null,true);
                    $startDate = api_format_date($startDate, DATE_FORMAT_LONG_NO_DAY);
                }
                if (!empty($sessionInfo['display_end_date'])) {
                    $endDate = api_get_local_time($sessionInfo['display_end_date']);
                    $endDate = api_format_date($endDate, DATE_FORMAT_LONG_NO_DAY);
                }
                break;
            case 2:
                if (!empty($sessionInfo['access_start_date'])) {
                    $startDate = api_get_local_time($sessionInfo['access_start_date'],null,null,true);
                    $startDate = api_format_date($startDate, DATE_FORMAT_LONG_NO_DAY);
                }
                if (!empty($sessionInfo['access_end_date'])) {
                    $endDate = strtotime(api_get_local_time($sessionInfo['access_end_date']));
                    $endDate = api_format_date($endDate, DATE_FORMAT_LONG_NO_DAY);
                }
                break;
        }

        if(is_null($startDate)){
            $startDate = api_format_date(strtotime(api_get_local_time($myCertificate['created_at'])), DATE_FORMAT_LONG_NO_DAY);
        }

        if(is_null($endDate)){
            $endDate = api_format_date(strtotime(api_get_local_time($myCertificate['created_at'])), DATE_FORMAT_LONG_NO_DAY);
        }

        $myContentHtml = str_replace(
            '((session_start_date))',
            $startDate,
            $myContentHtml
        );

        $myContentHtml = str_replace(
            '((session_end_date))',
            $endDate,
            $myContentHtml
        );
    }

    //Date Expedition

    $createdAt = '';
    if (!empty($myCertificate['created_at'])) {
        $createdAt = strtotime(api_get_local_time($myCertificate['created_at']));
        $createdAt = api_format_date($createdAt, DATE_FORMAT_LONG_NO_DAY);
    }
    $myContentHtml = str_replace(
        '((expedition_date))',
        $createdAt,
        $myContentHtml
    );

    $dateExpiration = api_format_date($myCertificate['expiration_date'], DATE_FORMAT_LONG_NO_DAY);
    $myContentHtml = str_replace(
        '((expiration_date))',
        $dateExpiration,
        $myContentHtml
    );
    $certificatesTrabajoAltoRiesgo = getCertificatesTrabajoAltoRiesgo(
        $userInfo['metadata'],
        $sessionId
    );

    $htmlCertificatesTrabajoAltoRiesgo = '';
    if (!empty($certificatesTrabajoAltoRiesgo)) {
        $htmlCertificatesTrabajoAltoRiesgo = '
        <div style="margin-top: 20px; font-family: Arial, sans-serif; font-size: 9pt; line-height: 1.4;">
            <div><strong>Certificados adicionales:</strong></div>';

        foreach ($certificatesTrabajoAltoRiesgo as $certificate) {
            $htmlCertificatesTrabajoAltoRiesgo .= '
            <div style="margin-left: 15px;">' . $certificate . '</div>';
        }

        $htmlCertificatesTrabajoAltoRiesgo .= '
        </div>';
    }

    $myContentHtml = str_replace(
        '((attach_certificates_alto_riesgo))',
        $htmlCertificatesTrabajoAltoRiesgo,
        $myContentHtml
    );

    $codeCertificate = EasyCertificatePlugin::getCodeCertificate($catId, $studentId);
    if (!empty($codeCertificate)) {
        $certificateValidate = EasyCertificatePlugin::getGenerateInfoCertificate(true, $codeCertificate['code_certificate_md5'], false);
        $proikosCertCorrelation = EasyCertificatePlugin::getProikosCertCode($codeCertificate['id_certificate']);
        $myContentHtml = str_replace(
            '((code_certificate))',
            strtoupper($codeCertificate['code_certificate_md5']),
            $myContentHtml
        );
        $certificateQR = EasyCertificatePlugin::getGenerateUrlImg($studentId, $codeCertificate['code_certificate_md5']);
        $myContentHtml = str_replace(
            '((qr-code))',
            '<span style="font-family: Arial, sans-serif; font-size: 9pt;">Código: ' . $proikosCertCorrelation . '</span> <br>' .
            '<img src="data:image/png;base64,' . $certificateQR . '">'
            ,
            $myContentHtml
        );

        $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
        $codCertificate = $codeCertificate['code_certificate'];
        if (!empty($codCertificate)) {
            $myContentHtml = str_replace(
                '((bar_code))',
                '<img src="data:image/png;base64,' . base64_encode($generator->getBarcode($codCertificate, $generator::TYPE_CODE_128)) . '">'
                ,
                $myContentHtml
            );
        }
    }

    $myContentHtml = strip_tags(
        $myContentHtml,
        '<p><b><strong><table><tr><td><th><tbody><span><i><li><ol><ul>
        <dd><dt><dl><br><hr><img><a><div><h1><h2><h3><h4><h5><h6>'
    );

    $nameUser = $userInfo['complete_name'];

    $template->assign('css_certificate', $linkCertificateCSS);
    $template->assign('orientation', $orientation);
    $template->assign('background_h', $urlBackgroundHorizontal);
    $template->assign('background_v', $urlBackgroundVertical);
    $template->assign('margin', $margin);
    $template->assign('front_content', $myContentHtml);
    $template->assign('show_back', $infoCertificate['show_back']);

// Rear certificate
    $laterContent = null;
    $laterContent .= '<table width="100%" class="contents-learnpath">';
    $laterContent .= '<tr>';
    $laterContent .= '<td>';
    $myContentHtml = strip_tags(
        $infoCertificate['back_content'],
        '<p><b><strong><table><tr><td><th><span><i><li><ol><ul>' .
        '<dd><dt><dl><br><hr><img><a><div><h1><h2><h3><h4><h5><h6>'
    );
    $laterContent .= $myContentHtml;
    $laterContent .= '</td>';
    $laterContent .= '</tr>';
    $laterContent .= '</table>';

    $template->assign('back_content', $laterContent);
    $content = $template->fetch('easycertificate/template/certificate.tpl');

    if($exportAllInOne){
        $htmlText.= $content;
    } else {
        $htmlText = $starPage.$content.$endPage;
        $nameCertificate = strtoupper(str_replace([" ", ","], "_", $userInfo['complete_name']));
        $fileName = $nameCertificate . '_CERT_' . $courseInfo['code'];
        $fileName = api_replace_dangerous_char($fileName);
        $htmlList[$fileName] = $htmlText;
        $fileList[] = $archivePath.$fileName.'.pdf';
    }
}

if($exportAllInOne) {
    $fileName = '_CERT_' . $courseInfo['code'] . '_ALL';
    $fileName = api_replace_dangerous_char($fileName);
    $params = [
        'filename' => $fileName,
        'pdf_title' => 'Certificate',
        'pdf_description' => '',
        'format' => $format,
        'orientation' => $pageOrientation,
        'left' => 0,
        'top' => 0,
        'bottom' => 0,
        'right' => 0
    ];
    $pdf = new PDF($params['format'], $params['orientation'], $params);
    $pdf->content_to_pdf($starPage.$htmlText.$endPage, '', $fileName, null, 'D', false, null, false, false, false);
}

if (!is_dir($archivePath)) {
    mkdir($archivePath, api_get_permissions_for_new_directories());
}
ini_set('max_execution_time', '300');
foreach ($htmlList as $fileName => $content) {
    $fileName = api_replace_dangerous_char($fileName);
    $params = [
        'filename' => $fileName,
        'pdf_title' => 'Certificate',
        'pdf_description' => '',
        'format' => $format,
        'orientation' => $pageOrientation,
        'left' => 0,
        'top' => 0,
        'bottom' => 0,
        'right' => 0
    ];

    $pdf = new PDF($params['format'], $params['orientation'], $params);
    if ($exportZip) {
        $filePath = $archivePath.$fileName.'.pdf';
        $pdf->content_to_pdf($content, '', $fileName, null, 'F', true, $filePath, false, false, false);
    } else {
        $pdf->content_to_pdf($content, '', $fileName, null, 'D', false, null, false, false, false);
    }
}

if (!empty($fileList)) {
    $zipFile = $archivePath.'certificates_'.api_get_unique_id().'.zip';
    $zipFolder = new PclZip($zipFile);
    foreach ($fileList as $file) {
        $zipFolder->add($file, PCLZIP_OPT_REMOVE_ALL_PATH);
    }
    $name = 'certificates_'.$courseInfo['code'].'_'.$currentLocalTime.'.zip';
    DocumentManager::file_send_for_download($zipFile, true, $name);
    exit;
}

function getIndexFiltered($index): string
{
    $txt = strip_tags($index, "<b><strong><i>");
    $txt = str_replace(chr(13).chr(10).chr(13).chr(10), chr(13).chr(10), $txt);
    $lines = explode(chr(13).chr(10), $txt);
    $text1 = '';
    for ($x = 0; $x < 47; $x++) {
        if (isset($lines[$x])) {
            $text1 .= $lines[$x].chr(13).chr(10);
        }
    }

    $text2 = '';
    for ($x = 47; $x < 94; $x++) {
        if (isset($lines[$x])) {
            $text2 .= $lines[$x].chr(13).chr(10);
        }
    }

    $showLeft = str_replace(chr(13).chr(10), "<br/>", $text1);
    $showRight = str_replace(chr(13).chr(10), "<br/>", $text2);
    $result = '<table width="100%">';
    $result .= '<tr>';
    $result .= '<td style="width:50%;vertical-align:top;padding-left:15px; font-size:12px;">'.$showLeft.'</td>';
    $result .= '<td style="vertical-align:top; font-size:12px;">'.$showRight.'</td>';
    $result .= '<tr>';
    $result .= '</table>';

    return $result;
}

function getUserInfo($studentId) {
    $userInfo = api_get_user_info($studentId);
    $allowProikos = api_get_plugin_setting('proikos', 'tool_enable') === 'true';
    $userInfo['metadata'] = [];
    if ($allowProikos) {
        $pluginProikos = ProikosPlugin::create();
        $userMetadata = $pluginProikos->getUserMetadata($studentId);

        if (empty($userMetadata) || !is_array($userMetadata) || $userMetadata == 'null') {
            $userMetadata = [];
        }

        $userInfo['metadata'] = $userMetadata;
    }

    return $userInfo;
}

function getCertificatesTrabajoAltoRiesgo($userMetadata, $sessionId)
{
    $certificates = [];
    if (empty($userMetadata) || !isset($userMetadata['attachments']) || !is_array($userMetadata['attachments'])) {
        return $certificates;
    }

    if (empty($userMetadata['attachments'])) {
        return $certificates;
    }

    foreach ($userMetadata['attachments'] as $attachment) {
        if ($attachment['session_id'] != $sessionId) {
            continue;
        }

        if (empty($attachment['optional_request_attach_certificates'])) {
            continue;
        }

        foreach ($attachment['optional_request_attach_certificates'] as $certificate) {
            $certificates[] = $certificate;
        }
    }

    return $certificates;
}
