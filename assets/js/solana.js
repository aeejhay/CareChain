// CareChain — Solana Integration
// Phantom wallet, credential minting, shift escrow payments

const SOLANA_NETWORK = 'devnet';

/**
 * Devnet RPC URLs for the browser. Public Solana URLs often fail CORS from localhost;
 * first entry is same-origin PHP proxy (see api/solana_rpc.php).
 */
let devnetRpcEndpoints = [];
let connection = null;
/** Matches `connection` endpoint — used to order balance RPC fallbacks. */
let activeRpcEndpoint = '';
let walletPublicKey = null;

function refreshDevnetRpcEndpoints() {
    const list = [];
    if (typeof window !== 'undefined' && window.location && /^https?:$/i.test(window.location.protocol)) {
        list.push(window.location.origin + '/carechain/api/solana_rpc.php');
    }
    list.push('https://rpc.ankr.com/solana_devnet');
    devnetRpcEndpoints = list;
    activeRpcEndpoint = devnetRpcEndpoints[0] || 'https://rpc.ankr.com/solana_devnet';
}

/** Phantom uses its own web3 PublicKey class; Connection.getBalance needs ours. */
function normalizeWeb3PublicKey(key) {
    if (!key) return null;
    try {
        return new solanaWeb3.PublicKey(key.toString());
    } catch (err) {
        console.error('Invalid wallet public key:', err);
        return null;
    }
}

// ─── Toast Notifications ─────────────────────────────────────────────────────

(function injectToastStyles() {
    const style = document.createElement('style');
    style.textContent = `
        #toastContainer {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem;
            pointer-events: none;
        }
        .cc-toast {
            padding: 0.75rem 1.25rem; border-radius: 10px; font-size: 0.875rem;
            font-family: 'DM Sans', sans-serif; font-weight: 500;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18); max-width: 340px;
            opacity: 1; transition: opacity 0.3s ease; pointer-events: auto;
        }
        .cc-toast.success { background: #1D9E75; color: #fff; }
        .cc-toast.error   { background: #E24B4A; color: #fff; }
        .cc-toast.warning { background: #BA7517; color: #fff; }
        .cc-toast.info    { background: #3D3D3A; color: #fff; }
    `;
    document.head.appendChild(style);
})();

function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'cc-toast ' + type;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ─── Solana Connection ────────────────────────────────────────────────────────

function initSolana() {
    try {
        if (typeof solanaWeb3 === 'undefined') {
            throw new Error('@solana/web3.js not loaded (expected global solanaWeb3)');
        }
        refreshDevnetRpcEndpoints();
        connection = new solanaWeb3.Connection(activeRpcEndpoint, 'confirmed');
        updateSolanaStatus(true);
    } catch (err) {
        console.error('Solana connection failed:', err);
        connection = null;
        updateSolanaStatus(false);
    }
}

function updateSolanaStatus(active) {
    const dot  = document.getElementById('solanaStatusDot');
    const text = document.getElementById('solanaStatusText');
    if (dot)  dot.classList.toggle('active', active);
    if (text) text.textContent = active ? 'Solana Devnet — Connected' : 'Solana Devnet — Disconnected';
}

/** Updates every `[data-sol-balance]` (header + dashboard). */
function setSolBalanceLabels(numericBalance, loading) {
    let text = '— SOL';
    if (loading) text = '…';
    else if (typeof numericBalance === 'number' && !Number.isNaN(numericBalance)) text = numericBalance.toFixed(4) + ' SOL';
    document.querySelectorAll('[data-sol-balance]').forEach((el) => {
        el.textContent = text;
    });
}

function warnIfPhantomNotDevnet() {
    const p = getPhantom();
    if (!p) return;
    const rpc = String(p.rpcEndpoint || '').toLowerCase();
    const chain = String(p.chainId || p.network || '').toLowerCase();
    const looksDevnet = rpc.includes('devnet') || chain.includes('devnet') || chain === 'solana:devnet';
    const looksMainnet = rpc.includes('mainnet') || chain.includes('mainnet') || chain === 'solana:mainnet';
    if (looksMainnet && !looksDevnet) {
        showToast('Phantom is on mainnet — CareChain reads devnet. Switch Phantom to Devnet so your balance matches.', 'warning');
    }
    const hint = document.getElementById('dashboardWalletClusterHint');
    if (hint) {
        if (looksMainnet && !looksDevnet) {
            hint.textContent = 'Phantom is not on devnet; the SOL amount below is your devnet on-chain balance (often 0 on mainnet wallets).';
            hint.style.display = 'block';
        } else {
            hint.textContent = '';
            hint.style.display = 'none';
        }
    }
}

