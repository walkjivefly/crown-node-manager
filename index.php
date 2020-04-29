<?php

namespace App;

ini_set('display_startup_errors',1); 
ini_set('display_errors','on');  // 1
error_reporting(E_ALL); // 11

require_once 'src/Autoloader.php';
Autoloader::register();

// Check IP, deny access if not allowed
if(!(empty(Config::ACCESS_IP) OR $_SERVER['REMOTE_ADDR'] == "127.0.0.1" OR $_SERVER['REMOTE_ADDR'] == "::1" OR $_SERVER['REMOTE_ADDR'] == Config::ACCESS_IP OR $_SERVER['REMOTE_ADDR'] == Config::ACCESS_IP2 OR $_SERVER['REMOTE_ADDR'] == Config::ACCESS_IP3)){
	header('Location: login.html');
	exit; 
}

// Cronjob Rule Run
if(isset($_GET['job']) AND $_GET['job'] === substr(hash('sha256', Config::PASSWORD."ebe8d532"),0,24)){
	require_once 'src/Utility.php';
	$crownd = new jsonRPCClient('http://'.Config::RPC_USER.':'.Config::RPC_PASSWORD.'@'.Config::RPC_IP.'/');
	Rule::run();
	exit;
}


// Start check user session
session_start();
$passToken = hash('sha256', Config::PASSWORD."ibe81rn6");

// Active Session
if(isset($_SESSION['login']) AND $_SESSION['login'] === TRUE){
	// Nothing needs to be done
	
// Login Cookie available	
}elseif(isset($_COOKIE["Login"]) AND $_COOKIE["Login"] == $passToken){
		$_SESSION['login'] = TRUE;
		$_SESSION["csfrToken"] = hash('sha256', random_bytes(20));

// Login		
}elseif(!isset($_SESSION['login']) AND isset($_POST['password']) AND $_POST['password'] == Config::PASSWORD){
	ini_set('session.cookie_httponly', '1');
	$passHashed = hash('sha256', Config::PASSWORD);
	
		$_SESSION['login'] = TRUE;
		$_SESSION["csfrToken"] = hash('sha256', random_bytes(20));
		if(isset($_POST['stayloggedin'])){		
			setcookie("Login", $passToken, time()+2592000, "","",FALSE, TRUE);
		}

// Not logged in or invalid data
//}else{
//	header('Location: login.html');
//	exit; 	
}

// Load ulitily and content creator functions
require_once 'src/Utility.php';
require_once 'src/Content.php';

// Globals
$error = "";
$message = "";
$trafficC = 0;
$trafficCIn = 0;
$trafficCOut = 0;
$newPeersCount = 0;
$crownd = new jsonRPCClient('http://'.Config::RPC_USER.':'.Config::RPC_PASSWORD.'@'.Config::RPC_IP.'/', Config::DEBUG);

