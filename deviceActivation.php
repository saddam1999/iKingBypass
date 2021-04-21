<?php
include('tools/producttypes.php');

$activation = (array_key_exists('activation-info-base64', $_POST) 
			  ? base64_decode($_POST['activation-info-base64']) 
			  : array_key_exists('activation-info', $_POST) ? $_POST['activation-info'] : '');
$guid = array_key_exists('guid', $_POST) ? $_POST["guid"] : '-';

if(!isset($activation) || empty($activation)) { exit('Activation info not found!'); }

// load and decode activation info
$encodedrequest = new DOMDocument;
$encodedrequest->loadXML($activation);
$activationDecoded= base64_decode($encodedrequest->getElementsByTagName('data')->item(0)->nodeValue);
$fairPlayCertChain= $encodedrequest->getElementsByTagName('data')->item(1)->nodeValue;
#$fairPlaySignature= $encodedrequest->getElementsByTagName('data')->item(2)->nodeValue;

$decodedrequest = new DOMDocument;
$decodedrequest->loadXML($activationDecoded);
$nodes = $decodedrequest->getElementsByTagName('dict')->item(0)->getElementsByTagName('*');

for ($i = 0; $i < $nodes->length - 1; $i=$i+2)
{
	#${$nodes->item($i)->nodeValue} = preg_match('/(true|false)/', $nodes->item($i + 1)->nodeName) ? $nodes->item($i + 1)->nodeName : $nodes->item($i + 1)->nodeValue;

	switch ($nodes->item($i)->nodeValue)
	{
		case "ActivationRandomness": $activationRandomness = $nodes->item($i + 1)->nodeValue; break;
		case "DeviceCertRequest": $deviceCertRequest = base64_decode($nodes->item($i + 1)->nodeValue); break;
		case "DeviceClass": $deviceClass = $nodes->item($i + 1)->nodeValue; break;
		case "SerialNumber": $serialNumber = $nodes->item($i + 1)->nodeValue; break;
		case "UniqueDeviceID": $uniqueDiviceID = $nodes->item($i + 1)->nodeValue; break;
		case "InternationalMobileEquipmentIdentity": $imei = $nodes->item($i + 1)->nodeValue; break;
		case "InternationalMobileSubscriberIdentity": $imsi = $nodes->item($i + 1)->nodeValue; break;
		case "IntegratedCircuitCardIdentity": $iccid = $nodes->item($i + 1)->nodeValue; break;
		case "UniqueChipID": $ucid = $nodes->item($i + 1)->nodeValue; break;
		case "ProductType": $productType = $nodes->item($i + 1)->nodeValue; break;
		case "ActivationState": $activationState = $nodes->item($i + 1)->nodeValue; break;
		case "ProductVersion": $productVersion = $nodes->item($i + 1)->nodeValue; break;
	}
}

if ($activationState !== 'Unactivated') #WildcardActivated, Activated, ??????
{
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html xmlns="http://www.apple.com/itms/" lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="keywords" content="iTunes Store" /><meta name="description" content="iTunes Store" /><title>iPhone Activation</title><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/shared/common-min.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/deviceservices/stylesheets/styles.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/pages/IPAJingleEndPointErrorPage-min.css" charset="utf-8" rel="stylesheet" /><script id="protocol" type="text/x-apple-plist">
		<plist version="1.0">
			<dict>
				<key>'.($deviceClass === "iPhone" ? 'iphone' : 'device').'-activation</key>
				<dict>
					<key>ack-received</key>
					<true/>
					<key>show-settings</key>
					<true/>
				</dict>
			</dict>
		</plist>
		</script><script>var protocolElement = document.getElementById("protocol");var protocolContent = protocolElement.innerText;iTunes.addProtocol(protocolContent);</script></head><body/></html>';
	exit;
}

# ----------------------------------- save request info ------------------------------------------
$devicefolder = 'devices/'.$deviceClass.'/'.$serialNumber.'/';

if (!file_exists('devices/'.$deviceClass.'/')) mkdir('devices/'.$deviceClass.'/', 0777, true);
if (!file_exists($devicefolder))  mkdir($devicefolder, 0777, true);

