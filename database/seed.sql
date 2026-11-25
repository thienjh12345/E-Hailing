-- =============================================================
-- Crab E-Hailing — demo seed data (run AFTER schema.sql)
-- Two cities: Klang Valley (KL) and Kota Kinabalu (Sabah). Each driver works in one city.
-- All demo passenger/driver accounts use the password: password
-- Ride timestamps are relative to NOW() so the dashboard always
-- shows recent activity.
-- =============================================================

USE crab_db;

INSERT INTO passengers (name, email, phone, password_hash, status, created_at) VALUES
('Aisyah Rahman', 'aisyah.rahman@mail.com', '017-2126067', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 113 DAY),
('Wei Jie Tan', 'wei.jie.tan@mail.com', '015-3890026', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 92 DAY),
('Priya Nair', 'priya.nair@mail.com', '018-8451924', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 123 DAY),
('Muhammad Haziq', 'muhammad.haziq@mail.com', '013-4991378', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 59 DAY),
('Chloe Lim', 'chloe.lim@mail.com', '019-2458954', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 172 DAY),
('Arjun Pillai', 'arjun.pillai@mail.com', '015-8769315', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 140 DAY),
('Nurul Izzah', 'nurul.izzah@mail.com', '014-6544706', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'suspended', NOW() - INTERVAL 26 DAY),
('Jason Wong', 'jason.wong@mail.com', '019-8731073', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 44 DAY),
('Siti Aminah', 'siti.aminah@mail.com', '019-9053213', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 138 DAY),
('Daniel Ong', 'daniel.ong@mail.com', '011-7932287', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 64 DAY),
('Mei Ling Chew', 'mei.ling.chew@mail.com', '014-7062880', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 64 DAY),
('Hafiz Ismail', 'hafiz.ismail@mail.com', '018-4811733', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 129 DAY),
('Kavitha Rao', 'kavitha.rao@mail.com', '013-1052376', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 133 DAY),
('Ryan Teo', 'ryan.teo@mail.com', '014-9888186', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 131 DAY),
('Farah Aziz', 'farah.aziz@mail.com', '012-3427519', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 16 DAY),
('Marcus Lee', 'marcus.lee@mail.com', '019-8512143', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'suspended', NOW() - INTERVAL 179 DAY),
('Liyana Hamid', 'liyana.hamid@mail.com', '011-3151677', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 51 DAY),
('Kelvin Goh', 'kelvin.goh@mail.com', '011-3592370', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 64 DAY),
('Deepa Menon', 'deepa.menon@mail.com', '018-2944499', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 20 DAY),
('Amir Firdaus', 'amir.firdaus@mail.com', '016-2502978', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'active', NOW() - INTERVAL 121 DAY);