// ─── Wallet UI ────────────────────────────────────────────────────────────────

function setWalletDropdownOpen(open) {
    const btn = document.getElementById('connectWallet');
    const dropdown = document.getElementById('walletDropdown');
    if (dropdown) {
        dropdown.classList.toggle('open', open);
        dropdown.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function updateWalletBtn(connected, pubkey = null) {
    const btn      = document.getElementById('connectWallet');
    const addrEl   = document.getElementById('walletFullAddr');
    const dropdown = document.getElementById('walletDropdown');

    if (connected && pubkey) {
        const addr = pubkey.toString();
        if (btn) {
            btn.textContent = addr.slice(0, 4) + '…' + addr.slice(-4);
            btn.classList.add('connected');
            btn.title = 'Wallet menu — click to open';
        }
        if (addrEl) addrEl.textContent = addr;
        setSolBalanceLabels(null, true);
        getBalance()
            .then((bal) => setSolBalanceLabels(bal, false))
            .catch(() => setSolBalanceLabels(null, false));
    } else {
        if (btn) {
            btn.textContent = 'Connect Wallet';
            btn.classList.remove('connected');
            btn.title = '';
        }
        if (addrEl) addrEl.textContent = '—';
        setSolBalanceLabels(null, false);
        if (dropdown) setWalletDropdownOpen(false);
    }
}

// ─── Wallet Dropdown Menu ─────────────────────────────────────────────────────

function handleWalletClick(event) {
    if (event) event.stopPropagation();
    if (!walletPublicKey) {
        connectWallet();
    } else {
        toggleWalletMenu();
    }
}

function toggleWalletMenu() {
    const dropdown = document.getElementById('walletDropdown');
    if (!dropdown) return;
    const open = !dropdown.classList.contains('open');
    setWalletDropdownOpen(open);
}

async function refreshBalance() {
    if (!walletPublicKey) return;
    setSolBalanceLabels(null, true);
    const balance = await getBalance();
    setSolBalanceLabels(balance, false);
    showToast('Balance: ' + (Number.isNaN(balance) ? '—' : balance.toFixed(4)) + ' SOL', 'info');
}

function copyWalletAddress() {
    if (!walletPublicKey) return;
    navigator.clipboard.writeText(walletPublicKey.toString())
        .then(() => showToast('Address copied!', 'success'))
        .catch(() => showToast('Copy failed', 'error'));
}

// ─── Phantom Provider ─────────────────────────────────────────────────────────

function getPhantom() {
    if ('solana' in window && window.solana.isPhantom) return window.solana;
    return null;
}

// ─── Wallet Connection ────────────────────────────────────────────────────────

function readPhantomPublicKey(phantom, connectResult) {
    const pk = connectResult && connectResult.publicKey ? connectResult.publicKey : null;
    return pk || phantom.publicKey || null;
}

async function connectWallet() {
    const phantom = getPhantom();
    if (!phantom) {
        showToast('Phantom wallet not installed — visit phantom.app', 'error');
        window.open('https://phantom.app/', '_blank');
        return;
    }

    try {
        const response = await phantom.connect();
        walletPublicKey = normalizeWeb3PublicKey(readPhantomPublicKey(phantom, response));
        if (!walletPublicKey) {
            showToast('Could not read wallet address', 'error');
            return;
        }
        updateWalletBtn(true, walletPublicKey);
        await saveWalletAddress(walletPublicKey.toString());
        warnIfPhantomNotDevnet();

        const balance = await getBalance();
        setSolBalanceLabels(balance, false);
        const balStr = Number.isFinite(balance) ? balance.toFixed(4) : '—';
        showToast('Wallet connected! Balance: ' + balStr + ' SOL', 'success');
    } catch (err) {
        if (err.code !== 4001) { // 4001 = user rejected
            console.error('Wallet connection failed:', err);
            showToast('Connection failed: ' + err.message, 'error');
        }
    }
}

async function disconnectWallet() {
    setWalletDropdownOpen(false);
    // Update state immediately so the disconnect event listener sees it's already handled
    walletPublicKey = null;
    updateWalletBtn(false);
    showToast('Wallet disconnected', 'info');

    const phantom = getPhantom();
    if (phantom) {
        try { await phantom.disconnect(); } catch (_) {}
    }
}

// ─── Phantom Event Listeners ──────────────────────────────────────────────────

function setupPhantomListeners() {
    const phantom = getPhantom();
    if (!phantom) return;

    phantom.on('disconnect', () => {
        if (!walletPublicKey) return; // already handled by disconnectWallet()
        walletPublicKey = null;
        updateWalletBtn(false);
        showToast('Wallet disconnected', 'info');
    });

    phantom.on('accountChanged', (newPublicKey) => {
        if (newPublicKey) {
            walletPublicKey = normalizeWeb3PublicKey(newPublicKey);
            updateWalletBtn(true, walletPublicKey);
            saveWalletAddress(walletPublicKey.toString());
            warnIfPhantomNotDevnet();
            showToast('Wallet account changed', 'info');
        } else {
            walletPublicKey = null;
            updateWalletBtn(false);
        }
    });
}

// ─── Auto-reconnect ───────────────────────────────────────────────────────────

async function autoConnect() {
    const phantom = getPhantom();
    if (!phantom) return;

    try {
        const response = await phantom.connect({ onlyIfTrusted: true });
        walletPublicKey = normalizeWeb3PublicKey(readPhantomPublicKey(phantom, response));
        if (walletPublicKey) {
            updateWalletBtn(true, walletPublicKey);
            warnIfPhantomNotDevnet();
        }
    } catch (_) {
        // Not previously trusted — normal
    }
}

// ─── Backend Sync ─────────────────────────────────────────────────────────────

async function saveWalletAddress(address) {
    if (document.body && document.body.dataset.loggedIn !== '1') return;
    try {
        const fd = new FormData();
        fd.append('action', 'save_wallet');
        fd.append('wallet_address', address);
        await fetch('/carechain/api/wallet.php', { method: 'POST', body: fd });
    } catch (err) {
        console.error('Failed to save wallet:', err);
    }
}

// ─── Balance ──────────────────────────────────────────────────────────────────

async function fetchLamportsWithFallback(conn, pk) {
    try {
        return await conn.getBalance(pk, 'confirmed');
    } catch (e1) {
        return await conn.getBalance(pk);
    }
}

async function getBalance() {
    if (!walletPublicKey) return 0;
    const pk = normalizeWeb3PublicKey(walletPublicKey);
    if (!pk) return 0;

    refreshDevnetRpcEndpoints();
    const tryUrls = [...devnetRpcEndpoints];

    let lastErr = null;
    for (const url of tryUrls) {
        try {
            const conn = connection && url === activeRpcEndpoint
                ? connection
                : new solanaWeb3.Connection(url, 'confirmed');
            const lamports = await fetchLamportsWithFallback(conn, pk);
            if (typeof lamports === 'number' && !Number.isNaN(lamports)) {
                return lamports / solanaWeb3.LAMPORTS_PER_SOL;
            }
        } catch (err) {
            lastErr = err;
        }
    }
    console.error('Balance check failed (all RPCs):', lastErr);
    return NaN;
}

// ─── Devnet Airdrop ───────────────────────────────────────────────────────────

async function requestAirdrop() {
    if (!walletPublicKey || !connection) {
        showToast('Connect your wallet first!', 'warning');
        return;
    }

    showToast('Requesting 1 SOL airdrop…', 'info');
    try {
        const { blockhash, lastValidBlockHeight } = await connection.getLatestBlockhash();
        const signature = await connection.requestAirdrop(walletPublicKey, solanaWeb3.LAMPORTS_PER_SOL);
        await connection.confirmTransaction({ signature, blockhash, lastValidBlockHeight });

        const balance = await getBalance();
        setSolBalanceLabels(balance, false);
        showToast('Airdrop successful! Balance: ' + balance.toFixed(4) + ' SOL', 'success');
    } catch (err) {
        console.error('Airdrop failed:', err);
        showToast('Airdrop failed — try again in a moment', 'error');
    }
}

// ─── Transaction Helper ───────────────────────────────────────────────────────

async function sendTransaction(transaction) {
    const phantom = getPhantom();
    const { blockhash, lastValidBlockHeight } = await connection.getLatestBlockhash();
    transaction.recentBlockhash = blockhash;
    transaction.feePayer = walletPublicKey;

    const signed = await phantom.signTransaction(transaction);
    const signature = await connection.sendRawTransaction(signed.serialize());
    await connection.confirmTransaction({ signature, blockhash, lastValidBlockHeight });
    return signature;
}

// ─── Credential Minting ───────────────────────────────────────────────────────

async function mintCredentialNFT(credentialType, workerName, docId) {
    if (!walletPublicKey || !connection) {
        showToast('Connect your wallet first!', 'warning');
        return null;
    }
    if (!getPhantom()) return null;

    try {
        // Memo tx as proof-of-concept soulbound credential
        // In production: calls Anchor program to mint a real soulbound NFT
        const transaction = new solanaWeb3.Transaction().add(
            solanaWeb3.SystemProgram.transfer({
                fromPubkey: walletPublicKey,
                toPubkey: walletPublicKey,
                lamports: 1
            })
        );

        const txSignature = await sendTransaction(transaction);

        const fd = new FormData();
        fd.append('action', 'save_credential');
        fd.append('doc_id', docId);
        fd.append('tx_signature', txSignature);
        await fetch('/carechain/api/wallet.php', { method: 'POST', body: fd });

        showToast('Credential minted on Solana!', 'success');
        return txSignature;
    } catch (err) {
        console.error('Minting failed:', err);
        showToast('Credential minting failed: ' + err.message, 'error');
        return null;
    }
}

// ─── Prototype escrow vault (devnet) — must match config/solana_escrow.php ───

function getPrototypeEscrowPublicKey() {
    const raw = typeof window !== 'undefined' ? window.CARECHAIN_PROTO_ESCROW_PUBKEY : '';
    if (!raw || typeof solanaWeb3 === 'undefined') {
        throw new Error('Prototype escrow vault is not configured (missing CARECHAIN_PROTO_ESCROW_PUBKEY)');
    }
    return new solanaWeb3.PublicKey(raw);
}

async function walletApiFetch(formData) {
    const res = await fetch('/carechain/api/wallet.php', { method: 'POST', body: formData });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) {
        const msg = data.error || ('HTTP ' + res.status);
        throw new Error(msg);
    }
    return data;
}

/**
 * Facility funds prototype escrow: SOL transfer from Phantom wallet → Prototype Escrow Vault (devnet).
 * Then persists signature via save_escrow.
 */
async function fundEscrow(shiftId, amountSol) {
    const phantom = getPhantom();
    if (!phantom) {
        showToast('Please install Phantom wallet — visit phantom.app', 'error');
        window.open('https://phantom.app/', '_blank');
        return null;
    }
    if (!connection) {
        initSolana();
        if (!connection) {
            showToast('Solana connection not ready — refresh the page.', 'error');
            return null;
        }
    }

    if (!walletPublicKey) {
        showToast('Please connect Phantom wallet', 'warning');
        await connectWallet();
        if (!walletPublicKey) return null;
    }

    const lamports = Math.floor(Number(amountSol) * solanaWeb3.LAMPORTS_PER_SOL);
    if (!Number.isFinite(lamports) || lamports < 1) {
        showToast('Invalid SOL amount', 'error');
        return null;
    }

    try {
        const vault = getPrototypeEscrowPublicKey();
        const transaction = new solanaWeb3.Transaction().add(
            solanaWeb3.SystemProgram.transfer({
                fromPubkey: walletPublicKey,
                toPubkey: vault,
                lamports
            })
        );

        showToast('Approve the transfer in Phantom…', 'info');
        const txSignature = await sendTransaction(transaction);

        const fd = new FormData();
        fd.append('action', 'save_escrow');
        fd.append('shift_id', String(shiftId));
        fd.append('escrow_tx_signature', txSignature);
        fd.append('escrow_amount_sol', String(amountSol));
        await walletApiFetch(fd);

        showToast('Escrow funded on Solana devnet — signature saved.', 'success');
        setTimeout(() => window.location.reload(), 800);
        return txSignature;
    } catch (err) {
        console.error('fundEscrow failed:', err);
        showToast(err.message || 'Escrow funding failed', 'error');
        return null;
    }
}

/** @deprecated use fundEscrow */
async function createShiftEscrow(shiftId, amountSOL) {
    return fundEscrow(shiftId, amountSOL);
}

/**
 * After shift is completed: facility sends SOL from Phantom → worker wallet (prototype “release”).
 */
async function releasePayment(shiftId, workerWalletAddress, amountSol, workerId) {
    const phantom = getPhantom();
    if (!phantom) {
        showToast('Please install Phantom wallet — visit phantom.app', 'error');
        return null;
    }
    if (!connection) {
        initSolana();
        if (!connection) {
            showToast('Solana connection not ready — refresh the page.', 'error');
            return null;
        }
    }

    if (!walletPublicKey) {
        showToast('Please connect Phantom wallet', 'warning');
        await connectWallet();
        if (!walletPublicKey) return null;
    }

    if (!workerWalletAddress || typeof workerWalletAddress !== 'string') {
        showToast('Worker wallet address missing — worker must connect wallet in CareChain', 'error');
        return null;
    }

    let workerPubkey;
    try {
        workerPubkey = new solanaWeb3.PublicKey(workerWalletAddress.trim());
    } catch (e) {
        showToast('Worker wallet address is invalid', 'error');
        return null;
    }

    const lamports = Math.floor(Number(amountSol) * solanaWeb3.LAMPORTS_PER_SOL);
    if (!Number.isFinite(lamports) || lamports < 1) {
        showToast('Invalid SOL amount', 'error');
        return null;
    }

    try {
        const transaction = new solanaWeb3.Transaction().add(
            solanaWeb3.SystemProgram.transfer({
                fromPubkey: walletPublicKey,
                toPubkey: workerPubkey,
                lamports
            })
        );

        showToast('Approve payment to worker in Phantom…', 'info');
        const txSignature = await sendTransaction(transaction);

        const fd = new FormData();
        fd.append('action', 'release_payment');
        fd.append('shift_id', String(shiftId));
        fd.append('worker_id', String(workerId));
        fd.append('payment_tx_signature', txSignature);
        await walletApiFetch(fd);

        showToast('Payment released to worker wallet on devnet.', 'success');
        setTimeout(() => window.location.reload(), 800);
        return txSignature;
    } catch (err) {
        console.error('releasePayment failed:', err);
        showToast(err.message || 'Payment release failed', 'error');
        return null;
    }
}


// ─── Explorer Link ────────────────────────────────────────────────────────────

function viewOnExplorer(txSignature) {
    window.open('https://explorer.solana.com/tx/' + txSignature + '?cluster=devnet', '_blank');
}

// Inline HTML handlers (onclick="…") resolve on `window` — bind explicitly for all browsers.
if (typeof window !== 'undefined') {
    window.fundEscrow = fundEscrow;
    window.releasePayment = releasePayment;
    window.createShiftEscrow = createShiftEscrow;
    window.viewOnExplorer = viewOnExplorer;
    window.connectWallet = connectWallet;
    window.handleWalletClick = handleWalletClick;
    window.refreshBalance = refreshBalance;
    window.disconnectWallet = disconnectWallet;
    window.copyWalletAddress = copyWalletAddress;
    window.requestAirdrop = requestAirdrop;
    window.mintCredentialNFT = mintCredentialNFT;
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initSolana();
    setupPhantomListeners();
    autoConnect();

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && walletPublicKey) {
            getBalance().then((bal) => setSolBalanceLabels(bal, false)).catch(() => {});
            warnIfPhantomNotDevnet();
        }
    });

    // Close dropdown when clicking outside the wallet menu
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('walletMenu');
        if (menu && !menu.contains(e.target)) {
            const dropdown = document.getElementById('walletDropdown');
            if (dropdown) setWalletDropdownOpen(false);
        }
    });
});
