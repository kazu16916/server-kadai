<?php
// 【脆弱なコード】
// 本番環境では決して有効にしてはいけない、詳細なエラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// セッションが開始されていなければ開始する
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