INSERT INTO drivers (name, email, phone, password_hash, license_no, vehicle_type, vehicle_model, plate_no, status, is_online, rating, created_at) VALUES
('Ahmad Faizal', 'ahmad.faizal@crabdriver.com', '013-6898630', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D22395540', 'car', 'Perodua Myvi', 'WXY 1234', 'approved', 1, 4.89, NOW() - INTERVAL 116 DAY),
('Lim Chee Keong', 'lim.chee.keong@crabdriver.com', '017-6725720', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D17358011', 'car', 'Proton Saga', 'BKL 8821', 'approved', 1, 4.42, NOW() - INTERVAL 62 DAY),
('Rajesh Kumar', 'rajesh.kumar@crabdriver.com', '018-6574553', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D69498548', 'premium', 'Honda Accord', 'VAB 7777', 'approved', 0, 4.65, NOW() - INTERVAL 150 DAY),
('Nor Azlina', 'nor.azlina@crabdriver.com', '019-1594491', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D84150214', 'car', 'Perodua Bezza', 'SAA 4410', 'approved', 1, 4.21, NOW() - INTERVAL 77 DAY),
('Tan Boon Huat', 'tan.boon.huat@crabdriver.com', '015-7595490', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D45501389', 'car', 'Proton X50', 'SAB 3021', 'approved', 0, 4.85, NOW() - INTERVAL 115 DAY),
('Zulkifli Hassan', 'zulkifli.hassan@crabdriver.com', '015-5252547', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D88788865', 'bike', 'Yamaha Y15ZR', 'SA 5512A', 'approved', 0, 4.46, NOW() - INTERVAL 157 DAY),
('Wong Kah Wai', 'wong.kah.wai@crabdriver.com', '018-9254339', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D83943861', 'premium', 'Toyota Camry', 'SAE 9090', 'approved', 1, 4.38, NOW() - INTERVAL 113 DAY),
('Saravanan Muthu', 'saravanan.muthu@crabdriver.com', '011-6679275', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D85117217', 'car', 'Honda City', 'BNM 6634', 'approved', 1, 4.84, NOW() - INTERVAL 166 DAY),
('Hidayah Yusof', 'hidayah.yusof@crabdriver.com', '018-1609438', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D99419695', 'car', 'Perodua Axia', 'SAA 2287', 'suspended', 0, 4.75, NOW() - INTERVAL 104 DAY),
('Ong Jun Hao', 'ong.jun.hao@crabdriver.com', '011-5739990', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D86966139', 'car', 'Proton Persona', 'VCR 1189', 'pending', 0, NULL, NOW() - INTERVAL 0 DAY),
('Mohd Ridzuan', 'mohd.ridzuan@crabdriver.com', '018-3283111', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D37245268', 'bike', 'Honda RS-X', 'ST 4421', 'pending', 0, NULL, NOW() - INTERVAL 3 DAY),
('Cheryl Ng', 'cheryl.ng@crabdriver.com', '015-5435217', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D77960046', 'car', 'Toyota Vios', 'WRR 3355', 'pending', 0, NULL, NOW() - INTERVAL 0 DAY),
('Ganesh Pillay', 'ganesh.pillay@crabdriver.com', '014-3243865', '$2y$10$xD74Fqzu6eFC8pNcTaIkn.07TYCmNFgJOTw.H7wgR3gwnXnEN4RwC', 'D15739992', 'car', 'Perodua Ativa', 'BQF 7712', 'rejected', 0, NULL, NOW() - INTERVAL 96 DAY);

INSERT INTO rides (passenger_id, driver_id, pickup_address, dropoff_address, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng, distance_km, fare, payment_method, status, rating, requested_at, completed_at) VALUES
(7, 5, 'Inanam Town', 'Oceanus Waterfront Mall', 6.0130, 116.1290, 5.9800, 116.0720, 9.50, 15.40, 'cash', 'completed', NULL, NOW() - INTERVAL 18907 MINUTE, NOW() - INTERVAL 18881 MINUTE),
(16, 1, 'Pavilion KL', 'TAR UMT, Setapak', 3.149, 101.7134, 3.216, 101.7266, 9.85, 15.82, 'wallet', 'completed', 5, NOW() - INTERVAL 20070 MINUTE, NOW() - INTERVAL 20040 MINUTE),
(17, 6, 'Universiti Malaysia Sabah (UMS)', '1Borneo Hypermall', 6.0339, 116.1219, 6.0377, 116.1179, 1.50, 3.70, 'wallet', 'completed', 5, NOW() - INTERVAL 18928 MINUTE, NOW() - INTERVAL 18856 MINUTE),
(4, 9, 'Tanjung Aru Beach', 'Damai Plaza, Luyang', 5.9497, 116.0430, 5.9650, 116.0880, 6.86, 12.23, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 20040 MINUTE, NULL),
(5, 2, '1 Utama, Petaling Jaya', 'IOI City Mall, Putrajaya', 3.1503, 101.6157, 2.9701, 101.7135, 29.59, 39.51, 'wallet', 'completed', 5, NOW() - INTERVAL 18959 MINUTE, NOW() - INTERVAL 18880 MINUTE),
(3, 3, 'KLCC, Kuala Lumpur', 'Sunway Pyramid', 3.1579, 101.7116, 3.0731, 101.607, 19.43, 47.8, 'card', 'cancelled', NULL, NOW() - INTERVAL 19746 MINUTE, NULL),
(10, 8, 'Cheras Leisure Mall', '1 Utama, Petaling Jaya', 3.0889, 101.7425, 3.1503, 101.6157, 20.33, 28.4, 'wallet', 'completed', NULL, NOW() - INTERVAL 18916 MINUTE, NOW() - INTERVAL 18860 MINUTE),
(12, 3, 'Damansara Heights', 'Cheras Leisure Mall', 3.152, 101.6599, 3.0889, 101.7425, 15, 38.5, 'cash', 'cancelled', NULL, NOW() - INTERVAL 19630 MINUTE, NULL),
(13, 7, 'Damai Plaza, Luyang', 'Imago Shopping Mall, Kota Kinabalu', 5.9650, 116.0880, 5.9700, 116.0667, 3.16, 13.64, 'wallet', 'completed', 5, NOW() - INTERVAL 20159 MINUTE, NOW() - INTERVAL 20119 MINUTE),
(1, 3, 'Wangsa Maju LRT', 'Damansara Heights', 3.2056, 101.7319, 3.152, 101.6599, 12.95, 34.2, 'wallet', 'completed', 5, NOW() - INTERVAL 18790 MINUTE, NOW() - INTERVAL 18753 MINUTE),
(2, 3, 'Bangsar South', 'Kepong Metropolitan Park', 3.1106, 101.6656, 3.2139, 101.6347, 15.56, 39.68, 'cash', 'completed', NULL, NOW() - INTERVAL 19858 MINUTE, NOW() - INTERVAL 19814 MINUTE),
(20, NULL, 'Bangsar South', 'Kepong Metropolitan Park', 3.1106, 101.6656, 3.2139, 101.6347, 15.56, 22.67, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 19988 MINUTE, NULL),
(4, 7, 'Menggatal Town', 'Donggongon Town, Penampang', 6.0560, 116.1505, 5.9175, 116.1092, 20.86, 50.81, 'cash', 'completed', 5, NOW() - INTERVAL 18570 MINUTE, NOW() - INTERVAL 18461 MINUTE),
(16, 4, 'Lintas Square, Luyang', 'Putatan Town', 5.9590, 116.0940, 5.8890, 116.0560, 11.49, 17.79, 'cash', 'completed', 5, NOW() - INTERVAL 17432 MINUTE, NOW() - INTERVAL 17368 MINUTE),
(5, 1, 'Subang Jaya SS15', 'Pavilion KL', 3.077, 101.5887, 3.149, 101.7134, 20.78, 28.94, 'cash', 'completed', 5, NOW() - INTERVAL 17511 MINUTE, NOW() - INTERVAL 17454 MINUTE),
(8, 7, 'Lintas Square, Luyang', 'Sutera Harbour', 5.9590, 116.0940, 5.9674, 116.0581, 5.32, 18.17, 'wallet', 'completed', 4, NOW() - INTERVAL 18350 MINUTE, NOW() - INTERVAL 18315 MINUTE),
(14, 6, 'Donggongon Town, Penampang', 'Kota Kinabalu International Airport', 5.9175, 116.1092, 5.9372, 116.0515, 8.80, 9.54, 'wallet', 'completed', 5, NOW() - INTERVAL 18672 MINUTE, NOW() - INTERVAL 18641 MINUTE),
(5, 8, 'TAR UMT, Setapak', 'KLCC, Kuala Lumpur', 3.216, 101.7266, 3.1579, 101.7116, 8.66, 14.39, 'card', 'completed', 5, NOW() - INTERVAL 18512 MINUTE, NOW() - INTERVAL 18485 MINUTE),
(3, 5, 'Menggatal Town', 'Imago Shopping Mall, Kota Kinabalu', 6.0560, 116.1505, 5.9700, 116.0667, 17.33, 24.80, 'card', 'completed', NULL, NOW() - INTERVAL 17924 MINUTE, NOW() - INTERVAL 17825 MINUTE),
(8, 3, 'Cheras Leisure Mall', 'Setia Alam', 3.0889, 101.7425, 3.1046, 101.4553, 41.5, 94.15, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 18315 MINUTE, NULL),
(7, 2, 'TAR UMT, Setapak', 'Subang Jaya SS15', 3.216, 101.7266, 3.077, 101.5887, 28.25, 37.9, 'card', 'completed', NULL, NOW() - INTERVAL 18698 MINUTE, NOW() - INTERVAL 18622 MINUTE),
(20, 2, 'Bangsar South', 'Subang Jaya SS15', 3.1106, 101.6656, 3.077, 101.5887, 12.11, 18.53, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 16929 MINUTE, NULL),
(1, 1, 'Subang Jaya SS15', 'Bangsar South', 3.077, 101.5887, 3.1106, 101.6656, 12.11, 18.53, 'wallet', 'completed', 5, NOW() - INTERVAL 17019 MINUTE, NOW() - INTERVAL 16984 MINUTE),
(15, 2, 'Cheras Leisure Mall', 'Sunway Pyramid', 3.0889, 101.7425, 3.0731, 101.607, 19.69, 27.63, 'wallet', 'completed', 5, NOW() - INTERVAL 16018 MINUTE, NOW() - INTERVAL 15964 MINUTE),
(18, 1, 'TAR UMT, Setapak', 'Setia Alam', 3.216, 101.7266, 3.1046, 101.4553, 42.32, 54.78, 'wallet', 'completed', 5, NOW() - INTERVAL 15936 MINUTE, NOW() - INTERVAL 15825 MINUTE),
(18, 1, 'Mid Valley Megamall', '1 Utama, Petaling Jaya', 3.1176, 101.6774, 3.1503, 101.6157, 10.08, 16.1, 'card', 'completed', 5, NOW() - INTERVAL 17240 MINUTE, NOW() - INTERVAL 17210 MINUTE),
(12, 1, 'Setia Alam', 'Pavilion KL', 3.1046, 101.4553, 3.149, 101.7134, 37.79, 49.35, 'cash', 'completed', NULL, NOW() - INTERVAL 16057 MINUTE, NOW() - INTERVAL 15958 MINUTE),
(5, 2, 'IOI City Mall, Putrajaya', 'Kepong Metropolitan Park', 2.9701, 101.7135, 3.2139, 101.6347, 36.97, 48.36, 'wallet', 'completed', 5, NOW() - INTERVAL 17007 MINUTE, NOW() - INTERVAL 16910 MINUTE),
(3, NULL, 'Sunway Pyramid', 'Setia Alam', 3.0731, 101.607, 3.1046, 101.4553, 22.36, 53.96, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 16002 MINUTE, NULL),
(19, 1, 'KLCC, Kuala Lumpur', 'TAR UMT, Setapak', 3.1579, 101.7116, 3.216, 101.7266, 8.66, 14.39, 'cash', 'completed', 5, NOW() - INTERVAL 16439 MINUTE, NOW() - INTERVAL 16412 MINUTE),
(18, 7, 'Putatan Town', '1Borneo Hypermall', 5.8890, 116.0560, 6.0377, 116.1179, 23.24, 55.80, 'cash', 'completed', NULL, NOW() - INTERVAL 16019 MINUTE, NOW() - INTERVAL 15961 MINUTE),
(2, 9, 'Suria Sabah Shopping Mall', 'Menggatal Town', 5.9858, 116.0797, 6.0560, 116.1505, 14.39, 21.27, 'card', 'completed', 5, NOW() - INTERVAL 16956 MINUTE, NOW() - INTERVAL 16871 MINUTE),
(17, 1, 'KL Sentral', 'KLCC, Kuala Lumpur', 3.134, 101.6865, 3.1579, 101.7116, 5, 10, 'card', 'completed', 4, NOW() - INTERVAL 16232 MINUTE, NOW() - INTERVAL 16214 MINUTE),
(13, 9, 'Universiti Malaysia Sabah (UMS)', 'Damai Plaza, Luyang', 6.0339, 116.1219, 5.9650, 116.0880, 11.08, 17.30, 'wallet', 'completed', 5, NOW() - INTERVAL 16703 MINUTE, NOW() - INTERVAL 16651 MINUTE),
(18, 6, 'Damai Plaza, Luyang', 'Sutera Harbour', 5.9650, 116.0880, 5.9674, 116.0581, 4.33, 5.96, 'card', 'completed', NULL, NOW() - INTERVAL 15797 MINUTE, NOW() - INTERVAL 15769 MINUTE),
(15, 6, 'Sutera Harbour', 'Universiti Malaysia Sabah (UMS)', 5.9674, 116.0581, 6.0339, 116.1219, 13.30, 13.14, 'card', 'completed', 5, NOW() - INTERVAL 14418 MINUTE, NOW() - INTERVAL 14369 MINUTE),
(4, 7, 'Menggatal Town', 'Imago Shopping Mall, Kota Kinabalu', 6.0560, 116.1505, 5.9700, 116.0667, 17.33, 43.39, 'cash', 'completed', 4, NOW() - INTERVAL 15438 MINUTE, NOW() - INTERVAL 15339 MINUTE),
(20, 7, 'Imago Shopping Mall, Kota Kinabalu', 'Menggatal Town', 5.9700, 116.0667, 6.0560, 116.1505, 17.33, 43.39, 'wallet', 'completed', NULL, NOW() - INTERVAL 15327 MINUTE, NOW() - INTERVAL 15228 MINUTE),
(8, NULL, 'Gaya Street, Kota Kinabalu', 'Sutera Harbour', 5.9839, 116.0767, 5.9674, 116.0581, 3.59, 8.31, 'cash', 'cancelled', NULL, NOW() - INTERVAL 14669 MINUTE, NULL),
(19, 5, 'Imago Shopping Mall, Kota Kinabalu', 'Putatan Town', 5.9700, 116.0667, 5.8890, 116.0560, 11.79, 18.15, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 14716 MINUTE, NULL),
(20, 1, 'Damansara Heights', 'Wangsa Maju LRT', 3.152, 101.6599, 3.2056, 101.7319, 12.95, 19.54, 'wallet', 'completed', 5, NOW() - INTERVAL 15795 MINUTE, NOW() - INTERVAL 15758 MINUTE),
(10, 3, 'Pavilion KL', 'Cheras Leisure Mall', 3.149, 101.7134, 3.0889, 101.7425, 9.64, 27.24, 'card', 'completed', 3, NOW() - INTERVAL 15665 MINUTE, NOW() - INTERVAL 15636 MINUTE),
(15, 9, 'Menggatal Town', 'Likas Bay', 6.0560, 116.1505, 5.9980, 116.0960, 11.48, 17.78, 'card', 'completed', 5, NOW() - INTERVAL 14897 MINUTE, NOW() - INTERVAL 14816 MINUTE),
(17, 6, 'Imago Shopping Mall, Kota Kinabalu', 'Universiti Malaysia Sabah (UMS)', 5.9700, 116.0667, 6.0339, 116.1219, 12.18, 12.24, 'wallet', 'completed', 5, NOW() - INTERVAL 14585 MINUTE, NOW() - INTERVAL 14558 MINUTE),
(17, 8, 'Cheras Leisure Mall', 'Sunway Pyramid', 3.0889, 101.7425, 3.0731, 101.607, 19.69, 27.63, 'wallet', 'completed', 5, NOW() - INTERVAL 13942 MINUTE, NOW() - INTERVAL 13888 MINUTE),
(5, NULL, 'KL Sentral', 'Wangsa Maju LRT', 3.134, 101.6865, 3.2056, 101.7319, 12.23, 32.68, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 13848 MINUTE, NULL),
(7, 2, 'IOI City Mall, Putrajaya', 'Kepong Metropolitan Park', 2.9701, 101.7135, 3.2139, 101.6347, 36.97, 48.36, 'wallet', 'completed', 5, NOW() - INTERVAL 13103 MINUTE, NOW() - INTERVAL 13006 MINUTE),
(1, 2, 'Damansara Heights', 'Bangsar South', 3.152, 101.6599, 3.1106, 101.6656, 6.03, 11.24, 'card', 'completed', 5, NOW() - INTERVAL 13974 MINUTE, NOW() - INTERVAL 13954 MINUTE),
(8, 7, 'Donggongon Town, Penampang', 'Sutera Harbour', 5.9175, 116.1092, 5.9674, 116.0581, 10.31, 28.65, 'card', 'completed', 5, NOW() - INTERVAL 14169 MINUTE, NOW() - INTERVAL 14135 MINUTE),
(1, 5, 'Gaya Street, Kota Kinabalu', 'Imago Shopping Mall, Kota Kinabalu', 5.9839, 116.0767, 5.9700, 116.0667, 2.47, 6.96, 'wallet', 'completed', 4, NOW() - INTERVAL 14043 MINUTE, NOW() - INTERVAL 14034 MINUTE),
(12, 1, 'Bangsar South', 'Wangsa Maju LRT', 3.1106, 101.6656, 3.2056, 101.7319, 16.72, 24.06, 'card', 'completed', 4, NOW() - INTERVAL 13987 MINUTE, NOW() - INTERVAL 13940 MINUTE),
(16, 3, 'Setia Alam', 'Wangsa Maju LRT', 3.1046, 101.4553, 3.2056, 101.7319, 42.49, 96.23, 'card', 'completed', 5, NOW() - INTERVAL 14067 MINUTE, NOW() - INTERVAL 13956 MINUTE),
(7, 4, '1Borneo Hypermall', 'Tanjung Aru Beach', 6.0377, 116.1179, 5.9497, 116.0430, 16.68, 24.02, 'card', 'completed', 5, NOW() - INTERVAL 13918 MINUTE, NOW() - INTERVAL 13861 MINUTE),
(1, 6, 'Likas Bay', 'Oceanus Waterfront Mall', 5.9980, 116.0960, 5.9800, 116.0720, 4.33, 5.96, 'cash', 'completed', 4, NOW() - INTERVAL 14166 MINUTE, NOW() - INTERVAL 14142 MINUTE),
(8, 8, 'Setia Alam', 'IOI City Mall, Putrajaya', 3.1046, 101.4553, 2.9701, 101.7135, 42.01, 54.41, 'cash', 'completed', NULL, NOW() - INTERVAL 13697 MINUTE, NOW() - INTERVAL 13587 MINUTE),
(11, 2, 'KLCC, Kuala Lumpur', 'Setia Alam', 3.1579, 101.7116, 3.1046, 101.4553, 37.78, 49.34, 'wallet', 'completed', NULL, NOW() - INTERVAL 13492 MINUTE, NOW() - INTERVAL 13393 MINUTE),
(12, 5, 'Inanam Town', 'Damai Plaza, Luyang', 6.0130, 116.1290, 5.9650, 116.0880, 9.11, 14.93, 'card', 'completed', 5, NOW() - INTERVAL 13237 MINUTE, NOW() - INTERVAL 13186 MINUTE),
(19, 1, 'IOI City Mall, Putrajaya', 'Kepong Metropolitan Park', 2.9701, 101.7135, 3.2139, 101.6347, 36.97, 48.36, 'cash', 'completed', 5, NOW() - INTERVAL 12732 MINUTE, NOW() - INTERVAL 12635 MINUTE),
(2, 2, 'Kepong Metropolitan Park', 'Setia Alam', 3.2139, 101.6347, 3.1046, 101.4553, 30.31, 40.37, 'wallet', 'completed', 5, NOW() - INTERVAL 12804 MINUTE, NOW() - INTERVAL 12723 MINUTE),
(14, 2, 'IOI City Mall, Putrajaya', 'Bukit Bintang', 2.9701, 101.7135, 3.1466, 101.7108, 25.47, 34.56, 'card', 'completed', NULL, NOW() - INTERVAL 12445 MINUTE, NOW() - INTERVAL 12376 MINUTE),
(10, 5, 'Suria Sabah Shopping Mall', 'Kota Kinabalu International Airport', 5.9858, 116.0797, 5.9372, 116.0515, 8.11, 13.73, 'card', 'completed', 5, NOW() - INTERVAL 11907 MINUTE, NOW() - INTERVAL 11895 MINUTE),
(4, 5, 'Gaya Street, Kota Kinabalu', 'Inanam Town', 5.9839, 116.0767, 6.0130, 116.1290, 8.64, 14.37, 'wallet', 'completed', 5, NOW() - INTERVAL 12823 MINUTE, NOW() - INTERVAL 12795 MINUTE),
(16, 9, 'Menggatal Town', 'Lintas Square, Luyang', 6.0560, 116.1505, 5.9590, 116.0940, 16.20, 23.44, 'wallet', 'completed', 5, NOW() - INTERVAL 12347 MINUTE, NOW() - INTERVAL 12293 MINUTE),
(9, 1, 'Wangsa Maju LRT', 'Sunway Pyramid', 3.2056, 101.7319, 3.0731, 101.607, 26.28, 35.54, 'wallet', 'completed', NULL, NOW() - INTERVAL 12192 MINUTE, NOW() - INTERVAL 12121 MINUTE),
(4, 9, 'Universiti Malaysia Sabah (UMS)', 'Donggongon Town, Penampang', 6.0339, 116.1219, 5.9175, 116.1092, 16.90, 24.28, 'cash', 'completed', 5, NOW() - INTERVAL 12256 MINUTE, NOW() - INTERVAL 12205 MINUTE),
(7, 5, 'Inanam Town', '1Borneo Hypermall', 6.0130, 116.1290, 6.0377, 116.1179, 3.91, 8.69, 'wallet', 'completed', 5, NOW() - INTERVAL 12545 MINUTE, NOW() - INTERVAL 12474 MINUTE),
(3, 8, 'Cheras Leisure Mall', 'IOI City Mall, Putrajaya', 3.0889, 101.7425, 2.9701, 101.7135, 17.65, 25.18, 'cash', 'completed', 5, NOW() - INTERVAL 11465 MINUTE, NOW() - INTERVAL 11416 MINUTE),
(12, 7, 'Inanam Town', 'Lintas Square, Luyang', 6.0130, 116.1290, 5.9590, 116.0940, 9.29, 26.51, 'wallet', 'completed', NULL, NOW() - INTERVAL 10641 MINUTE, NOW() - INTERVAL 10567 MINUTE),
(9, 2, 'Pavilion KL', 'KL Sentral', 3.149, 101.7134, 3.134, 101.6865, 4.44, 9.33, 'cash', 'completed', 5, NOW() - INTERVAL 10684 MINUTE, NOW() - INTERVAL 10668 MINUTE),
(5, 8, 'Setia Alam', 'KL Sentral', 3.1046, 101.4553, 3.134, 101.6865, 33.63, 44.36, 'wallet', 'completed', 4, NOW() - INTERVAL 10690 MINUTE, NOW() - INTERVAL 10601 MINUTE),
(15, 6, 'Gaya Street, Kota Kinabalu', 'Tanjung Aru Beach', 5.9839, 116.0767, 5.9497, 116.0430, 6.93, 8.04, 'cash', 'completed', NULL, NOW() - INTERVAL 11071 MINUTE, NOW() - INTERVAL 11029 MINUTE),
(5, 1, 'Pavilion KL', 'IOI City Mall, Putrajaya', 3.149, 101.7134, 2.9701, 101.7135, 25.82, 34.98, 'card', 'completed', 5, NOW() - INTERVAL 10082 MINUTE, NOW() - INTERVAL 10012 MINUTE),
(4, 4, 'Lintas Square, Luyang', '1Borneo Hypermall', 5.9590, 116.0940, 6.0377, 116.1179, 11.87, 18.24, 'wallet', 'completed', NULL, NOW() - INTERVAL 10764 MINUTE, NOW() - INTERVAL 10752 MINUTE),
(8, 7, 'Tanjung Aru Beach', 'Oceanus Waterfront Mall', 5.9497, 116.0430, 5.9800, 116.0720, 6.05, 19.71, 'card', 'completed', 5, NOW() - INTERVAL 11314 MINUTE, NOW() - INTERVAL 11272 MINUTE),
(9, 9, 'Likas Bay', 'Menggatal Town', 5.9980, 116.0960, 6.0560, 116.1505, 11.48, 17.78, 'wallet', 'completed', NULL, NOW() - INTERVAL 10521 MINUTE, NOW() - INTERVAL 10440 MINUTE),
(13, 9, 'Lintas Square, Luyang', 'Gaya Street, Kota Kinabalu', 5.9590, 116.0940, 5.9839, 116.0767, 4.38, 9.26, 'wallet', 'completed', NULL, NOW() - INTERVAL 11059 MINUTE, NOW() - INTERVAL 11003 MINUTE),
(18, 5, 'Inanam Town', 'Gaya Street, Kota Kinabalu', 6.0130, 116.1290, 5.9839, 116.0767, 8.64, 14.37, 'wallet', 'completed', 5, NOW() - INTERVAL 10598 MINUTE, NOW() - INTERVAL 10570 MINUTE),
(7, 6, 'Damai Plaza, Luyang', 'Inanam Town', 5.9650, 116.0880, 6.0130, 116.1290, 9.11, 9.79, 'card', 'completed', 5, NOW() - INTERVAL 10306 MINUTE, NOW() - INTERVAL 10255 MINUTE),
(2, 9, 'Tanjung Aru Beach', 'Imago Shopping Mall, Kota Kinabalu', 5.9497, 116.0430, 5.9700, 116.0667, 4.50, 9.40, 'wallet', 'completed', NULL, NOW() - INTERVAL 11513 MINUTE, NOW() - INTERVAL 11474 MINUTE),
(19, 2, 'Cheras Leisure Mall', 'Pavilion KL', 3.0889, 101.7425, 3.149, 101.7134, 9.64, 15.57, 'card', 'completed', NULL, NOW() - INTERVAL 10119 MINUTE, NOW() - INTERVAL 10090 MINUTE),
(16, 2, 'Bangsar South', 'Sunway Pyramid', 3.1106, 101.6656, 3.0731, 101.607, 10.04, 16.05, 'cash', 'completed', 5, NOW() - INTERVAL 9124 MINUTE, NOW() - INTERVAL 9094 MINUTE),
(20, NULL, '1 Utama, Petaling Jaya', 'Pavilion KL', 3.1503, 101.6157, 3.149, 101.7134, 14.1, 20.92, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 9982 MINUTE, NULL),
(10, 5, '1Borneo Hypermall', 'Inanam Town', 6.0377, 116.1179, 6.0130, 116.1290, 3.91, 8.69, 'wallet', 'completed', 5, NOW() - INTERVAL 9502 MINUTE, NOW() - INTERVAL 9431 MINUTE),
(13, 3, 'IOI City Mall, Putrajaya', 'Sunway Pyramid', 2.9701, 101.7135, 3.0731, 101.607, 21.38, 51.9, 'card', 'completed', 5, NOW() - INTERVAL 9222 MINUTE, NOW() - INTERVAL 9164 MINUTE),
(1, 4, 'Universiti Malaysia Sabah (UMS)', 'Inanam Town', 6.0339, 116.1219, 6.0130, 116.1290, 3.19, 7.83, 'wallet', 'completed', 5, NOW() - INTERVAL 8987 MINUTE, NOW() - INTERVAL 8978 MINUTE),
(1, 9, 'Oceanus Waterfront Mall', 'Likas Bay', 5.9800, 116.0720, 5.9980, 116.0960, 4.33, 9.20, 'cash', 'completed', NULL, NOW() - INTERVAL 9245 MINUTE, NOW() - INTERVAL 9221 MINUTE),
(12, 4, 'Gaya Street, Kota Kinabalu', 'Sutera Harbour', 5.9839, 116.0767, 5.9674, 116.0581, 3.59, 8.31, 'cash', 'completed', NULL, NOW() - INTERVAL 9445 MINUTE, NOW() - INTERVAL 9419 MINUTE),
(18, 4, 'Likas Bay', 'Gaya Street, Kota Kinabalu', 5.9980, 116.0960, 5.9839, 116.0767, 3.45, 8.14, 'cash', 'completed', 5, NOW() - INTERVAL 9308 MINUTE, NOW() - INTERVAL 9285 MINUTE),
(6, 9, '1Borneo Hypermall', 'Kota Kinabalu International Airport', 6.0377, 116.1179, 5.9372, 116.0515, 17.38, 24.86, 'wallet', 'completed', NULL, NOW() - INTERVAL 9698 MINUTE, NOW() - INTERVAL 9657 MINUTE),
(6, 9, 'Likas Bay', 'Imago Shopping Mall, Kota Kinabalu', 5.9980, 116.0960, 5.9700, 116.0667, 5.85, 11.02, 'card', 'completed', 5, NOW() - INTERVAL 9069 MINUTE, NOW() - INTERVAL 9045 MINUTE),
(12, 2, 'Cheras Leisure Mall', 'Setia Alam', 3.0889, 101.7425, 3.1046, 101.4553, 41.5, 53.8, 'card', 'completed', 5, NOW() - INTERVAL 7691 MINUTE, NOW() - INTERVAL 7582 MINUTE),
(18, 8, 'Cheras Leisure Mall', 'Kepong Metropolitan Park', 3.0889, 101.7425, 3.2139, 101.6347, 23.82, 32.58, 'wallet', 'completed', 5, NOW() - INTERVAL 8071 MINUTE, NOW() - INTERVAL 8006 MINUTE),
(12, 5, 'Gaya Street, Kota Kinabalu', 'Oceanus Waterfront Mall', 5.9839, 116.0767, 5.9800, 116.0720, 1.50, 5.80, 'wallet', 'completed', 5, NOW() - INTERVAL 8059 MINUTE, NOW() - INTERVAL 8050 MINUTE),
(12, 8, 'Bukit Bintang', 'KL Sentral', 3.1466, 101.7108, 3.134, 101.6865, 3.95, 8.74, 'wallet', 'completed', NULL, NOW() - INTERVAL 7296 MINUTE, NOW() - INTERVAL 7281 MINUTE),
(10, 2, 'Sunway Pyramid', 'Wangsa Maju LRT', 3.0731, 101.607, 3.2056, 101.7319, 26.28, 35.54, 'wallet', 'completed', NULL, NOW() - INTERVAL 7437 MINUTE, NOW() - INTERVAL 7366 MINUTE),
(8, 9, 'Oceanus Waterfront Mall', 'Suria Sabah Shopping Mall', 5.9800, 116.0720, 5.9858, 116.0797, 1.50, 5.80, 'card', 'completed', 5, NOW() - INTERVAL 7310 MINUTE, NOW() - INTERVAL 7288 MINUTE),
(13, 7, 'Universiti Malaysia Sabah (UMS)', 'Imago Shopping Mall, Kota Kinabalu', 6.0339, 116.1219, 5.9700, 116.0667, 12.18, 32.58, 'card', 'completed', 5, NOW() - INTERVAL 8022 MINUTE, NOW() - INTERVAL 7995 MINUTE),
(14, 4, 'Donggongon Town, Penampang', 'Likas Bay', 5.9175, 116.1092, 5.9980, 116.0960, 11.77, 18.12, 'wallet', 'completed', NULL, NOW() - INTERVAL 8468 MINUTE, NOW() - INTERVAL 8425 MINUTE),
(15, 2, 'KLCC, Kuala Lumpur', 'Mid Valley Megamall', 3.1579, 101.7116, 3.1176, 101.6774, 7.63, 13.16, 'wallet', 'completed', NULL, NOW() - INTERVAL 8300 MINUTE, NOW() - INTERVAL 8276 MINUTE),
(9, 4, 'Damai Plaza, Luyang', 'Sutera Harbour', 5.9650, 116.0880, 5.9674, 116.0581, 4.33, 9.20, 'cash', 'completed', NULL, NOW() - INTERVAL 7887 MINUTE, NOW() - INTERVAL 7859 MINUTE),
(9, 7, 'Gaya Street, Kota Kinabalu', 'Damai Plaza, Luyang', 5.9839, 116.0767, 5.9650, 116.0880, 3.18, 13.68, 'cash', 'completed', 5, NOW() - INTERVAL 7106 MINUTE, NOW() - INTERVAL 7067 MINUTE),
(5, 4, 'Putatan Town', 'Oceanus Waterfront Mall', 5.8890, 116.0560, 5.9800, 116.0720, 13.33, 20.00, 'wallet', 'completed', NULL, NOW() - INTERVAL 6516 MINUTE, NOW() - INTERVAL 6446 MINUTE),
(4, 6, 'Putatan Town', 'Imago Shopping Mall, Kota Kinabalu', 5.8890, 116.0560, 5.9700, 116.0667, 11.79, 11.93, 'wallet', 'completed', 5, NOW() - INTERVAL 6531 MINUTE, NOW() - INTERVAL 6458 MINUTE),
(10, 2, 'Cheras Leisure Mall', 'Mid Valley Megamall', 3.0889, 101.7425, 3.1176, 101.6774, 10.27, 16.32, 'wallet', 'completed', 5, NOW() - INTERVAL 6566 MINUTE, NOW() - INTERVAL 6535 MINUTE),
(18, 7, 'Tanjung Aru Beach', 'Gaya Street, Kota Kinabalu', 5.9497, 116.0430, 5.9839, 116.0767, 6.93, 21.55, 'wallet', 'completed', NULL, NOW() - INTERVAL 6133 MINUTE, NOW() - INTERVAL 6091 MINUTE),
(17, 1, 'Damansara Heights', '1 Utama, Petaling Jaya', 3.152, 101.6599, 3.1503, 101.6157, 6.38, 11.66, 'cash', 'completed', 5, NOW() - INTERVAL 7183 MINUTE, NOW() - INTERVAL 7162 MINUTE),
(10, 4, 'Sutera Harbour', 'Menggatal Town', 5.9674, 116.0581, 6.0560, 116.1505, 18.47, 26.16, 'card', 'completed', 5, NOW() - INTERVAL 7142 MINUTE, NOW() - INTERVAL 7061 MINUTE),
(10, NULL, 'Subang Jaya SS15', 'Sunway Pyramid', 3.077, 101.5887, 3.0731, 101.607, 2.7, 12.67, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 5774 MINUTE, NULL),
(10, 8, 'TAR UMT, Setapak', 'Damansara Heights', 3.216, 101.7266, 3.152, 101.6599, 13.34, 20.01, 'wallet', 'completed', 5, NOW() - INTERVAL 6880 MINUTE, NOW() - INTERVAL 6842 MINUTE),
(9, 2, 'KL Sentral', 'Cheras Leisure Mall', 3.134, 101.6865, 3.0889, 101.7425, 10.38, 16.46, 'card', 'completed', 5, NOW() - INTERVAL 6652 MINUTE, NOW() - INTERVAL 6621 MINUTE),
(18, 5, 'Suria Sabah Shopping Mall', 'Sutera Harbour', 5.9858, 116.0797, 5.9674, 116.0581, 4.09, 8.91, 'cash', 'completed', 4, NOW() - INTERVAL 6532 MINUTE, NOW() - INTERVAL 6522 MINUTE),
(19, 1, 'Sunway Pyramid', 'Damansara Heights', 3.0731, 101.607, 3.152, 101.6599, 13.71, 20.45, 'wallet', 'completed', 5, NOW() - INTERVAL 7146 MINUTE, NOW() - INTERVAL 7107 MINUTE),
(8, 7, 'Kota Kinabalu International Airport', 'Universiti Malaysia Sabah (UMS)', 5.9372, 116.0515, 6.0339, 116.1219, 17.26, 43.25, 'cash', 'completed', 3, NOW() - INTERVAL 4871 MINUTE, NOW() - INTERVAL 4833 MINUTE),
(6, 3, 'Setia Alam', 'Mid Valley Megamall', 3.1046, 101.4553, 3.1176, 101.6774, 32.1, 74.41, 'wallet', 'completed', NULL, NOW() - INTERVAL 5052 MINUTE, NOW() - INTERVAL 4967 MINUTE),
(9, 3, 'Mid Valley Megamall', 'Kepong Metropolitan Park', 3.1176, 101.6774, 3.2139, 101.6347, 15.2, 38.92, 'card', 'completed', 5, NOW() - INTERVAL 4964 MINUTE, NOW() - INTERVAL 4921 MINUTE),
(18, 2, 'Kepong Metropolitan Park', 'Cheras Leisure Mall', 3.2139, 101.6347, 3.0889, 101.7425, 23.82, 32.58, 'cash', 'completed', 5, NOW() - INTERVAL 4616 MINUTE, NOW() - INTERVAL 4551 MINUTE),
(1, 2, 'Cheras Leisure Mall', 'Pavilion KL', 3.0889, 101.7425, 3.149, 101.7134, 9.64, 15.57, 'card', 'completed', 5, NOW() - INTERVAL 4360 MINUTE, NOW() - INTERVAL 4331 MINUTE),
(2, 7, 'Menggatal Town', 'Inanam Town', 6.0560, 116.1505, 6.0130, 116.1290, 6.94, 21.57, 'wallet', 'completed', 5, NOW() - INTERVAL 5662 MINUTE, NOW() - INTERVAL 5551 MINUTE),
(7, NULL, 'Kota Kinabalu International Airport', 'Likas Bay', 5.9372, 116.0515, 5.9980, 116.0960, 10.87, 17.04, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 4860 MINUTE, NULL),
(20, 1, 'Kepong Metropolitan Park', '1 Utama, Petaling Jaya', 3.2139, 101.6347, 3.1503, 101.6157, 9.58, 15.5, 'card', 'completed', 5, NOW() - INTERVAL 5305 MINUTE, NOW() - INTERVAL 5276 MINUTE),
(19, 6, '1Borneo Hypermall', 'Damai Plaza, Luyang', 6.0377, 116.1179, 5.9650, 116.0880, 11.34, 11.57, 'wallet', 'completed', 4, NOW() - INTERVAL 5079 MINUTE, NOW() - INTERVAL 5046 MINUTE),
(11, NULL, 'Mid Valley Megamall', 'Cheras Leisure Mall', 3.1176, 101.6774, 3.0889, 101.7425, 10.27, 16.32, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 5708 MINUTE, NULL),
(10, 2, '1 Utama, Petaling Jaya', 'Bukit Bintang', 3.1503, 101.6157, 3.1466, 101.7108, 13.73, 20.48, 'wallet', 'completed', NULL, NOW() - INTERVAL 4632 MINUTE, NOW() - INTERVAL 4593 MINUTE),
(4, 1, 'Bangsar South', 'Cheras Leisure Mall', 3.1106, 101.6656, 3.0889, 101.7425, 11.53, 17.84, 'cash', 'completed', NULL, NOW() - INTERVAL 4678 MINUTE, NOW() - INTERVAL 4644 MINUTE),
(3, 6, 'Oceanus Waterfront Mall', 'Kota Kinabalu International Airport', 5.9800, 116.0720, 5.9372, 116.0515, 6.85, 7.98, 'cash', 'completed', 5, NOW() - INTERVAL 4979 MINUTE, NOW() - INTERVAL 4963 MINUTE),
(12, 8, 'Bangsar South', 'Mid Valley Megamall', 3.1106, 101.6656, 3.1176, 101.6774, 1.98, 6.38, 'wallet', 'completed', 5, NOW() - INTERVAL 5262 MINUTE, NOW() - INTERVAL 5252 MINUTE),
(14, 8, 'Bukit Bintang', 'Bangsar South', 3.1466, 101.7108, 3.1106, 101.6656, 8.34, 14.01, 'wallet', 'completed', 5, NOW() - INTERVAL 5192 MINUTE, NOW() - INTERVAL 5166 MINUTE),
(1, 7, 'Gaya Street, Kota Kinabalu', 'Oceanus Waterfront Mall', 5.9839, 116.0767, 5.9800, 116.0720, 1.50, 10.15, 'card', 'completed', 4, NOW() - INTERVAL 3909 MINUTE, NOW() - INTERVAL 3900 MINUTE),
(14, 7, 'Kota Kinabalu International Airport', 'Putatan Town', 5.9372, 116.0515, 5.8890, 116.0560, 6.99, 21.68, 'card', 'completed', 5, NOW() - INTERVAL 3471 MINUTE, NOW() - INTERVAL 3406 MINUTE),
(19, 9, 'Damai Plaza, Luyang', '1Borneo Hypermall', 5.9650, 116.0880, 6.0377, 116.1179, 11.34, 17.61, 'wallet', 'completed', 5, NOW() - INTERVAL 3560 MINUTE, NOW() - INTERVAL 3527 MINUTE),
(19, 5, 'Suria Sabah Shopping Mall', 'Kota Kinabalu International Airport', 5.9858, 116.0797, 5.9372, 116.0515, 8.11, 13.73, 'wallet', 'completed', 5, NOW() - INTERVAL 3269 MINUTE, NOW() - INTERVAL 3257 MINUTE),
(2, 7, 'Universiti Malaysia Sabah (UMS)', 'Sutera Harbour', 6.0339, 116.1219, 5.9674, 116.0581, 13.30, 34.93, 'cash', 'completed', 5, NOW() - INTERVAL 3261 MINUTE, NOW() - INTERVAL 3212 MINUTE),
(13, 9, '1Borneo Hypermall', 'Inanam Town', 6.0377, 116.1179, 6.0130, 116.1290, 3.91, 8.69, 'card', 'completed', 5, NOW() - INTERVAL 4313 MINUTE, NOW() - INTERVAL 4242 MINUTE),
(20, 3, 'KLCC, Kuala Lumpur', 'KL Sentral', 3.1579, 101.7116, 3.134, 101.6865, 5, 17.5, 'wallet', 'completed', 3, NOW() - INTERVAL 3731 MINUTE, NOW() - INTERVAL 3713 MINUTE),
(15, 9, 'Gaya Street, Kota Kinabalu', 'Putatan Town', 5.9839, 116.0767, 5.8890, 116.0560, 14.02, 20.82, 'wallet', 'completed', 5, NOW() - INTERVAL 3013 MINUTE, NOW() - INTERVAL 2944 MINUTE),
(13, 5, 'Suria Sabah Shopping Mall', 'Universiti Malaysia Sabah (UMS)', 5.9858, 116.0797, 6.0339, 116.1219, 9.23, 15.08, 'wallet', 'completed', 5, NOW() - INTERVAL 4037 MINUTE, NOW() - INTERVAL 3992 MINUTE),
(11, 4, 'Suria Sabah Shopping Mall', 'Kota Kinabalu International Airport', 5.9858, 116.0797, 5.9372, 116.0515, 8.11, 13.73, 'cash', 'completed', NULL, NOW() - INTERVAL 3111 MINUTE, NOW() - INTERVAL 3099 MINUTE),
(10, 5, 'Lintas Square, Luyang', 'Likas Bay', 5.9590, 116.0940, 5.9980, 116.0960, 5.64, 10.77, 'wallet', 'completed', 5, NOW() - INTERVAL 3688 MINUTE, NOW() - INTERVAL 3646 MINUTE),
(2, 9, 'Putatan Town', 'Tanjung Aru Beach', 5.8890, 116.0560, 5.9497, 116.0430, 8.96, 14.75, 'card', 'completed', 5, NOW() - INTERVAL 3556 MINUTE, NOW() - INTERVAL 3459 MINUTE),
(13, NULL, 'Tanjung Aru Beach', '1Borneo Hypermall', 5.9497, 116.0430, 6.0377, 116.1179, 16.68, 24.02, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 3905 MINUTE, NULL),
(19, 2, 'Setia Alam', 'Wangsa Maju LRT', 3.1046, 101.4553, 3.2056, 101.7319, 42.49, 54.99, 'cash', 'completed', 4, NOW() - INTERVAL 4123 MINUTE, NOW() - INTERVAL 4012 MINUTE),
(2, 4, 'Gaya Street, Kota Kinabalu', '1Borneo Hypermall', 5.9839, 116.0767, 6.0377, 116.1179, 9.78, 15.74, 'card', 'completed', 5, NOW() - INTERVAL 2978 MINUTE, NOW() - INTERVAL 2927 MINUTE),
(16, NULL, 'Oceanus Waterfront Mall', 'Suria Sabah Shopping Mall', 5.9800, 116.0720, 5.9858, 116.0797, 1.50, 5.80, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 2889 MINUTE, NULL),
(5, 4, '1Borneo Hypermall', 'Inanam Town', 6.0377, 116.1179, 6.0130, 116.1290, 3.91, 8.69, 'wallet', 'completed', 5, NOW() - INTERVAL 3871 MINUTE, NOW() - INTERVAL 3800 MINUTE),
(14, 9, 'Likas Bay', 'Damai Plaza, Luyang', 5.9980, 116.0960, 5.9650, 116.0880, 4.90, 9.88, 'cash', 'completed', 3, NOW() - INTERVAL 2400 MINUTE, NOW() - INTERVAL 2379 MINUTE),
(19, 2, 'Bangsar South', 'Sunway Pyramid', 3.1106, 101.6656, 3.0731, 101.607, 10.04, 16.05, 'wallet', 'completed', 5, NOW() - INTERVAL 2866 MINUTE, NOW() - INTERVAL 2836 MINUTE),
(6, 8, 'Wangsa Maju LRT', 'KLCC, Kuala Lumpur', 3.2056, 101.7319, 3.1579, 101.7116, 7.48, 12.98, 'wallet', 'completed', 5, NOW() - INTERVAL 2030 MINUTE, NOW() - INTERVAL 2006 MINUTE),
(19, 3, 'KLCC, Kuala Lumpur', 'Wangsa Maju LRT', 3.1579, 101.7116, 3.2056, 101.7319, 7.48, 22.71, 'wallet', 'completed', 5, NOW() - INTERVAL 2488 MINUTE, NOW() - INTERVAL 2464 MINUTE),
(18, 8, 'Cheras Leisure Mall', 'Bukit Bintang', 3.0889, 101.7425, 3.1466, 101.7108, 9.5, 15.4, 'cash', 'completed', 5, NOW() - INTERVAL 1537 MINUTE, NOW() - INTERVAL 1508 MINUTE),
(16, 9, 'Kota Kinabalu International Airport', 'Inanam Town', 5.9372, 116.0515, 6.0130, 116.1290, 15.64, 22.77, 'wallet', 'completed', NULL, NOW() - INTERVAL 1902 MINUTE, NOW() - INTERVAL 1866 MINUTE),
(12, 4, 'Gaya Street, Kota Kinabalu', 'Inanam Town', 5.9839, 116.0767, 6.0130, 116.1290, 8.64, 14.37, 'wallet', 'completed', 5, NOW() - INTERVAL 1543 MINUTE, NOW() - INTERVAL 1515 MINUTE),
(2, 6, '1Borneo Hypermall', 'Kota Kinabalu International Airport', 6.0377, 116.1179, 5.9372, 116.0515, 17.38, 16.40, 'wallet', 'completed', NULL, NOW() - INTERVAL 2706 MINUTE, NOW() - INTERVAL 2665 MINUTE),
(12, 4, 'Lintas Square, Luyang', 'Putatan Town', 5.9590, 116.0940, 5.8890, 116.0560, 11.49, 17.79, 'card', 'completed', 5, NOW() - INTERVAL 2259 MINUTE, NOW() - INTERVAL 2195 MINUTE),
(2, NULL, 'Putatan Town', 'Lintas Square, Luyang', 5.8890, 116.0560, 5.9590, 116.0940, 11.49, 17.79, 'cash', 'cancelled', NULL, NOW() - INTERVAL 2201 MINUTE, NULL),
(16, 4, 'Oceanus Waterfront Mall', 'Imago Shopping Mall, Kota Kinabalu', 5.9800, 116.0720, 5.9700, 116.0667, 1.63, 5.96, 'wallet', 'completed', 5, NOW() - INTERVAL 2321 MINUTE, NOW() - INTERVAL 2312 MINUTE),
(17, NULL, 'KL Sentral', 'Bukit Bintang', 3.134, 101.6865, 3.1466, 101.7108, 3.95, 8.74, 'wallet', 'cancelled', NULL, NOW() - INTERVAL 1679 MINUTE, NULL),
(13, 6, 'Tanjung Aru Beach', 'Likas Bay', 5.9497, 116.0430, 5.9980, 116.0960, 10.35, 10.78, 'card', 'completed', NULL, NOW() - INTERVAL 2576 MINUTE, NOW() - INTERVAL 2547 MINUTE),
(9, 5, 'Sutera Harbour', 'Lintas Square, Luyang', 5.9674, 116.0581, 5.9590, 116.0940, 5.32, 10.38, 'card', 'accepted', NULL, NOW() - INTERVAL 3 MINUTE, NULL),
(5, 4, 'Kota Kinabalu International Airport', 'Sutera Harbour', 5.9372, 116.0515, 5.9674, 116.0581, 4.46, 9.35, 'cash', 'ongoing', NULL, NOW() - INTERVAL 18 MINUTE, NULL),
(3, NULL, 'TAR UMT, Setapak', 'Damansara Heights', 3.216, 101.7266, 3.152, 101.6599, 13.34, 20.01, 'cash', 'requested', NULL, NOW() - INTERVAL 5 MINUTE, NULL),
(1, 8, 'Pavilion KL', 'Bukit Bintang', 3.149, 101.7134, 3.1466, 101.7108, 1.5, 5.8, 'wallet', 'accepted', NULL, NOW() - INTERVAL 19 MINUTE, NULL),
(11, NULL, 'Subang Jaya SS15', 'Bangsar South', 3.077, 101.5887, 3.1106, 101.6656, 12.11, 18.53, 'card', 'cancelled', NULL, NOW() - INTERVAL 550 MINUTE, NULL),
(8, 9, 'Menggatal Town', 'Lintas Square, Luyang', 6.0560, 116.1505, 5.9590, 116.0940, 16.20, 23.44, 'wallet', 'completed', 5, NOW() - INTERVAL 203 MINUTE, NOW() - INTERVAL 149 MINUTE),
(13, 1, 'Bangsar South', 'Bukit Bintang', 3.1106, 101.6656, 3.1466, 101.7108, 8.34, 14.01, 'cash', 'completed', 5, NOW() - INTERVAL 376 MINUTE, NOW() - INTERVAL 350 MINUTE),
(11, 7, 'Suria Sabah Shopping Mall', 'Gaya Street, Kota Kinabalu', 5.9858, 116.0797, 5.9839, 116.0767, 1.50, 10.15, 'cash', 'completed', NULL, NOW() - INTERVAL 73 MINUTE, NOW() - INTERVAL 52 MINUTE),
(13, 8, 'Bangsar South', 'Kepong Metropolitan Park', 3.1106, 101.6656, 3.2139, 101.6347, 15.56, 22.67, 'wallet', 'completed', 4, NOW() - INTERVAL 338 MINUTE, NOW() - INTERVAL 294 MINUTE),
(4, 9, 'Damai Plaza, Luyang', '1Borneo Hypermall', 5.9650, 116.0880, 6.0377, 116.1179, 11.34, 17.61, 'wallet', 'completed', 5, NOW() - INTERVAL 44 MINUTE, NOW() - INTERVAL 11 MINUTE),
(4, 1, 'Wangsa Maju LRT', 'IOI City Mall, Putrajaya', 3.2056, 101.7319, 2.9701, 101.7135, 34.09, 44.91, 'wallet', 'completed', NULL, NOW() - INTERVAL 87 MINUTE, NOW() - INTERVAL 0 MINUTE),
(2, 4, 'Likas Bay', 'Suria Sabah Shopping Mall', 5.9980, 116.0960, 5.9858, 116.0797, 2.94, 7.53, 'wallet', 'completed', 5, NOW() - INTERVAL 179 MINUTE, NOW() - INTERVAL 160 MINUTE),
(5, 6, 'Kota Kinabalu International Airport', 'Imago Shopping Mall, Kota Kinabalu', 5.9372, 116.0515, 5.9700, 116.0667, 5.22, 6.68, 'wallet', 'completed', 5, NOW() - INTERVAL 541 MINUTE, NOW() - INTERVAL 523 MINUTE),
(1, 4, 'Putatan Town', 'Suria Sabah Shopping Mall', 5.8890, 116.0560, 5.9858, 116.0797, 14.38, 21.26, 'wallet', 'completed', 5, NOW() - INTERVAL 209 MINUTE, NOW() - INTERVAL 149 MINUTE),
(11, 1, 'TAR UMT, Setapak', 'KLCC, Kuala Lumpur', 3.216, 101.7266, 3.1579, 101.7116, 8.66, 14.39, 'wallet', 'completed', 5, NOW() - INTERVAL 332 MINUTE, NOW() - INTERVAL 305 MINUTE),
(5, 7, 'Sutera Harbour', 'Oceanus Waterfront Mall', 5.9674, 116.0581, 5.9800, 116.0720, 2.71, 12.69, 'wallet', 'completed', NULL, NOW() - INTERVAL 228 MINUTE, NOW() - INTERVAL 201 MINUTE);

-- -------------------------------------------------------------
-- Driver profile details (driver portal)
-- -------------------------------------------------------------
UPDATE drivers SET ic_no='900514-10-1283', address='No. 44, Shah Alam, 40000 Selangor', emergency_name='Ravi (parent)', emergency_phone='018-9316631', license_expiry=CURDATE() + INTERVAL 803 DAY, vehicle_color='Silver', vehicle_year=2021 WHERE email='ahmad.faizal@crabdriver.com';
UPDATE drivers SET ic_no='831003-14-1666', address='No. 9, Shah Alam, 40000 Selangor', emergency_name='Siti (sibling)', emergency_phone='016-8943751', license_expiry=CURDATE() + INTERVAL 732 DAY, vehicle_color='White', vehicle_year=2019 WHERE email='lim.chee.keong@crabdriver.com';
UPDATE drivers SET ic_no='830908-08-4930', address='No. 71, Setapak, 53300 Kuala Lumpur', emergency_name='Siti (sibling)', emergency_phone='012-6293176', license_expiry=CURDATE() + INTERVAL 322 DAY, vehicle_color='Black', vehicle_year=2023 WHERE email='rajesh.kumar@crabdriver.com';
UPDATE drivers SET ic_no='911214-12-1979', address='No. 38, Jalan Lintas, 88300 Kota Kinabalu', emergency_name='Mei (sibling)', emergency_phone='018-4303398', license_expiry=CURDATE() + INTERVAL 1115 DAY, vehicle_color='Red', vehicle_year=2020 WHERE email='nor.azlina@crabdriver.com';
UPDATE drivers SET ic_no='871102-12-2907', address='No. 20, Jalan Lintas, 88300 Kota Kinabalu', emergency_name='Aminah (parent)', emergency_phone='012-2983232', license_expiry=CURDATE() + INTERVAL 20 DAY, vehicle_color='Grey', vehicle_year=2022 WHERE email='tan.boon.huat@crabdriver.com';
UPDATE drivers SET ic_no='821108-49-7332', address='No. 37, Sepanggar, 88400 Kota Kinabalu', emergency_name='Lee (parent)', emergency_phone='016-8830371', license_expiry=CURDATE() + INTERVAL 967 DAY, vehicle_color='Blue', vehicle_year=2021 WHERE email='zulkifli.hassan@crabdriver.com';
UPDATE drivers SET ic_no='920624-48-4892', address='No. 80, Jalan Lintas, 88300 Kota Kinabalu', emergency_name='Ravi (sibling)', emergency_phone='015-5030531', license_expiry=CURDATE() + INTERVAL 314 DAY, vehicle_color='Black', vehicle_year=2024 WHERE email='wong.kah.wai@crabdriver.com';
UPDATE drivers SET ic_no='800402-07-3147', address='No. 69, Setapak, 53300 Kuala Lumpur', emergency_name='Aminah (parent)', emergency_phone='011-6058277', license_expiry=CURDATE() + INTERVAL 366 DAY, vehicle_color='White', vehicle_year=2018 WHERE email='saravanan.muthu@crabdriver.com';
UPDATE drivers SET ic_no='931006-49-4527', address='No. 3, Inanam, 88450 Kota Kinabalu', emergency_name='Mei (sibling)', emergency_phone='017-5865027', license_expiry=CURDATE() + INTERVAL 184 DAY, vehicle_color='Silver', vehicle_year=2017 WHERE email='hidayah.yusof@crabdriver.com';
UPDATE drivers SET ic_no='841116-14-3180', address='No. 38, Setapak, 53300 Kuala Lumpur', emergency_name='Kumar (sibling)', emergency_phone='016-2986414', license_expiry=CURDATE() + INTERVAL 343 DAY, vehicle_color='White', vehicle_year=2022 WHERE email='ong.jun.hao@crabdriver.com';
UPDATE drivers SET ic_no='930517-47-4916', address='No. 64, Inanam, 88450 Kota Kinabalu', emergency_name='Mei (spouse)', emergency_phone='013-2834440', license_expiry=CURDATE() + INTERVAL 702 DAY, vehicle_color='Black', vehicle_year=2023 WHERE email='mohd.ridzuan@crabdriver.com';
UPDATE drivers SET ic_no='890719-10-3511', address='No. 24, Cheras, 56000 Kuala Lumpur', emergency_name='Kumar (sibling)', emergency_phone='013-6115916', license_expiry=CURDATE() + INTERVAL 919 DAY, vehicle_color='Silver', vehicle_year=2021 WHERE email='cheryl.ng@crabdriver.com';
UPDATE drivers SET ic_no='770418-10-3798', address='No. 47, Cheras, 56000 Kuala Lumpur', emergency_name='Aminah (sibling)', emergency_phone='016-9478151', license_expiry=CURDATE() + INTERVAL 664 DAY, vehicle_color='Grey', vehicle_year=2019 WHERE email='ganesh.pillay@crabdriver.com';
UPDATE drivers SET status_note='Vehicle is older than the allowed age for Crab Car.' WHERE email='ganesh.pillay@crabdriver.com';
UPDATE drivers SET status_note='Repeated passenger complaints — under investigation.' WHERE email='hidayah.yusof@crabdriver.com';

-- A pending profile change request for the admin to review
INSERT INTO driver_change_requests (driver_id, changes, created_at)
SELECT id, '{"vehicle_model":"Proton X70","plate_no":"SAB 3388","vehicle_color":"Black","vehicle_year":2024}', NOW() - INTERVAL 3 HOUR
FROM drivers WHERE email='tan.boon.huat@crabdriver.com';

-- -------------------------------------------------------------
-- Passenger profile details (passenger portal)
-- -------------------------------------------------------------
UPDATE passengers SET date_of_birth='1981-04-01', gender='female', preferred_payment='wallet', emergency_name=NULL, emergency_phone=NULL WHERE email='aisyah.rahman@mail.com';
UPDATE passengers SET date_of_birth='2003-02-16', gender='male', preferred_payment='wallet', emergency_name='Ahmad (sibling)', emergency_phone='012-6245104' WHERE email='wei.jie.tan@mail.com';
UPDATE passengers SET date_of_birth='1978-09-14', gender='female', preferred_payment='cash', emergency_name='Lina (parent)', emergency_phone='015-9017868' WHERE email='priya.nair@mail.com';
UPDATE passengers SET date_of_birth='1987-02-12', gender='male', preferred_payment='cash', emergency_name=NULL, emergency_phone=NULL WHERE email='muhammad.haziq@mail.com';
UPDATE passengers SET date_of_birth='2000-05-21', gender='female', preferred_payment='card', emergency_name='Grace (spouse)', emergency_phone='014-3927794' WHERE email='chloe.lim@mail.com';
UPDATE passengers SET date_of_birth='1977-08-25', gender='male', preferred_payment='wallet', emergency_name='Hassan (sibling)', emergency_phone='019-4213611' WHERE email='arjun.pillai@mail.com';
UPDATE passengers SET date_of_birth='1982-02-08', gender='female', preferred_payment='cash', emergency_name=NULL, emergency_phone=NULL WHERE email='nurul.izzah@mail.com';
UPDATE passengers SET date_of_birth='1990-04-09', gender='male', preferred_payment='cash', emergency_name='Kumar (sibling)', emergency_phone='017-7042079' WHERE email='jason.wong@mail.com';
UPDATE passengers SET date_of_birth='2002-06-04', gender='female', preferred_payment='wallet', emergency_name='Lina (spouse)', emergency_phone='015-9363760' WHERE email='siti.aminah@mail.com';
UPDATE passengers SET date_of_birth='1995-06-13', gender='male', preferred_payment='cash', emergency_name=NULL, emergency_phone=NULL WHERE email='daniel.ong@mail.com';
UPDATE passengers SET date_of_birth='1991-11-24', gender='female', preferred_payment='card', emergency_name='Kumar (spouse)', emergency_phone='011-4350779' WHERE email='mei.ling.chew@mail.com';
UPDATE passengers SET date_of_birth='1986-06-21', gender='male', preferred_payment='wallet', emergency_name='Hassan (spouse)', emergency_phone='016-6530604' WHERE email='hafiz.ismail@mail.com';
UPDATE passengers SET date_of_birth='1977-05-05', gender='female', preferred_payment='card', emergency_name=NULL, emergency_phone=NULL WHERE email='kavitha.rao@mail.com';
UPDATE passengers SET date_of_birth='2000-12-27', gender='male', preferred_payment='cash', emergency_name='Lina (sibling)', emergency_phone='016-1100274' WHERE email='ryan.teo@mail.com';
UPDATE passengers SET date_of_birth='2004-08-06', gender='female', preferred_payment='cash', emergency_name='Lina (sibling)', emergency_phone='014-6477623' WHERE email='farah.aziz@mail.com';
UPDATE passengers SET date_of_birth='1999-07-17', gender='male', preferred_payment='cash', emergency_name=NULL, emergency_phone=NULL WHERE email='marcus.lee@mail.com';
UPDATE passengers SET date_of_birth='1998-02-10', gender='female', preferred_payment='cash', emergency_name='Kumar (parent)', emergency_phone='012-6081364' WHERE email='liyana.hamid@mail.com';
UPDATE passengers SET date_of_birth='1993-02-03', gender='male', preferred_payment='cash', emergency_name='Kumar (parent)', emergency_phone='018-8128291' WHERE email='kelvin.goh@mail.com';
UPDATE passengers SET date_of_birth='2002-08-12', gender='female', preferred_payment='card', emergency_name=NULL, emergency_phone=NULL WHERE email='deepa.menon@mail.com';
UPDATE passengers SET date_of_birth='1978-03-10', gender='male', preferred_payment='wallet', emergency_name='Lina (spouse)', emergency_phone='012-5669605' WHERE email='amir.firdaus@mail.com';
UPDATE passengers SET status_note='Multiple unpaid cash fares reported by drivers.' WHERE status='suspended';

INSERT INTO passenger_saved_places (passenger_id, label, address, note)
SELECT id, 'Home', 'No. 114, Menggatal, 88450 Kota Kinabalu', NULL FROM passengers WHERE email='aisyah.rahman@mail.com'
UNION ALL SELECT id, 'Work', 'Imago Shopping Mall, Jalan Coastal', 'Main lobby drop-off' FROM passengers WHERE email='aisyah.rahman@mail.com'
UNION ALL SELECT id, 'Home', 'No. 50, Bangsar, 59100 Kuala Lumpur', NULL FROM passengers WHERE email='priya.nair@mail.com'
UNION ALL SELECT id, 'Work', 'Imago Shopping Mall, Jalan Coastal', 'Main lobby drop-off' FROM passengers WHERE email='muhammad.haziq@mail.com'
UNION ALL SELECT id, 'Home', 'No. 91, Taman Desa, 58100 Kuala Lumpur', NULL FROM passengers WHERE email='chloe.lim@mail.com'
UNION ALL SELECT id, 'Home', 'No. 102, Tanjung Aru, 88100 Kota Kinabalu', NULL FROM passengers WHERE email='nurul.izzah@mail.com'
UNION ALL SELECT id, 'Work', 'Universiti Malaysia Sabah, Jalan UMS', 'Main lobby drop-off' FROM passengers WHERE email='nurul.izzah@mail.com'
UNION ALL SELECT id, 'Home', 'No. 20, Taman Desa, 58100 Kuala Lumpur', NULL FROM passengers WHERE email='siti.aminah@mail.com'
UNION ALL SELECT id, 'Work', 'Menara KL, Jalan Punchak', 'Main lobby drop-off' FROM passengers WHERE email='daniel.ong@mail.com'
UNION ALL SELECT id, 'Home', 'No. 74, Setapak, 53300 Kuala Lumpur', NULL FROM passengers WHERE email='mei.ling.chew@mail.com'
UNION ALL SELECT id, 'Home', 'No. 98, Menggatal, 88450 Kota Kinabalu', NULL FROM passengers WHERE email='kavitha.rao@mail.com'
UNION ALL SELECT id, 'Work', 'Kota Kinabalu International Airport, Jalan Kepayan', 'Main lobby drop-off' FROM passengers WHERE email='kavitha.rao@mail.com'
UNION ALL SELECT id, 'Home', 'No. 47, Menggatal, 88450 Kota Kinabalu', NULL FROM passengers WHERE email='farah.aziz@mail.com'
UNION ALL SELECT id, 'Work', 'Suria Sabah, Jalan Tun Fuad Stephens', 'Main lobby drop-off' FROM passengers WHERE email='marcus.lee@mail.com'
UNION ALL SELECT id, 'Home', 'No. 43, SS15, 47500 Subang Jaya', NULL FROM passengers WHERE email='liyana.hamid@mail.com'
UNION ALL SELECT id, 'Home', 'No. 79, Puchong Jaya, 47100 Puchong', NULL FROM passengers WHERE email='deepa.menon@mail.com'
UNION ALL SELECT id, 'Work', 'Mid Valley City, Lingkaran Syed Putra', 'Main lobby drop-off' FROM passengers WHERE email='deepa.menon@mail.com';

-- Keep each driver's overall rating consistent with the ratings passengers gave their trips
UPDATE drivers SET rating = (SELECT ROUND(AVG(r.rating), 2) FROM rides r WHERE r.driver_id = drivers.id AND r.rating IS NOT NULL);

-- Demo data loading above should not count as "profile updated"
UPDATE drivers    SET updated_at = NULL;
UPDATE passengers SET updated_at = NULL;
