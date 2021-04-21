<?php
$productNames = array(
	'AppleTV2,1' => 'Apple TV 2G',
	'AppleTV3,1' => 'Apple TV 3',
	'AppleTV3,2' => 'Apple TV 3 (2013)',
	'iPad1,1' => 'iPad 1',
	'iPad2,1' => 'iPad 2 (WiFi)',
	'iPad2,2' => 'iPad 2 (GSM)',
	'iPad2,3' => 'iPad 2 (CDMA)',
	'iPad2,4' => 'iPad 2 (Mid 2012)',
	'iPad2,5' => 'iPad Mini (WiFi)',
	'iPad2,6' => 'iPad Mini (GSM)',
	'iPad2,7' => 'iPad Mini (Global)',
	'iPad3,1' => 'iPad 3 (WiFi)',
	'iPad3,2' => 'iPad 3 (CDMA)',
	'iPad3,3' => 'iPad 3 (GSM)',
	'iPad3,4' => 'iPad 4 (WiFi)',
	'iPad3,5' => 'iPad 4 (GSM)',
	'iPad3,6' => 'iPad 4 (Global)',
	'iPad4,1' => 'iPad Air (WiFi)',
	'iPad4,2' => 'iPad Air (Cellular)',
	'iPad4,3' => 'iPad Air (China)',
	'iPad4,4' => 'iPad Mini Retina (WiFi)',
	'iPad4,5' => 'iPad Mini Retina (Cellular)',
	'iPad4,6' => 'iPad Mini Retina (China)',
	'iPhone1,1' => 'iPhone 2G',
	'iPhone1,2' => 'iPhone 3G',
	'iPhone2,1' => 'iPhone 3G[S]',
	'iPhone3,1' => 'iPhone 4 (GSM)',
	'iPhone3,2' => 'iPhone 4 (GSM / 2012)',
	'iPhone3,3' => 'iPhone 4 (CDMA)',
	'iPhone4,1' => 'iPhone 4[S]',
	'iPhone5,1' => 'iPhone 5 (GSM)',
	'iPhone5,2' => 'iPhone 5 (Global)',
	'iPhone5,3' => 'iPhone 5c (GSM)',
	'iPhone5,4' => 'iPhone 5c (Global)',
	'iPhone6,1' => 'iPhone 5s (GSM)',
	'iPhone6,2' => 'iPhone 5s (Global)',
	'iPod1,1' => 'iPod touch 1G',
	'iPod2,1' => 'iPod touch 2G',
	'iPod3,1' => 'iPod touch 3',
	'iPod4,1' => 'iPod touch 4',
	'iPod5,1' => 'iPod touch 5');


$serverlist = file("servers.txt", FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
		
$requestlist = '';
foreach (glob("requests/*.xml") as $filename) 
{
    $requestlist = $requestlist.'<a href="fake-request.php?file='.basename($filename).'" >'.basename($filename, '.xml').'</a><br/>';
}

$requestfilename = 'requests/'.(isset($_GET['file']) ? $_GET['file'] : 'fake.php');

if (isset($_GET['file']) && file_exists($requestfilename))
{
	$activationinfo = file_get_contents($requestfilename);

	// load and decode activation info 
	$encodedrequest = new DOMDocument;
	$encodedrequest->loadXML($activationinfo);
	$activationDecoded= base64_decode($encodedrequest->getElementsByTagName('data')->item(0)->nodeValue);

	$decodedrequest = new DOMDocument;
	$decodedrequest->loadXML($activationDecoded);
	$nodes = $decodedrequest->getElementsByTagName('dict')->item(0)->getElementsByTagName('*');

	for ($i = 0; $i < $nodes->length - 1; $i=$i+2)
	{
		switch ($nodes->item($i)->nodeValue)
		{
			case "UniqueChipID": $ECID = $nodes->item($i + 1)->nodeValue; break;
			case "IntegratedCircuitCardIdentity": $ICCID = $nodes->item($i + 1)->nodeValue; break;
			case "SerialNumber": $AppleSerialNumber = $nodes->item($i + 1)->nodeValue; break;		
			case "InternationalMobileEquipmentIdentity": $IMEI = $nodes->item($i + 1)->nodeValue; break;		
			case "InternationalMobileSubscriberIdentity": $IMSI = $nodes->item($i + 1)->nodeValue; break;
		}
	}
}
$initialserver = isset($serverlist[0]) ? $serverlist[0] : 'http://yhamentech.com/';


foreach($serverlist as $item) { $servers = $servers.'<option value='.$item.'>'.$item.'</option>'; };

echo '
<html>
<title>IDevice activation</title>
<script type="text/javascript">function changeServer(aForm,aValue) { aForm.setAttribute("action",aValue); } </script>
<body>
<form id="request-form" action="'.$initialserver.'" method="POST">
  <p><b>iDevice activation form</b></p>
  <p><input type="submit" value="Activate"></p>
  <p>
		<table>
			<tr>
				<td>
					<table>
						<tr><td>SERVER:</td><td><select name="SRVNAME" size=1 required autofocus onChange="changeServer(this.form,this.value);" style="width: 635px">'.$servers.'</select></td></tr>
						<tr><td>ECID:</td><td><input type="text" size=100 name="ECID" value="'.$ECID.'"></td></tr>
						<tr><td>MachineName:</td><td><input type="text" size=100 name="machineName" value="ICLOUD"></td></tr>
						<tr><td>InStoreActivation:</td><td><input type="text" size=100 name="InStoreActivation" value="false"></td></tr>
						<tr><td>ICCID:</td><td><input type="text" size=100 name="ICCID" value="'.$ICCID.'"></td></tr>
						<tr><td>GUID:</td><td><input type="text" size=100 name="guid" value="0DFAE16C.6F57B068.B803AFB4.CC724E15.96ED2D9C.BFAF971B.95634B69"></td></tr>
						<tr><td>Apple serial number:</td><td><input type="text" size=100 name="AppleSerialNumber" value="'.$AppleSerialNumber.'"></td></tr>
						<tr><td>IMEI:</td><td><input type="text" size=100 name="IMEI" value="'.$IMEI.'"></td></tr>
						<tr><td>IMSI:</td><td><input type="text" size=100 name="IMSI" value="'.$IMSI.'"></td></tr>
						<tr><td>Activation info:</td><td><textarea name="activation-info" cols="80" rows="15" >'.$activationinfo.'</textarea></td></tr>
						<tr><td></td><td><input type="hidden" name="activation-info-base64" value="'.base64_encode($activationinfo).'"></td></tr>
					</table>
				</td>
				<td><div style="overflow:auto;height:500px;width:400px;">'.$requestlist.'</div></td>
			</tr>
		</table>
  </p>  
 </form> 
</body>
</html>';


?>