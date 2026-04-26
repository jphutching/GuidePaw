USE psd_app_logs;

CREATE TABLE IF NOT EXISTS dogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    breed VARCHAR(120) DEFAULT NULL,
    chip_number VARCHAR(80) DEFAULT NULL,
    weight_lbs DECIMAL(5,2) DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    birth_is_approximate TINYINT(1) DEFAULT 0,
    approx_age_years DECIMAL(4,1) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dogs_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE daily_logs ADD COLUMN IF NOT EXISTS dog_id INT DEFAULT NULL AFTER user_id;
ALTER TABLE daily_logs ADD CONSTRAINT fk_dog_logs FOREIGN KEY (dog_id) REFERENCES dogs(id) ON DELETE CASCADE;
CREATE INDEX idx_dog_logs ON daily_logs(dog_id);

CREATE TABLE IF NOT EXISTS dog_handlers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dog_id INT NOT NULL,
    user_id INT NOT NULL,
    invited_by_user_id INT NOT NULL,
    role ENUM('owner','collaborator') DEFAULT 'collaborator',
    permission_level ENUM('view','edit') DEFAULT 'edit',
    status ENUM('accepted','revoked') DEFAULT 'accepted',
    accepted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dog_user (dog_id, user_id),
    CONSTRAINT fk_dog_handlers_dog FOREIGN KEY (dog_id) REFERENCES dogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_dog_handlers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_dog_handlers_inviter FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS handler_handshakes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dog_id INT NOT NULL,
    code VARCHAR(12) NOT NULL UNIQUE,
    created_by_user_id INT NOT NULL,
    requested_by_user_id INT DEFAULT NULL,
    requested_permission ENUM('view','edit') DEFAULT 'edit',
    status ENUM('open','requested','approved','declined','expired','revoked') DEFAULT 'open',
    expires_at DATETIME NOT NULL,
    requested_at DATETIME DEFAULT NULL,
    decided_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_handshake_dog FOREIGN KEY (dog_id) REFERENCES dogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_handshake_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_handshake_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dog_vets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dog_id INT NOT NULL,
    clinic_name VARCHAR(120) NOT NULL,
    vet_name VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dog_vets_dog FOREIGN KEY (dog_id) REFERENCES dogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_dog_vets_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dog_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dog_id INT NOT NULL,
    uploaded_by_user_id INT NOT NULL,
    doc_type ENUM('vet_record','service_letter') NOT NULL,
    title VARCHAR(150) NOT NULL,
    provider_name VARCHAR(150) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dog_documents_dog FOREIGN KEY (dog_id) REFERENCES dogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_dog_documents_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dog_vet_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dog_id INT NOT NULL,
    dog_vet_id INT DEFAULT NULL,
    created_by_user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    appointment_at DATETIME NOT NULL,
    reminder_at DATETIME DEFAULT NULL,
    location_text VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('scheduled','completed','cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dog_appointments_dog FOREIGN KEY (dog_id) REFERENCES dogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_dog_appointments_vet FOREIGN KEY (dog_vet_id) REFERENCES dog_vets(id) ON DELETE SET NULL,
    CONSTRAINT fk_dog_appointments_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO dogs (owner_user_id, name, breed, chip_number, weight_lbs)
SELECT u.id, u.dog_name, u.breed, u.chip_number, u.weight_lbs
FROM users u
LEFT JOIN dogs d ON d.owner_user_id = u.id AND d.name = u.dog_name
WHERE d.id IS NULL;

UPDATE daily_logs dl
JOIN dogs d ON d.owner_user_id = dl.user_id
SET dl.dog_id = d.id
WHERE dl.dog_id IS NULL;
