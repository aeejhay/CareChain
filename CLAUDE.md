# CareChain — Project Context for Claude Code

## What Is This Project

CareChain is a decentralized healthcare staffing platform built on Solana blockchain for the **Solana Frontier Hackathon** (Colosseum). Submission deadline: **May 11, 2026**.

**Tagline:** "Every link matters"
**Pitch:** "Think Grab, but for healthcare providers."

The platform connects healthcare workers (nurses, HCAs, carers) directly with facilities (nursing homes, hospitals, clinics) — no agencies, no middlemen. Workers find shifts on their terms, get blockchain-verified credentials, and receive instant payment via Solana smart contract escrow.

## Who Is Building This

A solo developer — a healthcare assistant (HCA) working in Dublin, Ireland. This project is born from lived experience with the healthcare staffing crisis: 12-hour shifts, chronic understaffing, agencies taking 30-40% cuts, and workers having no work-life balance. The personal story is central to the hackathon pitch.

## Tech Stack

- **Backend:** Plain PHP (no framework) — chosen for speed of development
- **Database:** MySQL — running on XAMPP (Windows)
- **Frontend:** Server-rendered HTML/CSS/JS from PHP — no React, no Node.js
- **Solana Integration:** Browser-side JavaScript using `@solana/web3.js` via unpkg CDN
- **Wallet:** Phantom wallet (browser extension)
- **Blockchain:** Solana devnet
- **Smart Contracts:** Anchor framework (for credential minting as soulbound NFTs and shift escrow payments)
- **Local Dev:** XAMPP on Windows (`C:\xampp\htdocs\carechain\`)
- **URL:** `http://localhost/carechain/`

## Architecture Overview

```
Users (Worker / Facility)
        |
        v
PHP Backend (Apache/XAMPP)
├── Auth (session-based login/register)
├── Worker Profiles (name, job title, experience, availability, rate)
├── Facility Profiles (name, type, address, county)
├── Shift Manager (CRUD shifts, apply, accept, complete)
├── Dashboard (role-based stats and recent activity)
├── Document Upload (credentials like NMBI, FETAC, Garda vetting)
├── Admin Verification (approve/reject uploaded documents)
└── API endpoint for wallet operations
        |
        v
    MySQL Database (carechain)
        |
        v
    Solana Devnet (browser-side JS)
    ├── Phantom wallet connect + auto-reconnect
    ├── Credential NFT minting (memo tx as soulbound token proof)
    ├── Shift escrow (fund → release on completion)
    └── Payment release (SOL transfer to worker wallet)
```

**Key architecture decision:** PHP handles ALL app logic. Solana interactions happen client-side via JavaScript through the user's Phantom wallet. No Node.js server needed.

## Database Schema

Database name: `carechain`

Tables:
- `users` — id, email, password (bcrypt), role (worker/facility/admin), wallet_address
- `worker_profiles` — user_id FK, first_name, last_name, phone, job_title (nurse/hca/carer/midwife/physio/other), nmbi_number, fetac_level, years_experience, bio, availability, hourly_rate, rating, total_shifts, is_verified, credential_nft_address
- `facility_profiles` — user_id FK, facility_name, facility_type (nursing_home/hospital/home_care/clinic/rehab/other), address, city, county, eircode, phone, contact_person, description, is_verified
- `shifts` — facility_id FK, title, description, shift_date, start_time, end_time, hourly_rate, total_pay, required_role, required_experience, urgency (normal/urgent/critical), status (open/claimed/in_progress/completed/cancelled), escrow_address, escrow_tx_signature
- `shift_applications` — shift_id FK, worker_id FK, status (pending/accepted/rejected/completed), applied_at, accepted_at, completed_at, payment_tx_signature
- `documents` — user_id FK, doc_type (nmbi_registration/garda_vetting/fetac_cert/manual_handling/patient_moving/first_aid/covid_cert/other), doc_name, file_path, status (pending/approved/rejected), nft_mint_address, uploaded_at, verified_at
- `reviews` — shift_id FK, reviewer_id FK, reviewee_id FK, rating (1-5), comment

Admin seed account: admin@carechain.io / admin123

## Current File Structure

```
C:\xampp\htdocs\carechain\
├── config/
│   └── database.php          ← PDO connection, session, helper functions
├── includes/
│   ├── header.php             ← Shared nav with CareChain logo, role-based links, wallet button
│   └── footer.php             ← Footer with Solana status indicator, loads web3.js + solana.js
├── assets/
│   ├── css/
│   │   └── style.css          ← Full CSS: DM Sans font, teal/coral palette, cards, badges, forms, responsive
│   └── js/
│       └── solana.js          ← Phantom wallet connect, credential mint, escrow, payment release
├── sql/
│   └── carechain.sql          ← Full schema + admin seed
├── api/
│   └── wallet.php             ← API for save_wallet, save_credential, save_escrow, release_payment
├── uploads/
│   └── documents/             ← Worker document uploads (created manually)
├── index.php                  ← Landing page: hero, features, how-it-works
├── register.php               ← Registration with role selector + dynamic fields
├── login.php                  ← Login with session creation
├── dashboard.php              ← Role-based dashboard (worker/facility/admin stats)
├── shifts.php                 ← Browse shifts (worker), create/manage shifts (facility), shift detail, applications
├── profile.php                ← Edit profile + upload credentials/documents
├── verify.php                 ← Admin: review pending documents, approve/reject, view recently verified
└── logout.php                 ← Session destroy + redirect
```

## Design System / Brand

- **Colors:** Teal (#1D9E75 primary, #0F6E56 dark) + Coral (#D85A30 accent)
- **Font:** DM Sans (Google Fonts)
- **Logo:** Heart shape formed by two interlocking halves (teal left = workers, coral right = facilities), medical cross at center
- **Badges:** open (green), urgent (amber), critical (red), claimed (teal), completed (gray), verified (teal), pending (amber), rejected (red)
- **Style:** Warm, human, professional — not clinical or startup-y. Emphasizes the people side of healthcare.

## Key Flows (The "Golden Path" Demo)

The hackathon demo should showcase ONE complete flow:

1. **Worker registers** → selects role, fills profile (name, job title, experience)
2. **Worker uploads credential** → e.g. FETAC cert, Garda vetting
3. **Admin verifies credential** → approves document from verify.php
4. **Worker gets credential minted on-chain** → soulbound NFT via Phantom wallet (from profile.php)
5. **Facility posts a shift** → date, time, hourly rate, required role, urgency
6. **Facility funds escrow on Solana** → SOL locked in smart contract
7. **Worker browses and applies for shift** → from shifts.php
8. **Facility accepts worker** → shift status changes to "claimed"
9. **Facility marks shift complete** → triggers payment release
10. **Worker gets paid instantly** → SOL transferred to their Phantom wallet, tx visible on Solana Explorer

## Solana Integration Details

All Solana code is in `assets/js/solana.js` and runs in the browser:

- **Network:** Solana devnet (`https://api.devnet.solana.com`)
- **Wallet:** Phantom — connect via `window.solana.connect()`
- **Credential Minting:** Creates a memo transaction (self-transfer of 1 lamport) as proof-of-concept soulbound token. In production, this would call an Anchor program to mint a real soulbound NFT.
- **Escrow:** Simplified self-transfer for hackathon demo. In production, uses PDA escrow via Anchor.
- **Payment Release:** Direct SOL transfer from facility wallet to worker wallet using `SystemProgram.transfer()`.
- **Status Indicator:** Footer shows Solana devnet connection status (green dot = connected).
- **Auto-connect:** If wallet was previously connected, auto-reconnects on page load.

## What Still Needs Work

### Must-Do Before Submission (May 11)
- [ ] Test full golden path flow end-to-end
- [ ] Fix any bugs found during testing
- [ ] Create GitHub repository and push code
- [ ] Record demo video (3-5 minutes showing the golden path)
- [ ] Write/practice 3-minute pitch (anchored in personal healthcare story)
- [ ] Submit on Colosseum platform

### Nice-to-Have Improvements
- [ ] Seed database with sample data (demo workers, facilities, shifts) for a smoother demo
- [ ] Add "Request Airdrop" button for devnet testing
- [ ] Polish mobile responsive design
- [ ] Add shift filtering/search
- [ ] Add reviews after shift completion
- [ ] Improve credential card UI on profile page with "Mint on Solana" button for approved docs
- [ ] Add notification/feedback when wallet actions succeed
- [ ] Add a README.md for the GitHub repo

## Hackathon Submission Answers (Already Written)

- **Brief description (500 chars):** Decentralized healthcare staffing platform connecting workers directly with facilities, blockchain-verified credentials, instant USDC payment via escrow.
- **What are you building (800 chars):** P2P healthcare staffing marketplace for burned-out workers and short-staffed facilities. Soulbound NFTs for credentials, smart contract escrow for instant pay. "Think Grab, but for healthcare providers."
- **Why build this, why now (800 chars):** Personal experience as HCA in Dublin, EU staffing crisis at peak, Solana's speed/fees make real-time payments viable.
- **Technologies:** Solana devnet, Anchor, @solana/web3.js, SPL Token, Phantom, PHP, MySQL, HTML/CSS/JS, Claude AI
- **Category:** Consumer

## Important Notes for Development

1. **No Node.js / npm** — Everything runs on XAMPP. JS libraries loaded via CDN.
2. **Session-based auth** — No JWT, no API tokens. Simple `$_SESSION` in PHP.
3. **PDO for database** — All queries use prepared statements via PDO.
4. **Helper functions** in `config/database.php`: `isLoggedIn()`, `getUserRole()`, `redirect()`, `sanitize()`, `flash()`.
5. **All paths are absolute** from `/carechain/` — e.g. `/carechain/dashboard.php`.
6. **Currency is EUR (€)** — displayed on shift cards and forms. Solana payments are in SOL on devnet.
7. **Irish healthcare context** — NMBI registration (nursing), FETAC/QQI certs, Garda vetting, county-based locations.
8. **Document types:** NMBI registration, Garda vetting, FETAC/QQI certificate, manual handling, patient moving & handling, first aid, Covid certification.
