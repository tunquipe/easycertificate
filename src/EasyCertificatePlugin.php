<?php
/* For license terms, see /license.txt */
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
/**
 * Plugin class for the Easy certificate plugin.
 *
 * @package chamilo.plugin.easycertificate
 *
 * @author Alex Aragon Calixto <aragcar@gmail.com>
 */
class EasyCertificatePlugin extends Plugin
{
    const TABLE_EASYCERTIFICATE = 'plugin_easycertificate';
    const TABLE_EASYCERTIFICATE_REMINDER = 'plugin_easycertificate_reminder';
    const TABLE_EASYCERTIFICATE_SEND = 'plugin_easycertificate_send';
    const TABLE_PROIKOS_USERS = 'plugin_proikos_users';
    const TABLE_PLUGIN_EASY_CERTIFICATE_SEND = 'plugin_easycertificate_send';
    public $isCoursePlugin = true;

    // When creating a new course this settings are added to the course
    public $course_settings = [
        [
            'name' => 'easycertificate_course_enable',
            'type' => 'checkbox',
        ],
        [
            'name' => 'use_certificate_default',
            'type' => 'checkbox',
        ],
    ];

    /**
     * Constructor.
     */
    protected function __construct()
    {
        parent::__construct(
            '2.0',
            'Alex Aragón Calixto <br> Magaly Ancalle',
            [
                'enable_plugin_easycertificate' => 'boolean',
                'percentage' => 'boolean',
                'enable_plugin_congratulations' => 'boolean'

            ]
        );

        $this->isAdminPlugin = true;
    }

    /**
     * Instance the plugin.
     *
     * @staticvar null $result
     *
     * @return EasyCertificatePlugin
     */
    public static function create()
    {
        static $result = null;

        return $result ? $result : $result = new self();
    }

