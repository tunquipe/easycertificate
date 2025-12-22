<?php
/* For licensing terms, see /license.txt */

$useDefault = false;
$isDefault = isset($_GET['default']) ? (int) $_GET['default'] : null;

if ($isDefault === 1) {
    $cidReset = true;
}

$course_plugin = 'easycertificate';
require_once __DIR__.'/../config.php';

$_setting['student_view_enabled'] = 'false';

$userId = api_get_user_id();
$plugin = EasyCertificatePlugin::create();
$nameTools = $plugin->get_lang('CertificateSetting');
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';
$accessUrlId = api_get_current_access_url_id();
$course_info = api_get_course_info();
$message = null;

if ($isDefault === 1) {
    $courseId = 0;
    $courseCode = '';
    $sessionId = 0;
    $enableCourse = false;
    $useDefault = true;
    $defaultCertificate = 1;
    $nameTools = $plugin->get_lang('CertificateSettingDefault');
    $urlParams = '?default=1';
} else {
    $courseId = api_get_course_int_id();
    $courseCode = api_get_course_id();
    $sessionId = api_get_session_id();
    $enableCourse = api_get_course_setting('easycertificate_course_enable', $course_info) == 1 ? true : false;
    $useDefault = api_get_course_setting('use_certificate_default', $course_info) == 1 ? true : false;
    $defaultCertificate = 0;
    $urlParams = '?'.api_get_cidreq();
}
/*$sessionInfo = [];
if ($sessionId > 0) {
    $sessionInfo = SessionManager::fetch($sessionId);
    var_dump($sessionInfo);
}*/
$message = null;
if (!$enable) {
    api_not_allowed(true, $plugin->get_lang('ToolDisabled'));
}

if (!$enableCourse && !$useDefault) {
    api_not_allowed(true, $plugin->get_lang('ToolDisabledCourse'));
}

if ($enableCourse && $useDefault) {
    api_not_allowed(true, $plugin->get_lang('ToolUseDefaultSettingCourse'));
}

$allow = api_is_platform_admin() || api_is_teacher();
if (!$allow) {
    api_not_allowed(true);
}
$table = Database::get_main_table(EasyCertificatePlugin::TABLE_EASYCERTIFICATE);

$htmlHeadXtra[] = api_get_css_asset('cropper/dist/cropper.min.css');
$htmlHeadXtra[] = api_get_asset('cropper/dist/cropper.min.js');
$htmlHeadXtra[] = api_get_css(
    api_get_path(WEB_PLUGIN_PATH).'easycertificate/resources/css/form.css'
);
$htmlHeadXtra[] = '<script>
    $(function () {

        //$("input[name=orientation][value=h]").prop("checked", true);

        $("#delete_certificate").click(function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (confirm("'.$plugin->get_lang("QuestionDelete").'")) {
                let courseId = '.$courseId.';
                let sessionId = '.$sessionId.';
                let accessUrlId = '.$accessUrlId.';
                let plugin_path = "'.api_get_path(WEB_PLUGIN_PATH).'";
                let ajax_path = plugin_path + "easycertificate/src/easycertificate.ajax.php?a=delete_certificate";
                $.ajax({
                    data: {courseId: courseId, sessionId: sessionId, accessUrlId: accessUrlId},
                    url: ajax_path,
                    type: "POST",
                    success: function (response) {
                        window.location.reload();
                    }
                });
            }
        });

    });
</script>';

// Get info certificate
$infoCertificate = EasyCertificatePlugin::getInfoCertificate($courseId, $sessionId, $accessUrlId);

$form = new FormValidator(
    'formEdit',
    'post',
    api_get_self().$urlParams,
    null
);

