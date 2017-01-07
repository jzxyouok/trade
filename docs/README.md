#### 网页订单接口 /trade
跳转到WEB支付页面

参数名 | 类型 | 必选 | 描述
--- | --- |:---:| ---
gateway     | varchar(16)  | 是 | 支付网关
app_id      | varchar(16)  | 是 | 应用ID
subject     | varchar(64)  | 是 | 产品主题
amount      | decimal(10,2)| 是 | 金额, 例: 4.99
currency    | varchar(3)   | 是 | 币种, 例: CNY
user_id     | varchar(16)  | 是 | 账号ID
product_id  | varchar(60)  | 是 | 产品ID
end_user    | varchar(64)  | 是 | 终端用户, 例: 201-800123
uuid        | varchar(36)  | 否 | 唯一设备ID, 客户端生成, 例: 3F2504E0-4F89-11D3-9A0C-0305E82C3301
adid        | varchar(36)  | 否 | 广告追踪标识, 客户端获取, 如IDFA, MAC
device      | varchar(32)  | 否 | 设备信息, 如 iphone7 plus
channel     | varchar(32)  | 否 | 渠道信息, 如 baidu_ad



#### 订单接口API /trade/create

参数名 | 类型 | 必选 | 描述
--- | --- |:---:| ---
gateway     | varchar(16)  | 是 | 支付网关
app_id      | varchar(16)  | 是 | 应用ID
subject     | varchar(64)  | 是 | 产品主题
amount      | decimal(10,2)| 是 | 金额, 例: 4.99
currency    | varchar(3)   | 是 | 币种, 例: CNY
user_id     | varchar(16)  | 是 | 账号ID
product_id  | varchar(60)  | 是 | 产品ID
end_user    | varchar(64)  | 是 | 终端用户, 例: 201-800123
uuid        | varchar(36)  | 否 | 唯一设备ID, 客户端生成, 例: 3F2504E0-4F89-11D3-9A0C-0305E82C3301
adid        | varchar(36)  | 否 | 广告追踪标识, 客户端获取, 如IDFA, MAC
device      | varchar(32)  | 否 | 设备信息, 如 iphone7 plus
channel     | varchar(32)  | 否 | 渠道信息, 如 baidu_ad

返回值：


#### 通知回调接口 /notify/{GATEWAY}
本接口仅用于支付网关设置
