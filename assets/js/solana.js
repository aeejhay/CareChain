// CareChain — Solana Integration
// Handles: Phantom wallet, credential NFT minting, shift escrow payments

const SOLANA_NETWORK = 'devnet';
const SOLANA_RPC = 'https://api.devnet.solana.com';

let connection = null;
let walletPublicKey = null;

// Initialize Solana connection
function initSolana() {
    try {
        connection = new solanaWeb3.Connection(SOLANA_RPC, 'confirmed');
        updateSolanaStatus(true);
        console.log('Solana devnet connected');
    } catch (err) {
        console.error('Solana connection failed:', err);
        updateSolanaStatus(false);
    }
}

// Update the status indicator in footer
function updateSolanaStatus(active) {
    const dot = document.getElementById('solanaStatusDot');
    const text = document.getElementById('solanaStatusText');
    if (dot) {
        dot.classList.toggle('active', active);
    }
    if (text) {
        text.textContent = active ? 'Solana Devnet — Connected' : 'Solana Devnet — Disconnected';
    }
}

// Check if Phantom wallet is installed
function getPhantom() {
    if ('solana' in window) {
        const provider = window.solana;
        if (provider.isPhantom) {
            return provider;
        }
    }
    return null;
}

// Connect Phantom wallet
async function connectWallet() {
    const phantom = getPhantom();
    
    if (!phantom) {
        alert('Phantom wallet not found! Please install it from phantom.app');
        window.open('https://phantom.app/', '_blank');
        return;
    }
    
    try {
        const response = await phantom.connect();
        walletPublicKey = response.publicKey;
        
        const walletBtn = document.getElementById('connectWallet');
        const shortAddr = walletPublicKey.toString().slice(0, 4) + '...' + walletPublicKey.toString().slice(-4);
        walletBtn.textContent = shortAddr;
        walletBtn.classList.add('connected');
        
        // Save wallet address to backend
        await saveWalletAddress(walletPublicKey.toString());
        
        console.log('Wallet connected:', walletPublicKey.toString());
    } catch (err) {
        console.error('Wallet connection failed:', err);
    }
}

// Save wallet address to PHP backend
async function saveWalletAddress(address) {
    try {
        const formData = new FormData();
        formData.append('action', 'save_wallet');
        formData.append('wallet_address', address);
        
        await fetch('/carechain/api/wallet.php', {
            method: 'POST',
            body: formData
        });
    } catch (err) {
        console.error('Failed to save wallet:', err);
    }
}

// Get wallet SOL balance
async function getBalance() {
    if (!walletPublicKey || !connection) return 0;
    
    try {
        const balance = await connection.getBalance(walletPublicKey);
        return balance / solanaWeb3.LAMPORTS_PER_SOL;
    } catch (err) {
        console.error('Balance check failed:', err);
        return 0;
    }
}

// Request devnet airdrop (for testing)
async function requestAirdrop() {
    if (!walletPublicKey || !connection) {
        alert('Connect your wallet first!');
        return;
    }
    
    try {
        const signature = await connection.requestAirdrop(
            walletPublicKey,
            solanaWeb3.LAMPORTS_PER_SOL * 1
        );
        await connection.confirmTransaction(signature);
        
        const balance = await getBalance();
        alert('Airdrop successful! Balance: ' + balance.toFixed(4) + ' SOL');
        console.log('Airdrop tx:', signature);
    } catch (err) {
        console.error('Airdrop failed:', err);
        alert('Airdrop failed. Try again in a moment.');
    }
}

// Mint credential as soulbound token (simplified for hackathon)
// In production this would call an Anchor program
async function mintCredentialNFT(credentialType, workerName, docId) {
    if (!walletPublicKey || !connection) {
        alert('Connect your wallet first!');
        return null;
    }
    
    const phantom = getPhantom();
    if (!phantom) return null;
    
    try {
        // Create a memo transaction as proof-of-concept credential
        // In production: this calls the Anchor program to mint a soulbound NFT
        const transaction = new solanaWeb3.Transaction();
        
        // Add memo with credential data
        const credentialData = JSON.stringify({
            type: 'CareChain_Credential',
            credential: credentialType,
            holder: workerName,
            issued: new Date().toISOString(),
            network: 'solana-devnet'
        });
        
        // Create a simple transfer to self as on-chain proof
        transaction.add(
            solanaWeb3.SystemProgram.transfer({
                fromPubkey: walletPublicKey,
                toPubkey: walletPublicKey,
                lamports: 1 // Minimal transfer as credential anchor
            })
        );
        
        transaction.feePayer = walletPublicKey;
        const { blockhash } = await connection.getLatestBlockhash();
        transaction.recentBlockhash = blockhash;
        
        const signed = await phantom.signTransaction(transaction);
        const txSignature = await connection.sendRawTransaction(signed.serialize());
        await connection.confirmTransaction(txSignature);
        
        // Save NFT address to backend
        await saveCredentialToBackend(docId, txSignature);
        
        console.log('Credential minted! Tx:', txSignature);
        return txSignature;
        
    } catch (err) {
        console.error('Minting failed:', err);
        alert('Credential minting failed: ' + err.message);
        return null;
    }
}

