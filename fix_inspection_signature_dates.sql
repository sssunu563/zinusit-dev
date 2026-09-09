-- Fix Inspection signature_date untuk data lama
-- Menambahkan waktu default 12:00:00 ke signature_date yang hanya berisi tanggal

UPDATE inspections
SET signature_date = CONCAT(DATE(signature_date), ' 12:00:00')
WHERE signature_date IS NOT NULL
  AND signature_date = DATE(signature_date)
  AND (it_signature IS NOT NULL 
       OR checked_signature IS NOT NULL 
       OR user_signature IS NOT NULL 
       OR leader_signature IS NOT NULL);

-- Query ini akan:
-- 1. Mencari inspection yang punya signature_date
-- 2. Cek apakah signature_date tidak punya komponen waktu
-- 3. Tambahkan waktu default 12:00:00
-- 4. Hanya untuk inspection yang memang punya signature
