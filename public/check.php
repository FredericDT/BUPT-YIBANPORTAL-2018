<?php

require "../db_config.php";

$scId = '1005_0';
$enterYear = '2018';
$certPath = "../";

$messages = [
    'en' => [
        'name_prc_id_not_null' => 'Name and prc_id can not be empty.',
        'database_error' => 'Database error.',
        'name_prc_id_pair_not_exist' => 'Your input is wrong. Please check.'
    ],
    'zhs' => [
        'name_prc_id_not_null' => '姓名及身份证号不能为空',
        'database_error' => '数据库错误',
        'name_prc_id_pair_not_exist' => '姓名身份证号组合有误'
    ],
    'zht' => [
        'name_prc_id_not_null' => '姓名及身份證號不能為空',
        'database_error' => '數據庫錯誤',
        'name_prc_id_pair_not_exist' => '姓名身份證號組合有誤'
    ],
    'ja' => [
        'name_prc_id_not_null' => '入力は空白にすることはできません。',
        'database_error' => 'データベースエラー',
        'name_prc_id_pair_not_exist' => '入力した情報が間違っています。確認してください'
    ]
];

$l = isset($_REQUEST['l']) ? $_REQUEST['l'] : 'en';
$m = $messages[$l];

function encodeArr($infoArr) {
    global $certPath;
    $infoJson = json_encode($infoArr);
    $privkey = file_get_contents($certPath . 'certification.pem');
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
    global $scId;
    $say = encodeArr($infoArr);
    $type = $isMobile ? '&type=mobile' : '';
    $hrefUrl = 'https://o.yiban.cn/uiss/check?scid=' . $scId . $type;
    return [
        'html' => "<form style='display:none;' id='run' name='run' method='post' action='{$hrefUrl}'><input name='say' type='text' value='{$say}' /></form>",
        'script' => 'document.run.submit();'
    ];
}

if (!isset($_REQUEST['name']) || !isset($_REQUEST['prc_id'])) {
    exit(json_encode(['ok' => false, 'msg' => $m['name_prc_id_not_null']]));
}

$name = trim(htmlspecialchars($_REQUEST['name']));
$prc_id = trim($_REQUEST['prc_id']);

if ($prc_id == '' || $name == '') {
    exit(json_encode(['ok' => false, 'msg' => $m['name_prc_id_not_null']]));
}

$mysqli = new mysqli($db_host, $db_user, $db_password, $db_database);
if (!$mysqli) {
    exit(json_encode(['ok' => false, 'msg' => $m['database_error']]));
}
$mysqli->query("set names 'utf8'");

$stmt = $mysqli->prepare("SELECT `school_id`,`prc_id`,`realname`,`gender`,`college`,`major`,`class` FROM `existed_info` WHERE realname=? AND prc_id=?");
$stmt->bind_param('ss', $name, $prc_id);
$stmt->execute();
$stmt->bind_result($db_school_id, $db_prc_id, $db_realname, $db_gender, $db_college, $db_major, $db_class);
$stmt->fetch();

$stmt->close();
$mysqli->close();

if (!isset($db_realname) || $db_realname == '') {
    exit(json_encode(['ok' => false, 'msg' => $m['name_prc_id_pair_not_exist']]));
}

$yb_data = [
    'name' => $db_realname,
    'student_id' => $db_school_id,
    'status_id' => '', // 身份证号
    'enter_year' => $enterYear,
    'status' => '', //学生状态（0-在读、1-休学、2-离校）
    'schooling' => '',
    'education' => '',
    'role' => 0, // student
    'college' => $db_college,
    'sex' => '',
    'specialty' => '',
    'eclass' => $db_class,
    'native_place' => '',
    'build_time' => time(),
    ];

exit(json_encode(array_merge(['ok' => true], run($yb_data))));