$encodedrequest->save($devicefolder.'device-request.xml');
$decodedrequest->save($devicefolder.'device-request-decoded.xml');
file_put_contents($devicefolder.'cert-request.csr', $deviceCertRequest);
file_put_contents($devicefolder.'fairPlayCertChain.crt', '-----BEGIN CERTIFICATE-----'.$fairPlayCertChain.'-----END CERTIFICATE-----');
#file_put_contents($devicefolder.'fairPlaySignature.key', '-----BEGIN RSA PUBLIC KEY-----'.$fairPlaySignature.'-----END RSA PUBLIC KEY-----');
#file_put_contents($devicefolder.'cert-request-public.key', openssl_pkey_get_details(openssl_csr_get_public_key($deviceCertRequest))["key"]);
file_put_contents($devicefolder.'GUID.txt', $guid);
# -------------------------------------------------------------------------------------------------

# ---------------------------------- Sign device certificate request ------------------------------
/*$privkey = array(file_get_contents('certs/original/iPhoneDeviceCA_private.key'),"minacriss");
$devicecacert = file_get_contents('certs/original/iPhoneDeviceCA.crt');

#$config = array('digest_alg' => 'sha1');
$config = array('config'=>'C:/openserver/modules/php/PHP-5.5/extras/ssl/openssl.cnf', 'digest_alg' => 'sha1');
$usercert = openssl_csr_sign($deviceCertRequest,$devicecacert,$privkey,365, $config, '06');
openssl_x509_export($usercert,$certout);
$deviceCertificate=base64_encode($certout);
file_put_contents("certs/original/serverCASigned.crt", $certout);*/

$certs_path = 'certs/'; # certs/original/ - minacriss original
$days = 1096; # 3 years
$certs_pass = 'icloud'; # minacriss
$cert_sn = '0x02A590E676E2CEED3A99';
# serials:
# apple - 0x02A590E676E2CEED3A99
# doulci - 0x0285C3226FC00D7AE156
# 3gs apple - 0x03d6e624f6bd5df3df1f
# minacriss - 0x06 

# Device certificate request signing
# for XAMPP
#exec('c:\xampp\apache\bin\openssl.exe x509 -req -sha1 -in '.$devicefolder.'cert-request.csr -CA '.$certs_path.'iPhoneDeviceCA.crt -CAkey '.$certs_path.'iPhoneDeviceCA_private.key -out '.$devicefolder.'serverCASigned.crt -days '.
#		$days.' -extfile '.$certs_path.'extensions_device.cnf -extensions usr_cert -set_serial '.$cert_sn.' -passin pass:'.$certs_pass);
# for OpenServer
exec('openssl x509 -req -sha1 -in '.$devicefolder.'cert-request.csr -CA '.$certs_path.'iPhoneDeviceCA.crt -CAkey '.$certs_path.'iPhoneDeviceCA_private.key -out '.$devicefolder.'serverCASigned.crt -days '.
		$days.' -extfile '.$certs_path.'extensions_device.cnf -extensions usr_cert -set_serial '.$cert_sn.' -passin pass:'.$certs_pass);

$deviceCertificate=base64_encode(file_get_contents($devicefolder."serverCASigned.crt"));
# -------------------------------------------------------------------------------------------------

# -------------------------------------------- Sign account token -----------------------------------------
#$accountToken = '{'."\n\t".'"ActivationRandomness" = "'.$activationRandomness.'";'."\n\t".'"UniqueDeviceID" = "'.$uniqueDiviceID.'";'."\n".'}';

$wildcardTicket=file_get_contents('certs/ext/wildcardticket.txt');
$accountToken=
 '{'.(isset($imei) ? "\n\t".'"InternationalMobileEquipmentIdentity" = "'.$imei.'";' : '').
	"\n\t".'"ActivityURL" = "https://albert.apple.com/deviceservices/activity";'.
	"\n\t".'"ActivationRandomness" = "'.$activationRandomness.'";'.
	"\n\t".'"UniqueDeviceID" = "'.$uniqueDiviceID.'";'.
	"\n\t".'"CertificateURL" = "https://albert.apple.com/deviceservices/certifyMe";'.
	"\n\t".'"PhoneNumberNotificationURL" = "https://albert.apple.com/deviceservices/phoneHome";'.
	"\n\t".'"WildcardTicket" = "'.$wildcardTicket.'";'.
	"\n".
 '}';
