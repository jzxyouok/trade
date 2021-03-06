## 1.1 支付接口文档说明
* 本接口出现的user_id等同于账号系统里出现的open_id
* 建议游戏应用设计角色ID时，应保证角色ID全局唯一 (不影响合服)

___

## 2.1 充值页面 /trade
跳转到WEB支付页面 (不使用SDK支付)

参数名 | 类型 | 必选 | 描述
--- | --- |:---:| ---
app_id      | varchar(16)  | 是 | 应用ID
gateway     | varchar(16)  | 否 | 支付网关(留空则列出所有支持的网关)
user_id     | varchar(16)  |[是]| 平台账号ID(user_id,access_token二选一)
access_token| varchar(1000)|[是]| access_token(user_id,access_token二选一)
custom      | varchar(64)  | 否 | 自定义, 例:终端用户201-800123 (强烈建议)
subject     | varchar(64)  | 否 | 产品标题
amount      | decimal(10,2)| 否 | 金额, 例: 4.99
currency    | varchar(3)   | 否 | 币种, 例: CNY
product_id  | varchar(60)  | 否 | 产品ID, 如不指定则跳转到选择产品界面
uuid        | varchar(36)  | 否 | 唯一设备ID, 客户端生成, 例: 3F2504E0-4F89-11D3-9A0C-0305E82C3301
adid        | varchar(36)  | 否 | 广告追踪标识, 客户端获取, 如IDFA, MAC
device      | varchar(32)  | 否 | 设备信息, 如 iphone7 plus
channel     | varchar(32)  | 否 | 渠道信息, 如 baidu_ad

___

## 2.2 创建订单接口API /trade/create
用于SDK创建订单时使用

参数名 | 类型 | 必选 | 描述
--- | --- |:---:| ---
app_id      | varchar(16)  | 是 | 应用ID
gateway     | varchar(16)  | 是 | 支付网关
user_id     | varchar(16)  |[是]| 平台账号ID
access_token| varchar(1000)|[是]| access_token(user_id,access_token二选一)
custom      | varchar(64)  | 是 | 自定义, 例:终端用户201-800123 (强烈建议)
product_id  | varchar(60)  | 是 | 产品ID
uuid        | varchar(36)  | 否 | 唯一设备ID, 客户端生成, 例: 3F2504E0-4F89-11D3-9A0C-0305E82C3301
adid        | varchar(36)  | 否 | 广告追踪标识, 客户端获取, 如IDFA, MAC
device      | varchar(32)  | 否 | 设备信息, 如 iphone7 plus
channel     | varchar(32)  | 否 | 渠道信息, 如 baidu_ad

失败返回：
```json
{
    "code": 1,
    "msg": "failed"
}
```

成功返回：  
```json
{
    "code": 0,
    "msg": "success",
    "transaction": "20170329105856612534004667",
    "product_id": "com.xt.200",
    "amount": "20.00",
    "currency": "CNY",
    "reference": "",
    "raw": "alipay_sdk=omnipay-alipay&app_id=2015112700879615&biz_content=%7B%22out_trade_no%22%3A%2220170329105856612534004667%22%2C%22total_amount%22%3A%2220.00%22%2C%22subject%22%3A%22com.xt.200%22%2C%22product_code%22%3A%22QUICK_MSECURITY_PAY%22%7D&charset=UTF-8&format=JSON&method=alipay.trade.app.pay¬ify_url=https%3A%2F%2Ftrade.yinhecool.com%2Fnotify%2Falipay&sign_type=RSA×tamp=2017-03-29+10%3A58%3A33&version=1.0&sign=kDs6IOydRvCFKaDu0sDvZ1WntiPGl0c%3D"
}
```

返回参数说明：

参数名 | 类型 | 必选 | 描述
--- | --- |:---:| ---
code        | int(10)       | - | 状态码,0成功
msg         | varchar(64)   | - | 返回信息
transaction | varchar(32)   | - | 平台订单号
product_id  | varchar(32)   | - | 产品ID
amount      | decimal(10,2) | - | 产品金额
currency    | varchar(3)    | - | 货币类型
reference   | varchar(32)   | - | 网关订单ID
raw         | varchar(1000) | - | 网关返回的原始信息

