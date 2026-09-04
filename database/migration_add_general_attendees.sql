-- Migration: General (non-member) attendees for meeting types that opt in.
--
-- Some meeting types (e.g. Presbytery in Council) can draw far more people
-- than the small, curated meeting_type_members roster covers - up to ~90,
-- unknown in advance, who are nonetheless genuine members of that body and
-- may move/second motions. This adds an opt-in flag per meeting type, lets
-- meeting_attendees record a free-text name instead of a board_members
-- link, and lets resolutions/amendments/procedural proposals record a
-- free-text mover/seconder/proposer name as a point-in-time snapshot
-- alongside the existing board_members FK.

USE governance_board;

ALTER TABLE meeting_types
    ADD COLUMN general_attendance_enabled BOOLEAN NOT NULL DEFAULT FALSE AFTER shortcode;

UPDATE meeting_types SET general_attendance_enabled = TRUE WHERE name = 'Presbytery in Council';

ALTER TABLE meeting_attendees
    MODIFY COLUMN member_id INT NULL,
    ADD COLUMN attendee_name VARCHAR(200) NULL AFTER member_id,
    ADD CONSTRAINT chk_meeting_attendee_identity CHECK (
        (member_id IS NOT NULL AND attendee_name IS NULL) OR
        (member_id IS NULL AND attendee_name IS NOT NULL)
    ),
    ADD INDEX idx_meeting_attendee_name (meeting_id, attendee_name);

ALTER TABLE resolutions
    ADD COLUMN motion_moved_by_name VARCHAR(200) NULL AFTER motion_moved_by,
    ADD COLUMN motion_seconded_by_name VARCHAR(200) NULL AFTER motion_seconded_by;

ALTER TABLE resolution_amendments
    ADD COLUMN moved_by_name VARCHAR(200) NULL AFTER moved_by,
    ADD COLUMN seconded_by_name VARCHAR(200) NULL AFTER seconded_by;

ALTER TABLE procedural_proposals
    ADD COLUMN proposed_by_name VARCHAR(200) NULL AFTER proposed_by,
    ADD COLUMN seconded_by_name VARCHAR(200) NULL AFTER seconded_by;