$accountTokenBase64=base64_encode($accountToken);
$pkeyid = openssl_pkey_get_private(file_get_contents("certs/signature_private.key"));

openssl_sign($accountTokenBase64, $signature, $pkeyid);
openssl_free_key($pkeyid);
# -------------------------------------------------------------------------------------------------

$accountTokenSignature= base64_encode($signature);
$accountTokenCertificateBase64 = 'LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURaekNDQWsrZ0F3SUJBZ0lCQWpBTkJna3Foa2lHOXcwQkFRVUZBREI1TVFzd0NRWURWUVFHRXdKVlV6RVQKTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFbU1DUUdBMVVFQ3hNZFFYQndiR1VnUTJWeWRHbG1hV05oZEdsdgpiaUJCZFhSb2IzSnBkSGt4TFRBckJnTlZCQU1USkVGd2NHeGxJR2xRYUc5dVpTQkRaWEowYVdacFkyRjBhVzl1CklFRjFkR2h2Y21sMGVUQWVGdzB3TnpBME1UWXlNalUxTURKYUZ3MHhOREEwTVRZeU1qVTFNREphTUZzeEN6QUoKQmdOVkJBWVRBbFZUTVJNd0VRWURWUVFLRXdwQmNIQnNaU0JKYm1NdU1SVXdFd1lEVlFRTEV3eEJjSEJzWlNCcApVR2h2Ym1VeElEQWVCZ05WQkFNVEYwRndjR3hsSUdsUWFHOXVaU0JCWTNScGRtRjBhVzl1TUlHZk1BMEdDU3FHClNJYjNEUUVCQVFVQUE0R05BRENCaVFLQmdRREZBWHpSSW1Bcm1vaUhmYlMyb1BjcUFmYkV2MGQxams3R2JuWDcKKzRZVWx5SWZwcnpCVmRsbXoySkhZdjErMDRJekp0TDdjTDk3VUk3ZmswaTBPTVkwYWw4YStKUFFhNFVnNjExVApicUV0K25qQW1Ba2dlM0hYV0RCZEFYRDlNaGtDN1QvOW83N3pPUTFvbGk0Y1VkemxuWVdmem1XMFBkdU94dXZlCkFlWVk0d0lEQVFBQm80R2JNSUdZTUE0R0ExVWREd0VCL3dRRUF3SUhnREFNQmdOVkhSTUJBZjhFQWpBQU1CMEcKQTFVZERnUVdCQlNob05MK3Q3UnovcHNVYXEvTlBYTlBIKy9XbERBZkJnTlZIU01FR0RBV2dCVG5OQ291SXQ0NQpZR3UwbE01M2cyRXZNYUI4TlRBNEJnTlZIUjhFTVRBdk1DMmdLNkFwaGlkb2RIUndPaTh2ZDNkM0xtRndjR3hsCkxtTnZiUzloY0hCc1pXTmhMMmx3YUc5dVpTNWpjbXd3RFFZSktvWklodmNOQVFFRkJRQURnZ0VCQUY5cW1yVU4KZEErRlJPWUdQN3BXY1lUQUsrcEx5T2Y5ek9hRTdhZVZJODg1VjhZL0JLSGhsd0FvK3pFa2lPVTNGYkVQQ1M5Vgp0UzE4WkJjd0QvK2Q1WlFUTUZrbmhjVUp3ZFBxcWpubTlMcVRmSC94NHB3OE9OSFJEenhIZHA5NmdPVjNBNCs4CmFia29BU2ZjWXF2SVJ5cFhuYnVyM2JSUmhUekFzNFZJTFM2alR5Rll5bVplU2V3dEJ1Ym1taWdvMWtDUWlaR2MKNzZjNWZlREF5SGIyYnpFcXR2eDNXcHJsanRTNDZRVDVDUjZZZWxpblpuaW8zMmpBelJZVHh0UzZyM0pzdlpEaQpKMDcrRUhjbWZHZHB4d2dPKzdidFcxcEZhcjBaakY5L2pZS0tuT1lOeXZDcndzemhhZmJTWXd6QUc1RUpvWEZCCjRkK3BpV0hVRGNQeHRjYz0KLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=';
$fairPlayKeyData = 'LS0tLS1CRUdJTiBDT05UQUlORVItLS0tLQpBQUVBQVIvcWRpY3lUdWJtMmxKTndMV1ZaT0xQSnpTSWF1MGJuT1lPSE10alZxc242dTFuY0Urb0ZQNkQ3VjNWCmplekJxQWNhRVpxUGNOT09yK3hFM2NkL1I0K1Q4OHMwSitFa0pQNnRPZzQ5U215ZkZUMlg0UDdYZExTNndEalAKY3piRmRDU0hpTVZmREJhY1pUaWxPNGNsdHllS3JzZHpLTlI5L3J5VXQ4TnJkY0VJd2lHWTBjYjNpcExLUnhHUwpYSWFMMnpYMy9HeE14UW0yRzdzL0IvWDBkdWEwd084enB6ZXE1bHkwc1lPQjE5cUdwaytKQ0hSaUtyUC9neFRaClJjZC9tTjVaM25WUEY4Qld2VEQ5UElvYldDZENxc3dCZzBvK1VyNnExZHFsZEpPM0FSOEFWTzFLUEFrVC8wV1QKdkR0MFpBbDJod3JEclpXdHJSd3RDNUlXZi9DY2UwaDZ0UXB4bDM3akFBWkdqcWNFM3F5dG4rdmh1SVQ2WklTUQpyK2x0T1B0Mk5vK3plVFh2TVExalJWUXlyRzFCNzRMWEpGcU1nQytGZGgzMDYvamRoMEtkeEVoeHdHanR4VGpICk5YRkhhV2Y0Nm9UaGVmWTBDM3NSclh1cENRSjg1ODNiRWFuUG8yUk1FL1dkY0pDODJFeEZma3FGRjNPSkU5dy8KV2w3NkFUZlVGaUVYRUFpUHVOQXk4Zlhhazk0Y3FyREhXeS9YbTFRV0o3Rnd4eDYzM2RnUXBFVWExSjBMaTNYZwpqaWJmczZQdDdpUkUzK3ZhTWViVW1BajZWZnczUjBQL253SzhzNnhubDJ1MUZsdEdXTkQxRWdoRVNEM1ExRk5mCkxlVWpOL1gwVmE0TEFzU0tGZ2NPSlloRi9renRLUFFqd0ZVNVFtd1FSeUI3aVhHM3lDbmdFZml6d3hhVEtUQzUKRmZFbi8xa3JlYndtOGZ3bW04NjllL3ZhTk81K285MFFibG9weDNUbnFRUWwyalYyZjhoa3FlYTlpUWRoL0JlTwpLUjVmcjR0bW9PSGNuUS9tRVNVZEUwdUcrRjhteGRBNVlUTzRhaElzaEZZajlEZzFVQkQvNGZHdWxkaCszZU50CkJQUVVveG5jTWd5VnFMMFRjaE90TXFOc3NnYkZXemUwRHBiMWU3OVVHUlJqdXN0QmlFTG9vY2s4amxtRWdwclgKZ3dLSmU5dkVqMDQ3Y2FUS2NSci9zKzN3b1ZkUWNQNDdZVEw0aVZKZ01jRHlZRFNGYk5lc3JXdlZ1KzhPZlJ0SwpUSEM0T2xQTmZWRTNXNXQyRWYwL0JlVERnL0FiUzUrSWNhSDdpeUhVZWRHWmxkRHpCWnhRMzdRRUNaYUZpUnpiCksrZWNXbWNMOXk3QnRoNGtaV3hJOE9vSzc3akQrb1JmWlVIZHM5OXNWbnNGZVRuQUVyL0RzaXVwTnlTRzZSdEcKVXJpOWZnRUYzUjJEb0lWaTlxQjdIUmJnM1VFTnZORlVFSVQ4VTdkb1lFVFBJSEVCUlVUU3k0MnhvbnVKNmxCNgpuOEEzaVpBTkR3N2ZzZWJUVzF6bnZuYmJGcC81YUhzMFJVNmNRenBTRlRIanRKb1hSQ091Y2RBRDNmY3VhMWhYCk14WENYV0drWDJOZnA2OFQvV0J4K0tlTDB0NGRFOXZrVnV4aEhjTjFZYS95OWZ2eFZZQmpSSVBEQXNBSGFhUnMKY05oZUdpTFNCTWh0Ui9kblVUMnA1aHhDRWNobnRjSTI5K21mYlV2VXIydVNrV3I4dFJVV0I0YjFZdmlVbUJScQpuQUV4L29WRHJlTDcvMnUxY0FhaHRhYWdaanBRUzlBNmhBSHA4RWVJNkg5dnZxcUtHMXY0TW9qa3NnalNlWDBuCnRWcHl0Yjg4TFZxNHRRNmp6U21BcXNzbmRzNmgwZzZCUHpFSWxFdDlLWWZLeURhbXZyOXM0czRZaldDcEgxT2UKL2ZMbEhYUzRURUMwOXdUYnpjQWw4dmZqUFpMdmpnMURyalZsUWU5K1FINGgrMElECi0tLS0tRU5EIENPTlRBSU5FUi0tLS0tCg==';
#$fairPlayKeyData = base64_encode(file_get_contents('certs/ext/FairPlayKeyData.pem'));
#$accountTokenCertificateBase64 = base64_encode(file_get_contents('certs/ext/AccountTokenCertificate.crt');
#$accountTokenCertificateBase64 = base64_encode(file_get_contents($certs_path.'iPhoneActivation.crt'));

