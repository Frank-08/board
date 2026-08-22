-- Migration: Word-style agenda/minutes formatting
-- Adds report-type + starred-item flags to agenda items, one level of nesting
-- and the same flags to agenda templates, an optional meeting end time, and
-- relaxes resolutions.title to optional (Word-style lettered clauses don't
-- carry individual titles).

USE governance_board;

ALTER TABLE meetings
    ADD COLUMN end_time TIME NULL AFTER scheduled_date;

ALTER TABLE agenda_items
    ADD COLUMN report_type ENUM('Written', 'Verbal') DEFAULT NULL AFTER presenter_id,
    ADD COLUMN is_starred BOOLEAN DEFAULT FALSE AFTER item_number;

ALTER TABLE agenda_templates
    ADD COLUMN sub_position INT NOT NULL DEFAULT 0 AFTER position,
    ADD COLUMN parent_id INT NULL AFTER sub_position,
    ADD COLUMN is_starred BOOLEAN DEFAULT FALSE AFTER parent_id,
    ADD CONSTRAINT fk_agenda_templates_parent FOREIGN KEY (parent_id) REFERENCES agenda_templates(id) ON DELETE CASCADE,
    ADD INDEX idx_parent (parent_id);

ALTER TABLE resolutions
    MODIFY COLUMN title VARCHAR(255) NULL;

-- Seed the two standard nested blocks used every Standing Committee meeting,
-- so new meetings created from the template don't need these re-typed by
-- hand. Uses a subquery rather than a hardcoded id in case seed data/import
-- order differs between environments.
SET @sc_meeting_type_id = (SELECT id FROM meeting_types WHERE name = 'Standing Committee' LIMIT 1);

INSERT INTO agenda_templates (meeting_type_id, title, item_type, position, sub_position, parent_id)
SELECT @sc_meeting_type_id, 'OPENING, WELCOME AND APOLOGIES', 'Information', 0, 0, NULL
WHERE @sc_meeting_type_id IS NOT NULL;
SET @opening_template_id = IF(@sc_meeting_type_id IS NOT NULL, LAST_INSERT_ID(), NULL);

INSERT INTO agenda_templates (meeting_type_id, title, item_type, position, sub_position, parent_id)
SELECT @sc_meeting_type_id, 'Welcome', 'Information', 0, 0, @opening_template_id WHERE @opening_template_id IS NOT NULL
UNION ALL
SELECT @sc_meeting_type_id, 'Recognition of Traditional Owners', 'Information', 0, 1, @opening_template_id WHERE @opening_template_id IS NOT NULL
UNION ALL
SELECT @sc_meeting_type_id, 'Apologies', 'Information', 0, 2, @opening_template_id WHERE @opening_template_id IS NOT NULL;

INSERT INTO agenda_templates (meeting_type_id, title, item_type, position, sub_position, parent_id)
SELECT @sc_meeting_type_id, 'Committee Minutes and Reports', 'Information', 100, 0, NULL
WHERE @sc_meeting_type_id IS NOT NULL;
SET @committee_reports_template_id = IF(@sc_meeting_type_id IS NOT NULL, LAST_INSERT_ID(), NULL);

INSERT INTO agenda_templates (meeting_type_id, title, item_type, position, sub_position, parent_id)
SELECT @sc_meeting_type_id, 'Boroondara Community Outreach (BCO)', 'Information', 0, 0, @committee_reports_template_id WHERE @committee_reports_template_id IS NOT NULL
UNION ALL
SELECT @sc_meeting_type_id, 'MFC', 'Information', 0, 1, @committee_reports_template_id WHERE @committee_reports_template_id IS NOT NULL
UNION ALL
SELECT @sc_meeting_type_id, 'R&PC', 'Information', 0, 2, @committee_reports_template_id WHERE @committee_reports_template_id IS NOT NULL
UNION ALL
SELECT @sc_meeting_type_id, 'PRC', 'Information', 0, 3, @committee_reports_template_id WHERE @committee_reports_template_id IS NOT NULL
UNION ALL
SELECT @sc_meeting_type_id, 'LPC', 'Information', 0, 4, @committee_reports_template_id WHERE @committee_reports_template_id IS NOT NULL;