    /**
     * This method creates the tables required to this plugin.
     */
    public function install()
    {
        //Installing course settings
        $this->install_course_fields_in_all_courses();

        $tablesToBeCompared = [self::TABLE_EASYCERTIFICATE];
        $em = Database::getManager();
        $cn = $em->getConnection();
        $sm = $cn->getSchemaManager();
        $tables = $sm->tablesExist($tablesToBeCompared);

        if ($tables) {
            return false;
        }

        require_once api_get_path(SYS_PLUGIN_PATH).'easycertificate/database.php';

        $sql = "CREATE TABLE IF NOT EXISTS ".self::TABLE_EASYCERTIFICATE_SEND." (
            id INT unsigned NOT NULL auto_increment PRIMARY KEY,
            user_id INT,
            course_id INT,
            session_id INT NULL,
            certificate_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reminder_30_sent     TINYINT(1)    NOT NULL DEFAULT 0,
            reminder_30_sent_at  DATETIME       NULL,
            reminder_15_sent     TINYINT(1)    NOT NULL DEFAULT 0,
            reminder_15_sent_at  DATETIME       NULL
        )";
        Database::query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . self::TABLE_EASYCERTIFICATE_REMINDER . " (
          id int unsigned NOT NULL AUTO_INCREMENT,
          access_url_id int unsigned NOT NULL,
          c_id int unsigned NOT NULL,
          session_id int unsigned NOT NULL,
          content_30 longtext COLLATE utf8mb3_unicode_ci NOT NULL,
          content_15 longtext COLLATE utf8mb3_unicode_ci NOT NULL,
          certificate_default int unsigned DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        )";
        Database::query($sql);
    }

    /**
     * This method drops the plugin tables.
     */
    public function uninstall()
    {
        // Deleting course settings.
        $this->uninstall_course_fields_in_all_courses();

        $tablesToBeDeleted = [
            self::TABLE_EASYCERTIFICATE,
            self::TABLE_EASYCERTIFICATE_SEND
        ];
        foreach ($tablesToBeDeleted as $tableToBeDeleted) {
            $table = Database::get_main_table($tableToBeDeleted);
            $sql = "DROP TABLE IF EXISTS $table";
            Database::query($sql);
        }
        $this->manageTab(false);



    }

    /**
     * This method update the previous plugin tables.
     */
    public function update()
    {
        $oldCertificateTable = 'gradebook_certificate_alternative';
        $base = api_get_path(WEB_UPLOAD_PATH);
        if (Database::num_rows(Database::query("SHOW TABLES LIKE '$oldCertificateTable'")) == 1) {
            $sql = "SELECT * FROM $oldCertificateTable";
            $res = Database::query($sql);
            while ($row = Database::fetch_assoc($res)) {
                $pathOrigin = $base.'certificates/'.$row['id'].'/';
                $params = [
                    'access_url_id' => api_get_current_access_url_id(),
                    'c_id' => $row['c_id'],
                    'session_id' => $row['session_id'],
                    'front_content' => $row['front_content'],
                    'back_content' => $row['back_content'],
                    'background_h' => $row['background_h'],
                    'background_v' => $row['background_v'],
                    'orientation' => $row['orientation'],
                    'margin_left' => intval($row['margin_left']),
                    'margin_right' => intval($row['margin_right']),
                    'margin_top' => intval($row['margin_top']),
                    'margin_bottom' => intval($row['margin_bottom']),
                    'certificate_default' => 0,
                    'show_back' => intval($row['show_back']),
                    'date_change' => intval($row['date_change']),
                ];

                $certificateId = Database::insert(self::TABLE_EASYCERTIFICATE, $params);

                // Image manager
                $pathDestiny = $base.'certificates/'.$certificateId.'/';

                if (!file_exists($pathDestiny)) {
                    mkdir($pathDestiny, api_get_permissions_for_new_directories(), true);
                }

                $imgList = [
                    'background_h',
                    'background_v',
                ];
                foreach ($imgList as $value) {
                    if (!empty($row[$value])) {
                        copy(
                            $pathOrigin.$row[$value],
                            $pathDestiny.$row[$value]
                        );
                    }
                }

                if ($row['certificate_default'] == 1) {
                    $params['c_id'] = 0;
                    $params['session_id'] = 0;
                    $params['certificate_default'] = 1;
                    $certificateId = Database::insert(self::TABLE_EASYCERTIFICATE, $params);
                    $pathOrigin = $base.'certificates/default/';
                    $pathDestiny = $base.'certificates/'.$certificateId.'/';
                    foreach ($imgList as $value) {
                        if (!empty($row[$value])) {
                            copy(
                                $pathOrigin.$row[$value],
                                $pathDestiny.$row[$value]
                            );
                        }
                    }
                }
            }

            $sql = "DROP TABLE IF EXISTS $oldCertificateTable";
            Database::query($sql);

            echo get_lang('MessageUpdate');
        }
    }

    /**
     * By default new icon is invisible.
     *
     * @return bool
     */
    public function isIconVisibleByDefault()
    {
        return false;
    }

    public static function getCodeCertificate($catId, $userId){
        $userId = (int) $userId;
        $catId = (int) $catId;
        $list = [];

        $certificateTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CERTIFICATE);
        $categoryTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CATEGORY);

        $sql = "SELECT cer.id as id_certificate, CONCAT(cer.id,'-',cer.cat_id,'-',cer.user_id) as code_certificate
                FROM $certificateTable cer
                INNER JOIN $categoryTable cat
                ON (cer.cat_id = cat.id AND cer.user_id = $userId)
                WHERE cer.cat_id = $catId";

        $rs = Database::query($sql);
        if (Database::num_rows($rs) > 0) {
            $row = Database::fetch_assoc($rs);
            $list = [
                'id_certificate' => $row['id_certificate'],
                'code_certificate' => $row['code_certificate'],
                'code_certificate_md5' => md5($row['code_certificate'])
            ];
        }
        return $list;
    }
    /**
     * Get certificate data.
     *
     * @param int $id     The certificate
     * @param int $userId
     *
     * @return array
     */
    public static function getCertificateData($id, $userId)
    {
        $id = (int) $id;
        $userId = (int) $userId;

        if (empty($id) || empty($userId)) {
            return [];
        }

        $certificateTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CERTIFICATE);
        $categoryTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CATEGORY);
        $sql = "SELECT cer.user_id AS user_id, cat.session_id AS session_id, cat.course_code AS course_code , cer.cat_id AS cat_id
                FROM $certificateTable cer
                INNER JOIN $categoryTable cat
                ON (cer.cat_id = cat.id AND cer.user_id = $userId)
                WHERE cer.id = $id";

        $rs = Database::query($sql);

        if (Database::num_rows($rs) > 0) {
            $row = Database::fetch_assoc($rs);
            $courseCode = $row['course_code'];
            $sessionId = $row['session_id'];
            $userId = $row['user_id'];
            $categoryId = $row['cat_id'];

            if (api_get_course_setting('easycertificate_course_enable', $courseCode)) {
                return [
                    'course_code' => $courseCode,
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'category_id' => $categoryId
                ];
            }
        }

        return [];
    }

    /**
     * Check if it redirects.
     *
     * @param certificate $certificate
     * @param int         $certId
     * @param int         $userId
     */
    public static function redirectCheck($certificate, $certId, $userId)
    {
        $certId = (int) $certId;
        $userId = !empty($userId) ? $userId : api_get_user_id();

        if (api_get_plugin_setting('easycertificate', 'enable_plugin_easycertificate') === 'true') {
            $infoCertificate = self::getCertificateData($certId, $userId);
            //var_dump($infoCertificate);
            if (!empty($infoCertificate)) {

                if ($certificate->user_id == api_get_user_id() && !empty($certificate->certificate_data)) {
                    $certificateId = $certificate->certificate_data['id'];
                    $extraFieldValue = new ExtraFieldValue('user_certificate');
                    $value = $extraFieldValue->get_values_by_handler_and_field_variable(
                        $certificateId,
                        'downloaded_at'
                    );
                    if (empty($value)) {
                        $params = [
                            'item_id' => $certificate->certificate_data['id'],
                            'extra_downloaded_at' => api_get_utc_datetime(),
                        ];
                        $extraFieldValue->saveFieldValues($params);
                    }
                }

                $url = api_get_path(WEB_PLUGIN_PATH).'easycertificate/src/print_certificate.php'.
                    '?student_id='.$infoCertificate['user_id'].
                    '&course_code='.$infoCertificate['course_code'].
                    '&session_id='.$infoCertificate['session_id'].
                    '&cat_id='.$infoCertificate['category_id'];
                header('Location: '.$url);
                exit;
            }
        }
    }

    public static function redirectCheckFree($certificate, $certId, $userId)
    {
        $certId = (int) $certId;
        $userId = !empty($userId) ? $userId : api_get_user_id();

        if (api_get_plugin_setting('easycertificate', 'enable_plugin_easycertificate') === 'true') {
            $infoCertificate = self::getCertificateData($certId, $userId);
            //var_dump($infoCertificate);
            if (!empty($infoCertificate)) {

                if ($certificate->user_id == api_get_user_id() && !empty($certificate->certificate_data)) {
                    $certificateId = $certificate->certificate_data['id'];
                    $extraFieldValue = new ExtraFieldValue('user_certificate');
                    $value = $extraFieldValue->get_values_by_handler_and_field_variable(
                        $certificateId,
                        'downloaded_at'
                    );
                    if (empty($value)) {
                        $params = [
                            'item_id' => $certificate->certificate_data['id'],
                            'extra_downloaded_at' => api_get_utc_datetime(),
                        ];
                        $extraFieldValue->saveFieldValues($params);
                    }
                }

                $url = api_get_path(WEB_PLUGIN_PATH).'easycertificate/src/print_certificate.php'.
                    '?student_id='.$infoCertificate['user_id'].
                    '&course_code='.$infoCertificate['course_code'].
                    '&session_id='.$infoCertificate['session_id'].
                    '&cat_id='.$infoCertificate['category_id'];
                header('Location: '.$url);
                exit;
            }
        }
    }

    public static function getNumberOfDaysToExpiration($courseId, $sessionId, $accessUrlId, $user_id)
    {
        $infoCert = self::getInfoCertificate($courseId, $sessionId, $accessUrlId);
        $proikos = ProikosPlugin::create();
        $userInfoProikos = $proikos->getInfoUserProikos($user_id);
        $expirationDays = $infoCert['expiration_date_contractor'];
        if(empty($userInfoProikos)) {
            $userInfoProikos['stakeholders'] = 1;
        }
        if($userInfoProikos['stakeholders'] == 1){
            $expirationDays = $infoCert['expiration_date_petroperu'];
        }

        return [
            'expected_days' => $expirationDays,
            'date_issue_mode' => $infoCert['date_change'],
        ];

    }
    /**
     * Get certificate info.
     *
     * @param int $courseId
     * @param int $sessionId
     * @param int $accessUrlId
     *
     * @return array
     */
    public static function getInfoCertificate($courseId, $sessionId, $accessUrlId)
    {
        $courseId = (int) $courseId;
        $sessionId = (int) $sessionId;
        $accessUrlId = !empty($accessUrlId) ? (int) $accessUrlId : 1;

        $table = Database::get_main_table(self::TABLE_EASYCERTIFICATE);
        $sql = "SELECT * FROM $table
                WHERE
                    c_id = $courseId AND
                    session_id = $sessionId AND
                    access_url_id = $accessUrlId";
        $result = Database::query($sql);
        $resultArray = [];
        if (Database::num_rows($result) > 0) {
            while ($row = Database::fetch_array($result)) {
                $resultArray = [
                    'id' => $row['id'],
                    'access_url_id' => $row['access_url_id'],
                    'session_id' => $row['session_id'],
                    'c_id' => $row['c_id'],
                    'front_content' => $row['front_content'],
                    'back_content' => $row['back_content'],
                    'background_h' => $row['background_h'],
                    'background_v' => $row['background_v'],
                    'orientation' => $row['orientation'],
                    'margin_left' => $row['margin_left'],
                    'margin_right' => $row['margin_right'],
                    'margin_top' => $row['margin_top'],
                    'margin_bottom' => $row['margin_bottom'],
                    'certificate_default' => $row['certificate_default'],
                    'show_back' => $row['show_back'],
                    'date_change' => $row['date_change'],
                    'expiration_date_contractor' => $row['expiration_date_contractor'],
                    'expiration_date_petroperu' => $row['expiration_date_petroperu'],
                ];
            }
        }

        return $resultArray;
    }

    /**
     * Get default certificate info.
     *
     * @param int $accessUrlId
     *
     * @return array
     */
    public static function getInfoCertificateDefault($accessUrlId)
    {
        $accessUrlId = !empty($accessUrlId) ? (int) $accessUrlId : 1;

        $table = Database::get_main_table(self::TABLE_EASYCERTIFICATE);
        $sql = "SELECT * FROM $table
                WHERE certificate_default = 1 AND access_url_id = $accessUrlId";
        $result = Database::query($sql);
        $resultArray = [];
        if (Database::num_rows($result) > 0) {
            $resultArray = Database::fetch_array($result);
        }

        return $resultArray;
    }
    public static function getScoreForExams($userID, $courseId = 0 , $sessionId = 0){

        $plugin = ProikosPlugin::create();

        $tbl_course = Database::get_main_table(TABLE_MAIN_COURSE);
        $tbl_session_course_user = Database::get_main_table(TABLE_MAIN_SESSION_COURSE_USER);
        $tbl_user = Database::get_main_table(TABLE_MAIN_USER);
        $tbl_session = Database::get_main_table(TABLE_MAIN_SESSION);
        $tbl_session_category = Database::get_main_table(TABLE_MAIN_SESSION_CATEGORY);
        $tbl_proikos_user = Database::get_main_table(self::TABLE_PROIKOS_USERS);
        $table_plugin_easycertificate_send = Database::get_main_table(self::TABLE_PLUGIN_EASY_CERTIFICATE_SEND);
        $sql = "
            SELECT DISTINCT
                u.id,
                u.username,
                u.registration_date,
                u.email,
                u.username as DNI,
                CONCAT(u.firstname, ' ', u.lastname) as student,
                sc.id as session_category_id,
                sc.name as session_category_name,
                srcu.session_id,
                srcu.c_id,
                c.code,
                s.name as session_name,
                 ppu.name_company,
                ppu.ruc_company,
                ppu.stakeholders,
                IF(ppu.area = -1, 'No registrado', ppu.area) as area,
                IF(ppu.metadata IS NOT NULL AND ppu.metadata != '', 'true', 'false') as metadata_exists,
                COALESCE((
                        SELECT
                            CASE
                                WHEN pecs.certificate_id IS NOT NULL
                                    AND CURRENT_DATE >= DATE(pecs.created_at)
                                    AND (pecs.reminder_15_sent_at IS NULL OR CURRENT_DATE <= pecs.reminder_15_sent_at)
                                THEN '1'
                                ELSE '2'
                            END
                        FROM {$table_plugin_easycertificate_send} pecs
                        WHERE pecs.user_id = u.id
                            AND pecs.session_id = s.id
                            AND pecs.course_id = srcu.c_id
                        ORDER BY pecs.id DESC
                        LIMIT 1
                    ), '3') AS certificate_status
            FROM
                $tbl_session_course_user srcu
            INNER JOIN
                $tbl_course c ON c.id = srcu.c_id
            INNER JOIN
                $tbl_session s ON s.id = srcu.session_id
            INNER JOIN
                $tbl_session_category sc ON sc.id = s.session_category_id
            INNER JOIN
                $tbl_user u ON u.user_id = srcu.user_id
            INNER JOIN
                $tbl_proikos_user ppu ON ppu.user_id = u.id
            WHERE
                srcu.status = 0 ";

        if(!empty($userID)){
            $sql.= " AND u.id = $userID ";
        }

        if($courseId != 0){
            $sql.= " AND srcu.c_id = $courseId ";
        }

        if($sessionId != 0){
            $sql.= " AND srcu.session_id = $sessionId ";
        }

        $result = Database::query($sql);
        $average = 0;
        if (Database::num_rows($result) > 0) {
            while ($row = Database::fetch_assoc($result)) {
                $userScore = $plugin->getResultExerciseStudent($row['id'], $row['c_id'], $row['session_id']);

                $row['exams'] = $userScore;

                // Validar si los valores existen o son vacíos, y si lo son, asignar 0
                $examen_de_entrada = isset($row['exams']['examen_de_entrada']) && $row['exams']['examen_de_entrada'] !== '' ? $row['exams']['examen_de_entrada'] : 0;
                $examen_de_salida = isset($row['exams']['examen_de_salida']) && $row['exams']['examen_de_salida'] !== '' ? $row['exams']['examen_de_salida'] : 0;
                $taller = isset($row['exams']['taller']) && $row['exams']['taller'] !== '' ? $row['exams']['taller'] : 0;

                // Definir los porcentajes para cada examen
                $ponderacion_entrada = 0.10;  // 10%
                $ponderacion_salida = 0.30;   // 30%
                $ponderacion_taller = 0.60;   // 60%

                $entrance = floatval($examen_de_entrada);
                $workshop = floatval($taller);
                $exit = floatval($examen_de_salida);
                $average = ($entrance * $ponderacion_entrada) + ($workshop * $ponderacion_taller) + ($exit * $ponderacion_salida);

            }
            return $average;
        }
    }
    /**
     * @param string $codeCourse code course
     * @param int $userID userid
     * @param int $type visible
     * @param int $sessionId session id course
     * @return int
     */
    public static function getScoreForEvaluations($codeCourse, $userID, $type = 0, $sessionId = 0){
        $average = 0;
        if (empty($codeCourse) || empty($userID)) {
            return 0;
        }
        $tableGradeBookCategory = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CATEGORY);
        $tableGradeBookEvaluation = Database::get_main_table(TABLE_MAIN_GRADEBOOK_EVALUATION);
        $tableGradeBookResult = Database::get_main_table(TABLE_MAIN_GRADEBOOK_RESULT);

        $sql = "SELECT gc.id, gc.name as category, gc.session_id, gc.course_code, ge.name, gr.score, gc.visible FROM $tableGradeBookCategory gc
                INNER JOIN $tableGradeBookEvaluation ge ON gc.id = ge.category_id
                INNER JOIN $tableGradeBookResult gr ON gr.evaluation_id = ge.id
                WHERE gc.visible = $type AND gc.course_code='$codeCourse' AND gr.user_id = $userID AND gc.session_id = $sessionId";

        $result = Database::query($sql);
        $resultArray = [];

        if (Database::num_rows($result) > 0) {
            while ($row = Database::fetch_array($result)) {
                $resultArray[] = [
                    'id' => $row['id'],
                    'category' => $row['category'],
                    'name' => $row['name'],
                    'score' => $row['score']
                ];
            }
        }

        $countEvaluations = count($resultArray);
        $totalEvaluations = 0;
        foreach ($resultArray as $evaluation){
            $totalEvaluations += doubleval($evaluation['score']);
        }
        if (!empty($totalEvaluations)) {
            $average = $totalEvaluations / $countEvaluations;
        }

        return number_format($average,1);

    }
    public static function getExtraFieldsUserAll($tags = false){
        $list = null;
        $extraField = new ExtraField('user');
        $extraFieldsAll = $extraField->get_all(['filter = ?' => 1], 'option_order');
        foreach ($extraFieldsAll as $field) {
            if($tags){
                $list[] = '(('.$field['variable'].'))';
            } else {
                $list[] = $field['variable'];
            }

        }
        return $list;
    }

    /**
     * @param string $variable tag extra field
     * @param int $userId user id
     * @return mixed|null
     */
    public static function getValueExtraField($variable, $userId){
        if (empty($variable)) {
            return null;
        }
        if (empty($userId)) {
            $userId = api_get_user_id();
        }
        $extraFieldValue = new ExtraFieldValue('user');
        $valueUserExtraField = $extraFieldValue->get_values_by_handler_and_field_variable(
            $userId,
            $variable
        );
        return $valueUserExtraField['value'];
    }

    public static function getGenerateInfoCertificate($info, $codeCertificate = null, $percentage = false)
    {
        $list   = [];
        $percentageValue = "";
        if ($percentage != 'false'){
            $percentageValue = "%";
        }

        if($info === true)
        {
            $codeCertificate  = (string) $codeCertificate;
            if(!empty($codeCertificate)) {
                $certificateTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CERTIFICATE);
                $categoryTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CATEGORY);
                $sql = "SELECT cer.id as id_certificate, CONCAT(cer.id,'-',cer.cat_id,'-',cer.user_id) as code_certificate,
                    cer.score_certificate, cer.cat_id,cer.user_id,
                    DATE_FORMAT(cer.created_at, '%Y-%m-%d') as created_at,
                    DATE_FORMAT(cer.expiration_date, '%Y-%m-%d') as expiration_date,
                    cat.weight,cat.course_code, cat.session_id
                    FROM $certificateTable cer
                    INNER JOIN $categoryTable cat
                    ON (cer.cat_id = cat.id)
                    WHERE md5(CONCAT(cer.id,'-',cer.cat_id,'-',cer.user_id)) = '$codeCertificate'";
                $rs = Database::query($sql);
                if (Database::num_rows($rs) > 0) {
                    $row = Database::fetch_assoc($rs);
                    $userInfo = api_get_user_info($row['user_id'], false, false, true, true, false, true);
                    $courseInfo = api_get_course_info($row['course_code']);

                    $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
                    $codCertificate = $row['code_certificate'];
                    $imgCodeBar = '';

                    if (!empty($codCertificate)) {
                        $imgCodeBar = '<img src="data:image/png;base64,' . base64_encode($generator->getBarcode($codCertificate, $generator::TYPE_CODE_128)) . '">';
                    }

                    $scoreExams = EasyCertificatePlugin::getScoreForExams($row['user_id'], $courseInfo['real_id'] ,$row['session_id']);

                    $list   = [
                        'id_certificate' => $row['id_certificate'],
                        'studentName' => $userInfo['firstname'].' '.$userInfo['lastname'],
                        'courseName' => $courseInfo['name'],
                        'datePrint' => $row['created_at'],
                        'expiration_date' => $row['expiration_date'],
                        'scoreCertificate' => $scoreExams.' / '.$row['score_certificate'].$percentageValue,
                        'codeCertificate' => md5($row['code_certificate']),
                        'proikosCertCode' => self::getProikosCertCode($row['id_certificate']),
                        'urlBarCode' => $imgCodeBar,
                    ];
                }
            }
            return $list;
        }
    }

    public static function getGenerateInfoForUsername($info, $username = null, $percentage = false)
    {
        $list   = [];
        $percentageValue = "";
        if ($percentage != 'false'){
            $percentageValue = "%";
        }

        if($info === true)
        {
            if(!empty($username)) {
                $certificateTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CERTIFICATE);
                $categoryTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CATEGORY);
                $userTable = Database::get_main_table(TABLE_MAIN_USER);
                $sql = "
                                SELECT
                    u.lastname,
                    u.firstname,
                    u.username,
                    cer.id AS id_certificate,
                    CONCAT(cer.id,
                            '-',
                            cer.cat_id,
                            '-',
                            cer.user_id) AS code_certificate,
                    cer.score_certificate,
                    cer.cat_id,
                    cer.user_id,
                    DATE_FORMAT(cer.created_at, '%Y-%m-%d') AS created_at,
                    DATE_FORMAT(cer.expiration_date, '%Y-%m-%d') AS expiration_date,
                    cat.weight,
                    cat.course_code,
                    cat.session_id
                FROM
                    $certificateTable cer
                    INNER JOIN
                    $categoryTable cat ON (cer.cat_id = cat.id)
                    INNER JOIN
                    $userTable u ON (u.id = cer.user_id)
                WHERE
                    u.username = $username
                ";

                $rs = Database::query($sql);
                if (Database::num_rows($rs) > 0) {
                    $row = Database::fetch_assoc($rs);
                    //$userInfo = api_get_user_info($row['user_id'], false, false, true, true, false, true);
                    $courseInfo = api_get_course_info($row['course_code']);

                    $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
                    $codCertificate = $row['code_certificate'];
                    $imgCodeBar = '';

                    if (!empty($codCertificate)) {
                        $imgCodeBar = '<img src="data:image/png;base64,' . base64_encode($generator->getBarcode($codCertificate, $generator::TYPE_CODE_128)) . '">';
                    }

                    $scoreExams = EasyCertificatePlugin::getScoreForExams($row['user_id'], $courseInfo['real_id'] ,$row['session_id']);
                    $url_certificate = api_get_path(WEB_PATH).'plugin/easycertificate/search.php?type=view&c_cert='.md5($codCertificate);
                    $certificateQR = EasyCertificatePlugin::getGenerateUrlImg($row['user_id'], md5($codCertificate));
                    $urlImgQR = '<img src="data:image/png;base64,' . $certificateQR . '">';
                    $url_download = api_get_path(WEB_PATH).'certificates/index.php?action=export_view&user_id='.$row['user_id'].'&id='.$row['id_certificate'];
                    $list   = [
                        'id_certificate' => $row['id_certificate'],
                        'studentName' => $row['firstname'].' '.$row['lastname'],
                        'username' => $row['username'],
                        'courseName' => $courseInfo['name'],
                        'datePrint' => $row['created_at'],
                        'expiration_date' => $row['expiration_date'],
                        'scoreCertificate' => $scoreExams.' / '.$row['score_certificate'].$percentageValue,
                        'codeCertificate' => md5($row['code_certificate']),
                        'proikosCertCode' => self::getProikosCertCode($row['id_certificate']),
                        'urlBarCode' => $imgCodeBar,
                        'url_certificate' => $url_certificate,
                        'url_download' => $url_download,
                        'qr_code' => $urlImgQR,
                    ];
                }
            }
            return $list;
        }
    }

    public static function getProikosCertCode($certId)
    {
        return str_pad($certId, 8, '0', STR_PAD_LEFT);
    }

    public static function getGenerateUrlImg($userId, $codeCertificate){
        $userId = (int) $userId;
        $codeCertificate  = (string) $codeCertificate;
        $generarImg  = "";
        if (!empty($userId) && !empty($codeCertificate)) {
            $urlcert = api_get_path(WEB_PATH).'certificates/index.php'.
                    '?action=view&ccert='.$codeCertificate;
            $generarImg = self::generateQRImage($urlcert);

        }
        return $generarImg;
    }


    /**
     * Generates a QR code for the certificate. The QR code embeds the text given.
     *
     * @param string $text Text to be added in the QR code
     * @param string $path file path of the image
     *
     * @return bool
     */
    public static function generateQRImage($text)
    {

        if (!empty($text) ) {
            $qrCode = new QrCode($text);
            $qrCode->setSize(120);
            $qrCode->setMargin(5);
            $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::MEDIUM());
            $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
            $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
            $qrCode->setValidateResult(false);
            $image = $qrCode->writeString();
            $imageQR=base64_encode($image);

            return $imageQR;
        }

        return false;
    }

    public static function getContentCongratulations(){
        $filepath = api_get_path(SYS_PLUGIN_PATH).'easycertificate/document/';
        $filename = $filepath.'congratulations.html';
        return file_get_contents($filename);
    }

    public static function registerCertificateUserSend($values){
        if (!is_array($values)) {
            return false;
        }
        $certificateSend = Database::get_main_table(self::TABLE_EASYCERTIFICATE_SEND);

        $params = [
            'user_id' => $values['user_id'],
            'course_id' => $values['course_id'],
            'session_id' => $values['session_id'],
            'certificate_id' => $values['certificate_id'],
            'send' => $values['send']
        ];
        $id = Database::insert($certificateSend, $params);
        if ($id > 0) {
            return $id;
        }

    }

    public static function getCertificateUser($userID){
        $certificateTable = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CERTIFICATE);
        $sql = "SELECT gc.id FROM $certificateTable gc WHERE gc.user_id = $userID";
        $rs = Database::query($sql);
        if (Database::num_rows($rs) > 0) {
            $row = Database::fetch_assoc($rs);
            return $row['id'];
        }
    }

    public static function getSendCertificate($idUser, $idCourse, $idSession){
        $table = Database::get_main_table(self::TABLE_EASYCERTIFICATE_SEND);
        $sql = "SELECT pes.id FROM $table pes WHERE pes.user_id = $idUser AND pes.course_id = $idCourse AND pes.session_id = $idSession";
        $rs = Database::query($sql);
        if (Database::num_rows($rs) > 0) {
            $row = Database::fetch_assoc($rs);
            if(empty($row)){
                return false;
            } else {
                return true;
            }
        }
    }

    public function expirationReminderContent()
    {
        $filepath = api_get_path(SYS_PLUGIN_PATH) . 'easycertificate/document/';
        $filename = $filepath . 'expiration_reminder.html';

        return file_get_contents($filename);
    }

    public function sendExpirationReminder()
    {
        // Tablas principales (usar constantes o definir si no existen)
        $tblSend = Database::get_main_table(self::TABLE_EASYCERTIFICATE_SEND);
        $tblCert = Database::get_main_table(self::TABLE_EASYCERTIFICATE);
        $tblCourse = Database::get_main_table(TABLE_MAIN_COURSE);
        $tblGradebookCategory = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CATEGORY);
        $tblGradebookCertificate = Database::get_main_table(TABLE_MAIN_GRADEBOOK_CERTIFICATE);
        $tblUser = Database::get_main_table(TABLE_MAIN_USER);

        // Construimos dos bloques: uno para 30 días antes, otro para 15.
        $intervals = [
            ['days' => 30, 'flag' => 'reminder_30_sent', 'flag_at' => 'reminder_30_sent_at', 'template' => 'content_30'],
            ['days' => 15, 'flag' => 'reminder_15_sent', 'flag_at' => 'reminder_15_sent_at', 'template' => 'content_15'],
        ];

        foreach ($intervals as $iv) {
            list($days, $flag, $flagAt, $template) = [$iv['days'], $iv['flag'], $iv['flag_at'], $iv['template']];

            // 1) Seleccionamos envíos que aún no tengan este recordatorio
            $sql = "
            SELECT
                s.id           AS send_id,
                s.user_id      AS user_id,
                u.email        AS email,
                ge.id          AS certificate_id,
                s.created_at   AS issued_at,
                u.firstname    AS firstname,
                u.lastname     AS lastname,
                COALESCE(c1.expiration_date, c2.expiration_date) AS expiration_date,
                s.course_id,
                s.session_id
            FROM {$tblSend} AS s

            -- Intentamos hacer match con certificado real
            LEFT JOIN {$tblCert} AS c1
                ON s.course_id = c1.c_id AND s.session_id = c1.session_id

            -- Si no hay match, usamos el certificado por defecto
            LEFT JOIN {$tblCert} AS c2
                ON c1.id IS NULL AND c2.c_id = 0 AND c2.session_id = 0

            INNER JOIN {$tblCourse} AS co ON co.id = s.course_id
            INNER JOIN {$tblGradebookCategory} AS gc ON gc.course_code = co.code AND gc.session_id = s.session_id
            INNER JOIN {$tblGradebookCertificate} AS ge ON ge.cat_id = gc.id AND ge.id = s.certificate_id AND ge.user_id = s.user_id
            INNER JOIN {$tblUser} AS u ON u.user_id = s.user_id

            WHERE
                COALESCE(c1.expiration_date, c2.expiration_date) IS NOT NULL
                AND NOW() >= DATE_SUB(
                    DATE_ADD(s.created_at, INTERVAL COALESCE(c1.expiration_date, c2.expiration_date) DAY),
                    INTERVAL {$days} DAY
                )
                /* No lo hemos enviado todavía */
                AND s.{$flag} = 0
        ";

            $res = Database::query($sql);
            if (Database::num_rows($res) === 0) {
                continue;
            }

            // 2) Iteramos y enviamos
            while ($row = Database::fetch_array($res, 'ASSOC')) {
                $sendId   = (int) $row['send_id'];
                $userId   = (int) $row['user_id'];
                $email    = $row['email'];
                $certId   = (int) $row['certificate_id'];
                $firstname= $row['firstname'];
                $lastname = $row['lastname'];
                $userInfo = api_get_user_info($row['user_id'], false, false, true, true, false, true);
                $langPrefix = $userInfo['language'] === 'english' ? 'en_' : '';

                // Personaliza asunto según días
                $courseInfo = api_get_course_info_by_id($row['course_id']);
                $content = self::getInfoCertificateReminder($row['course_id'], $row['session_id'], api_get_current_access_url_id());

                if (empty($content)) {
                    $content = self::getInfoCertificateReminderDefault(api_get_current_access_url_id());
                }

                $content = str_replace(
                    ['((nombre_usuario))', '((nombre_curso))'],
                    ["{$firstname} {$lastname}", $courseInfo['name']],
                    $content[$langPrefix . $template] ?? ''
                );

                $certIdSubject = self::getProikosCertCode($certId);
                $subject = "Recordatorio: tu certificado PROIKOS-{$certIdSubject} vence en {$days} días";
                api_mail_html(
                    "{$firstname} {$lastname}",
                    $email,
                    $subject,
                    $content
                );

                // 3) Marcamos este recordatorio como enviado
                $updateSql = "
                UPDATE {$tblSend}
                   SET {$flag}    = 1,
                       {$flagAt} = NOW()
                 WHERE id = {$sendId}
            ";
                Database::query($updateSql);
            }
        }
    }

    public static function getInfoCertificateReminder($courseId, $sessionId, $accessUrlId)
    {
        $courseId = (int) $courseId;
        $sessionId = (int) $sessionId;
        $accessUrlId = !empty($accessUrlId) ? (int) $accessUrlId : 1;

        $table = Database::get_main_table(self::TABLE_EASYCERTIFICATE_REMINDER);
        $sql = "SELECT * FROM $table
                WHERE
                    c_id = $courseId AND
                    session_id = $sessionId AND
                    access_url_id = $accessUrlId";
        $result = Database::query($sql);
        $resultArray = [];
        if (Database::num_rows($result) > 0) {
            while ($row = Database::fetch_array($result)) {
                $resultArray = [
                    'id' => $row['id'],
                    'access_url_id' => $row['access_url_id'],
                    'session_id' => $row['session_id'],
                    'c_id' => $row['c_id'],
                    'content_30' => $row['content_30'],
                    'content_15' => $row['content_15'],
                    'en_content_30' => $row['en_content_30'],
                    'en_content_15' => $row['en_content_15']
                ];
            }
        }

        return $resultArray;
    }

    public static function getInfoCertificateReminderDefault($accessUrlId)
    {
        $accessUrlId = !empty($accessUrlId) ? (int) $accessUrlId : 1;

        $table = Database::get_main_table(self::TABLE_EASYCERTIFICATE_REMINDER);
        $sql = "SELECT * FROM $table
                WHERE certificate_default = 1 AND access_url_id = $accessUrlId";
        $result = Database::query($sql);
        $resultArray = [];
        if (Database::num_rows($result) > 0) {
            $resultArray = Database::fetch_array($result);
        }

        return $resultArray;
    }
}
