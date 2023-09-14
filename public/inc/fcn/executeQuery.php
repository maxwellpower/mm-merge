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

// Function to execute a SQL query
function executeQuery($query, $dryRun = true, $debug = true): false|array
{
    global $pdo;

    $pdo->beginTransaction();
    // Only add RETURNING * for INSERT or UPDATE statements
    if (stripos($query, 'INSERT') === 0 || stripos($query, 'UPDATE') === 0) {
        $query .= " RETURNING *";
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    if ($debug) {
        syslog(LOG_DEBUG, "[DEBUG][MERGE]: Running Query: $query");
        echo "<div class='row'><div class='col'><p><strong>QUERY</strong>: <code>$query</code></p><p><strong>RESULTS</strong>:</p>";
        echo "<div class='row bg-dark border border-dark border-3 rounded mb-3 mt-3'><div class='col'><pre class='mt-2 text-light overflow-auto'><code>";
        $queryResult = json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
        syslog(LOG_DEBUG, "[DEBUG][MERGE]: Query Results: $queryResult");
        print_r($queryResult);
        echo "</code></pre></div></div>";
    }
    if ($_SESSION['safe_mode']) {
        $pdo->rollBack();
        if ($debug) {
            syslog(LOG_DEBUG, "[DEBUG][MERGE]: SAFE MODE - Changes NOT Committed!");
            echo "<p class='alert alert-warning'><strong>NOTE</strong>: This was a safe mode run! Changes were <b>NOT</b> committed.</p></div></div>";
        }
        return false;
    }
    if ($dryRun) {
        $pdo->rollBack();
        if ($debug) {
            syslog(LOG_DEBUG, "[DEBUG][MERGE]: DRY RUN - Changes NOT Committed!");
            echo "<p class='alert alert-success'><strong>NOTE</strong>: This was a dry run! Changes were <b>NOT</b> committed.</p></div></div>";
        }
        return false;
    } else {
        $pdo->commit();
        if ($debug) {
            syslog(LOG_DEBUG, "[DEBUG][MERGE]: Changes Committed!");
            echo "<p class='alert alert-success'><strong>NOTE</strong>: Changes were committed!</p></div></div>";
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function executeSELECTQuery($query): false|array
{
    global $pdo;
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function executeRollbackChanges(): void
{
    global $pdo;
    $pdo->rollBack();
}
