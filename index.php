<?php
//
########################################################################
#HomeAssistant中开启了auth_providers模式
#配置HomeAssistant的URL访问地址（必填）
$webSite = "https://xxx.xxx.xxx:44300";
#$webSite = "http://home.meng-qi.com:48123";

#当前请求站点的http协议方式(http或https)，为空时自动识别（配置为nginx反代时可能无法正确获取，则需要配置此变量值）
$httpType = "";

#数据存储文件名定义，可任意定义，存储时会二次加密文件名（必填）
$storeFileName = "db.config";

#访问当前文件index.php时的密钥KEY，可任意定义，也可以为空
#密钥KEY不为空时，需要通过GET或POST将[key]传值，否则提示错误
$key = "";
########################################################################

$callbackUrlPath = "/index.php";
$storeRealFileName = substr(SHA1($storeFileName),0,10);
if ($httpType != null){
	$http_type = $httpType . '://';
}else{
	$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
}


$webSiteUrl =$http_type.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']; 

header('Access-Control-Allow-Origin:'.$webSite);  
header('Access-Control-Allow-Methods:POST,GET');  


$ClientID = $http_type.$_SERVER['HTTP_HOST'];
$callbackUrlFullPath = dirname($webSiteUrl) . $callbackUrlPath;
$AuthState = $_REQUEST["state"];
$AuthCode = $_GET["code"];
$AuthKEY = $_REQUEST["key"];
$realToken = $_REQUEST["realtoken"];
$requestAPI = $_REQUEST["requestapi"];
$tokenVilid = "false";


if ($AuthState == null){
	$data = getToken();

	if ($data == null)
	{
		$data = '{"error1":"Token has expired, please open the website \''.$callbackUrlFullPath.'\' to create a token"}';
		echo $data;
	}
	else
	{
		$obj = json_decode($data,true);
		
		$data = refreshToken($obj["refresh_token"]);
		
		
		$obj = json_decode($data,true);
		//echo $obj["error"];
		//return;
		if ($obj["error"] != null)
		{
			ResetGlobal($storeRealFileName);
			$data = '{"error2":"'.$obj["error"].'"}';
			echo $data;
		}
		else
		{
			$realToken = $obj["token_type"]." ". $obj["access_token"];
			$data = testTokenVerify($realToken);

			try
			{
				$obj = json_decode($data,true);
				if ($obj["message"] != null)
				{
					echo "Token is valid;";
					$tokenVilid = "true";
				}
			}
			catch(Exception $e)
			{
				echo "Token has expired;";
				$tokenVilid = "false";
			}
			
		}
	}
}
elseif ($AuthState == "requestAuth" && $AuthCode != "")
{
	$data = requestToken();
	echo $data;
	return;
}
// client request token
elseif (strtolower($AuthState) == "clientrequesttoken")
{
	if (!$key == null)
	{
		if ($key != $AuthKEY)
		{
			$data = '{"error":"key is invilid"}';
			echo $data;
			return;
		}
	}
	
	$data = getToken();
	if ($data == null)
	{
		$data = '{"error":"Token has expired, please open the website \''.$callbackUrlFullPath.'\' to create a token"}';
		echo $data;
		return;
	}

	$obj = json_decode($data,true);
	$data = refreshToken($obj["refresh_token"]);
	$obj = json_decode($data,true);
	
	if ($obj["error"] != null)
	{
		ResetGlobal($storeRealFileName);
		$data = '{"error":"'.$obj["error"].'"}';
		echo $data;
		return;
	}
	
	if ($realToken == "1" || strtolower($realToken)=="y")
	{
		echo $obj["token_type"]." ". $obj["access_token"];
	}
	else
	{
		echo $data;
	}
	return;
}
// client request api
elseif (strtolower($AuthState) == "clientrequestapi" && $requestAPI != null)
{
	if (!$key == null)
	{
		if ($key != $AuthKEY)
		{
			$data = '{"error":"key is invilid"}';
			echo $data;
			return;
		}
	}
	
	$data = getToken();
	if ($data == null)
	{
		$data = '{"error":"Token has expired, please open the website \''.$callbackUrlFullPath.'\' to create a token"}';
		echo $data;
		return;
	}
	
	
		
	$obj = json_decode($data,true);
	
	$data = refreshToken($obj["refresh_token"]);
	$obj = json_decode($data,true);
	
	if ($obj["error"] != null)
	{
		ResetGlobal($storeRealFileName);
		$data = '{"error":"'.$obj["error"].'"}';
		echo $data;
		return;
	}
	
	$realToken = $obj["token_type"]." ". $obj["access_token"];

	echo requestAPI($requestAPI,$realToken);
	return;
	
}

