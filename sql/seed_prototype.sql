-- CareChain prototype demo: sample worker, 3 Dublin nursing homes, open shifts with map pins.
-- Password for all demo accounts: demo123
--
-- Import order:
--   1) carechain.sql (or ensure facility_profiles has latitude, longitude)
--   2) If upgrading an old DB: run alter_facility_map_columns.sql first (skip if columns exist)
--   3) Run this file once. To re-run, delete demo users first:
--        DELETE FROM users WHERE email LIKE '%.demo@carechain.io';

USE carechain;

SET @demo_pw = '$2y$10$/VnWnOGY/gLkpIxLCLzn8e159o2fsGznpf7fFY749z/0JteXIbNuS';

-- Demo worker
INSERT INTO users (email, password, role) VALUES ('worker.demo@carechain.io', @demo_pw, 'worker');
SET @worker_id = LAST_INSERT_ID();

INSERT INTO worker_profiles (
    user_id, first_name, last_name, phone, job_title, years_experience, bio, availability, hourly_rate, is_verified, total_shifts
) VALUES (
    @worker_id,
    'Aoife',
    'Murphy',
    '0830000001',
    'hca',
    4,
    'Prototype demo worker — dementia care, moving & handling, night shifts.',
    'flexible',
    16.50,
    1,
    12
);

-- Facility 1 — Sandymount
INSERT INTO users (email, password, role) VALUES ('maryfield.demo@carechain.io', @demo_pw, 'facility');
SET @fac_maryfield = LAST_INSERT_ID();

INSERT INTO facility_profiles (
    user_id, facility_name, facility_type, address, city, county, eircode, phone, contact_person, description, is_verified, latitude, longitude
) VALUES (
    @fac_maryfield,
    'Maryfield Nursing Home',
    'nursing_home',
    '18 Seafort Avenue',
    'Sandymount',
    'Dublin',
    'D04 R7T2',
    '01 234 5601',
    'Mary O''Brien',
    '68-bed nursing home near the coast — prototype demo site.',
    1,
    53.3358,
    -6.2132
);

-- Facility 2 — Phibsborough
INSERT INTO users (email, password, role) VALUES ('liffeyvale.demo@carechain.io', @demo_pw, 'facility');
SET @fac_liffeyvale = LAST_INSERT_ID();

INSERT INTO facility_profiles (
    user_id, facility_name, facility_type, address, city, county, eircode, phone, contact_person, description, is_verified, latitude, longitude
) VALUES (
    @fac_liffeyvale,
    'Liffey Vale Care Centre',
    'nursing_home',
    '44 North Circular Road',
    'Phibsborough',
    'Dublin',
    'D07 H9K3',
    '01 234 5602',
    'James Walsh',
    '120 beds — rehab and long-stay; strong HCA team.',
    1,
    53.3592,
    -6.2734
);

-- Facility 3 — Clontarf
INSERT INTO users (email, password, role) VALUES ('seapoint.demo@carechain.io', @demo_pw, 'facility');
SET @fac_seapoint = LAST_INSERT_ID();

INSERT INTO facility_profiles (
    user_id, facility_name, facility_type, address, city, county, eircode, phone, contact_person, description, is_verified, latitude, longitude
) VALUES (
    @fac_seapoint,
    'Seapoint View Nursing Home',
    'nursing_home',
    '6 Castle Avenue',
    'Clontarf',
    'Dublin',
    'D03 K4P1',
    '01 234 5603',
    'Sinead Kerr',
    'Smaller home — high staff ratios; evening cover often needed.',
    1,
    53.3645,
    -6.2058
);

-- Open shifts (dates stay valid for ~6 months from import; adjust shift_date if needed)
INSERT INTO shifts (facility_id, title, description, shift_date, start_time, end_time, hourly_rate, total_pay, required_role, required_experience, urgency, status) VALUES
(@fac_maryfield, 'Night HCA — dementia unit', 'Assist with personal care, observations, and handover. Moving & handling cert required.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '20:00:00', '08:00:00', 18.50, 222.00, 'hca', 1, 'urgent', 'open'),
(@fac_maryfield, 'Day nurse — medication round', 'General nursing duties; NMBI active registration.', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '08:00:00', '20:00:00', 28.00, 336.00, 'nurse', 2, 'normal', 'open'),
(@fac_liffeyvale, 'HCA day shift — rehab ward', 'Rehabilitation unit; hoist work and mobility support.', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '07:30:00', '19:30:00', 17.25, 207.00, 'hca', 0, 'normal', 'open'),
(@fac_liffeyvale, 'Critical cover — evening', 'Short notice; extra cover for sick leave.', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '16:00:00', '22:00:00', 22.00, 132.00, 'any', 1, 'critical', 'open'),
(@fac_seapoint, 'Twilight carer', 'Tea, personal care, and settling — 6 hour block.', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '16:00:00', '22:00:00', 16.00, 96.00, 'carer', 0, 'normal', 'open'),
(@fac_seapoint, 'Weekend HCA', 'Saturday cover; activities and dining support.', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '08:00:00', '20:00:00', 18.00, 216.00, 'hca', 1, 'normal', 'open');
