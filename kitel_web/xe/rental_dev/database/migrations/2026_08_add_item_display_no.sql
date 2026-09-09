ALTER TABLE kitel_rental_items
  ADD COLUMN IF NOT EXISTS display_no INT UNSIGNED NULL AFTER serial_no;

UPDATE kitel_rental_items
SET display_no = serial_no
WHERE display_no IS NULL;
