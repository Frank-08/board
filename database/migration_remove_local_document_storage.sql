-- Remove the last of local document storage: documents are SharePoint links.
--
-- file_path / file_size / mime_type described files kept in uploads/ before
-- the SharePoint migration. Nothing reads or writes them any more, and
-- api/download.php / api/view_pdf.php only ever redirect to sharepoint_url,
-- so a row without a SharePoint link is already unreachable from the app.
--
-- STEP 1 - review legacy rows that still have no SharePoint link. Upload
-- each file to SharePoint and set its link (via the Documents page or an
-- UPDATE), or delete the row, before STEP 3.
SELECT id, title, file_name, file_path, meeting_id, agenda_item_id, created_at
FROM documents
WHERE sharepoint_url IS NULL OR sharepoint_url = '';

-- STEP 2 - drop the local-storage columns. file_name stays: it is the
-- display name derived from the SharePoint URL.
-- (Take a note of any file_path values from STEP 1 first - they go here.)
ALTER TABLE documents
    DROP COLUMN file_path,
    DROP COLUMN file_size,
    DROP COLUMN mime_type;

-- STEP 3 - make the link mandatory, matching schema.sql. This fails while
-- any row from STEP 1 is left, which is the point; fix those and rerun it.
ALTER TABLE documents
    MODIFY sharepoint_url VARCHAR(2048) NOT NULL;
