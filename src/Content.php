<?php

namespace App;

function createMainContent(){
	global $crownd, $trafficCIn, $trafficCOut, $newPeersCount;

	$blockReward = 5;
	$blocksPerDay = 1440;
	$mnPercent = 0.37;
	$snPercent= 0.08;
	$mintPercent = 0.3;
	$govPercent = 0.25;
	$poolPerDay = $blockReward*$blocksPerDay;
	$mnCollateral = 10000;
	$snCollateral = 500;
	$snShare = $snCollateral/$mnCollateral;
	$mnShare = 1-$snShare;
	
	$peers = getPeerData();
	$peerCount = count($peers);
	$banListInfo = createBanListContent();

	$content = [];
	$content['bannedPeers'] = $banListInfo['totalBans'];
	$content['last24h'] = $banListInfo['lastCount'];
	$content['mnCount'] = $crownd->masternode('count');
	$content['mnEnabledCount'] = $crownd->masternode('count','enabled');
	$content['snCount'] = $crownd->systemnode('count');
	$content['snEnabledCount'] = $crownd->systemnode('count','enabled');

	$content['mnROI'] = round(($poolPerDay*($mnPercent+$mintPercent*$mnShare)/$content['mnEnabledCount'])*365/$mnCollateral*100,1);
	$content['snROI'] = round(($poolPerDay*($snPercent+$mintPercent*$snShare)/$content['snEnabledCount'])*365/$snCollateral*100,1);
	
	$content['nextSuperblock'] = $crownd->mnbudget('nextblock');
	try{
		$content['nfProtosCount'] = $crownd->nftproto('totalsupply');
		$content['nftCount'] = $crownd->nftoken('totalsupply');
	}catch(\Exception $e){
		$content['nfProtosCount'] = "N/A";
		$content['nftCount'] = "N/A";
	}
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
	global $trafficC, $trafficCIn, $trafficCOut, $crownd, $newPeersCount;

	$peers = getPeerData();
	$netinfo = $crownd->getnettotals();

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
	global $crownd, $error;

    // Crown doesn't (yet) support listbanned RPC, fake empty result
	//$banlist = $crownd->listbanned();
    $banlist = [];

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
		// In last 24h
		if($ban['ban_created'] >= time()-86400){
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
	$content['avgTime'] = 0; // after codebase update round($avgTime/(86400*$totalBans),0);

	// Calculate percentage auto/user bans
	$content['autoCount'] = $autoCount;
	$content['userCount'] = $userCount;
	$content['autoPer'] = 0; // after codebase update  round($autoCount/$totalBans,2)*100;
	$content['userPer'] = 0; // after codebase update  round($userCount/$totalBans,2)*100;

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
	global $crownd;

	$content = [];
	$content["totalTx"] = 0;
	$content["totalFees"] = 0;
	$content["totalSize"] = 0;
	$content["segwitCount"] = 0;

	// Get some stuff we'll need later. Shame I can't work out how to do this just once in the Node class 
	// and have it available everywhere but that's OOP shite for you.
	$blockchainInfo = $crownd->getblockchaininfo();
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

	$blockHash = $crownd->getbestblockhash();

	for($i = 0; $i < Config::DISPLAY_BLOCKS; $i++){
		$block = $crownd->getblock($blockHash);
		if($i==0){ 
			$content["latest"] = $block["height"];
		}
		$content["blocks"][$block["height"]]["hash"] = $block["hash"];
		$content["blocks"][$block["height"]]["size"] = round($block["size"]/1000,2);
		$content["totalSize"] += $block["size"];
		$content["blocks"][$block["height"]]["versionhex"] = "N/A";
		$content["blocks"][$block["height"]]["voting"] = "N/A";
		$content["blocks"][$block["height"]]["time"] = getDateTime($block["time"]);
		$content["blocks"][$block["height"]]["timeago"] = round((time() - $block["time"])/60);
		$content["blocks"][$block["height"]]["coinbasetx"] = $block["tx"][0];
		$content["blocks"][$block["height"]]["coinstaketx"] = $block["tx"][1];
		$coinbaseTx = $crownd->getrawtransaction($block["tx"][0], 1);
		$coinstakeTx = $crownd->getrawtransaction($block["tx"][1], 1);
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
	global $crownd;

	$content["recentForks"] = 0;	// Count forks in last 24h

	$forks = $crownd->getchaintips();
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
			$block = $crownd->getblock($fork["hash"]);
			$content["blocks"][$i]["size"] = round($block["size"]/1000,2);
			//$content["blocks"][$i]["versionhex"] = $block["versionHex"];
			//$content["blocks"][$i]["voting"] = getVoting($block["versionHex"]);
			$content["blocks"][$i]["time"] = getDateTime($block["time"]);
			$lastTime = $block["time"];
			$content["blocks"][$i]["timeago"] = round((time() - $block["time"])/3600);
			$content["blocks"][$i]["txcount"] = count($block["tx"]);

			if($content["blocks"][$i]["timeago"] <= 24){
				$content["recentForks"]++;
			}
		}
		$i++;
	}

	$content["timeframe"] = round((time()-$lastTime)/3600);
	$content["forkCount"] = Config::DISPLAY_FORKS - 1;	// Don't count most recent block as a fork
	$content["recentForks"]--;	// Don't count most recent block as a fork

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
	global $crownd;

	$content['txs'] = $crownd->getrawmempool(TRUE);
	$content['txs'] = array_slice($content['txs'], 0, CONFIG::DISPLAY_TXS);
	$content['node'] = new Node();

	return $content;
}

