<?php

$course_plugin = 'easycertificate';
require_once __DIR__ . '/../config.php';

api_block_anonymous_users();

$plugin = EasyCertificatePlugin::create();
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';

if (!$enable) {
    api_not_allowed(true);
}

$accessUrlId = api_get_current_access_url_id();
$categoryId = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;
$studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

if (empty($studentId)) {
    api_not_allowed(true);
}

$courseCode = isset($_REQUEST['course_code']) ? Database::escape_string($_REQUEST['course_code']) : '';
$courseInfo = api_get_course_info($courseCode);

if (empty($courseInfo)) {
    api_not_allowed(true);
}

$sessionId = isset($_REQUEST['session_id']) ? (int)$_REQUEST['session_id'] : 0;
$sessionInfo = SessionManager::fetch($sessionId);

// Get certificate info
$infoCertificate = EasyCertificatePlugin::getInfoCertificate($courseInfo['real_id'], $sessionId, $accessUrlId);

if (empty($infoCertificate) || !is_array($infoCertificate)) {
    $infoCertificate = EasyCertificatePlugin::getInfoCertificateDefault($accessUrlId);
}

if (empty($infoCertificate)) {
    api_not_allowed(true);
}

// Certificate configuration
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
$courseName = $courseInfo['name'];
$userInfo = getUserInfo($studentId);

$userName = $userInfo['complete_name'];

$pageTitle = "Certificado - {$courseName} - {$userName}";

$template = new Template($templateName);

// CSS Links

$linkCertificateCSS = '
    <title>' . htmlspecialchars($pageTitle) . '</title>
    <link rel="stylesheet" type="text/css" href="' . api_get_path(WEB_PLUGIN_PATH) . 'easycertificate/resources/css/certificate.css">
    <link rel="stylesheet" type="text/css" href="' . api_get_path(WEB_CSS_PATH) . 'document.css">
    <style>
        /* Estilos para pantalla - Vista previa con scroll */
        @media screen {
            html, body {
                margin: 0;
                padding: 20px;
                background-color: #525659;
                overflow: auto;
                min-height: 100vh;
            }

            #page-a, #page-b {
                margin: 20px auto;
                box-shadow: 0 0 10px rgba(0,0,0,0.5);
                background: white;
                display: block;
                transform-origin: top center;
            }

            /* Para orientación horizontal */
            #page-a[data-orientation="h"],
            #page-b[data-orientation="h"] {
                width: 29.7cm;
                height: 21cm;
                max-width: 100%;
            }

            /* Para orientación vertical */
            #page-a[data-orientation="v"],
            #page-b[data-orientation="v"] {
                width: 21cm;
                height: 29.7cm;
                max-width: 100%;
            }

            /* Responsive - ajustar en pantallas pequeñas */
            @media screen and (max-width: 1200px) {
                #page-a, #page-b {
                    transform: scale(0.8);
                    margin: 10px auto;
                }
            }

            @media screen and (max-width: 900px) {
                #page-a, #page-b {
                    transform: scale(0.6);
                    margin: 5px auto;
                }
            }

            @media screen and (max-width: 600px) {
                body {
                    padding: 10px;
                }
                #page-a, #page-b {
                    transform: scale(0.4);
                    margin: 0 auto;
                }
            }
        }

        /* Estilos para impresión - Sin cambios */
        @media print {
            html, body {
                margin: 0;
                padding: 0;
                background: none;
                overflow: visible;
            }

            @page {
                size: ' . $format . ';
                margin: 0;
            }

            #page-a, #page-b {
                page-break-inside: avoid;
                margin: 0;
                box-shadow: none;
                transform: none !important;
                display: block;
                background: white;
            }

            #page-a {
                page-break-after: always;
            }

            #page-b {
                page-break-before: always;
            }
        }

        /* Estilos comunes */
        #page-a, #page-b {
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }
    </style>';

$starPage = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    ' . $linkCertificateCSS . '
</head>
<body>';
//'<body style="margin: 0; padding: 0; overflow: hidden;">';

$endPage = '</body></html>';

// Get user info

$path = api_get_path(WEB_UPLOAD_PATH) . 'certificates/';

// Background images
$urlBackgroundHorizontal = $path . $infoCertificate['background_h'];
$urlBackgroundVertical = $path . $infoCertificate['background_v'];

// Get all user info for certificate
$allUserInfo = DocumentManager::get_all_info_to_certificate(
    $studentId,
    $courseCode,
    false
);

