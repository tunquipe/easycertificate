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
    <meta name="description" content="Certificado de ' . htmlspecialchars($userName) . ' para el curso ' . htmlspecialchars($courseName) . '">
    <meta name="author" content="' . htmlspecialchars($userName) . '">
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
                /* Deshabilitar selección de texto */
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
            }

            #page-a, #page-b {
                margin: 20px auto;
                box-shadow: 0 0 10px rgba(0,0,0,0.5);
                background: white;
                display: block;
                transform-origin: top center;
                /* Protección adicional */
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                pointer-events: auto;
            }

            /* Prevenir selección de imágenes */
            img {
                user-select: none;
                -webkit-user-drag: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                pointer-events: none;
            }

            /* BOTÓN FLOTANTE DE IMPRESIÓN */
            #print-button {
                position: fixed;
                bottom: 30px;
                right: 30px;
                z-index: 9999;
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

            /* Para orientación horizontal */
            #page-a[data-orientation="h"],
            #page-b[data-orientation="h"] {
                width: 29.7cm;
                height: 21cm;
                max-width: none;
                min-width: 29.7cm;
            }

            /* Para orientación vertical */
            #page-a[data-orientation="v"],
            #page-b[data-orientation="v"] {
                width: 21cm;
                height: 29.7cm;
                max-width: none;
                min-width: 21cm;
            }

            /* ===================================
               RESPONSIVE PARA TABLETS Y MÓVILES
               Mantiene formato con scroll
               =================================== */

            /* Tablets en landscape */
            @media screen and (max-width: 1200px) {
                #page-a, #page-b {
                    margin: 15px auto;
                }
            }

            /* Tablets en portrait */
            @media screen and (max-width: 900px) {
                body {
                    padding: 15px;
                }

                #page-a, #page-b {
                    margin: 15px auto;
                }
            }

            /* Móviles - Con scroll horizontal */
            @media screen and (max-width: 768px) {
                html {
                    overflow-x: auto;
                    overflow-y: auto;
                }

                body {
                    padding: 10px;
                    overflow-x: auto;
                    overflow-y: auto;
                    min-width: min-content;
                }

                #page-a, #page-b {
                    margin: 10px auto;
                    box-shadow: 0 0 8px rgba(0,0,0,0.4);
                    /* Mantener tamaño original, permitir scroll */
                    max-width: none;
                }

                /* Mantener dimensiones originales en móvil */
                #page-a[data-orientation="h"],
                #page-b[data-orientation="h"] {
                    width: 29.7cm;
                    height: 21cm;
                    min-width: 29.7cm;
                }

                #page-a[data-orientation="v"],
                #page-b[data-orientation="v"] {
                    width: 21cm;
                    height: 29.7cm;
                    min-width: 21cm;
                }

                /* Botón en móvil - solo ícono */
                #print-button {
                    bottom: 15px;
                    right: 15px;
                    padding: 0;
                    border-radius: 50%;
                    width: 56px;
                    height: 56px;
                    justify-content: center;
                }

                #print-button span {
                    display: none;
                }
            }

            /* Móviles muy pequeños */
            @media screen and (max-width: 480px) {
                body {
                    padding: 5px;
                }

                #page-a, #page-b {
                    margin: 5px auto;
                }

                #print-button {
                    bottom: 10px;
                    right: 10px;
                    width: 50px;
                    height: 50px;
                }
            }
        }

        /* Estilos para impresión */
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

            #page-a, #page-b {
                page-break-inside: avoid;
                margin: 0;
                box-shadow: none;
                transform: none !important;
                display: block;
                background: white;
            }

            #page-a[data-orientation="h"],
            #page-b[data-orientation="h"] {
                width: 29.7cm;
                height: 21cm;
            }

            #page-a[data-orientation="v"],
            #page-b[data-orientation="v"] {
                width: 21cm;
                height: 29.7cm;
            }

            #page-a {
                page-break-after: always;
            }

            #page-b {
                page-break-before: always;
            }

            /* OCULTAR BOTÓN AL IMPRIMIR */
            #print-button {
                display: none !important;
            }
        }

        /* Estilos comunes */
        #page-a, #page-b {
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        /* Estilos de la barra de scroll personalizados (opcional) */
        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        ::-webkit-scrollbar-track {
            background: #3a3a3a;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 6px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
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

$endPage = '
<script>
    (function() {
        "use strict";

        // Detectar si es móvil
        function isMobile() {
            return window.innerWidth <= 768;
        }

        // Ajustar zoom automáticamente solo en DESKTOP
        function adjustCertificateZoom() {
            // Solo aplicar transformaciones en desktop si es necesario
            if (!isMobile() && window.matchMedia("screen").matches) {
                const pages = document.querySelectorAll("#page-a, #page-b");
                const windowWidth = window.innerWidth - 40;

                pages.forEach(page => {
                    const pageWidth = page.offsetWidth;

                    // Si el certificado es más ancho que la ventana, escalar
                    if (pageWidth > windowWidth) {
                        const scale = windowWidth / pageWidth;
                        page.style.transform = `scale(${scale})`;
                        page.style.transformOrigin = "top center";
                    } else {
                        page.style.transform = "none";
                    }
                });
            } else if (isMobile()) {
                // En móvil, NO escalar - permitir scroll
                const pages = document.querySelectorAll("#page-a, #page-b");
                pages.forEach(page => {
                    page.style.transform = "none";
                });
            }
        }

        // Ejecutar al cargar
        window.addEventListener("load", adjustCertificateZoom);

        // Al cambiar tamaño
        let resizeTimer;
        window.addEventListener("resize", function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(adjustCertificateZoom, 250);
        });

        // Restaurar para impresión
        window.addEventListener("beforeprint", () => {
            const pages = document.querySelectorAll("#page-a, #page-b");
            pages.forEach(page => {
                page.style.transform = "none";
            });
        });

        // Volver a ajustar después de imprimir
        window.addEventListener("afterprint", adjustCertificateZoom);

        // ============================================
        // PROTECCIONES DE SEGURIDAD
        // ============================================

        // 1. Deshabilitar clic derecho
        document.addEventListener("contextmenu", function(e) {
            e.preventDefault();
            return false;
        }, false);

        // 2. Deshabilitar selección de texto
        document.addEventListener("selectstart", function(e) {
            e.preventDefault();
            return false;
        }, false);

        // 3. Deshabilitar arrastrar elementos
        document.addEventListener("dragstart", function(e) {
            e.preventDefault();
            return false;
        }, false);

        // 4. Deshabilitar copiar
        document.addEventListener("copy", function(e) {
            e.preventDefault();
            return false;
        }, false);

        // 5. Deshabilitar cortar
        document.addEventListener("cut", function(e) {
            e.preventDefault();
            return false;
        }, false);

        // 6. Bloquear teclas de acceso rápido
        document.addEventListener("keydown", function(e) {
            // F12 - DevTools
            if (e.keyCode === 123) {
                e.preventDefault();
                return false;
            }

            // Ctrl+Shift+I - Inspector
            if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
                e.preventDefault();
                return false;
            }

            // Ctrl+Shift+J - Console
            if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
                e.preventDefault();
                return false;
            }

            // Ctrl+U - Ver código fuente
            if (e.ctrlKey && e.keyCode === 85) {
                e.preventDefault();
                return false;
            }

            // Ctrl+Shift+C - Selector de elementos
            if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
                e.preventDefault();
                return false;
            }

            // Ctrl+S - Guardar página
            if (e.ctrlKey && e.keyCode === 83) {
                e.preventDefault();
                return false;
            }

            // Ctrl+A - Seleccionar todo
            if (e.ctrlKey && e.keyCode === 65) {
                e.preventDefault();
                return false;
            }

            // Ctrl+C - Copiar
            if (e.ctrlKey && e.keyCode === 67 && !e.shiftKey) {
                e.preventDefault();
                return false;
            }
        }, false);

        // 7. Prevenir zoom con pellizco en móvil
        document.addEventListener("gesturestart", function(e) {
            e.preventDefault();
        });

        // 8. Prevenir zoom con doble tap en móvil
        let lastTouchEnd = 0;
        document.addEventListener("touchend", function(e) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

    })();
</script>
</body></html>';

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
