-- Run once on an existing CareChain DB created before map coordinates existed.
-- If you see "Duplicate column", your schema already includes these fields.

USE carechain;

ALTER TABLE facility_profiles
    ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL AFTER is_verified,
    ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL AFTER latitude;
