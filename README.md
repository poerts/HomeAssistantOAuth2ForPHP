# HomeAssistantOAuth2ForPHP

## 用途
1. 使用类似tasker、IFTTT工具和HomeAssistant配合使用时，HomeAssistant新版本(0.76+)中增加了新的授权方式auth_providers，当使用不兼容旧版模式时，原有的所有配置会失效，采用新的授权方式下，使用tasker、IFTTT时会遇到无法方便获得授权的问题
2. 此php用来解决授权问题，利用php服务端做授权认证，返回给客户端（tasker、IFTTT等）access_token信息

## 特点
1. 使用PHP语言编写服务端，便于快速部署（单文件）
2. 配置简单，最小仅需配置文件最上方的webSite的值即可
3. 安全性高，可以额外配置key值，增加一层保护（防止此url被泄露时出现access_token被截取）

## 使用方法
1. 安装配置任意php环境，且所在目录拥有写入权限
2. 拷贝index.php文件至任意可访问目录(不建议修改文件名)，且目录允许php写入文件（用于refresh_token的保存（加密存储））
3. 编辑index.php文件，修改最上方“$webSite=”的值，改为HomeAssistant的刚问路径（支持http/https）,如“https://home.xxxx.com:8123” 。key值可改可不改，为安全起见建议key值做配置（可任意定义）
4. 【登录，网关服务端建立token】浏览器访问index.php文件，界面会自动跳转到HomeAssistant登录页，登录后php路径下会写入一个数据文件（文件名为10位的字母+数字），标识token获取成功
5. 【获取token】浏览器访问index.php文件,使用get/post方式传值state=clientrequesttoken，如“ https://home.xxxx.com/index.php?state=clientrequest&key=123 ” ,浏览器输出json格式字符串的access_token值表示token获取成功。（如果之前的步骤配置了key值，这里访问http时需要携带key值的传参）
6. 【调用RestAPI】浏览器访问index.php文件,使用get/post方式传值state=clientrequestapi，并同时携带requestapi参数，如“https://home.xxxx.com/index.php?state=clientrequestapi&key=123&requestapi=/api/config ”,正常情况下浏览器会返回json格式的字符串（同访问HomeAssistant时{https://home.xxxx.com:8123/api/config }返回的结果相同）,相当于绕开auth认证替代了直接访问HomeAssistant完成REST API请求的过程
  > index.php的请求参数说明（get/post均可）：
  >> * state（必填） : clientrequesttoken 或 clientrequestapi
  >> * key（选填） : （配合二次认证使用）
  >> * realtoken（配合state = clientrequesttoken 时使用）：值为1时，返回真实的可直接使用的token（格式：token_type+' '+access_token）；值为空时，返回HomeAssistant提供的token原形json字符串
  >> * requestapi（配合state = clientrequestapi 时使用）：调用HomeAssistant的REST API时，URL地址栏的路径+完整参数（get方式），如“api/config”、“api/states/device_tracker.xxxxx”
