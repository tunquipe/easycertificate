<?php

/* For licensing terms, see /license.txt */

/**
 * Descarga masiva de certificados en HTML paginado.
 *
 * Replica la lógica de view_certificate.php pero iterando sobre todos los
 * estudiantes con certificado en la categoría del gradebook. Genera un único
 * documento HTML con cada certificado (anverso/reverso) en su propia hoja de
 * impresión, de modo que la generación del PDF se delega al navegador del
 * cliente (window.print()) sin carga adicional sobre el servidor.
 */

$course_plugin = 'easycertificate';
require_once __DIR__ . '/../config.php';

api_block_anonymous_users();

if (!api_is_allowed_to_edit() && !api_is_student_boss()) {
    api_not_allowed(true);
}

$plugin = EasyCertificatePlugin::create();
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';

if (!$enable) {
    api_not_allowed(true);
}

api_set_more_memory_and_time_limits();

$accessUrlId = api_get_current_access_url_id();
$categoryId = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;

$courseCode = isset($_REQUEST['course_code']) ? Database::escape_string($_REQUEST['course_code']) : api_get_course_id();
$courseInfo = api_get_course_info($courseCode);

if (empty($courseInfo)) {
    api_not_allowed(true);
}

$sessionId = isset($_REQUEST['session_id']) ? (int) $_REQUEST['session_id'] : api_get_session_id();
$sessionInfo = SessionManager::fetch($sessionId);

// Get certificate info
$infoCertificate = EasyCertificatePlugin::getInfoCertificate($courseInfo['real_id'], $sessionId, $accessUrlId);

if (empty($infoCertificate) || !is_array($infoCertificate)) {
    $infoCertificate = EasyCertificatePlugin::getInfoCertificateDefault($accessUrlId);
}

if (empty($infoCertificate)) {
    api_not_allowed(true);
}

// Lista de estudiantes con certificado en la categoría
$certificateList = GradebookUtils::get_list_users_certificates($categoryId);

if (empty($certificateList)) {
    api_not_allowed(true);
}

// Certificate configuration
$orientation = $infoCertificate['orientation'];
$format = 'A4-L';
if ($orientation != 'h') {
    $format = 'A4';
}

$marginLeft = ($infoCertificate['margin_left'] > 0) ? $infoCertificate['margin_left'] . 'cm' : 0;
$marginRight = ($infoCertificate['margin_right'] > 0) ? $infoCertificate['margin_right'] . 'cm' : 0;
$marginTop = ($infoCertificate['margin_top'] > 0) ? $infoCertificate['margin_top'] . 'cm' : 0;
$marginBottom = ($infoCertificate['margin_bottom'] > 0) ? $infoCertificate['margin_bottom'] . 'cm' : 0;
$margin = $marginTop . ' ' . $marginRight . ' ' . $marginBottom . ' ' . $marginLeft;

$templateName = $plugin->get_lang('ExportCertificate');
$courseName = $courseInfo['name'];

$pageTitle = "Certificados - {$courseName}";

$template = new Template($templateName);

