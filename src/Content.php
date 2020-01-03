<?php

namespace App;

function createMainContent(){
	global $trafficCIn, $trafficCOut, $newPeersCount;


	$peers = getPeerData();
	$peerCount = count($peers);
	$banListInfo = createBanListContent();

	$content = [];
	$content['bannedPeers'] = $banListInfo['totalBans'];
	$content['last24h'] = $banListInfo['lastCount'];
	$content['node'] = new Node();
	if(Config::PEERS_GEO){
		$content['map'] = createMapJs($peerCount);
	}
	$content['geo'] = Config::PEERS_GEO;
	$content['nPeers'] = $newPeersCount;
	$content['chartData'] = getTopClients($peers);

	// Current peers traffic
	$content['trafcin'] = round($trafficCIn/1000, 2);
	$content['trafcout'] = round($trafficCOut/1000, 2);

	return $content;
	
}

function createPeerContent(){
	global $trafficC, $trafficCIn, $trafficCOut, $bitcoind, $newPeersCount;

	$peers = getPeerData();
	$netinfo = $bitcoind->getnettotals();

	$content = getMostPop($peers);
	$content['peers'] = $peers;
	$content['tPeers'] = count($peers);
	$content['nPeers'] = $newPeersCount;
	$content['segWitP'] = round($content['segWitC']/$content['tPeers'],2)*100;
	$content['cTraf'] = round($trafficC/1000,2);
	$content['trafcin'] = round($trafficCIn/1000,2);
	$content['trafcout'] = round($trafficCOut/1000,2);
	$content['tTraf'] = ($netinfo["totalbytesrecv"] + $netinfo["totalbytessent"])/1000000;
	$content['cTrafP'] = round($content['cTraf']/$content['tTraf'],2)*100;
	$content['geo'] = Config::PEERS_GEO;

	return $content;
}

function createBanListContent(){
	global $bitcoind, $error;

        // Crown doesn't (yet) support listbanned RPC, fake empty result
	//$banlist = $bitcoind->listbanned();
        $banlist = '';

	$content = [];
	$lastCount = 0;
	$autoCount = 0;
	$autoPerc = 0;
	$userCount = 0;
	$userPerc  = 0;
	$avgTime = 0;
	$settCore = 0;

	// Total Bans
	$totalBans = count($banlist);

	foreach($banlist as &$ban){
		 // In last 25h
		if($ban['ban_created'] >= time()-24*3600){
			$lastCount++;
		}
		 // Auto/User Ban Count
		$ban['ban_reason'] = getBanReason($ban['ban_reason']);
		if($ban['ban_reason'] == "Auto"){
			$autoCount++;
		}else{
			$userCount++;
		}

		// Sum up all ban time
		$avgTime += $ban['banned_until']-$ban['ban_created'];

		// Calculate Core ban time settings (only done once)
		if($settCore == 0){
			if($ban['ban_reason'] == "Auto"){
			   $settCore = (int)$ban['banned_until'] - (int)$ban['ban_created'];
			}
		}

		$ban['ban_duration'] = round(($ban['banned_until'] - $ban['ban_created'])/86400,1);
		$ban['ban_created'] = getDateTime($ban['ban_created']);
		$ban['banned_until'] = getDateTime($ban['banned_until']);
		if(!checkIpBanList($ban['address'])){
			$error = "Invalid ban list IP";
			return false;
		}
		$ban['ipv6'] = checkIfIpv6($ban['address']);
	}

	// Calculate and format avergae ban time
	$content['avgTime'] = round($avgTime/(86400*$totalBans),0);

	// Calculate percentage auto/user bans
	$content['autoCount'] = $autoCount;
	$content['userCount'] = $userCount;
	$content['autoPer'] = round($autoCount/$totalBans,2)*100;
	$content['userPer'] = round($userCount/$totalBans,2)*100;

	$content['totalBans'] = $totalBans;
	$content['lastCount'] = $lastCount;

	// Setting Core Setting and check if default
	$content['settCore'] = $settCore/86400;
	if($content['settCore'] != 1){
		$content['settCoreMode'] = "Custom";
	}else{
	   $content['settCoreMode'] = "Default";
	}

	// List of all banned peers
	$content['banList'] = $banlist;
	

	return $content;
}

