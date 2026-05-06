CREATE TABLE IF NOT EXISTS dog_access_audit_events (
    id SERIAL PRIMARY KEY,
    dog_id INTEGER NULL REFERENCES dogs(id) ON DELETE CASCADE,
    actor_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    target_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    event_type TEXT NOT NULL,
    event_summary TEXT NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    metadata JSONB NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_dog_access_audit_events_dog_created ON dog_access_audit_events(dog_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_dog_access_audit_events_actor_created ON dog_access_audit_events(actor_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_dog_access_audit_events_target_created ON dog_access_audit_events(target_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_dog_access_audit_events_type_created ON dog_access_audit_events(event_type, created_at DESC);

CREATE OR REPLACE FUNCTION gp_audit_dog_status_change()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF COALESCE(OLD.lifecycle_status, 'active') IS DISTINCT FROM COALESCE(NEW.lifecycle_status, 'active') THEN
            INSERT INTO dog_access_audit_events (dog_id, actor_user_id, event_type, event_summary, old_value, new_value, metadata)
            VALUES (
                NEW.id,
                NEW.owner_user_id,
                'dog_status_changed',
                'Dog lifecycle status changed',
                COALESCE(OLD.lifecycle_status, 'active'),
                COALESCE(NEW.lifecycle_status, 'active'),
                jsonb_build_object('dog_name', NEW.name, 'old_owner_user_id', OLD.owner_user_id, 'new_owner_user_id', NEW.owner_user_id)
            );
        END IF;

        IF OLD.owner_user_id IS DISTINCT FROM NEW.owner_user_id THEN
            INSERT INTO dog_access_audit_events (dog_id, actor_user_id, target_user_id, event_type, event_summary, old_value, new_value, metadata)
            VALUES (
                NEW.id,
                OLD.owner_user_id,
                NEW.owner_user_id,
                'dog_owner_changed',
                'Dog ownership changed',
                OLD.owner_user_id::TEXT,
                NEW.owner_user_id::TEXT,
                jsonb_build_object('dog_name', NEW.name, 'previous_owner_user_id', OLD.owner_user_id, 'new_owner_user_id', NEW.owner_user_id)
            );
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_gp_audit_dog_status_change ON dogs;
CREATE TRIGGER trg_gp_audit_dog_status_change
AFTER UPDATE ON dogs
FOR EACH ROW
EXECUTE FUNCTION gp_audit_dog_status_change();

CREATE OR REPLACE FUNCTION gp_audit_dog_handler_change()
RETURNS TRIGGER AS $$
DECLARE
    dog_name_value TEXT;
BEGIN
    SELECT name INTO dog_name_value FROM dogs WHERE id = COALESCE(NEW.dog_id, OLD.dog_id);

    IF TG_OP = 'INSERT' THEN
        INSERT INTO dog_access_audit_events (dog_id, actor_user_id, target_user_id, event_type, event_summary, old_value, new_value, metadata)
        VALUES (
            NEW.dog_id,
            NEW.invited_by_user_id,
            NEW.user_id,
            'dog_handler_access_added',
            'Shared handler access added',
            NULL,
            COALESCE(NEW.permission_level, 'view'),
            jsonb_build_object('dog_name', dog_name_value, 'role', NEW.role, 'status', NEW.status, 'access_ends_at', NEW.access_ends_at)
        );
        RETURN NEW;
    END IF;

    IF TG_OP = 'UPDATE' THEN
        IF COALESCE(OLD.status, '') IS DISTINCT FROM COALESCE(NEW.status, '')
            OR COALESCE(OLD.permission_level, '') IS DISTINCT FROM COALESCE(NEW.permission_level, '')
            OR COALESCE(OLD.access_ends_at::TEXT, '') IS DISTINCT FROM COALESCE(NEW.access_ends_at::TEXT, '') THEN
            INSERT INTO dog_access_audit_events (dog_id, actor_user_id, target_user_id, event_type, event_summary, old_value, new_value, metadata)
            VALUES (
                NEW.dog_id,
                NEW.invited_by_user_id,
                NEW.user_id,
                'dog_handler_access_changed',
                'Shared handler access changed',
                jsonb_build_object('status', OLD.status, 'permission_level', OLD.permission_level, 'access_ends_at', OLD.access_ends_at)::TEXT,
                jsonb_build_object('status', NEW.status, 'permission_level', NEW.permission_level, 'access_ends_at', NEW.access_ends_at)::TEXT,
                jsonb_build_object('dog_name', dog_name_value, 'role', NEW.role, 'revoked_at', NEW.revoked_at)
            );
        END IF;
        RETURN NEW;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_gp_audit_dog_handler_insert ON dog_handlers;
CREATE TRIGGER trg_gp_audit_dog_handler_insert
AFTER INSERT ON dog_handlers
FOR EACH ROW
EXECUTE FUNCTION gp_audit_dog_handler_change();

DROP TRIGGER IF EXISTS trg_gp_audit_dog_handler_update ON dog_handlers;
CREATE TRIGGER trg_gp_audit_dog_handler_update
AFTER UPDATE ON dog_handlers
FOR EACH ROW
EXECUTE FUNCTION gp_audit_dog_handler_change();

CREATE OR REPLACE FUNCTION gp_audit_dog_transfer_change()
RETURNS TRIGGER AS $$
DECLARE
    dog_name_value TEXT;
BEGIN
    SELECT name INTO dog_name_value FROM dogs WHERE id = COALESCE(NEW.dog_id, OLD.dog_id);

    IF TG_OP = 'INSERT' THEN
        INSERT INTO dog_access_audit_events (dog_id, actor_user_id, target_user_id, event_type, event_summary, old_value, new_value, metadata)
        VALUES (
            NEW.dog_id,
            NEW.from_user_id,
            NEW.to_user_id,
            'dog_transfer_requested',
            'Dog ownership transfer requested',
            NULL,
            NEW.status,
            jsonb_build_object('dog_name', dog_name_value, 'request_id', NEW.id, 'keep_previous_owner_access', NEW.keep_previous_owner_access, 'note', NEW.note)
        );
        RETURN NEW;
    END IF;

    IF TG_OP = 'UPDATE' AND COALESCE(OLD.status, '') IS DISTINCT FROM COALESCE(NEW.status, '') THEN
        INSERT INTO dog_access_audit_events (dog_id, actor_user_id, target_user_id, event_type, event_summary, old_value, new_value, metadata)
        VALUES (
            NEW.dog_id,
            NEW.to_user_id,
            NEW.from_user_id,
            'dog_transfer_status_changed',
            'Dog ownership transfer status changed',
            OLD.status,
            NEW.status,
            jsonb_build_object('dog_name', dog_name_value, 'request_id', NEW.id, 'keep_previous_owner_access', NEW.keep_previous_owner_access, 'responded_at', NEW.responded_at)
        );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_gp_audit_dog_transfer_insert ON dog_transfer_requests;
CREATE TRIGGER trg_gp_audit_dog_transfer_insert
AFTER INSERT ON dog_transfer_requests
FOR EACH ROW
EXECUTE FUNCTION gp_audit_dog_transfer_change();

DROP TRIGGER IF EXISTS trg_gp_audit_dog_transfer_update ON dog_transfer_requests;
CREATE TRIGGER trg_gp_audit_dog_transfer_update
AFTER UPDATE ON dog_transfer_requests
FOR EACH ROW
EXECUTE FUNCTION gp_audit_dog_transfer_change();