if ($form->validate()) {
    $formValues = $form->getSubmitValues();
    if (empty($formValues['front_content'])) {
        $contentsFront = '';
    } else {
        $contentFront = $formValues['front_content'];
    }

    if (empty($formValues['back_content'])) {
        $contentBack = '';
    } else {
        $contentBack = $formValues['back_content'];
    }

    $check = Security::check_token('post');
    if ($check) {
        $params = [
            'access_url_id' => api_get_current_access_url_id(),
            'c_id' => $formValues['c_id'],
            'session_id' => $formValues['session_id'],
            'front_content' => $contentFront,
            'back_content' => $contentBack,
            'orientation' => $formValues['orientation'],
            'margin_left' => (int) $formValues['margin_left'],
            'margin_right' => (int) $formValues['margin_right'],
            'margin_top' => (int) $formValues['margin_top'],
            'margin_bottom' => (int) $formValues['margin_bottom'],
            'certificate_default' => 0,
            'show_back' => (int) $formValues['show_back'],
            'date_change' => (int) $formValues['date_change']
        ];

        if (intval($formValues['default_certificate'] == 1)) {
            $params['certificate_default'] = 1;
        }

        // Insert or Update
        if ($infoCertificate['id'] > 0) {
            $certificateId = $infoCertificate['id'];
            Database::update($table, $params, ['id = ?' => $certificateId]);
        } else {
            $certificateId = Database::insert($table, $params);
        }

        // Image manager
        $fieldList = [
            'background_h',
            'background_v'
        ];

        foreach ($fieldList as $field) {
            $checkLogo[$field] = false;
            if (!empty($formValues['remove_'.$field]) || $_FILES[$field]['size']) {
                checkInstanceImage(
                    $certificateId,
                    $infoCertificate[$field],
                    $field
                );
            }

            if ($_FILES[$field]['size']) {
                $newPicture = api_upload_file(
                    'certificates',
                    $_FILES[$field],
                    $certificateId,
                    $formValues[$field.'_crop_result']
                );
                if ($newPicture) {
                    $sql = "UPDATE $table
                            SET $field = '".$newPicture['path_to_save']."'
                            WHERE id = $certificateId";
                    Database::query($sql);
                    $checkLogo[$field] = true;
                }
            }
        }

        // Certificate Default
        if (intval($formValues['use_default'] == 1)) {
            $infoCertificateDefault = EasyCertificatePlugin::getInfoCertificateDefault($accessUrlId);
            if (!empty($infoCertificateDefault)) {
                foreach ($fieldList as $field) {
                    if (!empty($infoCertificateDefault[$field]) && !$checkLogo[$field]) {
                        $sql = "UPDATE $table
                                SET $field = '".$infoCertificateDefault[$field]."'
                                WHERE id = $certificateId";
                        Database::query($sql);
                    }
                }
            }
        }

        Display::addFlash(Display::return_message(get_lang('Saved')));

        Security::clear_token();
        header('Location: '.api_get_self().$urlParams);
        exit;
    }
}

if (empty($infoCertificate)) {
    $infoCertificate = EasyCertificatePlugin::getInfoCertificateDefault($accessUrlId);
    $useDefault = true;
}

// Display the header
$tpl = new Template($nameTools,true,true,false,false,true,false);

$actionsLeft = Display::url(
    Display::return_icon('easycertificate.png', get_lang('Certificate'), '', ICON_SIZE_MEDIUM),
    'print_certificate.php'.$urlParams
);
if (!empty($courseId) && !$useDefault) {
    $actionsLeft .= Display::url(
        Display::return_icon('delete.png', $plugin->get_lang('DeleteCertificate'), '', ICON_SIZE_MEDIUM),
        'delete_certificate.php' . $urlParams,
        ['id' => 'delete_certificate']
    );
}

$actions = Display::toolbarAction(
    'toolbar-document',
    [
        $actionsLeft
    ]
);

if ($useDefault && $courseId > 0) {
    $message = Display::return_message(get_lang('InfoFromDefaultCertificate'), 'info');
}

// Student and course section
$form->addHeader($plugin->get_lang('FrontContentCertificate'));

$dir = '/';
$courseInfo = api_get_course_info();

$isAllowedToEdit = api_is_allowed_to_edit(null, true);
$isInCourse = !empty($courseInfo) && is_array($courseInfo);

// Configurar rutas según el contexto
if ($isInCourse) {
    // Estamos dentro de un curso - usar rutas reales
    $coursePath = $courseInfo['path'];
    $createDocumentDir = api_get_path(WEB_COURSE_PATH) . $coursePath . '/document/';
    $createDocumentWebDir = api_get_path(WEB_COURSE_PATH) . $coursePath . '/document/';
    $baseHref = api_get_path(WEB_COURSE_PATH) . $coursePath . '/document/' . $dir;
} else {
    // Vista previa - usar rutas vacías o valores por defecto
    $createDocumentDir = '';
    $createDocumentWebDir = '';
    $baseHref = '';
}

$editorConfigOne = [
    'ToolbarSet' => $isAllowedToEdit ? 'Documents' : 'DocumentsStudent',
    'Width' => '100%',
    'Height' => '300px',
    'cols-size' => [0, 12, 0],
    'FullPage' => true,
    'InDocument' => true,
    'CreateDocumentDir' => $createDocumentDir,
    'CreateDocumentWebDir' => $createDocumentWebDir,
    'BaseHref' => $baseHref,
];

