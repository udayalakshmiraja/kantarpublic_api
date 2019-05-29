<?php
// turn off WSDL caching
ini_set("soap.wsdl_cache_enabled", "0");

class Fileapi
{
    public $surveyNumber;
   
    public $waveNumber;
    public $fileName;
    public $fileType;
    public $status;
    public $category;
    public $surveyfile_ext;
    public $token;
}

$dbUser='root';
$dbHost='localhost';
$dbName='myebdb';
$dbPort='3306';
$dbPass='';
$uploadPath='D:/upload/';
$soapClientPath_wsdl = 'http://localhost/sos/getfilesapi.php?wsdl';
$soapClientOptions = ['trace'=>1,'cache_wsdl'=>WSDL_CACHE_NONE];
$tokenExpirationTime = 10; //please specify in minutes

if (empty($_GET) || isset($_GET['wsdl'])) {
    intSoapFile();
} elseif (isset($_GET['getfileslist']) ) {
    getFileList();
} elseif (isset($_GET['getdatafile'])) {
    getDataFiles();
} elseif (isset($_GET['getsecuritytoken']) ) {
    getTokens();
}

function intSoapFile()
{
    // initialize SOAP Server
    $server=new SoapServer("survey.wsdl", [
        'classmap'=>[
            'file'=>'Fileapi',
        ]
    ]);

    // register available functions
    $server->addFunction('getDataFile');
    $server->addFunction('getSurveyResultStatus');
    $server->addFunction('getToken');
    // start handling requests
    $server->handle();
}

function getDataFile($files)
{
    $a=array();
    foreach ($files as $f) {
        array_push($a, $f);
    }
    return $a;
}

function getSurveyResultStatus($files)
{
    return array('surveyFile' => $files);
}

function getToken($token)
{
    return $token;
}

function getDbInstance(){
    global $dbUser, $dbHost, $dbPass, $dbName;

    return mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
}

function getDataFiles()
{
    $files = new Fileapi();
    $s='';
    $f='';
    $t='';
    $ts='';
    $n='';
	//echo $_GET['fileName'];exit;
     global $soapClientOptions, $soapClientPath_wsdl,$uploadPath;
    if (isset($_GET['surveyNumber'])) {
         $s=$files->surveyNumber=$_GET['surveyNumber'];
    }
    if (isset($_GET['fileName'])) {
         $f=$files->fileName=$_GET['fileName'];
    }
    if (isset($_GET['fileType'])) {
         $t=$files->fileType=$_GET['fileType'];
    }
    if (isset($_GET['token'])) {
         $ts=$files->token=$_GET['token'];
    }
    if (isset($_GET['getSurveyResultStatus_reqExt'])) {
        $n=$files->getSurveyResultStatus_reqExt=$_GET['getSurveyResultStatus_reqExt'];
    }
    $fnmae=$f.'.'.$t;
    $n='fileapi';

    $cxn = getDbInstance();
    $mysql_run=mysqli_query($cxn, "SELECT * from tokens where token='$ts'");
    $rowd=mysqli_fetch_array($mysql_run);

    $datecurrent = date('Y-m-d H:i:s');
    try {
        if ($rowd['valid_upto']>$datecurrent) {
            if ((isset($f) && !empty($f)) && (isset($s) && !empty($s)) && (isset($t) && !empty($t))) {
                $mysql_run=mysqli_query($cxn, "SELECT * FROM tb_files b,tb_files_projdlv_his a
					WHERE b.filename ='$fnmae'  and a.id='$s'");
                $result=mysqli_fetch_array($mysql_run);

                $subpath=$result['subpath'];
                $extension=$result['extension'];
                $hash=$result['hash'];
                //$path = '/var/www/html/uploads/'.$subpath."/";
                $filenames=$hash.'.'.$extension;

                $fileName = basename($filenames);
                $filePath = $uploadPath.$fileName;

                $dl_file = preg_replace("([^\w\s\d\-_~,;:\[\]\(\).]|[\.]{2,})", '', $filenames); // simple file name validation
                $dl_file = filter_var($dl_file, FILTER_SANITIZE_URL); // Remove (more) invalid characters
                $fullPath = $uploadPath.$dl_file;

                if ($fd = fopen($fullPath, "r")) {
                    $fsize = filesize($fullPath);
                    $path_parts = pathinfo($fullPath);
                    $ext = strtolower($path_parts["extension"]);
                    switch ($ext) {
                        case "pdf":
                            header("Content-type: application/pdf");
                            header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\""); // use 'attachment' to force a file download
                            break;

                        case "xls":
                            header('Content-Type: application/vnd.ms-excel');
                            header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\""); // use 'attachment' to force a file download
                            break;
                        case "xlsx":
                            header('Content-Type: application/vnd.ms-excel');
                            header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\""); // use 'attachment' to force a file download
                            break;
                        case "doc":
                            header('Content-Type: application/msword');
                            header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\""); // use 'attachment' to force a file download
                            break;
                        case "sav":
                            header('Content-Type: application/x-spss-sav');
                            header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\""); // use 'attachment' to force a file download
                            break;
                        // add more headers for other content types here
                        default:
                            header("Content-type: application/octet-stream");
                            header("Content-Disposition: filename=\"".$path_parts["basename"]."\"");
                            break;
                    }
                    header("Content-length: $fsize");
                    header("Cache-control: private"); //use this to open files directly
                    while (!feof($fd)) {
                        $buffer = fread($fd, 2048);
                        echo $buffer;
                    }
                }


                // initialize SOAP client and call web service function
                $client=new SoapClient($soapClientPath_wsdl, $soapClientOptions);
                $resp  =$client->getDataFile($f);

            } else {
                echo "Please enter survey number and filename and file extension";
            }
        } else {
            echo "Token expired. Please re-generate the token";
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}


