<?php

$remote = 'http://h.gitlab.fujinapp.cn/zpphp/spay.git';
$refSpec = 'master';

if (isPost()) {

    $refSpec = trim($_POST['ref_spec']) ?: $refSpec;

    $username = trim($_POST['username']);
    if ($username) {
        $password = $_POST['password'];
        $userInfo = "{$username}:{$password}@";

        // 插入用户信息
        $repository = substr_replace($remote, $userInfo, strpos($remote, '//') + 2, 0);
    } else {
        $repository = $remote;
    }

    exec("git pull {$repository} {$refSpec} 2>&1", $output, $return);

    if ($return !== 0) {
        // 操作失败
    }
    $result = implode("\n", $output);
}

echo <<<HTML
<!DOCTYPE html>
<html lang="zh-cmn-Hans">
<head>
    <meta charset="UTF-8">
    <title></title>
</head>
<body>
    <form action="" method="post">
        <label>远端:
            <input type="text" name="remote" value="{$remote}" readonly>
        </label>
        <br/>
        <label>引用:
            <input type="text" name="ref_spec" value="{$refSpec}" placeholder="{$refSpec}">
        </label>
        <br/>
        <br/>
        <label>用户名:
            <input type="text" name="username" value="{$username}" autofocus>
        </label>
        <br/>
        <label>密码:
            <input type="password" name="password">
        </label>
        <br/>
        <input type="submit" value="git pull">
    </form>
    <pre>
        {$result}
    </pre>
</body>
</html>
HTML;

function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}
