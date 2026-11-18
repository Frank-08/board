-- Let clerks/admins opt in to an email when a member submits an agenda item
-- (toggle on submit_agenda.php's Review Queue).
ALTER TABLE users
    ADD COLUMN notify_agenda_submissions BOOLEAN NOT NULL DEFAULT FALSE
        COMMENT 'Email me when an agenda item is submitted (manage_agenda users)'
        AFTER must_change_password;