function createNftProtocolsContent($count = 20, $skip = 0, $height = "*", $txonly = FALSE){
	global $crownd;
	try{
		$content['nftProtocols'] = $crownd->nftproto('list', $count, $skip, $height, $txonly);
		$content['request'] = $count." ".$skip." ".$height." ".$txonly;
		$content['nftProtosCount'] = $crownd->nftproto('totalsupply');
		$content['nftCount'] = $crownd->nftoken('totalsupply');
	}catch(\Exception $e){
		$content['nftProtocols'] = [];
		$content['nftProtosCount'] = "N/A";
		$content['nftCount'] = "N/A";
		$content['request'] = "NFT functionality is disabled.";
	}
	//$content['node'] = new Node();
	return $content;
}

function createNftsContent($protocol = "*", $owner = "*", $count = 20, $skip = 0, $height = "*"){
	global $crownd;
	try{
		$content['nftokens'] = $crownd->nftoken("list", $protocol, $owner, $count, $skip, $height);
		$i = 0;
		foreach($content["nftokens"] as $token){
			if($i == Config::DISPLAY_TOKENS){
				break;
			}
			$content["nftokens"][$i]["timestamp"] = getDateTime($token["timestamp"]);
			$i++;
		}
		$content['nftCount'] = $i;
		$content['request'] = $protocol." ".$owner." ".$count." ".$skip." ".$height;
		$content['nftProtosCount'] = $crownd->nftproto('totalsupply');
		$content['totalsupply'] = $crownd->nftoken('totalsupply');
	}catch(\Exception $e){
		$content['nftokens'] = [];
		$content['request'] = "NFT functionality is disabled.";
		$content['nftProtosCount'] = "N/A";
		$content['totalsupply'] = "N/A";
		$content['nftCount'] = "N/A";
	};
	//$content['node'] = new Node();
	return $content;
}

function createNodesContent($type){
	global $crownd;

	if($type == "MN"){
		$nodes = $crownd->masternodelist('full');
		$content["nodeCount"] = $crownd->masternode('count');
		$content["EnabledCount"] = $crownd->masternode('count','enabled');
		$LastWinner = $crownd->masternode('current');
	}else{
		$nodes = $crownd->systemnodelist('full');
		$content["nodeCount"] = $crownd->systemnode('count');
		$content["EnabledCount"] = $crownd->systemnode('count','enabled');
		$LastWinner = $crownd->systemnode('current');
	}

	$content["LastWinner"] = $LastWinner["pubkey"];
	$i = 0;
	foreach($nodes as $txid => $node){
		if($i == Config::DISPLAY_NODES){
			break;
		}
		list($content["node"][$i]["txid"], $unused) = explode("-", $txid);
		list($content["node"][$i]["status"],$content["node"][$i]["protocol"],$content["node"][$i]["address"],
			$content["node"][$i]["IP"], $content["node"][$i]["lastseen"],$content["node"][$i]["activetime"],
			$content["node"][$i]["lastpaid"]) = sscanf($node, "%s %s %s %s %d %d %d");
		$content["node"][$i]["lastseen"] = getDateTime($content["node"][$i]["lastseen"]);
		$content["node"][$i]["lastpaid"] = getDateTime($content["node"][$i]["lastpaid"]);
		if(Config::HUMAN_TIMES) {
			$content["node"][$i]["activetime"] = secondsToHuman($content["node"][$i]["activetime"]);
		}
		$i++;
	}

	return $content;
}

