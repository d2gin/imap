# RoundCube邮件
```php
<?php
$inbox = new \icy8\imap\Inbox();
$inbox->config([
    'server'   => "admin.com",// 邮箱域名
    'user'     => '123@admin.com', // 邮箱地址
    'password' => '123456', //邮箱密码
    'port'     => 143, // imap端口
])->open();
$inbox->fetchAll();// 获取所有
$inbox->limit(10)->fetchAll();// 获取10条
$inbox->fetch();// 获取最新一条
$inbox->find(1);// 指定获取某一条
```