// Process content
$myContentHtml = $infoCertificate['front_content'];
$myContentHtml = str_replace(chr(13) . chr(10) . chr(13) . chr(10), chr(13) . chr(10), $myContentHtml);

$infoToBeReplacedInContentHtml = $allUserInfo[0];
$infoToReplaceInContentHtml = $allUserInfo[1];
$myContentHtml = str_replace(
    $infoToBeReplacedInContentHtml,
    $infoToReplaceInContentHtml,
    $myContentHtml
);

// Score Certificate
$score = GradebookUtils::get_certificate_by_user_id($categoryId, $studentId);

$myContentHtml = str_replace('((username))', $userInfo['username'], $myContentHtml);
$myContentHtml = str_replace('((score_certificate))', $score['score_certificate'] ?? '', $myContentHtml);

// Simple average without category
$simpleAverageNotCategory = EasyCertificatePlugin::getScoreForEvaluations($courseInfo['code'], $studentId, 0, $sessionId);
$myContentHtml = str_replace('((simple_average))', $simpleAverageNotCategory, $myContentHtml);

// Simple average with category
$simpleAverageCategory = EasyCertificatePlugin::getScoreForEvaluations($courseInfo['code'], $studentId, 1, $sessionId);
$myContentHtml = str_replace('((simple_average_category))', $simpleAverageCategory, $myContentHtml);

// Extra Fields
$extraFieldsAll = EasyCertificatePlugin::getExtraFieldsUserAll(false);
if ($extraFieldsAll) {
    foreach ($extraFieldsAll as $field) {
        $valueExtraField = EasyCertificatePlugin::getValueExtraField($field, $studentId);
        $myContentHtml = str_replace('((' . $field . '))', $valueExtraField, $myContentHtml);
    }
}

// Get Category GradeBook
$myCertificate = GradebookUtils::get_certificate_by_user_id($categoryId, $studentId);

// Session Dates
$startDate = null;
$endDate = null;
if ($sessionId > 0 && !empty($sessionInfo)) {
    switch (intval($infoCertificate['date_change'])) {
        case 1:
            if (!empty($sessionInfo['display_start_date'])) {
                $startDate = api_get_local_time($sessionInfo['display_start_date'], null, null, true);
                $startDate = api_format_date($startDate, DATE_FORMAT_LONG_NO_DAY);
            }
            if (!empty($sessionInfo['display_end_date'])) {
                $endDate = api_get_local_time($sessionInfo['display_end_date'], null, null, true);
                $endDate = api_format_date($endDate, DATE_FORMAT_LONG_NO_DAY);
            }
            break;
        case 2:
            if (!empty($sessionInfo['access_start_date'])) {
                $startDate = api_get_local_time($sessionInfo['access_start_date'], null, null, true);
                $startDate = api_format_date($startDate, DATE_FORMAT_LONG_NO_DAY);
            }
            if (!empty($sessionInfo['access_end_date'])) {
                $endDate = api_get_local_time($sessionInfo['access_end_date'], null, null, true);
                $endDate = api_format_date($endDate, DATE_FORMAT_LONG_NO_DAY);
            }
            break;
    }

    if (is_null($startDate) && !empty($myCertificate['created_at'])) {
        $startDate = api_format_date(strtotime(api_get_local_time($myCertificate['created_at'])), DATE_FORMAT_LONG_NO_DAY);
    }

    if (is_null($endDate) && !empty($myCertificate['created_at'])) {
        $endDate = api_format_date(strtotime(api_get_local_time($myCertificate['created_at'])), DATE_FORMAT_LONG_NO_DAY);
    }

    $myContentHtml = str_replace('((session_start_date))', $startDate ?? '', $myContentHtml);
    $myContentHtml = str_replace('((session_end_date))', $endDate ?? '', $myContentHtml);
}

// Date Expedition
$createdAt = '';
if (!empty($myCertificate['created_at'])) {
    $createdAt = strtotime(api_get_local_time($myCertificate['created_at']));
    $createdAt = api_format_date($createdAt, DATE_FORMAT_LONG_NO_DAY);
}
$myContentHtml = str_replace('((expedition_date))', $createdAt, $myContentHtml);

$dateExpiration = !empty($myCertificate['expiration_date'])
    ? api_format_date($myCertificate['expiration_date'], DATE_FORMAT_LONG_NO_DAY)
    : '';
