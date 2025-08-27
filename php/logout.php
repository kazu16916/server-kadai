<?php
session_start();

// 認証情報だけ削除
unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);

// IPシミュレーションや trusted_ip は消さない
session_write_close();

// ログインページへリダイレクト
header('Location: login.php?noauto=1&success=' . urlencode('ログアウトしました。'));
exit;
