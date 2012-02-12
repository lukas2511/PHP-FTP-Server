<?php

$users=array(
	"test" => "blabla",
);

$homes=array(
	"test" => "/opt/testdir",
);

$chroots=array(
	"test" => false,
);

function debug($msg){
	//echo "[DD] ".$msg."\n";
}

function send($socket,$msg){
	$msg=strtr($msg,array("\n"=>"","\0"=>"","\r"=>""));
	echo "[>>] ".$msg."\n";
	return socket_write($socket,$msg."\r\n");
}

function rdir($user,$homes,$chroots,$dir,$cdir){
	debug("$user $dir $cdir");
	while((substr($dir,-1)=="/" && $dir!="/")) $dir=substr($dir,0,-1);
	if(!isset($chroots[$user]) || !$chroots[$user]){
		if(substr($dir,0,1)=="/" && is_dir($dir)){
			return realpath($dir);
		}elseif(is_dir($cdir."/".$dir)){
			return realpath($cdir."/".$dir);
		}else{
			return false;
		}
	}else{
		return false; // TODO
	}
}

function rfile($user,$homes,$chroots,$file,$dir){
	debug("$user $file $dir");
	if(!isset($chroots[$user]) || !$chroots[$user]){
		if(substr($file,0,1)=="/" && is_file($file)){
			return realpath($file);
		}elseif(is_file($dir."/".$file)){
			return realpath($dir."/".$file);
		}else{
			return false;
		}
	}else{
		return false; // TODO
	}
}

function rdirname($user,$homes,$chroots,$dir,$reverse=false){
	if(!isset($chroots[$user]) || !$chroots[$user]){
		$rdir=$dir;
	}else{
		$rdir=false; // TODO
	}

	return $rdir;
}

// $socket

send($socket,"220---------- Welcome to PHP-FTPd ----------\r\n");
//send($socket,"220-You are user number 1 of 50 allowed.\r\n");
send($socket,"220-Local time is now ".date("H:i").".\r\n");
send($socket,"220 This is a private system - No anonymous login\r\n");
//send($socket,"220 You will be disconnected after 15 minutes of inactivity.\r\n");

$user="";
$pass="";
$login=false;
$dir="/";
$type="8-bit binary";