___

## 3.1 通知回调接口 /notify/{GATEWAY}
本接口仅用于支付网关设置或者来自于客户端的支付结果通知(apple,google)

* Apple Store:  
/notify/apple?app_id=100&user_id=100001&custom=201-800123&receipt=xxxx  
/notify/apple?app_id=100&access_token=xxxx&custom=201-800123&receipt=xxxx

* Google Play:  
/notify/google?app_id=100&user_id=100001&custom=201-800123&receipt=xxxx&sign=xxxx  
/notify/google?app_id=100&access_token=xxxx&custom=201-800123&receipt=xxxx&sign=xxxx

* 支付宝:  
/notify/alipay

* PaymentWall:  
/{APPID}/notify/paymentwall  
/notify/paymentwall  

* PayPal支付:   
/notify/paypal

* Mol支付:   
/notify/mol

* MyCard支付:   
/notify/mycard

参数说明:  

参数名 | 类型 | 必选 | 描述
--- | --- |:---:| ---
app_id      | varchar(16)  | 是 | 应用ID
access_token| varchar(1000)|[是]| 平台token(user_id,access_token二选一)
user_id     | varchar(16)  |[是]| 平台账号ID(user_id,access_token二选一)
custom      | varchar(64)  | 否 | 自定义, 例:终端用户201-800123
receipt     | varchar(1000)| 是 | Apple/Google收据
sign        | varchar(1000)|[是]| 签名, 仅Google平台有,Google必填

___

## 3.2 储值结果通知到CP-Server
用户在与支付网关完成交易后，平台会把支付的结果通过post方式通知给CP-Server(如游戏服务器)  
CP-Server如正确处理通知后则返回**success**，其他任何返回视为失败

示例请求URL: (为方便理解此示例使用get方式展示 正式环境使用post通知)  
[https://host/api?transaction=20170117081802187424000665&gateway=alipay&amount=0.05&currency=CNY&product_id=com.xt.product&user_id=100001&custom=201-800123&timestamp=1484641146&sign=5f8f696fb8312d9ad5f1150e41d68282](#)

通知参数如下:  

参数名 | 类型 | 描述
--- | --- | ---
transaction | varchar(32)  | 平台订单ID
gateway     | varchar(16)  | 支付网关
amount      | decimal(10,2)| 支付金额(元)
currency    | varchar(3)   | 货币类型
product_id  | varchar(60)  | 产品ID
user_id     | varchar(16)  | 平台账号ID
custom      | varchar(64)  | 自定义, 例:终端用户201-800123
timestamp   | varchar(10)  | 通知时间戳
sign        | varchar(32)  | 签名(用于CP-Server验证)

**签名规则:**  
1. 除去sign字段外，其他所有字段按键名升序排列，例: a=1&b=2&c=3&d=4  
2. 拼接密钥KEY，a=1&b=2&c=3&d=4KEY  
3. 最后md5(a=1&b=2&c=3&d=4KEY) 得到的字符串即为签名字符串  

___

## 4.1 产品接口 /product

请求参数：  

参数名 | 类型 | 必选 | 描述 
--- | --- |:---:| ---
app_id      | varchar(16)  | 是 | 应用ID
gateway     | varchar(16)  | 是 | 支付网关, 例: alipay、weixin

失败返回
```json
{
    "code": 1,
    "msg": "no products"
}
```
成功返回
```json
{
    "code": 0,
    "msg": "success",
    "data": [
        {
            "name": "100钻石",
            "product_id": "com.xt.100",
            "price": "10.00",
            "currency": "CNY",
            "coin": "100",
            "remark": "10元100钻石",
            "image": "http://www.example.com/logo.jpg"
        },
        {
            "name": "200钻石",
            "product_id": "com.xt.200",
            "price": "20.00",
            "currency": "CNY",
            "coin": "200",
            "remark": "20元200钻石",
            "image": "http://www.example.com/logo.jpg"
        }
    ]
}
```