function requestToken(){
	global $storeRealFileName,$webSite,$AuthCode,$ClientID;
	
	$url = $webSite . "/auth/token";
	$data_string = "grant_type=authorization_code&code=" . $AuthCode . "&client_id=" . $ClientID;

	$header = array();
	$header[] = 'Accept:application/json';
	$header[] = 'Content-Type:application/x-www-form-urlencoded';

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
	curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS,$data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER ,1); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
	curl_setopt($ch, CURLOPT_URL,$url);
	$data = curl_exec($ch); 
	//echo " url:".$url;
	//echo ' error:'.curl_errno($ch);
	$obj = json_decode($data,true);
	
	//echo var_dump($obj);
	
	$objToken = array("access_token"=>"111");
	$obj = array_diff_key($obj,$objToken);
	//echo json_encode($obj);
	$save = SetGlobal($storeRealFileName,json_encode($obj));
	
	curl_close($ch);
	
	return $data;
}

function refreshToken($refreshToken){
	global $storeRealFileName,$webSite,$ClientID;
	
	$url = $webSite . "/auth/token";
	$data_string = "grant_type=refresh_token&refresh_token=" . $refreshToken . "&client_id=" . $ClientID;
	#$data_string = array();
	#data_string[] = 'grant_type:refresh_token';
	#data_string[] = 'refresh_token:'.$refreshToken;
	#data_string[] = 'client_id:'.$ClientID;
	
	#$data_string = array(
	#	'grant_type'=>'refresh_token',
	#	'refresh_token'=>$refreshToken,
	#	'client_id'=>$ClientID
	#);
	
	$header = array();
	$header[] = 'Accept:application/json';
	$header[] = 'Content-Type:application/x-www-form-urlencoded';
	//$header[] = 'Content-Type:application/json';
	
	//echo $data_string;
	
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
	curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS,$data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true); 
	curl_setopt($ch, CURLOPT_URL,$url);
	$data = curl_exec($ch); 
	
	curl_close($ch);
	
	return $data;
}

function requestAPI($apiUrl,$token){
	global $storeRealFileName,$webSite,$AuthCode,$ClientID;
	
	if (substr($apiUrl,0,1) != "/"){
		$apiUrl1 = "/" . $apiUrl;
	}
	else
	{
		$apiUrl1 = $apiUrl;
	}
	
	$url = $webSite . $apiUrl1;
	//$data_string = "grant_type=authorization_code&code=" . $AuthCode . "&client_id=" . $ClientID;

	//echo "url:".$url;
	
	
	$header = array();
	$header[] = 'Authorization:'.$token;

	$input = file_get_contents('php://input');
	if ($input != null){
		$header[] = 'Content-Type:application/json';
	}else{
		$header[] = 'Content-Type:application/x-www-form-urlencoded';
		
	}
	
	//echo "url:".$url;
	
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
	curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
	//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	//curl_setopt($ch, CURLOPT_POSTFIELDS,$data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true); 
	
	if ($input != null){
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS,$input);
	}else{
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		
	}

	curl_setopt($ch, CURLOPT_URL,$url);
	$data = curl_exec($ch); 

	curl_close($ch);
	return $data;
}

//验证token是否有效
function testTokenVerify($realToken){
	$url = "/api/";
	return requestAPI($url,$realToken);
}


function getToken(){
	global $storeRealFileName;
    return GetGlobal($storeRealFileName);
	
}


function ResetGlobal($name)
{
   //return null;
   return unlink($name);
}
function SetGlobal($name,$value)
{
   return file_put_contents($name,base64_encode($value));
}
function GetGlobal($name)
{
   if(!file_exists($name)) return null;  
   $value=base64_decode(file_get_contents($name)); 
   return $value;
} 

?>
<!DOCTYPE html>
<script type = "text/javascript">
var state="<?php echo $_REQUEST["state"];?>";
var ClientID = '<?php echo ($ClientID);?>';
var callbackUrl = "<?php echo ($callbackUrlFullPath);?>";
var webSiteHA = "<?php echo $webSite;?>";
var tokenVilid = "<?php echo $tokenVilid;?>";
var redirectURL = encodeURI(webSiteHA + "/auth/authorize?client_id=" + ClientID + "&redirect_uri=" + callbackUrl+"&state=requestAuth");
var secs = 3; //倒计时的秒数 

function doUpdate(num) 
{ 
	document.getElementById('ShowDiv').innerHTML = '将在'+num+'秒后自动跳转到HomeAssistant登录页' ;
	if(num == 0) { window.location.href = redirectURL; }
}

window.onload = function() {
	

	if (state == "new")
	{
		window.location.href = redirectURL;
	}
	else if (tokenVilid == "false")
	{
		for(var i=secs;i>=0;i--) 
		{ 
			window.setTimeout('doUpdate(' + i + ')', (secs-i) * 1000); 
		} 
	}
}


</script>
<div id="ShowDiv" />
</html>