<?php
//
########################################################################
#HomeAssistant中开启了auth_providers模式
#配置HomeAssistant的URL访问地址（必填）
$webSite = "192.168.1.1:8123";

#数据存储文件名定义，可任意定义，存储时会二次加密文件名（必填）
$storeFileName = "db.config";

#访问当前文件index.php时的密钥KEY，可任意定义，也可以为空
#密钥KEY不为空时，需要通过GET或POST将[key]传值，否则提示错误
$key = "";
########################################################################

$callbackUrlPath = "/index.php";
$storeRealFileName = substr(SHA1($storeFileName),0,10);
$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
$webSiteUrl =$http_type.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']; 

header('Access-Control-Allow-Origin:'.$webSite);  
header('Access-Control-Allow-Methods:POST,GET');  


$ClientID = $http_type.$_SERVER['HTTP_HOST'];
$callbackUrlFullPath = dirname($webSiteUrl) . $callbackUrlPath;
$AuthState = $_REQUEST["state"];
$AuthCode = $_GET["code"];
$AuthKEY = $_REQUEST["key"];


//request homeassistant
if ($AuthState == "requestAuth" && $AuthCode != "")
{
	$data = requestToken();
	echo $data;
	return;
}
// client request
elseif (strtolower($AuthState) == "clientrequest")
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
	
	echo $data;
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
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true); 
	curl_setopt($ch, CURLOPT_URL,$url);
	$data = curl_exec($ch); 
	
	$obj = json_decode($data,true);
	
	//echo var_dump($obj);
	
	$objToken = array("access_token"=>"111");
	$obj = array_diff_key($obj,$objToken);

	$save = SetGlobal($storeRealFileName,json_encode($obj));
	
	curl_close($ch);
	
	return $data;
}

function refreshToken($refreshToken){
	global $storeRealFileName,$webSite,$ClientID;
	
	$url = $webSite . "/auth/token";
	$data_string = "grant_type=refresh_token&refresh_token=" . $refreshToken . "&client_id=" . $ClientID;

	$header = array();
	$header[] = 'Accept:application/json';
	$header[] = 'Content-Type:application/x-www-form-urlencoded';
	
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

function getToken(){
	global $storeRealFileName;
    return GetGlobal($storeRealFileName);
	
}


function ResetGlobal($name)
{
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
window.onload = function() {
var state="<?php echo $_REQUEST["state"];?>";
var ClientID = '<?php echo ($ClientID);?>';
var callbackUrl = "<?php echo ($callbackUrlFullPath);?>";
var webSiteHA = "<?php echo $webSite;?>";

var redirectURL = encodeURI(webSiteHA + "/auth/authorize?client_id=" + ClientID + "&redirect_uri=" + callbackUrl+"&state=requestAuth");

if (state == "")
{
	window.location.href = redirectURL;
}
else
{
	return;
}
}
</script>
</html>