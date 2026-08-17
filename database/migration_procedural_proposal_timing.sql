-- Migration: Timing for procedural proposals within the meeting flow
-- Lets a procedural proposal be recorded at the exact point it happened in
-- the meeting, even when it isn't substantively about the linked agenda
-- item — e.g. raised between two items rather than during either one's
-- discussion. UCA Manual for Meetings §5.15-5.16.

USE governance_board;

ALTER TABLE procedural_proposals
    ADD COLUMN agenda_position ENUM('Before', 'During', 'After') NOT NULL DEFAULT 'During' AFTER agenda_item_id;
