-- CareChain — prototype seed for 360° reviews (completed shifts + review rows + profile aggregates).
--
-- Prerequisites: database `carechain` exists with schema from `carechain.sql` (reviews table + unique key,
-- worker_profiles.total_reviews, facility_profiles.total_reviews).
--
-- Password for all accounts in this file: demo123
--   (same bcrypt as seed_prototype.sql)
--
-- Import: run this file once in phpMyAdmin or: mysql -u root -p carechain < sql/seed_reviews_prototype.sql
--
-- To remove this seed only (deletes users and cascades profiles, shifts, applications, reviews):
--   DELETE FROM users WHERE email IN (
--     'reviewproto.worker1@carechain.io',
--     'reviewproto.worker2@carechain.io',
--     'reviewproto.greenhome@carechain.io',
--     'reviewproto.bluecare@carechain.io'
--   );

USE carechain;

SET @demo_pw = '$2y$10$/VnWnOGY/gLkpIxLCLzn8e159o2fsGznpf7fFY749z/0JteXIbNuS';

-- ─── Demo workers ───────────────────────────────────────────────────────────

INSERT INTO users (email, password, role) VALUES ('reviewproto.worker1@carechain.io', @demo_pw, 'worker');
SET @w1 = LAST_INSERT_ID();

INSERT INTO worker_profiles (
    user_id, first_name, last_name, phone, job_title, years_experience, bio, availability, hourly_rate,
    rating, total_reviews, is_verified, total_shifts
) VALUES (
    @w1,
    'Niamh',
    'Ryan',
    '0831001001',
    'hca',
    5,
    'Review prototype — reliable HCA, night and day shifts.',
    'flexible',
    17.00,
    0.00,
    0,
    1,
    3
);

INSERT INTO users (email, password, role) VALUES ('reviewproto.worker2@carechain.io', @demo_pw, 'worker');
SET @w2 = LAST_INSERT_ID();

INSERT INTO worker_profiles (
    user_id, first_name, last_name, phone, job_title, years_experience, bio, availability, hourly_rate,
    rating, total_reviews, is_verified, total_shifts
) VALUES (
    @w2,
    'Sean',
    'Kelly',
    '0831001002',
    'nurse',
    8,
    'Review prototype — general nursing, medication rounds.',
    'part_time',
    29.50,
    0.00,
    0,
    1,
    6
);

-- ─── Demo facilities ────────────────────────────────────────────────────────

INSERT INTO users (email, password, role) VALUES ('reviewproto.greenhome@carechain.io', @demo_pw, 'facility');
SET @f_green = LAST_INSERT_ID();

INSERT INTO facility_profiles (
    user_id, facility_name, facility_type, address, city, county, eircode, phone, contact_person, description,
    rating, total_reviews, is_verified, latitude, longitude
) VALUES (
    @f_green,
    'Greenmile Nursing Home',
    'nursing_home',
    '12 Oak Road',
    'Dublin',
    'Dublin',
    'D08 X1Y2',
    '01 555 0101',
    'Claire Byrne',
    'Review prototype — coastal Dublin home; strong HCA team.',
    0.00,
    0,
    1,
    53.3200,
    -6.2300
);

INSERT INTO users (email, password, role) VALUES ('reviewproto.bluecare@carechain.io', @demo_pw, 'facility');
SET @f_blue = LAST_INSERT_ID();

INSERT INTO facility_profiles (
    user_id, facility_name, facility_type, address, city, county, eircode, phone, contact_person, description,
    rating, total_reviews, is_verified, latitude, longitude
) VALUES (
    @f_blue,
    'Blue Ridge Care Centre',
    'nursing_home',
    '88 Harbour View',
    'Cork',
    'Cork',
    'T12 AB3C',
    '021 555 0202',
    'Tom Walsh',
    'Review prototype — Cork rehab and long-stay.',
    0.00,
    0,
    1,
    51.8985,
    -8.4756
);