// CSS Links
$linkCertificateCSS = '
    <title>' . htmlspecialchars($pageTitle) . '</title>
    <meta name="description" content="Certificados del curso ' . htmlspecialchars($courseName) . '">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" type="text/css" href="' . api_get_path(WEB_PLUGIN_PATH) . 'easycertificate/resources/css/certificate.css">
    <link rel="stylesheet" type="text/css" href="' . api_get_path(WEB_CSS_PATH) . 'document.css">
    <style>
        /* Reset básico */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Estilos para pantalla - Vista previa con scroll */
        @media screen {
            html {
                overflow: auto;
            }

            body {
                margin: 0;
                padding: 20px;
                background-color: #525659;
                overflow: auto;
                min-height: 100vh;
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
            }

            .cert-page {
                margin: 20px auto;
                box-shadow: 0 0 10px rgba(0,0,0,0.5);
                background: white;
                display: block;
                transform-origin: top center;
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                pointer-events: auto;
            }

            img {
                user-select: none;
                -webkit-user-drag: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                pointer-events: none;
            }

            /* CONTENEDOR DE BOTONES FLOTANTES */
            #certificate-actions {
                position: fixed;
                bottom: 30px;
                right: 30px;
                display: flex;
                gap: 10px;
                z-index: 9999;
                pointer-events: auto;
            }

            /* BOTÓN FLOTANTE DE IMPRESIÓN */
            #print-button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 50px;
                padding: 15px 30px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                font-family: Arial, sans-serif;
                pointer-events: auto;
                user-select: none;
            }

            #print-button:hover {
                transform: translateY(-3px);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
                background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            }

            #print-button:active {
                transform: translateY(-1px);
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
            }

            #print-button svg {
                width: 20px;
                height: 20px;
                fill: white;
            }

            /* Aviso de cómo guardar en PDF */
            #pdf-hint {
                position: fixed;
                bottom: 30px;
                left: 30px;
                max-width: 360px;
                background: rgba(0,0,0,0.78);
                color: #fff;
                padding: 12px 16px;
                border-radius: 10px;
                font-family: Arial, sans-serif;
                font-size: 13px;
                line-height: 1.45;
                z-index: 9999;
                box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            }

            #pdf-hint strong {
                color: #ffd966;
            }

            /* Contador de certificados */
            #cert-counter {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(0,0,0,0.7);
                color: white;
                padding: 8px 18px;
                border-radius: 20px;
                font-family: Arial, sans-serif;
                font-size: 14px;
                z-index: 9999;
            }

            /* Orientación */
            .cert-page[data-orientation="h"] {
                width: 29.7cm;
                height: 21cm;
                max-width: none;
                min-width: 29.7cm;
            }

            .cert-page[data-orientation="v"] {
                width: 21cm;
                height: 29.7cm;
                max-width: none;
                min-width: 21cm;
            }

            @media screen and (max-width: 768px) {
                html { overflow-x: auto; overflow-y: auto; }
                body { padding: 10px; overflow-x: auto; overflow-y: auto; min-width: min-content; }
                .cert-page { margin: 10px auto; box-shadow: 0 0 8px rgba(0,0,0,0.4); max-width: none; }
                .cert-page[data-orientation="h"] { width: 29.7cm; height: 21cm; min-width: 29.7cm; }
                .cert-page[data-orientation="v"] { width: 21cm; height: 29.7cm; min-width: 21cm; }
                #certificate-actions { bottom: 15px; right: 15px; gap: 8px; }
                #print-button { padding: 0; border-radius: 50%; width: 56px; height: 56px; justify-content: center; }
                #print-button span { display: none; }
                #pdf-hint { left: 10px; right: 10px; bottom: 80px; max-width: none; font-size: 12px; }
            }
        }

        /* Estilos para impresión - cada certificado en su hoja */
        @media print {
            html, body {
                margin: 0;
                padding: 0;
                background: none;
                overflow: visible;
                user-select: none;
            }

            @page {
                size: ' . $format . ';
                margin: 0;
            }

            .cert-page {
                page-break-inside: avoid;
                page-break-after: always;
                margin: 0;
                box-shadow: none;
                transform: none !important;
                display: block;
                background: white;
            }

            .cert-page:last-child {
                page-break-after: auto;
            }

            .cert-page[data-orientation="h"] {
                width: 29.7cm;
                height: 21cm;
            }

            .cert-page[data-orientation="v"] {
                width: 21cm;
                height: 29.7cm;
            }

            #certificate-actions,
            #cert-counter,
            #pdf-hint {
                display: none !important;
            }
        }

        /* Estilos comunes */
        .cert-page {
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        ::-webkit-scrollbar { width: 12px; height: 12px; }
        ::-webkit-scrollbar-track { background: #3a3a3a; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
    </style>';

$starPage = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    ' . $linkCertificateCSS . '
</head>
<body>';

$endPage = '
<script>
    (function() {
        "use strict";

        function isMobile() {
            return window.innerWidth <= 768;
        }

        function adjustCertificateZoom() {
            const pages = document.querySelectorAll(".cert-page");
            if (!isMobile() && window.matchMedia("screen").matches) {
                const windowWidth = window.innerWidth - 40;
                pages.forEach(page => {
                    const pageWidth = page.offsetWidth;
                    if (pageWidth > windowWidth) {
                        const scale = windowWidth / pageWidth;
                        page.style.transform = `scale(${scale})`;
                        page.style.transformOrigin = "top center";
                    } else {
                        page.style.transform = "none";
                    }
                });
            } else {
                pages.forEach(page => { page.style.transform = "none"; });
            }
        }

        window.addEventListener("load", adjustCertificateZoom);

        let resizeTimer;
        window.addEventListener("resize", function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(adjustCertificateZoom, 250);
        });

        window.addEventListener("beforeprint", () => {
            document.querySelectorAll(".cert-page").forEach(page => { page.style.transform = "none"; });
        });
        window.addEventListener("afterprint", adjustCertificateZoom);

        // Protecciones de seguridad
        document.addEventListener("contextmenu", e => { e.preventDefault(); return false; }, false);
        document.addEventListener("selectstart", e => { e.preventDefault(); return false; }, false);
        document.addEventListener("dragstart", e => { e.preventDefault(); return false; }, false);
        document.addEventListener("copy", e => { e.preventDefault(); return false; }, false);
        document.addEventListener("cut", e => { e.preventDefault(); return false; }, false);
        document.addEventListener("keydown", function(e) {
            if (e.keyCode === 123) { e.preventDefault(); return false; }
            if (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67)) { e.preventDefault(); return false; }
            if (e.ctrlKey && (e.keyCode === 85 || e.keyCode === 83 || e.keyCode === 65)) { e.preventDefault(); return false; }
            if (e.ctrlKey && e.keyCode === 67 && !e.shiftKey) { e.preventDefault(); return false; }
        }, false);
        document.addEventListener("gesturestart", e => { e.preventDefault(); });
        let lastTouchEnd = 0;
        document.addEventListener("touchend", function(e) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) { e.preventDefault(); }
            lastTouchEnd = now;
        }, false);
    })();
