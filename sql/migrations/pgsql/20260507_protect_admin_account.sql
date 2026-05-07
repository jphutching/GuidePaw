UPDATE users
SET user_role = 'admin',
    is_admin = 1,
    account_status = COALESCE(NULLIF(account_status, ''), 'active'),
    deactivated_at = NULL,
    deactivated_by_user_id = NULL
WHERE lower(username) = 'admin';

CREATE OR REPLACE FUNCTION guidepaw_protect_builtin_admin()
RETURNS trigger AS $$
BEGIN
    IF lower(OLD.username) = 'admin' THEN
        IF lower(NEW.username) <> 'admin' THEN
            RAISE EXCEPTION 'The built-in admin username cannot be changed.';
        END IF;
        IF COALESCE(NEW.is_admin, 0) <> 1 OR COALESCE(NEW.user_role, '') <> 'admin' THEN
            RAISE EXCEPTION 'The built-in admin account cannot be downgraded.';
        END IF;
        IF COALESCE(NEW.account_status, 'active') <> 'active' OR NEW.deactivated_at IS NOT NULL THEN
            RAISE EXCEPTION 'The built-in admin account cannot be deactivated.';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_protect_builtin_admin ON users;
CREATE TRIGGER trg_protect_builtin_admin
BEFORE UPDATE OR DELETE ON users
FOR EACH ROW
WHEN (lower(OLD.username) = 'admin')
EXECUTE FUNCTION guidepaw_protect_builtin_admin();
