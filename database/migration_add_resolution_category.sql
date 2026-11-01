-- Migration: Add a category to resolutions so they can be listed and
-- exported as a categorised resolutions register (resolutions.php,
-- export/resolutions.php). Free text (e.g. "Finance", "Property",
-- "Ministry Placements"); the UI suggests categories already in use.
-- NULL = uncategorised.

USE governance_board;

ALTER TABLE resolutions
    ADD COLUMN category VARCHAR(100) NULL AFTER title,
    ADD INDEX idx_category (category);
