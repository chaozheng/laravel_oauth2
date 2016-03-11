# Laravel OAuth2 Server
=================

1. 根据 [oauth2-server-php] https://github.com/bshaffer/oauth2-server-php 改写,其核心代码未做任何改动;
2. storage 部分使用 laravel ORM 代替;
3. database 部分增加和调整部分字段已适应laravel,sql文件database/migrations/oauth2.sql;
4. 应用部署完成后请执行 composer dump

### Authorization Code
    ex: http://localhost/authorize?response_type=code&client_id=testclient&state=xyz
    回调地址需自行在数据库中设置

### Access Token
    ex: curl -u testclient:testpass http://localhost/token -d 'grant_type=authorization_code&code=853aa0728362'
    不明白可参考 http://bshaffer.github.io/oauth2-server-php-docs/cookbook/
