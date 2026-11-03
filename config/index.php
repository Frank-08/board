<?php
// Directory browsing is not permitted. The repo .htaccess also sets
// "Options -Indexes", but deploy.sh does not sync .htaccess, so this stub
// guarantees a 403 instead of an Apache auto-index for this directory.
http_response_code(403);
header("Content-Type: text/plain");
echo "Forbidden";
