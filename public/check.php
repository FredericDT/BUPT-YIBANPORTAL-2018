<?php

require "../ldap_config.php";

/*
 * @param certPath
 *
 * path to the certification which should be delivered by the yiban company
 * should be ended with a path separator, in unix system which is "/"
 *
 * YOU ARE NOT ENCOURAGED TO PLACE THE CERTIFICATION BELOW THE "public" FOLDER
 * OR ANYWHERE CAN BE ACCESS DANGEROUSLY
 *
 */
const certPath = "../";

/*
 * @param logFile
 *
 * file path of the log
 *
 */
const logFile = '/var/log/yibanportal.log';

// a variable containing localize messages, using those messages via their key
$messages = [
    'en' => [
        'username_password_not_null' => 'Username and password can not be empty.',
        'database_error' => 'Database error.',
        'username_password_pair_not_exist' => 'Your input is wrong. Please check.'
    ],
    'zhs' => [
        'username_password_not_null' => '学号密码不能为空',
        'database_error' => '数据库错误',
        'username_password_pair_not_exist' => '学号密码组合有误'
    ],
    'zht' => [
        'username_password_not_null' => '學號密碼不能為空',
        'database_error' => '數據庫錯誤',
        'username_password_pair_not_exist' => '學號密碼組合有誤'
    ],
    'ja' => [
        'username_password_not_null' => '入力は空白にすることはできません。',
        'database_error' => 'データベースエラー',
        'username_password_pair_not_exist' => '入力した情報が間違っています。確認してください'
    ]
];

$l = isset($_POST['l']) ? $_POST['l'] : 'en';
$m = $messages[$l];

// the following two functions are derived from the UIS sdk, thx yiban
function encodeArr($infoArr) {
    $infoJson = json_encode($infoArr);
    $privkey = file_get_contents(certPath . 'certification.pem');
    $pack = "";
    foreach (str_split($infoJson, 245) as $str) {
        $crypted = "";
        openssl_private_encrypt($str, $crypted, $privkey);
        $pack .= $crypted;
    }
    $pack = base64_encode($pack);
    $pack = strtr(rtrim($pack, '='), '+/', '-_');
    return $pack;
}

function run($infoArr, $path = '', $isMobile = false) {
    $say = encodeArr($infoArr);
    $type = $isMobile ? '&type=mobile' : '';
    $hrefUrl = 'https://o.yiban.cn/uiss/check?scid=' . scId . $type;
    return ['html' => "<form style='display:none;' id='run' name='run' method='post' action='{$hrefUrl}'><input name='say' type='text' value='{$say}' /></form>", 'script' => 'document.run.submit();'];
}

function get_user_info_from_oa($id, $type) {
    global $oa_prefix;
    if ($type) {
        $url = $oa_prefix.'/api/v1/teacher-info?teacher_id=' . $id;
    } else {
        $url = $oa_prefix.'/api/v1/student-info?stu_id=' . $id;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 360);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    $data = json_decode($data, true);
    curl_close($ch);
    return $data;
}

function check_username_password_pair($username, $password) {
    global $ldap_host, $ldap_port, $ldap_admin_user, $ldap_admin_pass;
    $connection = @ldap_connect($ldap_host, $ldap_port);
    $v = @ldap_bind($connection, 'uid=' . $ldap_admin_user . ',ou=Manager,dc=bupt,dc=edu,dc=cn', $ldap_admin_pass);
    $v = ldap_search($connection, 'ou=People,dc=bupt,dc=edu,dc=cn', "uid=$username");
    $e = ldap_get_entries($connection, $v);
    $dn = $e[0]['dn'];
    $ldap_result = @ldap_bind($connection, $dn, $password);
    return $ldap_result != null;
}

const LOG_LEVEL_INFO = "INFO";
const LOG_LEVEL_WARNING = "WARNING";
const LOG_LEVEL_SEVERE = "SEVERE";
function yibanportal_log($level, $msg) {
    $handle = fopen(logFile, 'a') or fopen('yibanportal.log', 'a');
    fwrite($handle, '[ ' . $level . ' ' . date('YmdHis') . ' ] ' . $msg . "\n");
    fclose($handle);
}

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    exit(json_encode(['ok' => false, 'msg' => $m['username_password_not_null']]));
}

$username = trim(htmlspecialchars($_POST['username']));
$password = trim($_POST['password']);
$usertype = trim($_POST['usertype']);

if ($password == '' || $username == '') {
    exit(json_encode(['ok' => false, 'msg' => $m['username_password_not_null']]));
}

$ldap_status = check_username_password_pair($username, $password);

if (!$ldap_status) {
    yibanportal_log(LOG_LEVEL_WARNING, $username . ' tried to login but failed');
    exit(json_encode(['ok' => false, 'msg' => $m['username_password_pair_not_exist']]));
}

$user_info = get_user_info_from_oa($username, $usertype);

if (!$user_info['status']) {
    yibanportal_log(LOG_LEVEL_WARNING, $username . ' tried to login but failed');
    exit(json_encode(['ok' => false, 'msg' => $m['username_password_pair_not_exist']]));
}

$db_realname = $usertype > 0 ? $user_info['teacher']['name'] : $user_info['student']['name'];
$yb_data = ['name' => $db_realname, 'build_time' => time(), 'role' => $usertype];

if ($usertype > 0) {
    $yb_data['teacher_id'] = $user_info['teacher']['teacher_id'];
} else {
    $yb_data['student_id'] = $user_info['student']['student_id'];
    $yb_data['enter_year'] = substr($user_info['student']['student_id'],0,4);
    $yb_data['college'] = $user_info['student']['college'];
}

yibanportal_log(LOG_LEVEL_INFO, $username . ' logged in successfully');
exit(json_encode(array_merge(['ok' => true], run($yb_data))));