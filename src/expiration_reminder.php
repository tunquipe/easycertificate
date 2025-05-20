<?php

$isDefault = isset($_GET['default']) ? (int) $_GET['default'] : null;

if ($isDefault === 1) {
    $cidReset = true;
}

require_once __DIR__ . '/../config.php';
$plugin = EasyCertificatePlugin::create();
$nameTools = $plugin->get_lang('CertificateSetting');
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';
$course_info = api_get_course_info();

if ($isDefault === 1) {
    $courseId = 0;
    $courseCode = '';
    $sessionId = 0;
    $enableCourse = false;
    $useDefault = true;
    $defaultCertificate = 1;
    $nameTools = $plugin->get_lang('CertificateSettingDefault');
    $urlParams = '?default=1&demo=true';
} else {
    $courseId = api_get_course_int_id();
    $courseCode = api_get_course_id();
    $sessionId = api_get_session_id();
    $enableCourse = api_get_course_setting('easycertificate_course_enable', $course_info) == 1;
    $useDefault = api_get_course_setting('use_certificate_default', $course_info) == 1;
    $defaultCertificate = 0;
    $urlParams = '?'.api_get_cidreq();
}

if (!$enable) {
    api_not_allowed(true, $plugin->get_lang('ToolDisabled'));
}

if (!$enableCourse && !$useDefault) {
    api_not_allowed(true, $plugin->get_lang('ToolDisabledCourse'));
}

if ($enableCourse && $useDefault) {
    api_not_allowed(true, $plugin->get_lang('ToolUseDefaultSettingCourse'));
}

$message = null;
$actionsLeft = Display::url(
    Display::return_icon('back.png', null, [], ICON_SIZE_MEDIUM),
    'index.php?default=1'
);

$actions = Display::toolbarAction(
    'toolbar-document',
    [
        $actionsLeft
    ]
);

$pathFile = api_get_path(WEB_PLUGIN_PATH) . 'easycertificate/document/';
$baseHref = api_get_path(WEB_PLUGIN_PATH) . 'easycertificate/document/';

$accessUrlId = api_get_current_access_url_id();
// Get info certificate
$infoCertificate = EasyCertificatePlugin::getInfoCertificateReminder($courseId, $sessionId, $accessUrlId);

$form = new FormValidator(
    'form_expiration_reminder',
    'post',
    api_get_self() . $urlParams,
    null
);

// Student and course section
$form->addHeader('Configura tus mensajes de Expiración');

$editorConfigOne = [
    'ToolbarSet' => 'Documents',
    'Width' => '100%',
    'Height' => '300px',
    'cols-size' => [0, 12, 0],
    'FullPage' => true,
    'InDocument' => true,
    'CreateDocumentDir' => $pathFile,
    'CreateDocumentWebDir' => $pathFile,
    'BaseHref' => $baseHref,
];
$form->addHtml('<div class="row"> <h3> Recordatorio 30 días antes: </h3> </div>');
$form->addSelect(
    'lang_content_30',
    get_lang('Language'),
    [
        'spanish' => 'Español',
        'english' => 'English'
    ]
);
$form->addHtml(
    <<<EOT
<script>
    $(document).ready(function() {
        $('select[name="lang_content_30"]').change(function() {
            var selectedLang = $(this).val();
            if (selectedLang == 'spanish') {
                $('#container_es_content_30').show();
                $('#container_en_content_30').hide();
            } else {
                $('#container_es_content_30').hide();
                $('#container_en_content_30').show();
            }
        });
    });
</script>
EOT
);

// Spanish Content 30
$form->addHtml('<div class="row" id="container_es_content_30"><div class="col-md-2"></div><div class="col-md-8">');
$form->addHtmlEditor(
    'content_30',
    '',
    false,
    true,
    $editorConfigOne
);
$form->addHtml('</div><div class="col-md-2"></div></div>');

// English Content 30
$form->addHtml('<div class="row" id="container_en_content_30" style="display: none;"><div class="col-md-2"></div><div class="col-md-8">');
$form->addHtmlEditor(
    'en_content_30',
    '',
    false,
    true,
    $editorConfigOne
);
$form->addHtml('</div><div class="col-md-2"></div></div>');

$form->addHtml('<div class="row"> <h3> Recordatorio 15 días antes: </h3> </div>');
$form->addSelect(
    'lang_content_15',
    get_lang('Language'),
    [
        'spanish' => 'Español',
        'english' => 'English'
    ]
);
$form->addHtml(
    <<<EOT
<script>
    $(document).ready(function() {
        $('select[name="lang_content_15"]').change(function() {
            var selected15Lang = $(this).val();
            if (selected15Lang == 'spanish') {
                $('#container_es_content_15').show();
                $('#container_en_content_15').hide();
            } else {
                $('#container_es_content_15').hide();
                $('#container_en_content_15').show();
            }
        });
    });
</script>
EOT
);

// Spanish Content 15
$form->addHtml('<div class="row" id="container_es_content_15"><div class="col-md-2"></div><div class="col-md-8">');
$form->addHtmlEditor(
    'content_15',
    '',
    false,
    true,
    $editorConfigOne
);
$form->addHtml('</div><div class="col-md-2"></div></div>');

// English Content 15
$form->addHtml('<div class="row" id="container_en_content_15" style="display: none;"><div class="col-md-2"></div><div class="col-md-8">');
$form->addHtmlEditor(
    'en_content_15',
    '',
    false,
    true,
    $editorConfigOne
);
$form->addHtml('</div><div class="col-md-2"></div></div>');

$form->addElement('hidden', 'c_id', $courseId);
$form->addElement('hidden', 'session_id', $sessionId);

$form->addButton(
    'submit',
    get_lang('SaveMessage'),
    'check',
    'primary',
    'small',
    null,
    [
        'cols-size' => [2, 8, 2]
    ]
);

$table = Database::get_main_table(EasyCertificatePlugin::TABLE_EASYCERTIFICATE_REMINDER);

if ($form->validate()) {
    $formValues = $form->getSubmitValues();

    $params = [
        'access_url_id' => api_get_current_access_url_id(),
        'c_id' => $formValues['c_id'],
        'session_id' => $formValues['session_id'],
        'content_30' => $formValues['content_30'],
        'en_content_30' => $formValues['en_content_30'],
        'content_15' => $formValues['content_15'],
        'en_content_15' => $formValues['en_content_15'],
        'certificate_default' => $isDefault
    ];

    // Insert or Update
    if ($infoCertificate['id'] > 0) {
        $certificateId = $infoCertificate['id'];
        Database::update($table, $params, ['id = ?' => $certificateId]);
    } else {
        $certificateId = Database::insert($table, $params);
    }

    Display::addFlash(Display::return_message(get_lang('Saved')));

    header('Location: '.api_get_self().$urlParams);
    exit;
}

if (empty($infoCertificate)) {
    $infoCertificate = EasyCertificatePlugin::getInfoCertificateReminderDefault($accessUrlId);
    $useDefault = true;
}

$form->setDefaults([
    'content_30' => $infoCertificate['content_30'],
    'content_15' => $infoCertificate['content_15'],
    'en_content_30' => $infoCertificate['en_content_30'],
    'en_content_15' => $infoCertificate['en_content_15'],
    'c_id' => $courseId,
    'session_id' => $sessionId
]);

$tpl = new Template($nameTools, true, true, false, false, true, false);
$tpl->assign('actions', $actions);
$tpl->assign('message', $message);

$tpl->assign('form', $form->returnForm());
$content = $tpl->fetch('easycertificate/template/certificate_expiration_reminder.tpl');
$tpl->assign('content', $content);
$tpl->display_one_col_template();
