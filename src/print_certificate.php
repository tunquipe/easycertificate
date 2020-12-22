<?php
/* For licensing terms, see /license.txt */

use Chamilo\CourseBundle\Entity\CLpCategory;

$default = isset($_GET['default']) ? (int)$_GET['default'] : null;
$type = isset($_GET['type']) ? (int)$_GET['type'] : null;


if ($default === 1) {
    $cidReset = true;
}

$course_plugin = 'easycertificate';
require_once __DIR__ . '/../config.php';

api_block_anonymous_users();
$plugin = EasyCertificatePlugin::create();
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';
$tblProperty = Database::get_course_table(TABLE_ITEM_PROPERTY);
$content =[];

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
if (empty($_GET['export_all'])) {
    if (!isset($_GET['student_id'])) {
        $studentId = api_get_user_id();
    } else {
        $studentId = intval($_GET['student_id']);
    }
    $userList[] = api_get_user_info($studentId);
} else {
    $certificateTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CERTIFICATE);
    $categoryTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CATEGORY);
    $sql = "SELECT cer.user_id AS user_id
            FROM $certificateTable cer
            INNER JOIN $categoryTable cat
            ON (cer.cat_id = cat.id)
            WHERE cat.course_code = '$courseCode' AND cat.session_id = $sessionId";
    $rs = Database::query($sql);
    while ($row = Database::fetch_assoc($rs)) {
        $userList[] = api_get_user_info($row['user_id']);
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

$workSpace = intval(297 - $infoCertificate['margin_left'] - $infoCertificate['margin_right']);
$widthCell = intval($workSpace / 6);
$htmlList = [];

$currentLocalTime = api_get_local_time();

foreach ($userList as $userInfo) {
    $htmlText = null;

    $linkCertificateCSS = '
    <link rel="stylesheet"
        type="text/css"
        href="' . api_get_path(WEB_PLUGIN_PATH) . 'easycertificate/resources/css/certificate.css">';
    $linkCertificateCSS .= '
    <link rel="stylesheet"
        type="text/css"
        href="' . api_get_path(WEB_CSS_PATH) . 'document.css">';

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
        '((score_certificate))',
        $score['score_certificate'],
        $myContentHtml
    );

    //simple average with category
    $simpleAverageNotCategory = EasyCertificatePlugin::getScoreForEvaluations($courseInfo['code'], $studentId, 0);

    $myContentHtml = str_replace(
        '((simple_average))',
        $simpleAverageNotCategory,
        $myContentHtml
    );

    //simple average with category
    $simpleAverageCategory = EasyCertificatePlugin::getScoreForEvaluations($courseInfo['code'], $studentId, 1);

    $myContentHtml = str_replace(
        '((simple_average_category))',
        $simpleAverageCategory,
        $myContentHtml
    );

    //ExtraField
    $extraFieldsAll = EasyCertificatePlugin::getExtraFieldsUserAll(false);
    foreach ($extraFieldsAll as $field) {
        $valueExtraField = EasyCertificatePlugin::getValueExtraField($field, $studentId);
        $myContentHtml = str_replace(
            '(('.$field.'))',
            $valueExtraField,
            $myContentHtml
        );
    }

    //Session Date.
    $startDate = null;
    $endDate = null;
    if ($sessionId > 0) {
        switch ($infoCertificate['date_change']) {
            case 0:
                if (!empty($sessionInfo['display_start_date'])) {
                    $startDate = strtotime(api_get_local_time($sessionInfo['display_start_date']));
                    $startDate = api_format_date($startDate, DATE_FORMAT_LONG_NO_DAY);
                }
                if (!empty($sessionInfo['display_end_date'])) {
                    $endDate = strtotime(api_get_local_time($sessionInfo['display_end_date']));
                    $endDate = api_format_date($endDate, DATE_FORMAT_LONG_NO_DAY);
                }
                break;
            case 1:
                if (!empty($sessionInfo['access_start_date'])) {
                    $startDate = strtotime(api_get_local_time($sessionInfo['access_start_date']));
                    $startDate = api_format_date($startDate, DATE_FORMAT_LONG_NO_DAY);
                }
                if (!empty($sessionInfo['access_end_date'])) {
                    $endDate = strtotime(api_get_local_time($sessionInfo['access_end_date']));
                    $endDate = api_format_date($endDate, DATE_FORMAT_LONG_NO_DAY);
                }
                break;
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
    //Get Category GradeBook
    $myCertificate = GradebookUtils::get_certificate_by_user_id(
        $catId,
        $studentId
    );
    if (!empty($myCertificate['created_at'])) {
        $createdAt = strtotime(api_get_local_time($myCertificate['created_at']));
        $createdAt = api_format_date($createdAt, DATE_FORMAT_LONG_NO_DAY);
    }
    $myContentHtml = str_replace(
        '((expedition_date))',
        $createdAt,
        $myContentHtml
    );

    $codeCertificate = EasyCertificatePlugin::getCodeCertificate($catId,$studentId);
    $myContentHtml = str_replace(
        '((code_certificate))',
        strtoupper($codeCertificate['code_certificate_md5']),
        $myContentHtml
    );

    $certificateQR = EasyCertificatePlugin::getGenerateUrlImg($studentId, $catId, $codeCertificate['code_certificate_md5']);
    $myContentHtml = str_replace(
        '((qr-code))',
        '<img src="data:image/png;base64,'.$certificateQR.'">'
        ,
        $myContentHtml
    );

    $myContentHtml = strip_tags(
        $myContentHtml,
        '<p><b><strong><table><tr><td><th><tbody><span><i><li><ol><ul>
        <dd><dt><dl><br><hr><img><a><div><h1><h2><h3><h4><h5><h6>'
    );

    $orientation = $infoCertificate['orientation'];
    $format = 'A4-L';
    $pageOrientation = 'L';
    if($orientation != 'h'){
        $format = 'A4';
        $pageOrientation = 'P';
    }

    $marginLeft = ($infoCertificate['margin_left'] > 0) ? $infoCertificate['margin_left'].'cm' : 0;
    $marginRight = ($infoCertificate['margin_right'] > 0) ? $infoCertificate['margin_right'].'cm' : 0;
    $marginTop = ($infoCertificate['margin_top'] > 0) ? $infoCertificate['margin_top'].'cm' : 0;
    $marginBottom = ($infoCertificate['margin_bottom'] > 0) ? $infoCertificate['margin_bottom'].'cm' : 0;
    $margin = $marginTop.' '.$marginRight.' '.$marginBottom.' '.$marginLeft;

    $templateName = $plugin->get_lang('ExportCertificate');
    $template = new Template($templateName);
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
    $htmlText .= $content;

    $fileName = 'certificate_' . $courseInfo['code'] . '_' . $userInfo['complete_name'] . '_' . $currentLocalTime;
    $htmlList[$fileName] = $htmlText;
}

$fileList = [];
$archivePath = api_get_path(SYS_ARCHIVE_PATH) . 'certificates/';
if (!is_dir($archivePath)) {
    mkdir($archivePath, api_get_permissions_for_new_directories());
}

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
    if (count($htmlList) == 1) {
        $pdf->content_to_pdf($content, '', $fileName, null, 'D', false, null, false, false, false);
        exit;
    } else {
        $filePath = $archivePath . $fileName . '.pdf';
        $pdf->content_to_pdf($content, '', $fileName, null, 'F', true, $filePath, false, false, false);
        $fileList[] = $filePath;
    }
}


if (!empty($fileList)) {
    $zipFile = $archivePath . 'certificates_' . api_get_unique_id() . '.zip';
    $zipFolder = new PclZip($zipFile);
    foreach ($fileList as $file) {
        $zipFolder->add($file, PCLZIP_OPT_REMOVE_ALL_PATH);
    }
    $name = 'certificates_' . $courseInfo['code'] . '_' . $currentLocalTime . '.zip';
    DocumentManager::file_send_for_download($zipFile, true, $name);
    exit;
}

function getIndexFiltered($index)
{
    $txt = strip_tags($index, "<b><strong><i>");
    $txt = str_replace(chr(13) . chr(10) . chr(13) . chr(10), chr(13) . chr(10), $txt);
    $lines = explode(chr(13) . chr(10), $txt);
    $text1 = '';
    for ($x = 0; $x < 47; $x++) {
        if (isset($lines[$x])) {
            $text1 .= $lines[$x] . chr(13) . chr(10);
        }
    }

    $text2 = '';
    for ($x = 47; $x < 94; $x++) {
        if (isset($lines[$x])) {
            $text2 .= $lines[$x] . chr(13) . chr(10);
        }
    }

    $showLeft = str_replace(chr(13) . chr(10), "<br/>", $text1);
    $showRight = str_replace(chr(13) . chr(10), "<br/>", $text2);
    $result = '<table width="100%">';
    $result .= '<tr>';
    $result .= '<td style="width:50%;vertical-align:top;padding-left:15px; font-size:12px;">' . $showLeft . '</td>';
    $result .= '<td style="vertical-align:top; font-size:12px;">' . $showRight . '</td>';
    $result .= '<tr>';
    $result .= '</table>';

    return $result;
}