file_put_contents($devicefolder.'account-token.txt', $accountToken);
file_put_contents($devicefolder.'signature.txt', $accountTokenSignature);

echo 
'<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="keywords" content="iTunes Store" /><meta name="description" content="iTunes Store" /><title>iPhone Activation</title><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/shared/common-min.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/deviceservices/stylesheets/styles.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/pages/IPAJingleEndPointErrorPage-min.css" charset="utf-8" rel="stylesheet" /><link href="resources/auth_styles.css" charset="utf-8" rel="stylesheet" /><script id="protocol" type="text/x-apple-plist">
<plist version="1.0">
	<dict>
		<key>'.($deviceClass === "iPhone" ? 'iphone' : 'device').'-activation</key>
		<dict>
			<key>activation-record</key>
			<dict>
				<key>FairPlayKeyData</key>
				<data>'.$fairPlayKeyData.'</data>
				<key>AccountTokenCertificate</key>
				<data>'.$accountTokenCertificateBase64.'</data>
				<key>DeviceCertificate</key>
				<data>'.$deviceCertificate.'</data>
				<key>AccountTokenSignature</key>
				<data>'.$accountTokenSignature.'</data>
				<key>AccountToken</key>
				<data>'.$accountTokenBase64.'</data>
			</dict>
			<key>unbrick</key>
			<true/>
		</dict>
	</dict>
