-- GuidePaw training progression roadmap schema
-- Adds roadmap governance, training progression, candidate screening, goals,
-- regression tracking, coach review, rewards, and badges.

BEGIN;

CREATE TABLE IF NOT EXISTS feature_roadmap (
    id SERIAL PRIMARY KEY,
    flag_key VARCHAR(80) REFERENCES feature_flags(flag_key),
    priority_level VARCHAR(20) NOT NULL CHECK (priority_level IN ('must','should','could')),
    lifecycle_status VARCHAR(40) NOT NULL DEFAULT 'backlog',
    owner_name VARCHAR(120),
    milestone VARCHAR(80),
    success_metric TEXT,
    acceptance_criteria TEXT,
    release_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO feature_flags (flag_key, label, description, is_enabled, sort_order)
VALUES
('candidate_scoring_enabled', 'Candidate Scoring', 'Dog suitability and service-dog candidate assessment workflow.', 1, 210),
('goal_intake_enabled', 'Goal Intake', 'Client-specific training goal intake and measurable success criteria.', 1, 220),
('training_progression_enabled', 'Training Progression', 'Progression-based training modules, steps, and session tracking.', 1, 230),
('regression_engine_enabled', 'Regression Engine', 'Detects regression and recommends reset plans.', 1, 240),
('habit_repair_enabled', 'Habit Repair', 'Quick repair protocols for common behavior problems.', 1, 250),
('otr_micro_training_enabled', 'OTR Micro Training', 'Short truck-driver-friendly training sessions.', 1, 260),
('training_rewards_guide_enabled', 'Training Rewards Guide', 'Reward and gear guidance for handlers.', 1, 270),

('video_reviews_enabled', 'Video Reviews', 'Video self-assessment and checkpoint review support.', 0, 310),
('coach_review_enabled', 'Coach Review', 'Route safety and training concerns to coach review.', 0, 320),
('behavior_risk_scoring_enabled', 'Behavior Risk Scoring', 'Risk scoring for behavior incidents and candidate assessments.', 0, 330),
('trucking_mode_enabled', 'Trucking Mode', 'Driving-day and trucker-specific training mode.', 0, 340),
('goal_builder_enabled', 'Goal Builder', 'Guided conversion of vague goals into measurable goals.', 0, 350),

('ai_training_assistant_enabled', 'AI Training Assistant', 'Bounded troubleshooting assistant for training support.', 0, 410),
('community_challenges_enabled', 'Community Challenges', 'Optional handler challenge and motivation features.', 0, 420),
('wearable_integrations_enabled', 'Wearable Integrations', 'Future integrations with wearable devices.', 0, 430),
('trainer_marketplace_enabled', 'Trainer Marketplace', 'Future trainer discovery and referral feature.', 0, 440),
('candidate_comparison_enabled', 'Candidate Comparison', 'Compare multiple dog candidates side by side.', 0, 450)
ON CONFLICT (flag_key) DO NOTHING;

INSERT INTO feature_roadmap
(flag_key, priority_level, lifecycle_status, owner_name, milestone, success_metric, acceptance_criteria, release_notes)
VALUES
('candidate_scoring_enabled', 'must', 'feature_flag_created', NULL, 'MVP Training Core', '90 percent of new dog profiles complete candidate screening', 'User can complete candidate assessment and receive focus-level recommendation', 'Adds candidate screening foundation.'),
('goal_intake_enabled', 'must', 'feature_flag_created', NULL, 'MVP Training Core', '90 percent of training users create at least one measurable goal', 'User can convert a problem into observable desired behavior and success criteria', 'Adds goal intake foundation.'),
('training_progression_enabled', 'must', 'feature_flag_created', NULL, 'MVP Training Core', '70 percent of active users complete at least one module step', 'App can unlock, repeat, or hold modules based on logged success', 'Adds training progression foundation.'),
('regression_engine_enabled', 'must', 'feature_flag_created', NULL, 'MVP Training Core', 'Regression events generate a reset plan', 'App detects lower success or behavior relapse and recommends easier steps', 'Adds regression detection foundation.'),
('habit_repair_enabled', 'must', 'feature_flag_created', NULL, 'MVP Training Core', 'Users can start a repair protocol in under 30 seconds', 'Potty, pulling, barking, jumping, and cab-settle protocols exist', 'Adds quick repair protocol foundation.'),
('video_reviews_enabled', 'should', 'feature_flag_created', NULL, 'Beta 2', '60 percent of active beta users submit at least one checkpoint video', 'User can upload or attach short clips to training checkpoints', 'Future video review feature.'),
('coach_review_enabled', 'should', 'feature_flag_created', NULL, 'Beta 2', 'High-risk cases are routed to review queue', 'Safety flags generate coach review records', 'Future coach review feature.'),
('trucking_mode_enabled', 'should', 'feature_flag_created', NULL, 'Beta 2', 'Trucker users show higher 7-day adherence', 'User can select driving day, reset day, weather day, low-energy day, or high-stress day', 'Future trucking mode feature.'),
('ai_training_assistant_enabled', 'could', 'feature_flag_created', NULL, 'v1.2 Experiment', 'Reduces repeated support questions without unsafe advice', 'Assistant gives bounded, non-certification, safety-aware troubleshooting', 'Future AI troubleshooting experiment.')
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS dog_candidate_assessments (
    id SERIAL PRIMARY KEY,
    dog_id INTEGER NOT NULL,
    focus_level_recommended INTEGER,
    health_notes TEXT,
    confidence_score INTEGER,
    startle_recovery_score INTEGER,
    handler_engagement_score INTEGER,
    food_motivation_score INTEGER,
    toy_motivation_score INTEGER,
    settle_score INTEGER,
    human_neutrality_score INTEGER,
    dog_neutrality_score INTEGER,
    environment_score INTEGER,
    handling_score INTEGER,
    safety_flags TEXT,
    recommendation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_goals (
    id SERIAL PRIMARY KEY,
    dog_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    goal_category VARCHAR(80),
    current_problem TEXT,
    desired_behavior TEXT,
    context_environment TEXT,
    trigger_description TEXT,
    handler_time_budget_minutes INTEGER,
    reinforcer_preference TEXT,
    safety_risk SMALLINT DEFAULT 0,
    success_criteria TEXT,
    maintenance_plan TEXT,
    status VARCHAR(40) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_modules (
    id SERIAL PRIMARY KEY,
    module_key VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT,
    level_number INTEGER NOT NULL,
    focus_level_min INTEGER DEFAULT 0,
    category VARCHAR(80),
    is_active SMALLINT DEFAULT 1,
    sort_order INTEGER DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_module_steps (
    id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES training_modules(id),
    step_number INTEGER NOT NULL,
    title VARCHAR(160) NOT NULL,
    instructions TEXT,
    success_criteria TEXT,
    regression_instruction TEXT,
    estimated_minutes INTEGER DEFAULT 3,
    required_success_count INTEGER DEFAULT 4,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    dog_id INTEGER NOT NULL,
    goal_id INTEGER REFERENCES training_goals(id),
    module_id INTEGER REFERENCES training_modules(id),
    step_id INTEGER REFERENCES training_module_steps(id),
    context_environment TEXT,
    reps_attempted INTEGER DEFAULT 0,
    reps_successful INTEGER DEFAULT 0,
    stress_level INTEGER,
    handler_confidence INTEGER,
    notes TEXT,
    progression_status VARCHAR(40) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS behavior_incidents (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    dog_id INTEGER NOT NULL,
    incident_type VARCHAR(80),
    context_environment TEXT,
    trigger_description TEXT,
    severity INTEGER,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS regression_events (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    dog_id INTEGER NOT NULL,
    goal_id INTEGER REFERENCES training_goals(id),
    module_id INTEGER REFERENCES training_modules(id),
    detected_reason TEXT,
    recommended_action TEXT,
    status VARCHAR(40) DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coach_reviews (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    dog_id INTEGER NOT NULL,
    related_goal_id INTEGER REFERENCES training_goals(id),
    related_session_id INTEGER REFERENCES training_sessions(id),
    review_type VARCHAR(80),
    priority VARCHAR(40) DEFAULT 'normal',
    reason TEXT,
    status VARCHAR(40) DEFAULT 'open',
    coach_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_rewards (
    id SERIAL PRIMARY KEY,
    dog_id INTEGER NOT NULL,
    reward_type VARCHAR(80),
    reward_name VARCHAR(160),
    value_level VARCHAR(40),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_badges (
    id SERIAL PRIMARY KEY,
    badge_key VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT,
    criteria TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO training_modules
(module_key, title, description, level_number, focus_level_min, category, sort_order)
VALUES
('name_response', 'Name Response', 'Dog turns toward handler when name is said.', 1, 0, 'foundation', 10),
('marker_word', 'Marker Word', 'Dog understands yes or click predicts reward.', 1, 0, 'foundation', 20),
('potty_routine', 'Potty Routine', 'Dog eliminates outside or on cue and reduces accidents.', 1, 0, 'routine', 30),
('cab_calm', 'Cab Calm', 'Dog settles quietly in the truck cab.', 1, 1, 'otr_life', 40),
('loose_leash_check_in', 'Loose Leash Check-In', 'Dog checks in and keeps leash loose at truck stops.', 2, 1, 'manners', 50),
('settle_mat', 'Settle Mat', 'Dog relaxes on mat or bed.', 2, 0, 'manners', 60),
('barking_prevention', 'Barking Prevention', 'Dog notices trigger and redirects to handler.', 2, 0, 'habit_repair', 70),
('jumping_prevention', 'Jumping Prevention', 'Dog keeps four paws on floor during greetings.', 2, 0, 'habit_repair', 80),
('truck_stop_focus', 'Truck Stop Focus', 'Dog can focus around fuel, people, smells, and vehicles.', 3, 1, 'otr_life', 90),
('public_neutrality_foundation', 'Public Neutrality Foundation', 'Dog can ignore people, dogs, and food at controlled distance.', 4, 2, 'public_manners', 100)
ON CONFLICT (module_key) DO NOTHING;

COMMIT;
