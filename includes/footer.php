    <footer class="site-footer">
        <p>CareChain &copy; <?= date('Y') ?> — Every link matters. Built on <a href="https://solana.com" target="_blank">Solana</a></p>
        <div class="solana-status" style="justify-content: center; margin-top: 0.5rem;">
            <span class="dot" id="solanaStatusDot"></span>
            <span id="solanaStatusText">Solana Devnet</span>
        </div>
        <p class="text-muted" style="text-align: center; font-size: 0.8rem; margin-top: 0.75rem;">
            Need test SOL? Use the <a href="https://faucet.solana.com/" target="_blank" rel="noopener">Solana devnet faucet</a> or <strong>Request airdrop</strong> in the wallet menu (rate-limited).
        </p>
    </footer>

    <?= $extraScripts ?? '' ?>

    <?php
    $bufPath = dirname(__DIR__) . '/assets/js/buffer.bundle.run.js';
    $bufVer = is_file($bufPath) ? (string) @filemtime($bufPath) : '1';
    ?>
    <script src="/carechain/assets/js/buffer.bundle.run.js?v=<?= htmlspecialchars($bufVer, ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
        (function () {
            window.global = window.global || window;
            window.process = window.process || { env: {}, browser: true };
            if (typeof Buffer === 'undefined') {
                if (window.buffer && window.buffer.Buffer) {
                    window.Buffer = window.buffer.Buffer;
                } else {
                    console.error('CareChain: buffer polyfill failed to load (buffer.bundle.run.js).');
                }
            }
        })();
    </script>

    <?php
    $solEscPath = dirname(__DIR__) . '/config/solana_escrow.php';
    if (is_file($solEscPath)) {
        require_once $solEscPath;
        if (defined('CARECHAIN_PROTO_ESCROW_PUBKEY')) {
            echo '<script>window.CARECHAIN_PROTO_ESCROW_PUBKEY=' . json_encode(CARECHAIN_PROTO_ESCROW_PUBKEY, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' . "\n";
        }
    }
    ?>
    <script src="https://unpkg.com/@solana/web3.js@1.95.3/lib/index.iife.min.js"></script>
    <?php
    $solanaJsPath = dirname(__DIR__) . '/assets/js/solana.js';
    $solanaJsVer = is_file($solanaJsPath) ? (string) @filemtime($solanaJsPath) : '1';
    ?>
    <script src="/carechain/assets/js/solana.js?v=<?= htmlspecialchars($solanaJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
