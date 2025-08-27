<?php
// driveby_payload.php
require 'db.php';

// ランダム文字列生成
function rand_hex($len=8){return substr(bin2hex(random_bytes($len)),0,$len);}

// ファイル名など
$buildId = strtoupper(rand_hex(6));
$zipName = "driveby.zip";

// 仮のファイルを準備
$exeName="attack.exe";
$exeBody="This is harmless simulation.\n";

// ZIPを作成
$tmpZip=tempnam(sys_get_temp_dir(),'drvzip_');
$zip=new ZipArchive();
$zip->open($tmpZip,ZipArchive::OVERWRITE);
$zip->addFromString($exeName,$exeBody);
$zip->close();

// IDSログに記録（演習用）
try {
  $st=$pdo->prepare("INSERT INTO attack_logs(ip_address,attack_type,malicious_input,request_uri,user_agent,status_code,source_type)
                     VALUES(?,?,?,?,?,?,?)");
  $st->execute([
    $_SERVER['REMOTE_ADDR']??'',
    'Drive-by Download Payload',
    "archive={$zipName}",
    $_SERVER['REQUEST_URI']??'',
    $_SERVER['HTTP_USER_AGENT']??'',
    200,'Public'
  ]);
}catch(Throwable $e){}

// ヘッダー送信
header('Content-Type: application/zip');
header('Content-Length: '.filesize($tmpZip));
header('Content-Disposition: attachment; filename="'.$zipName.'"');
readfile($tmpZip);
unlink($tmpZip);
