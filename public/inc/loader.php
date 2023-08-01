<?php
# Mattermost User Merge Tool

# Copyright (c) 2023 Maxwell Power
#
# Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without
# restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom
# the Software is furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
# AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
# ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

// Database configuration
$PG_host = getenv('PG_HOST');
$PG_port = getenv('PG_PORT') ?: 5432;
$PG_dbname = getenv('PG_DATABASE');
$PG_user = getenv('PG_USER');
$PG_password = getenv('PG_PASSWORD');

// Other configuration
$safeMode = getenv('SAFE_MODE') ?: false;
$debugUsers = getenv('DEBUG_USERS') ?: false;

// Create a PDO connection to the PostgreSQL database
try {
    $dsn = "pgsql:host=$PG_host;port=$PG_port;dbname=$PG_dbname;user=$PG_user;password=$PG_password";
    $pdo = new PDO($dsn);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start Logging
if (!isset($openlog)) {
    $openLog = openlog("mm-merge", LOG_ODELAY, LOG_LOCAL0);
}
