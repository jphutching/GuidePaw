INSERT INTO feature_flags (flag_key, label, description, is_enabled, sort_order)
VALUES
('health_docs_enabled', 'Health Docs', 'Health documents and dog health section.', 1, 60),
('vet_appointments_enabled', 'Vet Appointments', 'Vet appointment tracking.', 1, 70),
('alerts_enabled', 'Alerts', 'Reminders and health/training alerts.', 1, 80),
('training_program_enabled', 'Training Program', 'Structured training program tools.', 1, 90),
('medications_enabled', 'Medications', 'Medication tracking tools.', 1, 100),
('certification_enabled', 'Certification', 'Certification and assessment tools.', 1, 110),
('backup_tools_enabled', 'Backup Tools', 'Backup/import/API/database utility pages.', 1, 120),
('ada_wallet_enabled', 'ADA Wallet Card', 'ADA/service dog wallet card tools.', 1, 130)
ON CONFLICT (flag_key) DO NOTHING;
