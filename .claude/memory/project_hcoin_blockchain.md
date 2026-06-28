---
name: HCoin Blockchain Setup
description: Treasury wallet address and blockchain deployment details for HCoin ERC-20 token
type: project
originSessionId: c4691139-d167-459a-8378-ad3cbcb3006b
---
Treasury / deployer wallet address: `0xe24c3C93F3d7919106C15385fB81dcA4DCef2a09`

**Why:** This is the user's MetaMask Polygon address, used as both contract deployer and treasury wallet for the HCoin ERC-20 token.

**How to apply:** Use this address as `_treasury` constructor arg when deploying HCoin.sol via Remix. Also goes into `blockchain-config.php` as `TREASURY_ADDRESS`. Server private key (from MetaMask export) goes in as `TREASURY_PRIVATE_KEY`.

Status: Wallet address confirmed. Next step: get Amoy testnet MATIC from faucet, then deploy via Remix.

Chain: Polygon Amoy testnet (chain 80002) first, then mainnet (chain 137).
Contract: Not yet deployed.
