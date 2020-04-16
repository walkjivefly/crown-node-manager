# Crown Platform Monitor v0.1.0

Crown Platform Monitor (CPM) is a visualisation and monitoring system for the Crown Platform network.
![](https://i.imgur.com/h5FCysx.png)


## Features

* Extensive dashboard with general information about the node, connected peers and the blockchain
* Overview of all connected peers inlcuding country, ISP, client, traffic usage, supported services...
* Overview of the last received blocks
* Overview of recent orphaned blocks / alternative chains
* Overview of the memory pool and the inflight transactions
* Overview of NFT framework protocols
* Overview of NFT framework tokens
* Overview of masternodes and systemnodes
* Overview of active proposals in the Crown Decentralised Governance system
* Overview of sporks


## Requirements

* Crown Core 0.14.0.0+
* Web server (e.g. Apache, PHP built-in web server)
* PHP 7.0.0+
* cURL


## Installation

1. Download Crown Platform Monitor either from [here](https://github.com/walkjivefly/crown-node-manager/releases) or by cloning this  repository.
2. Edit `src/Config.php` to enter your crownd RPC credentials, set a password and change other settings.
3. Upload the folder to the public directory of your web server. If the folder is accesible via the internet, I recommend renaming the folder to something unique. Although CPM is password protected and access can be limited to a specific IP, there can be security flaws and bugs.
4. Open the URL to the folder in your browser and login with the password choosen in `src/Config.php`.
5. Optional: Run `chmod -R 770 /path-to-folder/{data, src, views}`. Only necessary for non Apache (`AllowOverride All` necessary) and publicly accessible web server. For more information, read the next section.


## Security
 
* Access to CPM is by default limited to localhost. This can be expanded to a specific IP or disabled. If disabled, make sure to protect the CPM folder (.htaccess, rename it to something unique 
that an attacker will not guess). An attacker could "guess" your password, since there is no build-in brute force protection (if IP protection is disabled).
* The `data` folder contains your rules, logs and geo information about your peers. Make sure to protect (e.g. `chmod -R 770 data`) this sensitive information if your web server is publicly accessible. The previously mentioned
IP protection doesn't work here. If you use `Apache` you are fine, since the folder is protected with `.htaccess` (make sure `AllowOverride All` is set in your `apache2.conf` file).


## Roadmap

- [ ] NFT protocol and token pages enhancements

        * Highlight own protocols/tokens
		* Issue tokens from the protocols page
		* Display full registration information for selected protocol/token (get)
		* Display balanceof for a token
		* Display token totalsupply for a particular protocol

- [ ] Add geo-IP information to MN/SN pages

		* Location in table
		* Map per node type or one map with different colour markers for MN/SN


## Suggested enhancements for community participation

- [ ] Improve project structure
- [ ] Improve error handling
- [ ] Import rules functionality
- [ ] More help icons
- [ ] Use popover for help
- [ ] Display expanded peer/block info (popup)
- [ ] More customization settings
- [ ] Highlight suspicious peers
- [ ] Sort mempool tx, request more
- [ ] Option to import blacklist of spy / resource wasting peers
- [ ] Flag MNs/SNs in peers list
- [ ] Flag own MNs/SNs on those pages



## Donate

If you find the Crown Platform Monitor useful I'm happy to accept donations at 
[CRWHoXPTYBrFdSN8fL15jfdAD6iAapHvpUCQ](https://iquidus-01.crownplatform.com/address/CRWHoXPTYBrFdSN8fL15jfdAD6iAapHvpUCQ)