-- ─── Completed shifts (past dates) ─────────────────────────────────────────

INSERT INTO shifts (
    facility_id, title, description, shift_date, start_time, end_time,
    hourly_rate, total_pay, required_role, required_experience, urgency, status
) VALUES (
    @f_green,
    'Day HCA — review prototype shift A',
    'Completed demo shift for review seeding.',
    DATE_SUB(CURDATE(), INTERVAL 14 DAY),
    '08:00:00',
    '16:00:00',
    18.00,
    144.00,
    'hca',
    0,
    'normal',
    'completed'
);
SET @s_green_w1 = LAST_INSERT_ID();

INSERT INTO shifts (
    facility_id, title, description, shift_date, start_time, end_time,
    hourly_rate, total_pay, required_role, required_experience, urgency, status
) VALUES (
    @f_green,
    'Night nurse cover — review prototype B',
    'Completed demo shift for review seeding.',
    DATE_SUB(CURDATE(), INTERVAL 10 DAY),
    '20:00:00',
    '08:00:00',
    30.00,
    360.00,
    'nurse',
    2,
    'urgent',
    'completed'
);
SET @s_green_w2 = LAST_INSERT_ID();

INSERT INTO shifts (
    facility_id, title, description, shift_date, start_time, end_time,
    hourly_rate, total_pay, required_role, required_experience, urgency, status
) VALUES (
    @f_blue,
    'Weekend HCA — review prototype C',
    'Completed demo shift for review seeding.',
    DATE_SUB(CURDATE(), INTERVAL 7 DAY),
    '08:00:00',
    '20:00:00',
    17.50,
    210.00,
    'hca',
    1,
    'normal',
    'completed'
);
SET @s_blue_w1 = LAST_INSERT_ID();

-- ─── Completed applications ─────────────────────────────────────────────────

INSERT INTO shift_applications (shift_id, worker_id, status, applied_at, accepted_at, completed_at) VALUES
(@s_green_w1, @w1, 'completed', DATE_SUB(NOW(), INTERVAL 16 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY)),
(@s_green_w2, @w2, 'completed', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(@s_blue_w1, @w1, 'completed', DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY));

-- ─── 360° reviews (facility → worker and worker → facility per shift) ───────

INSERT INTO reviews (shift_id, reviewer_id, reviewee_id, rating, comment, created_at) VALUES
-- Greenmile ↔ Niamh (shift A)
(@s_green_w1, @f_green, @w1, 5, 'Excellent communication and punctual. Residents warmed to her quickly.', DATE_SUB(NOW(), INTERVAL 13 DAY)),
(@s_green_w1, @w1, @f_green, 4, 'Supportive team on the floor; clear handover notes.', DATE_SUB(NOW(), INTERVAL 13 DAY)),
-- Greenmile ↔ Sean (shift B)
(@s_green_w2, @f_green, @w2, 4, 'Solid clinical skills; would book again.', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(@s_green_w2, @w2, @f_green, 5, 'Fair workload and good equipment. Felt respected as agency staff.', DATE_SUB(NOW(), INTERVAL 9 DAY)),
-- Blue Ridge ↔ Niamh (shift C)
(@s_blue_w1, @f_blue, @w1, 5, 'Great attitude on a busy Saturday — thank you.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@s_blue_w1, @w1, @f_blue, 3, 'Parking was tight and break room crowded, but clinical team was fine.', DATE_SUB(NOW(), INTERVAL 6 DAY));

-- ─── Denormalised ratings on profiles (matches aggregates above) ───────────

UPDATE worker_profiles SET rating = 5.00, total_reviews = 2 WHERE user_id = @w1;
UPDATE worker_profiles SET rating = 4.00, total_reviews = 1 WHERE user_id = @w2;

UPDATE facility_profiles SET rating = 4.50, total_reviews = 2 WHERE user_id = @f_green;
UPDATE facility_profiles SET rating = 3.00, total_reviews = 1 WHERE user_id = @f_blue;