</script>
</body></html>';

$path = api_get_path(WEB_UPLOAD_PATH) . 'certificates/';
$urlBackgroundHorizontal = $path . $infoCertificate['background_h'];
$urlBackgroundVertical = $path . $infoCertificate['background_v'];

// Precalcular campos extra una sola vez (son globales, no dependen del usuario)
$extraFieldsAll = EasyCertificatePlugin::getExtraFieldsUserAll(false);

$dataOrientation = ($orientation == 'h') ? 'h' : 'v';
$bgImage = ($orientation == 'h') ? $urlBackgroundHorizontal : $urlBackgroundVertical;
$pageWidth = ($orientation == 'h') ? '29.7cm' : '21cm';
$pageHeight = ($orientation == 'h') ? '21cm' : '29.7cm';


$pagesHtml = '';
$generated = 0;

foreach ($certificateList as $value) {
    $studentId = (int) $value['user_id'];
    $userInfo = getUserInfo($studentId);

    if (empty($userInfo)) {
        continue;
    }

    $frontContent = buildCertificateContent(
        $studentId,
        $userInfo,
        $courseInfo,
        $sessionId,
        $sessionInfo,
        $categoryId,
        $infoCertificate,
        $extraFieldsAll
    );

    $backContent = '';
    if ($infoCertificate['show_back']) {
        $backContentHtml = strip_tags(
            $infoCertificate['back_content'],
            '<p><b><strong><table><tr><td><th><span><i><li><ol><ul><dd><dt><dl><br><hr><img><a><div><h1><h2><h3><h4><h5><h6>'
        );
        $backContent = '<table width="100%" class="contents-learnpath"><tr><td>' . $backContentHtml . '</td></tr></table>';
    }

    // Página anverso
    $pagesHtml .= renderCertPage($dataOrientation, $bgImage, $pageWidth, $pageHeight, $margin, $frontContent);

    // Página reverso
    if (!empty($backContent)) {
        $pagesHtml .= renderCertPage($dataOrientation, $bgImage, $pageWidth, $pageHeight, $margin, $backContent);
    }

    $generated++;
}

