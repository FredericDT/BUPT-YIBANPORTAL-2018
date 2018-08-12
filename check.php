<?php

require "db_config.php";

$scId = '1005_0';

function encodeArr($infoArr, $path) {
    $infoJson = json_encode($infoArr);
    $privkey = file_get_contents($path . 'certification.pem');
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
    $say = encodeArr($infoArr, $path);
    $type = $isMobile ? '&type=mobile' : '';
    $hrefUrl = 'https://o.yiban.cn/uiss/check?scid=' . $scId . $type;
    return [
        'html' => "<form style='display:none;' id='run' name='run' method='post' action='{$hrefUrl}'><input name='say' type='text' value='{$say}' /></form>",
        'script' => 'document.run.submit();'
    ];
}

if (!isset($_REQUEST['name']) || !isset($_REQUEST['prc_id'])) {
    exit(json_encode(['ok' => false, 'msg' => '姓名及身份证号不能为空']));
}

$name = htmlspecialchars($_REQUEST['name']);
$prc_id = $_REQUEST['prc_id'];

if ($prc_id == '' || $name == '') {
    exit(json_encode(['ok' => false, 'msg' => '姓名及身份证号不能为空']));
}

$mysqli = new mysqli($db_host, $db_user, $db_password, $db_database);
if (!$mysqli) {
    exit(json_encode(['ok' => false, 'msg' => '数据库错误']));
}
$mysqli->query("set names 'utf8'");

$stmt = $mysqli->prepare("SELECT `school_id`,`prc_id`,`realname`,`gender`,`college`,`major`,`class` FROM `existed_info` WHERE realname=? AND prc_id=?");
$stmt->bind_param('ss', $name, $prc_id);
$stmt->execute();
$stmt->bind_result($db_school_id, $db_prc_id, $db_realname, $db_gender, $db_college, $db_major, $db_class);
$stmt->fetch();

if (!isset($db_realname) || $db_realname == '') {
    exit(json_encode(['ok' => false, 'msg' => '姓名身份证号组合有误']));
}

$yb_data = [
    'name' => $db_realname,
    'student_id' => $db_school_id,
    'status_id' => '', // 身份证号
    'enter_year' => '2018',
    'status' => '', //学生状态（0-在读、1-休学、2-离校）
    'schooling' => '',
    'education' => '',
    'role' => 0,
    'college' => $db_college,
    'sex' => '',
    'specialty' => '',
    'eclass' => $db_class,
    'native_place' => '',
    'build_time' => time(),
    ];

$stmt->close();
$mysqli->close();

exit(json_encode(array_merge(['ok' => true], run($yb_data))));