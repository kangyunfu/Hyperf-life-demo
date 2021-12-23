# Hyperf-life-demo
===============
> hyperf: 2.1 
---
> 文档地址 : https://hyperf.wiki/2.2/#/zh-cn/quick-start/install
---

## 环境要求
    > PHP >= 7.4
    > Swoole PHP 扩展 >= 4.5，并关闭了 Short Name
    > Mysql >= 7.5
    > JSON PHP 扩展
    > Pcntl PHP 扩展
    > OpenSSL PHP 扩展（如需要使用到 HTTPS）
    > PDO PHP 扩展 （如需要使用到 MySQL 客户端）
    > Redis PHP 扩展 （如需要使用到 Redis 客户端）
    > Protobuf PHP 扩展 （如需要使用到 gRPC 服务端或客户端）

## 运行须知
    > 复制根目录中 .example.env 为 .env
    > composer install
    > 正常启动：php bin/hyperf.php start
    > 热更新启动：php bin/hyperf.php server:watch

    
## 分支说明
    ### 测试分支
    > test  测试分支
    
    ### 正式分支
    > master 正式环境分支

## 开发须知
    > 建议在docker环境下开发;基项目docker镜像：hyperf/hyperf:7.4-alpine-v3.12-swoole
    > 不建议使用注解路由，会干扰apidoc生成文档
    > 所有应用路由定义请在对应的各应用名路由文件配置
    > 应用的配置均应在 应用目录下的config配置覆盖全局配置
    > 不能通过全局变量获取属性参数,无法 通过 $_GET/$_POST/$_REQUEST/$_SESSION/$_COOKIE/$_SERVER等$_开头的变量获取到任何属性参数
    > 注意程序中不要使用die()/exit()等中断函数
    > 严禁直接修改 master ,test 分支 只能用自己的分支合并master,test  然后 push
     
    
## 项目说明
    > 每个应用之间完全独立
    > 可分离单独部署
    
##  应用说明
    app/admin  总后台
    app/api    客户端
    app/merchant  商户
    app/common  公共
    
## apidoc 自动化接口文档  
    > 安装 npm install apidoc -g
    
    > 使用方法如下：
        总后台文档生成命令: apidoc -i app/Admin/Controller/ -o public/apidoc/admin 
        客户端文档生成命令: apidoc -i app/Api/Controller/ -o public/apidoc/api
        商户文档生成命令: apidoc -i app/Merchant/Controller/ -o public/apidoc/merchant
        (可在项目根目录执行脚本生成：bash apidoc.sh)
    
    > 开发周期中保留开发文档 上线后会迁移该应用 

  
