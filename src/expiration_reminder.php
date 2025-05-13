<?php

require_once __DIR__ . '/../config.php';

$plugin = EasyCertificatePlugin::create();
$nameTools = $plugin->get_lang('CertificateSetting');
$enable = $plugin->get('enable_plugin_easycertificate') == 'true';

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

$form = new FormValidator(
    'form_expiration_reminder',
    'post',
    api_get_self(),
    null
);

// Student and course section
$form->addHeader('Configura tu mensaje de Expiración');

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
//$form->addText('title',get_lang('Title'));
$form->addHtml('<div class="row"><div class="col-md-2"></div><div class="col-md-8">');
$form->addHtmlEditor(
    'content',
    '',
    false,
    true,
    $editorConfigOne
);
$form->addHtml('</div><div class="col-md-2"></div></div>');
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
$filepath = api_get_path(SYS_PLUGIN_PATH) . 'easycertificate/document/';
$filename = $filepath . 'expiration_reminder.html';
if ($form->validate()) {
    $values = $form->getSubmitValues();
    //save content
    if (!file_exists($filepath . $filename)) {
        $content = stripslashes($values['content']);
        file_put_contents($filename, $content);
    }
} else {
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $values = [
            'content' => $content
        ];
        try {
            $form->setDefaults($values);
        } catch (Exception $e) {
            print_r($e);
        }
    }
}

$tpl = new Template($nameTools, true, true, false, false, true, false);
$tpl->assign('actions', $actions);
$tpl->assign('message', $message);

$tpl->assign('form', $form->returnForm());
$content = $tpl->fetch('easycertificate/template/certificate_expiration_reminder.tpl');
$tpl->assign('content', $content);
$tpl->display_one_col_template();
