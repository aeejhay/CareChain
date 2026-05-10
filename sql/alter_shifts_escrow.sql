-- Run on an existing CareChain database (phpMyAdmin or mysql CLI). Skip any statement that errors.

USE carechain;

ALTER TABLE shifts ADD COLUMN escrow_status ENUM('not_funded','funded','released','failed') NOT NULL DEFAULT 'not_funded' AFTER status;

ALTER TABLE shifts ADD COLUMN escrow_amount_sol DECIMAL(12,6) NOT NULL DEFAULT 0.000000 AFTER escrow_status;

ALTER TABLE shifts MODIFY COLUMN escrow_tx_signature VARCHAR(255) NULL DEFAULT NULL;

ALTER TABLE shifts ADD COLUMN escrow_funded_at DATETIME NULL DEFAULT NULL AFTER escrow_tx_signature;

ALTER TABLE shifts ADD COLUMN payment_tx_signature VARCHAR(255) NULL DEFAULT NULL AFTER escrow_funded_at;

ALTER TABLE shifts ADD COLUMN payment_released_at DATETIME NULL DEFAULT NULL AFTER payment_tx_signature;

ALTER TABLE shift_applications MODIFY COLUMN payment_tx_signature VARCHAR(255) NULL DEFAULT NULL;

UPDATE shifts
SET escrow_status = 'funded',
    escrow_funded_at = COALESCE(escrow_funded_at, created_at)
WHERE escrow_tx_signature IS NOT NULL
  AND TRIM(escrow_tx_signature) != ''
  AND escrow_status = 'not_funded';
