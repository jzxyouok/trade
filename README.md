## 说明
[Phalcon相关文档](http://docs.phalconphp.com/zh/latest/index.html)


## 所需环境
* PHP >= 5.5
* composer


## 使用方法
###### 首次安装调试时,需要将配置目录下config.example.ini 修改为config.ini

* securityPlugin = 0 不启权限控制
* securityPlugin = 1 使用此系统自带的RBAC权限控制
* securityPlugin = 2 使用权限控制中心的权限控制
* 配置文件中db_backend为后端RBAC使用的数据库配置
* Demo文件位置examples, 分别为模型文件Demo, 控制器文件Demo


## 可能用到的组件
* [endroid/qrcode](https://packagist.org/packages/endroid/qrcode) 用于生成二维码
* [phpgangsta/googleauthenticator](https://packagist.org/packages/phpgangsta/googleauthenticator) 令牌验证相关
* [overtrue/wechat](https://packagist.org/packages/overtrue/wechat) 微信SDK
* [xxtime/paytime](https://packagist.org/packages/xxtime/paytime) 支付PayTime
* [omnipay/omnipay](https://packagist.org/packages/omnipay/omnipay) 支付Omnipay
* [geoip2/geoip2](https://packagist.org/packages/geoip2/geoip2) 地理位置分析
* [alidayu](http://www.alidayu.com/) 短信功能


## 关于
* 主页: [https://github.com/xxtime/phalcon](https://github.com/xxtime/phalcon)
* 作者: [https://www.xxtime.com](https://www.xxtime.com)