$myContentHtml = str_replace('((expiration_date))', $dateExpiration, $myContentHtml);

// Certificates Trabajo Alto Riesgo
$certificatesTrabajoAltoRiesgo = getCertificatesTrabajoAltoRiesgo($userInfo['metadata'], $sessionId);

$htmlCertificatesTrabajoAltoRiesgo = '';
if (!empty($certificatesTrabajoAltoRiesgo)) {
    $htmlCertificatesTrabajoAltoRiesgo = '
    <div style="margin-top: 20px; font-family: Arial, sans-serif; font-size: 9pt; line-height: 1.4;">
        <div><strong>Certificados adicionales:</strong></div>';

    foreach ($certificatesTrabajoAltoRiesgo as $certificate) {
        $htmlCertificatesTrabajoAltoRiesgo .= '
        <div style="margin-left: 15px;">' . htmlspecialchars($certificate) . '</div>';
    }

    $htmlCertificatesTrabajoAltoRiesgo .= '
    </div>';
}

$myContentHtml = str_replace('((attach_certificates_alto_riesgo))', $htmlCertificatesTrabajoAltoRiesgo, $myContentHtml);

// Certificate Code, QR and Barcode
$codeCertificate = EasyCertificatePlugin::getCodeCertificate($categoryId, $studentId);
if (!empty($codeCertificate)) {
    $proikosCertCorrelation = EasyCertificatePlugin::getProikosCertCode($codeCertificate['id_certificate']);

    $myContentHtml = str_replace(
        '((code_certificate))',
        strtoupper($codeCertificate['code_certificate_md5']),
        $myContentHtml
    );

    $certificateQR = EasyCertificatePlugin::getGenerateUrlImg($studentId, $codeCertificate['code_certificate_md5']);

// QR posicionado en la esquina inferior izquierda
    $qrCodeHtml = '
<div style="position: absolute; bottom: 1cm; left: 1cm; z-index: 100;">
    <div style="font-family: Arial, sans-serif; font-size: 9pt; margin-bottom: 5px;">
        Código: ' . htmlspecialchars($proikosCertCorrelation) . '
    </div>
    <img src="data:image/png;base64,' . $certificateQR . '" alt="QR Code" style="width: 100px; height: 100px; display: block;">
</div>';

    $myContentHtml = str_replace('((qr-code))', $qrCodeHtml, $myContentHtml);

    $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
    $codCertificate = $codeCertificate['code_certificate'];
    if (!empty($codCertificate)) {
        $myContentHtml = str_replace(
            '((bar_code))',
            '<img src="data:image/png;base64,' . base64_encode($generator->getBarcode($codCertificate, $generator::TYPE_CODE_128)) . '" alt="Barcode">',
            $myContentHtml
        );
    }
}

$myContentHtml = strip_tags(
    $myContentHtml,
    '<p><b><strong><table><tr><td><th><tbody><span><i><li><ol><ul><dd><dt><dl><br><hr><img><a><div><h1><h2><h3><h4><h5><h6>'
);

// Assign template variables
$template->assign('css_certificate', $linkCertificateCSS);
$template->assign('orientation', $orientation);
$template->assign('background_h', $urlBackgroundHorizontal);
$template->assign('background_v', $urlBackgroundVertical);
$template->assign('margin', $margin);
$template->assign('front_content', $myContentHtml);
$template->assign('show_back', $infoCertificate['show_back']);

// Rear certificate
$laterContent = '<table width="100%" class="contents-learnpath">
    <tr>
        <td>';

$backContentHtml = strip_tags(
    $infoCertificate['back_content'],
    '<p><b><strong><table><tr><td><th><span><i><li><ol><ul><dd><dt><dl><br><hr><img><a><div><h1><h2><h3><h4><h5><h6>'
);

$laterContent .= $backContentHtml . '
        </td>
    </tr>
</table>';

$template->assign('back_content', $laterContent);

// Generate HTML
$content = $template->fetch('easycertificate/template/certificate_html.tpl');
$htmlText = $starPage . $content . $endPage;

echo $htmlText;

function getUserInfo($studentId) {
    $userInfo = api_get_user_info($studentId);

    if (empty($userInfo)) {
        return null;
    }

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

function getCertificatesTrabajoAltoRiesgo($userMetadata, $sessionId): array
{
    $certificates = [];

    if (empty($userMetadata) || !isset($userMetadata['attachments']) || !is_array($userMetadata['attachments'])) {
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
