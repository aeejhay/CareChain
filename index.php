<?php $pageTitle = 'Home'; require_once 'includes/header.php'; ?>

<section class="hero">
    <h1>Your shift. Your terms.</h1>
    <p>CareChain connects healthcare workers directly with facilities — no agencies, no middlemen. Blockchain-verified credentials, instant payments, total flexibility.</p>
    <div class="hero-buttons">
        <a href="/carechain/register.php?role=worker" class="btn btn-secondary btn-lg">I'm a Care Worker</a>
        <a href="/carechain/register.php?role=facility" class="btn btn-coral btn-lg">I'm a Facility</a>
    </div>
</section>

<div class="container">
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon teal">&#x1F6E1;</div>
            <h3>Verified Credentials</h3>
            <p>Your qualifications are minted as soulbound NFTs on Solana. Verify once — trusted everywhere. No more emailing certs to every new employer.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon coral">&#x26A1;</div>
            <h3>Instant Payment</h3>
            <p>Smart contract escrow releases your pay the moment a shift is confirmed complete. No invoicing, no 30-day wait, no agency cuts.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon amber">&#x1F4C5;</div>
            <h3>Flexible Shifts</h3>
            <p>Browse available shifts and pick the ones that fit your life. Work when you want, where you want. Take back your work-life balance.</p>
        </div>
    </div>

    <div style="text-align: center; padding: 2rem 0;">
        <h2 style="font-size: 1.6rem; margin-bottom: 0.5rem;">How it works</h2>
        <p style="color: var(--gray-500); max-width: 500px; margin: 0 auto 2rem;">Three steps to better healthcare staffing</p>
    </div>

    <div class="features-grid">
        <div class="card" style="text-align: center; padding: 2rem;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--teal-400); margin-bottom: 0.5rem;">1</div>
            <h3 style="margin-bottom: 0.5rem;">Register & Verify</h3>
            <p style="font-size: 0.9rem; color: var(--gray-500);">Workers upload credentials. Once verified, they're minted on-chain as soulbound tokens — your portable proof of qualification.</p>
        </div>
        <div class="card" style="text-align: center; padding: 2rem;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--coral-400); margin-bottom: 0.5rem;">2</div>
            <h3 style="margin-bottom: 0.5rem;">Find & Claim Shifts</h3>
            <p style="font-size: 0.9rem; color: var(--gray-500);">Facilities post shifts with pay rates. Workers browse and claim shifts that fit their schedule. No agency gatekeeping.</p>
        </div>
        <div class="card" style="text-align: center; padding: 2rem;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--amber-400); margin-bottom: 0.5rem;">3</div>
            <h3 style="margin-bottom: 0.5rem;">Work & Get Paid</h3>
            <p style="font-size: 0.9rem; color: var(--gray-500);">Complete the shift. Facility confirms. Smart contract releases payment instantly to your wallet. Done.</p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