while(($read=@socket_read($socket,2048,PHP_NORMAL_READ))!==false){
	$read=strtr($read,array("\n"=>"","\r"=>"","\0"=>""));
	if(!empty($read)){
		echo "[<<] ".$read."\n";
		$data=explode(" ",$read,2);

		if($data[0]=="SYST"){
				send($socket,"215 UNIX Type: L8\r\n");
		}elseif(!$login){
			if($data[0]=="USER" && preg_match("/^([a-z0-9]+)$/",$data[1])){
				$user=$data[1];
				send($socket,"331 User $user OK. Password required\r\n");
			}elseif($data[0]=="USER"){
				send($socket,"530 Login authentication failed\r\n");
			}elseif($data[0]=="PASS"){
				$pass=$data[1];
				if(isset($users[$user]) && $users[$user]==$pass){
					if(isset($chroots[$user]) && $chroots[$user]) { $dir="/"; } else { $dir=$homes[$user]; }
					//chdir($dir);
					send($socket,"230 OK. Current restricted directory is ".rdirname($user,$homes,$chroots,$dir)."\r\n");
					$login=true;
					//send($socket,"230 0 Kbytes used (0%) - authorized: 102400 Kb\r\n");
				}else{
					send($socket,"530 Login authentication failed\r\n");
				}
			}else{
				send($socket,"530 You aren't logged in\r\n");
			}
		}else{
			if($data[0]=="SYST"){
				send($socket,"215 UNIX Type: L8\r\n");
			}elseif($data[0]=="QUIT"){
				send($socket,"221 Goodbye.\r\n");
				unset($socket);
				die();
			}elseif($data[0]=="PORT"){
				$port=explode(",",$data[1]);
				if(count($port)!=6){
					send($socket,"501 Syntax error in IP address\r\n");
				}else{
					if(!is_numeric($port[0]) || $port[0]<1 || $port[0]>254){
						send($socket,"501 Syntax error in IP address\r\n");
					}elseif(!is_numeric($port[1]) || $port[1]<0 || $port[1]>254){
						send($socket,"501 Syntax error in IP address\r\n");
					}elseif(!is_numeric($port[2]) || $port[2]<0 || $port[2]>254){
						send($socket,"501 Syntax error in IP address\r\n");
					}elseif(!is_numeric($port[3]) || $port[3]<1 || $port[3]>254){
						send($socket,"501 Syntax error in IP address\r\n");
					}elseif(!is_numeric($port[4]) || $port[4]<1 || $port[4]>500){
						send($socket,"501 Syntax error in IP address\r\n");
					}elseif(!is_numeric($port[5]) || $port[5]<1 || $port[5]>500){
						send($socket,"501 Syntax error in IP address\r\n");
					}else{
						$ip=$port[0].".".$port[1].".".$port[2].".".$port[3];
						$port=hexdec(dechex($port[4]).dechex($port[5]));
						if($port<1024){
							send($socket,"501 Sorry, but I won't connect to ports < 1024\r\n");
						}elseif($port>65000){
							send($socket,"501 Sorry, but I won't connect to ports > 650000\r\n");
						}else{
							$ftpsock=@fsockopen($ip,$port);
							if($ftpsock){
								send($socket,"200 PORT command successful\r\n");
							}else{
								send($socket,"501 Connection failed\r\n");
							}
						}
					}
				}
			}elseif($data[0]=="LIST"){
				$filelist="";
				send($socket,"150 Opening ASCII mode data connection for file list\r\n");
				$rdir=rdirname($user,$homes,$chroots,$dir,true);
				if($handle = opendir($rdir)){
					while (false !== ($file = readdir($handle))) {
						if ($file != "." && $file != ".." && (substr($file,0,1)!="." || (isset($data[1]) && preg_match("/\-(.*)a/",$data[1]))) ) {
							$stats=stat($rdir."/".$file);
							if(is_dir($rdir."/".$file)) $mode="d"; else $mode="-";
							$moded=sprintf("%o", ($stats['mode'] & 000777));
							$mode1=substr($moded,0,1);
							$mode2=substr($moded,1,1);
							$mode3=substr($moded,2,1);
							switch($mode1){case "0": $mode.="---"; break;case "1": $mode.="--x"; break;case "2": $mode.="-w-"; break;case "3": $mode.="-wx"; break;case "4": $mode.="r--"; break;case "5": $mode.="r-x"; break;case "6": $mode.="rw-"; break;case "7": $mode.="rwx"; break;}
							switch($mode2){case "0": $mode.="---"; break;case "1": $mode.="--x"; break;case "2": $mode.="-w-"; break;case "3": $mode.="-wx"; break;case "4": $mode.="r--"; break;case "5": $mode.="r-x"; break;case "6": $mode.="rw-"; break;case "7": $mode.="rwx"; break;}
							switch($mode3){case "0": $mode.="---"; break;case "1": $mode.="--x"; break;case "2": $mode.="-w-"; break;case "3": $mode.="-wx"; break;case "4": $mode.="r--"; break;case "5": $mode.="r-x"; break;case "6": $mode.="rw-"; break;case "7": $mode.="rwx"; break;}
							$uidfill=""; for($i=strlen($stats['uid']);$i<5;$i++) $uidfill.=" ";
							$gidfill=""; for($i=strlen($stats['gid']);$i<5;$i++) $gidfill.=" ";
							$sizefill=""; for($i=strlen($stats['size']);$i<11;$i++) $sizefill.=" ";
							$nlinkfill=""; for($i=strlen($stats['nlink']);$i<5;$i++) $nlinkfill.=" ";
							$mtime=date("M d H:i",$stats['mtime']);
							$filelist.=$mode.$nlinkfill.$stats['nlink']." ".$stats['uid'].$uidfill.$stats['gid'].$gidfill.$sizefill.$stats['size']." ".$mtime." ".$file."\r\n";
						}
					}
					closedir($handle);
				}
				fwrite($ftpsock,$filelist);
				fclose($ftpsock);
				send($socket,"226 Transfer complete.\r\n");
 			}elseif($data[0]=="TYPE"){
				switch($data[1]){
					case "A": $type="ASCII"; break;
					case "I": $type="8-bit binary"; break;
				}
				send($socket,"200 TYPE is now ".$type);
			}elseif($data[0]=="CWD"){
				$cdir=$dir;
				if(($dir=rdir($user,$homes,$chroots,$data[1],$cdir))!=false){
					//chdir($dir);
					send($socket,"250 OK. Current directory is ".rdirname($user,$homes,$chroots,$dir)."\r\n");
				}else{
					$dir=$cdir;
					send($socket,"550 Can't change directory to ".$data[1].": No such file or directory\r\n");
				}
			}elseif($data[0]=="RETR"){
				if(($file=rfile($user,$homes,$chroots,$data[1],$dir))!=false){
					send($socket,"150 Connecting to client");
					if($fp=@fopen($file,"r")){
						while(!@feof($fp)){
							$cont=@fread($fp,1024);
							if(!@fwrite($ftpsock,$cont)) break;
						}
						if(@fclose($fp) && @fclose($ftpsock)){
							send($socket,"226 File successfully transferred");
						}else{
							send($socket,"550 Error during file-transfer");
						}
					}else{
						send($socket,"550 Can't open ".$data[1].": Permission denied");
					}
				}else{
					send($socket,"550 Can't open ".$data[1].": No such file or directory");
				}
			}elseif($data[0]=="STOR"){
				if(substr($data[1],0,1)!="/"){
					$rdir=rdirname($user,$homes,$chroots,$dir,true);
					$data[1]=$rdir."/".$data[1];
				}
				debug("File: ".$rdir."/".$data[1]);
				$fp=@fopen($data[1],"w");
				if(!$fp){
					send($socket,"553 Can't open that file: Permission denied");
				}else{
					send($socket,"150 Connecting to client");
					while(!@feof($ftpsock)){
						$cont=@fread($ftpsock,1024);
						if(!$cont) break;
						if(!@fwrite($fp,$cont)) break;
					}
					if(@fclose($fp) && @fclose($ftpsock)){
						send($socket,"226 File successfully transferred");
					}else{
						send($socket,"550 Error during file-transfer");
					}
				}
			}elseif($data[0]=="PWD"){
				send($socket,"257 \"".rdirname($user,$homes,$chroots,$dir)."\" is your current location");
			}elseif($data[0]=="SITE"){
				if(substr($data[1],0,6)=="CHMOD "){
					$chmod=explode(" ",$data[1],3);
					if(substr($chmod[2],0,1)!="/"){
						$rdir=rdirname($user,$homes,$chroots,$dir,true);
						$chmod[2]=$rdir."/".$chmod[2];
					}
					if(($file=rfile($user,$homes,$chroots,$chmod[2],$dir))!=false){
						if(@chmod($chmod[2],octdec($chmod[1]))){
							send($socket,"200 Permissions changed on test.img");
						}else{
							send($socket,"550 Could not change perms on ".$chmod[2].": Permission denied");
						}
					}else{
						send($socket,"550 Could not change perms on ".$chmod[2].": No such file or directory");
					}
				}else{
					send($socket,"500 Unknown Command\r\n");
				}
			}elseif($data[0]=="DELE"){
				if(substr($data[1],0,1)!="/"){
					$rdir=rdirname($user,$homes,$chroots,$dir,true);
					$data[1]=$rdir."/".$data[1];
				}
				if(!file_exists($data[1])){
					send($socket,"550 Could not delete ".$data[1].": No such file or directory");
				}elseif(@unlink($data[1])){
					send($socket,"250 Deleted ".$data[1]);
				}else{
					send($socket,"550 Could not delete ".$data[1].": Permission denied");
				}
			}elseif($data[0]=="RNFR"){
				if(substr($data[1],0,1)!="/"){
					$rdir=rdirname($user,$homes,$chroots,$dir,true);
					$data[1]=$rdir."/".$data[1];
				}
				if(!file_exists($data[1])){
					send($socket,"550 Sorry, but that file doesn't exist");
				}else{
					$rnfr=$data[1];
					send($socket,"350 RNFR accepted - file exists, ready for destination");
				}
			}elseif($data[0]=="RNTO" && isset($rnfr)){
				if(substr($data[1],0,1)!="/"){
					$rdir=rdirname($user,$homes,$chroots,$dir,true);
					$data[1]=$rdir."/".$data[1];
				}
				if(!is_dir(dirname($data[1]))){
					send($socket,"451 Rename/move failure: No such file or directory");
				}elseif(@rename($rnfr,$data[1])){
					send($socket,"250 File successfully renamed or moved");
				}else{
					send($socket,"451 Rename/move failure: Operation not permitted");
				}
				unset($rnfr);
			}elseif($data[0]=="MKD"){
				if(substr($data[1],0,1)!="/"){
					$rdir=rdirname($user,$homes,$chroots,$dir,true);
					$data[1]=$rdir."/".$data[1];
				}
				if(is_dir(dirname($data[1])) && file_exists($data[1])){
					send($socket,"550 Can't create directory: File exists");
				}elseif(is_dir(dirname($data[1]))){
					if(@mkdir($data[1])){
						send($socket,"257 \"".$data[1]."\" : The directory was successfully created");
					}else{
						send($socket,"550 Can't create directory: Permission denied");
					}
				}else{
					send($socket,"550 Can't create directory: No such file or directory");
				}
			}elseif($data[0]=="RMD"){
				if(substr($data[1],0,1)!="/"){
					$rdir=rdirname($user,$homes,$chroots,$dir,true);
					$data[1]=$rdir."/".$data[1];
				}
				if(is_dir(dirname($data[1])) && is_dir($data[1])){
					if(count(glob($data[1]."/*"))){
						send($socket,"550 Can't remove directory: Directory not empty");
					}elseif(@rmdir($data[1])){
						send($socket,"250 The directory was successfully removed");
					}else{
						send($socket,"550 Can't remove directory: Operation not permitted");
					}
				}elseif(is_dir(dirname($data[1])) && file_exists($data[1])){
					send($socket,"550 Can't remove directory: Not a directory");
				}else{
					send($socket,"550 Can't create directory: No such file or directory");
				}
			}

			else{
				send($socket,"500 Unknown Command\r\n");
			}
		}
	}
}

die();

?>