function createGovernanceContent(){
	global $crownd;
	$content["nextSuperblock"] = $crownd->mnbudget("nextblock");
	$proposals = $crownd->mnbudget("show");
	$mnCount = $crownd->masternode("count");
	$currentBlock = $crownd->getblockcount();
	$content["nextDate"] = "Estimated " . date("D j F Y H:i", time()+($content["nextSuperblock"]-$currentBlock)*60);
	$maxBudget = 54000;
	$content["budgetRequested"] = 0;
	$content["budgetPassing"] = 0;
	$content["budgetRemaining"] = $maxBudget;
	$content["pCount"] = 0;
	$i = 0;
    foreach($proposals as $proposal){
		$blockStart = $proposal["BlockStart"];
		$blockEnd = $blockStart + ($proposal["TotalPaymentCount"] - 1) * 43200;
		if($currentBlock <= $blockEnd){
			$content["proposal"][$i]["name"] = $proposal["Name"];
			$content["proposal"][$i]["URL"] = $proposal["URL"];
			$content["proposal"][$i]["hash"] = $proposal["Hash"];
			$content["proposal"][$i]["feeHash"] = $proposal["FeeHash"];
			$content["proposal"][$i]["firstBlock"] = $proposal["BlockStart"];
			$content["proposal"][$i]["totalPaymentCount"] = $proposal["TotalPaymentCount"];
			$content["proposal"][$i]["remainingPaymentCount"] = $proposal["RemainingPaymentCount"];
			$content["proposal"][$i]["paymentAddress"] = $proposal["PaymentAddress"];
			$content["proposal"][$i]["yeas"] = $proposal["Yeas"];
			$content["proposal"][$i]["nays"] = $proposal["Nays"];
			$content["proposal"][$i]["abstains"] = $proposal["Abstains"];
			$content["proposal"][$i]["totalPayment"] = $proposal["TotalPayment"];
			$content["proposal"][$i]["monthlyPayment"] = $proposal["MonthlyPayment"];
			$content["budgetRequested"] += $proposal["MonthlyPayment"];
			$content["proposal"][$i]["passingMargin"] = ($proposal["Yeas"]-$proposal["Nays"]-$proposal["Abstains"]);
			if($content["proposal"][$i]["passingMargin"] > $mnCount / 10) {
				$content["proposal"][$i]["passing"] = "Yes";
				$content["budgetPassing"] += $proposal["MonthlyPayment"];
			}else{
				$content["proposal"][$i]["passing"] = "No";
			}
			$i++;
		}
	}
	$content["pCount"] = $i;
	$content["budgetRemaining"] -= $content["budgetRequested"];
	if($content["budgetRequested"] > $maxBudget){
		$content["reqColour"] = "red";
	}elseif($content["budgetRequested"] > $maxBudget * 0.9){
		$content["reqColour"] = "orange";
	}else{
		$content["reqColour"] = "green";
	}
	if($content["budgetPassing"] > $maxBudget){
		$content["passingColour"] = "red";
	}elseif($content["budgetPassing"] > $maxBudget * 0.9){
		$content["passingColour"] = "orange";
	}else{
		$content["passingColour"] = "green";
	}
	if($content["budgetRemaining"] < 0){
		$content["remainingColour"] = "red";
	}elseif($content["budgetRemaining"] < $maxBudget * 0.1){
		$content["remainingColour"] = "orange";
	}else{
		$content["remainingColour"] = "green";
	}
	return $content;
}

function createSporksContent(){
	global $crownd;
	$sporks = $crownd->spork("show");
	$content["SporksEnabled"] = 0;
	$rightNow = time();
	$i = 0;
	foreach($sporks as $spork => $value){
		$content['sporks'][$i]['name'] = $spork;
		$content['sporks'][$i]['value'] = $value;
		$content['sporks'][$i]['timestamp'] = getDateTime($value);
		if($value <= $rightNow) {
			$content['sporks'][$i]['status'] = "On";
			$content['SporksEnabled']++;
		} else {
			$content['sporks'][$i]['status'] = "Off";
		}
		$i++;
	}
	$content['SporksCount'] = $i++;
	$content['node'] = new Node();
	return $content;
}

function createUnspentContent(){
	global $crownd, $error;
	
	$content = [];
	
	try{
		$unspents = $crownd->listunspent();
	}catch(\Exception $e){
		$error = "Wallet disabled!";
		return "";
	}
	$i = 0;

	foreach($unspents as $unspent){

		$content["utxo"][$i]["hash"] = $unspent["txid"];
		$content["utxo"][$i]["vout"] = $unspent["vout"];
		$content["utxo"][$i]["address"] = $unspent["address"];
		$content["utxo"][$i]["account"] = $unspent["account"];
		$content["utxo"][$i]["scriptpubkey"] = $unspent["scriptPubKey"];
		$content["utxo"][$i]["amount"] = $unspent["amount"];
		$content["utxo"][$i]["confs"] = $unspent["confirmations"];
		$content["utxo"][$i]["spendable"] = $unspent["spendable"];
		$i++;
	}
	$content['utxoCount'] = $i;
	$content['node'] = new Node();
	return $content;
}
?>
