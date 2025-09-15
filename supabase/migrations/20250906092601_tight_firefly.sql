/*
# Add Language Support to Users Table

1. New Columns
   - `language` (varchar) - User's preferred language (fr, en, ar)

2. Updates
   - Add language column to users table with default 'fr'
   - Update existing users to have default language
   - Add index for language queries

3. Data Migration
   - Set default language 'fr' for existing users
   - Ensure language field is properly indexed
*/

-- Add language column to users table
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'users' AND column_name = 'language'
    ) THEN
        ALTER TABLE users ADD COLUMN language VARCHAR(2) DEFAULT 'fr';
    END IF;
END $$;

-- Add constraint to ensure only valid languages
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.check_constraints
        WHERE constraint_name = 'chk_users_language'
    ) THEN
        ALTER TABLE users 
        ADD CONSTRAINT chk_users_language 
        CHECK (language IN ('fr', 'en', 'ar'));
    END IF;
END $$;

-- Update existing users to have default language if NULL
UPDATE users 
SET language = 'fr' 
WHERE language IS NULL;

-- Make language column NOT NULL after setting defaults
ALTER TABLE users ALTER COLUMN language SET NOT NULL;

-- Add index for language queries (optional but recommended)
CREATE INDEX IF NOT EXISTS idx_users_language ON users(language);

-- Update the admin user to have French as default
UPDATE users 
SET language = 'fr' 
WHERE email = 'admin@smsgateway.local' AND language IS NULL;