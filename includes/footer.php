    <footer class="site-footer">
        <p>CareChain &copy; <?= date('Y') ?> — Every link matters. Built on <a href="https://solana.com" target="_blank">Solana</a></p>
        <div class="solana-status" style="justify-content: center; margin-top: 0.5rem;">
            <span class="dot" id="solanaStatusDot"></span>
            <span id="solanaStatusText">Solana Devnet</span>
        </div>
    </footer>

    <?= $extraScripts ?? '' ?>

    <script src="https://unpkg.com/@solana/web3.js@1.95.3/lib/index.iife.min.js"></script>
    <script src="/carechain/assets/js/solana.js"></script>
</body>
</html>