if ($generated === 0) {
    api_not_allowed(true);
}

$counter = '<div id="cert-counter">' . $generated . ' ' . htmlspecialchars($plugin->get_lang('Certificate')) . '(s) - ' . htmlspecialchars($courseName) . '</div>';

// Descarga masiva mediante impresión nativa del navegador (Guardar como PDF).
// Este método usa el motor de render del navegador (texto vectorial), por lo
// que escala a cientos de certificados sin saturar memoria ni el servidor,
// a diferencia de la rasterización con html2canvas.
$actions = '<div id="certificate-actions">
    <button id="print-button" onclick="window.print()" title="Imprimir o Guardar como PDF">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
        </svg>
        <span>Imprimir / Guardar PDF</span>
    </button>
</div>

<div id="pdf-hint">
    Para descargar en PDF: pulsa <strong>Imprimir / Guardar PDF</strong> y en el destino elige
    <strong>"Guardar como PDF"</strong>. Asegúrate de tener activado <strong>"Gráficos de fondo"</strong>.
</div>';

echo $starPage . $counter . $pagesHtml . $actions . $endPage;

/**
 * Renderiza una hoja de certificado (anverso o reverso).
 */
function renderCertPage($dataOrientation, $bgImage, $pageWidth, $pageHeight, $margin, $content): string
{
    $bgStyle = !empty($bgImage) ? "background-image: url('" . $bgImage . "');" : '';

    return '
<div class="cert-page" data-orientation="' . $dataOrientation . '" style="
        ' . $bgStyle . '
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        width: ' . $pageWidth . ';
        height: ' . $pageHeight . ';
        ">
    <div style="
            width: 100%;
            height: 100%;
            padding: ' . $margin . ';
            box-sizing: border-box;
            position: relative;
            ">
        ' . $content . '
    </div>
</div>';
}

/**
 * Construye el contenido del anverso del certificado para un estudiante,
 * reemplazando todos los placeholders. Réplica de la lógica de view_certificate.php.
 */
function buildCertificateContent(
    $studentId,
    $userInfo,
    $courseInfo,
    $sessionId,
    $sessionInfo,
    $categoryId,
    $infoCertificate,
    $extraFieldsAll
): string {
    $courseCode = $courseInfo['code'];

    $allUserInfo = DocumentManager::get_all_info_to_certificate($studentId, $courseCode, false);

    $myContentHtml = $infoCertificate['front_content'];
    $myContentHtml = str_replace(chr(13) . chr(10) . chr(13) . chr(10), chr(13) . chr(10), $myContentHtml);
    $myContentHtml = str_replace($allUserInfo[0], $allUserInfo[1], $myContentHtml);

    $score = GradebookUtils::get_certificate_by_user_id($categoryId, $studentId);

    $myContentHtml = str_replace('((username))', $userInfo['username'], $myContentHtml);
    $myContentHtml = str_replace('((score_certificate))', $score['score_certificate'] ?? '', $myContentHtml);

    $simpleAverageNotCategory = EasyCertificatePlugin::getScoreForEvaluations($courseCode, $studentId, 0, $sessionId);
    $myContentHtml = str_replace('((simple_average))', $simpleAverageNotCategory, $myContentHtml);

    $simpleAverageCategory = EasyCertificatePlugin::getScoreForEvaluations($courseCode, $studentId, 1, $sessionId);
    $myContentHtml = str_replace('((simple_average_category))', $simpleAverageCategory, $myContentHtml);

    if ($extraFieldsAll) {
        foreach ($extraFieldsAll as $field) {
            $valueExtraField = EasyCertificatePlugin::getValueExtraField($field, $studentId);
            $myContentHtml = str_replace('((' . $field . '))', $valueExtraField, $myContentHtml);
        }
    }

    $myCertificate = $score;

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

    // Certificados Trabajo Alto Riesgo
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

    return $myContentHtml;
}

function getUserInfo($studentId)
{
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