function createBlocksContent(){
	global $bitcoind;

	$content = [];
	$content["totalTx"] = 0;
	$content["totalFees"] = 0;
	$content["totalSize"] = 0;
	$content["segwitCount"] = 0;

	// Get some stuff we'll need later. Shame I can't work out how to do this just once in the Node class 
	// and have it available everywhere but that's OOP shite for you.
	$blockchainInfo = $bitcoind->getblockchaininfo();
	$network = $blockchainInfo["chain"];  // main or test or dev
	if($network == "main"){
		$basefee = 3.75;
		$blocktime = 60;
		$sbinterval = 43200;
	}else{
		$basefee = 1.875;
		$blocktime = 90;
		$sbinterval = 50;
	}

	$blockHash = $bitcoind->getbestblockhash();

	for($i = 0; $i < Config::DISPLAY_BLOCKS; $i++){
		$block = $bitcoind->getblock($blockHash);
		$content["blocks"][$block["height"]]["hash"] = $block["hash"];
		$content["blocks"][$block["height"]]["size"] = round($block["size"]/1000,2);
		$content["totalSize"] += $block["size"];
		$content["blocks"][$block["height"]]["versionhex"] = "N/A";
		$content["blocks"][$block["height"]]["voting"] = "N/A";
		$content["blocks"][$block["height"]]["time"] = getDateTime($block["time"]);
		$content["blocks"][$block["height"]]["timeago"] = round((time() - $block["time"])/60);
		$content["blocks"][$block["height"]]["coinbasetx"] = $block["tx"][0];
		$content["blocks"][$block["height"]]["coinstaketx"] = $block["tx"][1];
		$coinbaseTx = $bitcoind->getrawtransaction($block["tx"][0], 1);
		$coinstakeTx = $bitcoind->getrawtransaction($block["tx"][1], 1);
		$coinbase = $coinbaseTx["vout"][1]["value"] + $coinbaseTx["vout"][2]["value"];
		$coinstake = $coinstakeTx["vout"][0]["value"];
		$superblock = $block["height"] % $sbinterval == 0;
		if($superblock){
			$content["blocks"][$block["height"]]["fees"] = 0;
		}else{
			$content["blocks"][$block["height"]]["fees"] = round($coinbase + $coinstake - $basefee, 5);
		}
		//$content["blocks"][$block["height"]]["fees"] = $coinbase;
		$content["totalFees"] += $content["blocks"][$block["height"]]["fees"];
		$content["blocks"][$block["height"]]["txcount"] = count($block["tx"]);
		$content["totalTx"] += $content["blocks"][$block["height"]]["txcount"];
		$blockHash = $block["previousblockhash"];
	}
	$content["avgTxSize"] = round(($content["totalSize"]/($content["totalTx"]))/1000,2);
	$content["avgSize"] = round($content["totalSize"]/(Config::DISPLAY_BLOCKS*1000),2);
	$content["totalSize"] = round($content["totalSize"]/1000000,2);
	$content["avgFee"] = round($content["totalFees"]/Config::DISPLAY_BLOCKS,2);
	$content["totalFees"] = round($content["totalFees"],2);
	$content["numberOfBlocks"] = Config::DISPLAY_BLOCKS;
	$content["timeframe"] = round(end($content["blocks"])["timeago"]/$blocktime,0);

	return $content;
}

function createForksContent(){
	global $bitcoind;

	// Count forks in last 24h
	$timeAgo = time()-86400;
	$content["recentForks"] = 0;

	$forks = $bitcoind->getchaintips();
	$i = 0;
	$lastTime = 0;

	foreach($forks as $fork){
		if($i == Config::DISPLAY_FORKS){
			break;
		}

		$content["blocks"][$i]["height"] = $fork["height"];
		$content["blocks"][$i]["hash"] = $fork["hash"];
		$content["blocks"][$i]["forklength"] = $fork["branchlen"];
		$content["blocks"][$i]["status"] = $fork["status"];
		$content["blocks"][$i]["succeeded"] = $fork["height"];

		if($fork["status"] != "headers-only" AND $fork["status"] != "unknown"){
			$block = $bitcoind->getblock($fork["hash"]);
			$content["blocks"][$i]["size"] = round($block["size"]/1000,2);
			$content["blocks"][$i]["versionhex"] = $block["versionHex"];
			$content["blocks"][$i]["voting"] = getVoting($block["versionHex"]);
			$content["blocks"][$i]["time"] = getDateTime($block["time"]);
			$lastTime = $block["time"];
			$content["blocks"][$i]["timeago"] = round((time() - $block["time"])/86400);
			$content["blocks"][$i]["txcount"] = count($block["tx"]);

			if($content["blocks"][$i]["time"] >= $timeAgo){
				$content["recentForks"]++;
			}
		}
		$i++;
	}

	// How far to go back (days)
	$content["timeframe"] = round((time()-$lastTime)/86400);
	$content["forkCount"] = Config::DISPLAY_FORKS;

	return $content;
}

/**
 * @param null $editID
 * @return mixed
 */
function createRulesContent($editID = NULL){

	$rulesContent['rules'] = Rule::getRules();
	$rulesContent['jobToken'] = substr(hash('sha256', CONFIG::PASSWORD."ebe8d532"),0,24);
	$rulesContent['editRule'] = new Rule();

	if (file_exists('data/rules.log')){
		$log = file_get_contents('data/rules.log');
	}else{
		$log = "No logs available";
	}
	$rulesContent['log'] = $log;


	if(!is_null($editID)){
		$response = Rule::getByID($_GET['id']);
		if($response != FALSE){
			$rulesContent['editRule'] = $response;
		// TODO: Return repsonse to controller
		}else{
			$error = "Couldn't find Rule!";
		}
	}

	return $rulesContent;
}

function createMempoolContent(){
	global $bitcoind;

	$content['txs'] = $bitcoind->getrawmempool(TRUE);
	$content['txs'] = array_slice($content['txs'], 0, CONFIG::DISPLAY_TXS);
	$content['node'] = new Node();

	return $content;
}

function createUnspentContent(){
	global $bitcoind, $error;
	
	try{
		$unspents = $bitcoind->listunspent();
	}catch(\Exception $e){
		$error = "Wallet disabled!";
		return "";
	}
	$i = 0;
	$lastTime = 0;

	foreach($unspents as $unspent){

	$content["utxo"][$i]["hash"] = $unspent["txid"];
	$content["utxo"][$i]["vout"] = $unspent["vout"];
	$content["utxo"][$i]["address"] = $unspent["address"];
	$content["utxo"][$i]["account"] = $unspent["account"];
	$content["utxo"][$i]["scriptpubkey"] = $unspent["scriptPubKey"];
	$content["utxo"][$i]["amount"] = $unspent["amount"];
	$content["utxo"][$i]["confs"] = $unspent["confirmations"];
	$content["utxo"][$i]["spendable"] = $unspent["spendable"];
	$content["utxo"][$i]["solvable"] = $unspent["solvable"];
	$content["utxo"][$i]["safe"] = $unspent["safe"];
	$content['node'] = new Node();

	$i++;
	
	
	}
	return $content;
}
?>