function getTokens()
{
    global $tokenExpirationTime;
    $token = sha1(uniqid(time(), true));
    $date = date('Y-m-d H:i:s');
    $endtime = date('Y-m-d H:i:s', strtotime("+$tokenExpirationTime minutes", strtotime($date)));
    $cxn = getDbInstance();
    $mysql_run=mysqli_query($cxn, "insert into tokens(valid_from,valid_upto,token) values('$date','$endtime','$token')
		");
    if($mysql_run)
    	echo $token;
    else
    	echo "Something went Wrong";
}

function getFileList()
{
    $files = new Fileapi();
    $s='';
    $f='';
    $t='';
    $n='';
    global $soapClientOptions, $soapClientPath_wsdl; 
    if (isset($_GET['surveyNumber'])) {
        $s=$files->surveyNumber=$_GET['surveyNumber'];
    }
    if (isset($_GET['waveNumber'])) {
        $f=$files->waveNumber=$_GET['waveNumber'];
    }
    if (isset($_GET['token'])) {
        $t=$files->token=$_GET['token'];
    }
    if (isset($_GET['surveyfile_ext'])) {
        $n=$files->surveyfile_ext=$_GET['surveyfile_ext']='filestatus api';
    }
    
    $cxn = getDbInstance();
    $mysql_run=mysqli_query($cxn, "SELECT * from tokens where token='$t'");
    $rowd=mysqli_fetch_array($mysql_run);
    $datecurrent = date('Y-m-d H:i:s');
    $surveyfilearray=array();


    try {
        if ($rowd['valid_upto']>$datecurrent) {
            if (isset($s)) {
                
                $mysql_run=mysqli_query($cxn, "SELECT a.status,d.extension,a.label,d.filename FROM tb_project b
					left join tb_taxonomy_item a on b.eb_wave=a.id
					left join tb_files_projdlv_his c on c.project_id=b.id
					left join tb_files d on d.id=c.sel_dfile_id
					where b.id=$s
					");
                $result=mysqli_fetch_array($mysql_run);

                $tempFiles = array();

                while ($result=mysqli_fetch_array($mysql_run)) {
                    $tempFiles[] = array('filename' => $result['filename'],
                        'category' => $result['label'],
                        'fileType' => $result['extension'],
                        'status' => $result['status']);
                }

                // initialize SOAP client and call web service function
                $client=new SoapClient($soapClientPath_wsdl, $soapClientOptions);

                try {
                    $resp = $client->getSurveyResultStatus($tempFiles);
                } catch (SoapFault $e) {
                    echo "Something went wrong";
                    print($client->__getLastResponse());
                }

                header("Content-type: text/xml");
                echo $client->__getLastResponse();
            } else {
                echo "Please enter survey number";
            }
        } else {
            echo "Token expired. Please re-generate the token";
        }
    } catch (Exception $e) {
        // echo "token expired";
        echo $e->getMessage();
    }
}
