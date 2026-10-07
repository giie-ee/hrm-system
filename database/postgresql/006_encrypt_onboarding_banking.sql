-- Protect onboarding banking fields with an application-encrypted envelope.
-- Existing plaintext is converted by bin/encrypt-onboarding-bank-data.php after
-- this migration and before the web server starts.

ALTER TABLE onboarding_forms
    ADD COLUMN IF NOT EXISTS banking_envelope JSONB;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'onboarding_forms_banking_envelope_object'
          AND conrelid = 'onboarding_forms'::regclass
    ) THEN
        ALTER TABLE onboarding_forms
            ADD CONSTRAINT onboarding_forms_banking_envelope_object
            CHECK (banking_envelope IS NULL OR jsonb_typeof(banking_envelope) = 'object');
    END IF;
END $$;

-- NOT VALID permits the application backfill to encrypt legacy rows first.
-- PostgreSQL still enforces this constraint for all new or updated rows.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'onboarding_forms_no_plaintext_banking'
          AND conrelid = 'onboarding_forms'::regclass
    ) THEN
        ALTER TABLE onboarding_forms
            ADD CONSTRAINT onboarding_forms_no_plaintext_banking
            CHECK (NOT (form_data ?| ARRAY['bank_name', 'bank_account_number']))
            NOT VALID;
    END IF;
END $$;

COMMENT ON COLUMN onboarding_forms.banking_envelope IS
    'AES-256-GCM envelope; key material is supplied only through protected application environment variables.';
