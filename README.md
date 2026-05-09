# CareChain

> "Every link matters"

**Think Grab, but for healthcare providers.**

CareChain is a decentralized healthcare staffing platform that connects workers (nurses, HCAs, carers) directly with facilities (nursing homes, hospitals, clinics) — no agencies, no middlemen. Workers find shifts on their terms, get blockchain-verified credentials, and receive instant payment via Solana smart contract escrow.

Built for the **Solana Frontier Hackathon (Colosseum)** by a healthcare assistant in Dublin, Ireland — born from lived experience with the staffing crisis.

---

## The Problem

- Healthcare workers face 12-hour shifts, chronic understaffing, and zero work-life balance
- Staffing agencies take 30–40% cuts from every shift
- Credentials are paper-based, slow to verify, and easy to fake
- Payments are delayed by days or weeks

## The Solution

CareChain cuts out the middleman entirely:

- **Direct matching** — Workers apply to shifts posted by facilities
- **Blockchain credentials** — Verified documents minted as soulbound NFTs on Solana
- **Instant payment** — SOL released automatically from escrow when a shift is completed
- **Transparent trust** — On-chain credential history, ratings, and payment records

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Plain PHP (no framework) |
| Database | MySQL via XAMPP |
| Frontend | Server-rendered HTML/CSS/JS |
| Blockchain | Solana devnet |
| Wallet | Phantom browser extension |
| Web3 Library | `@solana/web3.js` via CDN |
| Smart Contracts | Anchor framework |

---

## Features

- **Worker profiles** — Job title, experience, hourly rate, availability, NMBI number
- **Facility profiles** — Facility type, location (Irish county), contact details
- **Shift management** — Post, browse, apply, accept, complete shifts
- **Document uploads** — NMBI registration, Garda vetting, FETAC/QQI certs, manual handling, first aid
- **Admin verification** — Approve/reject uploaded credentials
- **Credential NFTs** — Approved docs minted on Solana as soulbound proof-of-credential
- **Shift escrow** — Facility locks SOL; released to worker on completion
- **Role-based dashboard** — Separate views for workers, facilities, and admin

---

## Getting Started (Local Development)

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL)
- [Phantom Wallet](https://phantom.app/) browser extension
- A web browser

### Setup

1. **Clone the repo** into your XAMPP htdocs folder:
   ```
   git clone https://github.com/aeejhay/CareChain.git C:\xampp\htdocs\carechain
   ```

2. **Start XAMPP** — launch Apache and MySQL from the XAMPP Control Panel

3. **Import the database:**
   - Open `http://localhost/phpmyadmin`
   - Create a database named `carechain`
   - Import `sql/carechain.sql`

4. **Configure database connection** in `config/database.php`:
   ```php
   $host = 'localhost';
   $dbname = 'carechain';
   $username = 'root';
   $password = ''; // your XAMPP MySQL password
   ```

5. **Create the uploads folder** (if it doesn't exist):
   ```
   C:\xampp\htdocs\carechain\uploads\documents\
   ```

6. **Open the app:**
   ```
   http://localhost/carechain
   ```

### Default Admin Account

```
Email:    admin@carechain.io
Password: admin123
```

---

## The Golden Path Demo

1. Worker registers and fills out their profile
2. Worker uploads a credential (e.g. FETAC cert, Garda vetting)
3. Admin approves the credential from `verify.php`
4. Worker connects Phantom wallet and mints their credential as a soulbound NFT
5. Facility posts a shift with date, time, rate, and required role
6. Facility funds the shift escrow in SOL
7. Worker browses shifts and applies
8. Facility accepts the worker — shift moves to "claimed"
9. Facility marks shift complete — escrow releases
10. Worker receives SOL instantly in their Phantom wallet

---

## Project Structure

```
carechain/
├── config/database.php       # DB connection + helper functions
├── includes/
│   ├── header.php            # Nav, logo, wallet button
│   └── footer.php            # Solana status + scripts
├── assets/
│   ├── css/style.css         # Design system (teal/coral palette)
│   └── js/solana.js          # Phantom wallet + Solana integration
├── api/wallet.php            # API for wallet/NFT/escrow operations
├── sql/carechain.sql         # Full schema + admin seed
├── uploads/documents/        # Worker credential uploads
├── index.php                 # Landing page
├── register.php              # Registration (worker / facility)
├── login.php                 # Login
├── dashboard.php             # Role-based dashboard
├── shifts.php                # Shift listings + management
├── profile.php               # Profile editor + credential upload
├── verify.php                # Admin credential verification
└── logout.php                # Session logout
```

---

## Solana Integration

All blockchain interactions happen client-side via the user's Phantom wallet — no Node.js server required.

- **Network:** Solana devnet
- **Credential minting:** Memo transaction as proof-of-concept soulbound token
- **Escrow:** SOL locked on shift creation, released on completion
- **Payment:** Direct `SystemProgram.transfer()` to worker wallet
- **Auto-connect:** Phantom reconnects automatically on page load

To test with devnet SOL, use the [Solana Faucet](https://faucet.solana.com/).

---

## Design

- **Colors:** Teal `#1D9E75` (workers) + Coral `#D85A30` (facilities)
- **Font:** DM Sans (Google Fonts)
- **Logo:** Two interlocking halves forming a heart — teal for workers, coral for facilities, medical cross at center
- **Tone:** Warm, human, professional — not clinical

---

## Built With

- [Solana Web3.js](https://solana-labs.github.io/solana-web3.js/)
- [Phantom Wallet](https://phantom.app/)
- [Anchor Framework](https://www.anchor-lang.com/)
- [XAMPP](https://www.apachefriends.org/)
- [Claude AI](https://claude.ai) — AI pair programming

---

## Hackathon

**Event:** Solana Frontier Hackathon — Colosseum  
**Category:** Consumer  
**Deadline:** May 11, 2026

*This project was built by a solo developer and healthcare assistant in Dublin, Ireland, to solve a problem experienced first-hand on the floor of understaffed facilities.*
