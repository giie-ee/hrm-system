-- Accounts created by HR at hire time get a temporary password and must change
-- it on first sign-in. Existing accounts are unaffected (default FALSE).
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
