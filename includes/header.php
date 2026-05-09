<?php require_once __DIR__ . '/../config/database.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'CareChain' ?> — CareChain</title>
    <link rel="stylesheet" href="/carechain/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="/carechain/" class="navbar-brand">
            <svg width="32" height="32" viewBox="0 0 64 64">
                <path d="M28 26C28 14 14 8 14 22C14 32 24 40 28 48C28 40 24 32 24 26C24 18 28 20 28 26Z" fill="#1D9E75" stroke="#0F6E56" stroke-width="1.5"/>
                <path d="M36 26C36 14 50 8 50 22C50 32 40 40 36 48C36 40 40 32 40 26C40 18 36 20 36 26Z" fill="#D85A30" stroke="#993C1D" stroke-width="1.5"/>
                <rect x="28" y="28" width="8" height="4" rx="1" fill="#1D9E75"/>
                <rect x="30" y="26" width="4" height="8" rx="1" fill="#1D9E75"/>
            </svg>
            <span><span class="care">Care</span><span class="chain">Chain</span></span>
        </a>
        <ul class="navbar-nav">
            <?php if (isLoggedIn()): ?>
                <li><a href="/carechain/dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
                <li><a href="/carechain/shifts.php" class="<?= basename($_SERVER['PHP_SELF']) == 'shifts.php' ? 'active' : '' ?>">Shifts</a></li>
                <li><a href="/carechain/profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">Profile</a></li>
                <?php if (getUserRole() === 'admin'): ?>
                    <li><a href="/carechain/verify.php" class="<?= basename($_SERVER['PHP_SELF']) == 'verify.php' ? 'active' : '' ?>">Verify</a></li>
                <?php endif; ?>
                <li><a href="/carechain/logout.php">Logout</a></li>
                <li><button class="wallet-btn" id="connectWallet" onclick="connectWallet()">Connect Wallet</button></li>
            <?php else: ?>
                <li><a href="/carechain/login.php">Login</a></li>
                <li><a href="/carechain/register.php" class="btn btn-primary btn-sm">Sign Up</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <?php if ($msg = flash('success')): ?>
        <div class="container"><div class="alert alert-success"><?= $msg ?></div></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="container"><div class="alert alert-error"><?= $msg ?></div></div>
    <?php endif; ?>