$html = '
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active">
        <a href="#front" aria-controls="front" role="tab" data-toggle="tab">'.$plugin->get_lang('FrontContent').'</a>
    </li>
    <li role="presentation">
        <a href="#back" aria-controls="back" role="tab" data-toggle="tab">'.$plugin->get_lang('BackContent').'</a>
    </li>
  </ul>
  <div class="tab-content">
    <div role="tabpanel" class="tab-pane active" id="front">
        <div class="panel-body">
        <p>'.$plugin->get_lang('FrontContentCertificate').'</p>';
$form->addHtml($html);
$form->addHtmlEditor(
    'front_content',
    '',
    false,
    true,
    $editorConfigOne,
    true
);
$html = '
        </div>
    </div>
    <div role="tabpanel" class="tab-pane" id="back">
        <div class="panel-body">
        <p>'.$plugin->get_lang('PostContentCertificate').'</p>
';
$form->addHtml($html);
$form->addCheckBox('show_back',$plugin->get_lang('ShowBack'),$plugin->get_lang('ShowBackHelp'));
$editorConfigTwo = [
    'ToolbarSet' => $isAllowedToEdit ? 'Documents' : 'DocumentsStudent',
    'Width' => '100%',
    'Height' => '300px',
    'cols-size' => [0, 12, 0],
    'FullPage' => true,
    'InDocument' => true,
    'CreateDocumentDir' => $createDocumentDir,
    'CreateDocumentWebDir' => $createDocumentWebDir,
    'BaseHref' => $baseHref,
];
$form->addHtmlEditor(
    'back_content',
    null,
    false,
    true,
    $editorConfigTwo
);
$form->addHtml('</div></div>
  </div>');

$listTags = [
    'user_firstname',
    'user_lastname',
    'gradebook_institution',
    'gradebook_sitename',
    'teacher_firstname',
    'teacher_lastname',
    'official_code',
    'date_certificate',
    'date_certificate_no_time',
    'course_code',
    'course_title',
    'gradebook_grade',
    'external_style',
    'session_start_date',
    'session_end_date',
    'expedition_date',
    'code_certificate',
    'score_certificate',
    'simple_average_category',
    'simple_average',
    'qr-code',
    'bar_code',
    'score_number'
];

$strInfo = '<ul class="list-tags">';
foreach ($listTags as $tag){
    $strInfo.= '<li>(('.$tag.'))</li>';
}
$strInfo.='</ul>';
$createCertificate = '<strong>'.get_lang('CreateCertificateWithTags').'</strong>';
$form->addElement(
    'html',
    Display::return_message($createCertificate.': '.$strInfo, 'normal', false)
);
//ExtraField
$extraFieldsUser = EasyCertificatePlugin::getExtraFieldsUserAll(true);

if(!empty($extraFieldsUser)){
    $strField = '<ul class="list-tags">';
    foreach ($extraFieldsUser as $tag){
        $strField.= '<li>'.$tag.'</li>';
    }
    $strField.='</ul>';
    $extraFieldCertificate = '<strong>'.$plugin->get_lang('ExtraFieldUserTags').'</strong>';
    $form->addElement(
        'html',
        Display::return_message($extraFieldCertificate.': '.$strField, 'success', false)
    );
}

// Signature section
$base = api_get_path(WEB_UPLOAD_PATH);
$path = $base.'certificates/';

$form->addHeader($plugin->get_lang('OtherOptions'));

$form->addHtml('<div class="row">
            <div class="col-md-6">');
//Orientation Paper
$form->addRadio(
    'orientation',
    get_lang('ChooseOrientation'),
    [
        'h' => get_lang('Horizontal'),
        'v' => get_lang('Vertical')
    ]
);


//Change date
$form->addRadio(
    'date_change',
    $plugin->get_lang('DateSession'),
    [
        '0' => $plugin->get_lang('UseDateViewSession'),
        '1' => $plugin->get_lang('UseDateAccessSession')
    ]
);


//Margin
$form->addNumeric(
    'margin_left',
    [
        $plugin->get_lang('MarginLeft'),
        null,
        $plugin->get_lang('Centimeters')
    ],
    [
        'cols-size' => [3, 5, 4],
        'value' => 2
    ]
);
$form->addNumeric(
    'margin_right',
    [
        $plugin->get_lang('MarginRight'),
        null,
        $plugin->get_lang('Centimeters')
    ],
    [
        'cols-size' => [3, 5, 4],
        'value' => 2
    ]
);
$form->addNumeric(
    'margin_top',
    [
        $plugin->get_lang('MarginTop'),
        null,
        $plugin->get_lang('Centimeters')
    ],
    [
        'cols-size' => [3, 5, 4],
        'value' => 2
    ]
);
$form->addNumeric(
    'margin_bottom',
    [
        $plugin->get_lang('MarginBottom'),
        null,
        $plugin->get_lang('Centimeters')
    ],
    [
        'cols-size' => [3, 5, 4],
        'value' => 2
    ]
);


$form->addHtml('</div><div class="col-md-6">');

// background 297/210
$form->addFile(
    'background_h',
    [
        $plugin->get_lang('BackgroundHorizontal'),
        $plugin->get_lang('BackgroundHorizontalHelp')
    ],
    [
        'id' => 'background_h',
        'class' => 'picture-form',
        'crop_image' => true,
        'crop_ratio' => '297 / 210',
    ]
);

if (!empty($infoCertificate['background_h'])) {
    $form->addElement('checkbox', 'remove_background_h', null, $plugin->get_lang('DelImage'));
    $form->addHtml('<div class="form-group "><label class="col-sm-2">&nbsp;</label>
        <div class="col-sm-10"><img src="'.$path.$infoCertificate['background_h'].'" width="100"  /></div></div>');
}

$form->addFile(
    'background_v',
    [
        $plugin->get_lang('BackgroundVertical'),
        $plugin->get_lang('BackgroundVerticalHelp')
    ],
    [
        'id' => 'background_v',
        'class' => 'picture-form',
        'crop_image' => true,
        'crop_ratio' => '210 / 297',
    ]
);

if (!empty($infoCertificate['background_v'])) {
    $form->addElement('checkbox', 'remove_background_v', null, $plugin->get_lang('DelImage'));
    $form->addHtml('<div class="form-group "><label class="col-sm-2">&nbsp;</label>
        <div class="col-sm-10"><img src="'.$path.$infoCertificate['background_v'].'" width="100"  /></div></div>');
}

$form->addProgress();

$allowedPictureTypes = api_get_supported_image_extensions(false);

$form->addRule(
    'background_h',
    get_lang('OnlyImagesAllowed').' ('.implode(', ', $allowedPictureTypes).')',
    'filetype',
    $allowedPictureTypes
);
$form->addRule(
    'background_v',
    get_lang('OnlyImagesAllowed').' ('.implode(', ', $allowedPictureTypes).')',
    'filetype',
    $allowedPictureTypes
);


$form->addHtml('</div></div>');
$form->addButton(
    'submit',
    get_lang('SaveCertificate'),
    'check',
    'primary',
    'large',
    null,
    [
        'cols-size' => [0, 12, 0]
    ]
);

$form->addElement('hidden', 'formSent');
$infoCertificate['formSent'] = 1;
$form->setDefaults($infoCertificate);
$token = Security::get_token();
$form->addElement('hidden', 'sec_token');
$form->addElement('hidden', 'use_default');
$form->addElement('hidden', 'default_certificate');
$form->addElement('hidden', 'c_id');
$form->addElement('hidden', 'session_id');
$form->setConstants(
    [
        'sec_token' => $token,
        'use_default' => $useDefault,
        'default_certificate' => $defaultCertificate,
        'c_id' => $courseId,
        'session_id' => $sessionId,
    ]
);
$tpl->assign('actions', $actions);
$tpl->assign('message', $message);
$tpl->assign('form', $form->returnForm());
$content = $tpl->fetch('easycertificate/template/certificate_index.tpl');
$tpl->assign('content', $content);
$tpl->display_one_col_template();


/**
 * Delete the file if there is only one instance.
 *
 * @param int    $certificateId
 * @param string $imagePath
 * @param string $field
 * @param string $type
 */
function checkInstanceImage($certificateId, $imagePath, $field, $type = 'certificates')
{
    $table = Database::get_main_table(EasyCertificatePlugin::TABLE_EASYCERTIFICATE);
    $imagePath = Database::escape_string($imagePath);
    $field = Database::escape_string($field);
    $certificateId = (int) $certificateId;

    $sql = "SELECT * FROM $table WHERE $field = '$imagePath'";
    $res = Database::query($sql);
    if (Database::num_rows($res) == 1) {
        api_remove_uploaded_file($type, $imagePath);
    }

    $sql = "UPDATE $table SET $field = '' WHERE id = $certificateId";
    Database::query($sql);
}