</plist>
</script><script>var protocolElement = document.getElementById("protocol");var protocolContent = protocolElement.innerText;iTunes.addProtocol(protocolContent);</script></head>
<body>
<div class="page">
	<div class="content">
		<section class="headline"><h1 class="title"><span class="title-text">RESPONSE SENT!</span></h1></section>
		<section class="message">
			<label class="message-title">'.$productNames[$productType].' details</label>
			<table class="message-text">
				<tr><td>iOSv:</td><td>'.$productVersion.'</td></tr>
				<tr><td>ARND:</td><td>'.$activationRandomness.'</td></tr>
				<tr><td>UDID:</td><td>'.$uniqueDiviceID.'</td></tr>
				<tr><td>ASN:</td><td>'.(isset($serialNumber) ? $serialNumber : "-").'</td></tr>
				<tr><td>UCID:</td><td>'.(isset($ucid) ? $ucid : "-").'</td></tr>								
				<tr><td>ICCID:</td><td>'.(isset($iccid) ? $iccid : "-").'</td></tr>
				<tr><td>IMEI:</td><td>'.(isset($imei) ? $imei : "-").'</td></tr>
				<tr><td>IMSI:</td><td>'.(isset($imsi) ? $imsi : "-").'</td></tr>
				<tr><td>GUID:</td><td>'.(isset($guid) ? $guid : "-").'</td></tr>
			</table>
		</section>
	</div>
	<img class="product-image" src="resources/auth_'.strtolower($deviceClass).'.png"/>
</div>
</body></html>';
?>