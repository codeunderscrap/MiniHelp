-- Migration: Add MM OS SSO support columns
-- Run this on existing databases that were created before SSO integration

ALTER TABLE users ADD COLUMN IF NOT EXISTS mmos_sub VARCHAR(128) NULL;
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_mmos_sub (mmos_sub);