// Content
// Main Page
if(empty($_GET) OR $_GET['p'] == "main") {   
	try{
	$content = createMainContent();
	}catch(\Exception $e) {
	   $error = "Node offline or incorrect RPC data";
	}
	$data = array('section' => 'main', 'title' => 'Home', 'content' => $content);   
   
// Peers Page   
}elseif($_GET['p'] == "peers") {
	
	// Information for header
	$content = createPeerContent();
	
	// Create page specfic variables
	$data = array('section' => 'peers', 'title' => 'Peers', 'content' => $content);

// Memory Pool Page	
}elseif($_GET['p'] == "mempool") {
	
	if(isset($_GET['e']) AND ctype_digit($_GET['id'])){
		$end = $_GET['e'];
	}else{
		$end = Config::DISPLAY_TXS;
	}
	
	$content = createMempoolContent($end);
	$data = array('section' => 'mempool', 'title' => 'Memory Pool', 'content' => $content);  
 
// NFT protocols page 
}elseif($_GET['p'] == "protocols") {
	$p1 = (string)Config::DISPLAY_PROTOS;
	$p2 = "0";
	$p3 = "*";
	$p4 = FALSE;
	if(isset($_GET['count'])){
		$p1 = $_GET['count'];
	}
	if(isset($_GET['skip'])){
		$p2 = $_GET['skip'];
	}
	if(isset($_GET['height'])){
		$p3 = $_GET['height'];
	}
	if(isset($_GET['txonly'])){
		$p4 = $_GET['txonly'];
	}
	$content = createNftProtocolsContent($p1, $p2, $p3, $p4);
	$data = array('section' => 'protocols', 'title' => 'NFT protocols', 'content' => $content);  
 
// NFT tokens page 
}elseif($_GET['p'] == "nfts") {
	$p1 = "*";
	$p2 = "*";
	$p3 = (string)Config::DISPLAY_TOKENS;
	$p4 = "0";
	$p5 = "*";
	if(isset($_GET['proto'])){
		$p1 = $_GET['proto'];
	}
	if(isset($_GET['owner'])){
		$p2 = $_GET['owner'];
	}
	if(isset($_GET['count'])){
		$p3 = $_GET['count'];
	}
	if(isset($_GET['skip'])){
		$p4 = $_GET['skip'];
	}
	if(isset($_GET['height'])){
		$p5 = $_GET['height'];
	}
	$content = createNftsContent($p1, $p2, $p3, $p4, $p5);
	$data = array('section' => 'nfts', 'title' => 'NFTs', 'content' => $content);  
 
// NFT competition page 
}elseif($_GET['p'] == "competition") {
	$p1 = "nftcomp";
	$p2 = "*";
	$p3 = (string)Config::DISPLAY_TOKENS;
	$p4 = "0";
	$p5 = "*";
	$content = createNftsContent($p1, $p2, $p3, $p4, $p5);
	$data = array('section' => 'competition', 'title' => 'NFT competition', 'content' => $content);  
 
// CRWcards page 
}elseif($_GET['p'] == "crwcards") {
	$p1 = "ccc";
	$p2 = "*";
	$p3 = (string)Config::DISPLAY_TOKENS;
	$p4 = "0";
	$p5 = "*";
	$content = createNftsContent($p1, $p2, $p3, $p4, $p5);
	$data = array('section' => 'crwcards', 'title' => 'Crown Cards', 'content' => $content);  
 
// Masternodes Page
}elseif($_GET['p'] == "masternodes") {
	$content = createNodesContent("MN");
	$data = array('section' => 'masternodes', 'title' => 'Masternodes', 'content' => $content);  
 
// Systemnodes Page
}elseif($_GET['p'] == "systemnodes") {
	$content = createNodesContent("SN");
	$data = array('section' => 'systemnodes', 'title' => 'Systemnodes', 'content' => $content);  
 
// Proposals Page
}elseif($_GET['p'] == "proposals") {
	$content = createGovernanceContent();
	$data = array('section' => 'proposals', 'title' => 'Proposals', 'content' => $content);

// Blocks Page 
}elseif($_GET['p'] == "blocks") {
	$content= createBlocksContent();
	$data = array('section' => 'blocks', 'title' => 'Blocks', 'content' => $content);
  
// Forks Page 
}elseif($_GET['p'] == "forks") {
	$content= createForksContent();
	$data = array('section' => 'forks', 'title' => 'Forks', 'content' => $content);
  
// Settings Page	
}elseif($_GET['p'] == "settings") {
	if(isset($_GET['c'])  AND $_GET['t'] == $_SESSION["csfrToken"]){
		if(isset($_GET['c']) AND $_GET['c'] == "geosave"){
			// Check if Geo Peer Tracing was changed
			if(isset($_POST['geopeers']) AND $_POST['geopeers'] == "on"){
				 $geoPeers = "true";
			}else{
				$geoPeers = "false";
			}

			// Write new settings in config.php
			if (file_exists('config.php')){
				$conf = file_get_contents('config.php');
				$conf = preg_replace("/geoPeers = (true|false);/i", 'geoPeers = '.$geoPeers.';', $conf);
				file_put_contents('config.php', $conf);
				$message = "Setings succesfully saved";
			}else{
				$error = "Config file does not exists";
			}				
			$message = "Settings succesfully saved";
		}
	}
   $data = array('section' => 'settings', 'title' => 'Settings', 'geoPeers' => Config::PEERS_GEO);

// Sporks page
}elseif($_GET['p'] == "sporks") {
	$content = createSporksContent();
	$data = array('section' => 'sporks', 'title' => 'Sporks', 'content' => $content);  

	
// About Page	
}elseif($_GET['p'] == "about") {
	$data = array('section' => 'about', 'title' => 'About'); 
	
}else{
	header('Location: index.php');
	exit; 	
}


// Create HTML output
if(isset($error)){
	$data['error'] = $error;
}
if(isset($message)){
	$data['message'] = $message;
}

$tmpl = new Template($data);
echo $tmpl->render();

?>