// Save credential transaction to PHP backend
async function saveCredentialToBackend(docId, txSignature) {
    try {
        const formData = new FormData();
        formData.append('action', 'save_credential');
        formData.append('doc_id', docId);
        formData.append('tx_signature', txSignature);
        
        await fetch('/carechain/api/wallet.php', {
            method: 'POST',
            body: formData
        });
    } catch (err) {
        console.error('Failed to save credential:', err);
    }
}

// Create shift escrow payment
async function createShiftEscrow(shiftId, amountSOL) {
    if (!walletPublicKey || !connection) {
        alert('Connect your wallet first!');
        return null;
    }
    
    const phantom = getPhantom();
    if (!phantom) return null;
    
    try {
        // In production: this creates a PDA escrow account via Anchor
        // For hackathon: we send SOL to a derived escrow address
        const escrowSeed = 'carechain-escrow-' + shiftId;
        
        const transaction = new solanaWeb3.Transaction();
        
        // Transfer to escrow (simplified — in production uses PDA)
        transaction.add(
            solanaWeb3.SystemProgram.transfer({
                fromPubkey: walletPublicKey,
                toPubkey: walletPublicKey, // Self-transfer as demo
                lamports: Math.floor(amountSOL * solanaWeb3.LAMPORTS_PER_SOL)
            })
        );
        
        transaction.feePayer = walletPublicKey;
        const { blockhash } = await connection.getLatestBlockhash();
        transaction.recentBlockhash = blockhash;
        
        const signed = await phantom.signTransaction(transaction);
        const txSignature = await connection.sendRawTransaction(signed.serialize());
        await connection.confirmTransaction(txSignature);
        
        // Save escrow to backend
        const formData = new FormData();
        formData.append('action', 'save_escrow');
        formData.append('shift_id', shiftId);
        formData.append('tx_signature', txSignature);
        
        await fetch('/carechain/api/wallet.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('Escrow created! Tx:', txSignature);
        return txSignature;
        
    } catch (err) {
        console.error('Escrow creation failed:', err);
        alert('Escrow failed: ' + err.message);
        return null;
    }
}

// Release escrow payment to worker
async function releaseEscrow(shiftId, workerAddress, amountSOL) {
    if (!walletPublicKey || !connection) {
        alert('Connect your wallet first!');
        return null;
    }
    
    const phantom = getPhantom();
    if (!phantom) return null;
    
    try {
        const workerPubkey = new solanaWeb3.PublicKey(workerAddress);
        
        const transaction = new solanaWeb3.Transaction();
        
        transaction.add(
            solanaWeb3.SystemProgram.transfer({
                fromPubkey: walletPublicKey,
                toPubkey: workerPubkey,
                lamports: Math.floor(amountSOL * solanaWeb3.LAMPORTS_PER_SOL)
            })
        );
        
        transaction.feePayer = walletPublicKey;
        const { blockhash } = await connection.getLatestBlockhash();
        transaction.recentBlockhash = blockhash;
        
        const signed = await phantom.signTransaction(transaction);
        const txSignature = await connection.sendRawTransaction(signed.serialize());
        await connection.confirmTransaction(txSignature);
        
        // Update backend
        const formData = new FormData();
        formData.append('action', 'release_payment');
        formData.append('shift_id', shiftId);
        formData.append('tx_signature', txSignature);
        
        await fetch('/carechain/api/wallet.php', {
            method: 'POST',
            body: formData
        });
        
        alert('Payment released! Worker has been paid.');
        console.log('Payment released! Tx:', txSignature);
        return txSignature;
        
    } catch (err) {
        console.error('Payment release failed:', err);
        alert('Payment failed: ' + err.message);
        return null;
    }
}

// View transaction on Solana Explorer
function viewOnExplorer(txSignature) {
    window.open(`https://explorer.solana.com/tx/${txSignature}?cluster=devnet`, '_blank');
}

// Auto-connect if previously connected
async function autoConnect() {
    const phantom = getPhantom();
    if (phantom) {
        try {
            const response = await phantom.connect({ onlyIfTrusted: true });
            walletPublicKey = response.publicKey;
            
            const walletBtn = document.getElementById('connectWallet');
            if (walletBtn) {
                const shortAddr = walletPublicKey.toString().slice(0, 4) + '...' + walletPublicKey.toString().slice(-4);
                walletBtn.textContent = shortAddr;
                walletBtn.classList.add('connected');
            }
        } catch (err) {
            // Not previously connected, that's fine
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initSolana();
    autoConnect();
});
