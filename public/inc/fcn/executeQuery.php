<?php

// Function to execute a SQL query
function executeQuery($query, $dryRun = true, $debug = true): false|array
{
    global $pdo;
    global $safeMode;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare($query . " RETURNING *");
    $stmt->execute();
    if ($debug) {
        echo "<div class='row'><div class='col'><p><strong>QUERY</strong>: <code>$query</code></p><p><strong>RESULTS</strong>:</p>";
        echo "<div class='row bg-dark border border-dark border-3 rounded mb-3 mt-3'><div class='col'><pre class='mt-2 text-light overflow-auto'><code>";
        print_r(json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
        echo "</code></pre></div></div>";
    }
    if ($safeMode) {
        $pdo->rollBack();
        if ($debug) {
            echo "<p class='alert alert-warning'><strong>NOTE</strong>: This was a safe mode run! Changes were not committed.</p></div></div>";
        }
        return false;
    }
    if ($dryRun) {
        $pdo->rollBack();
        if ($debug) {
            echo "<p class='alert alert-success'><strong>NOTE</strong>: This was a dry run! Changes were not committed.</p></div></div>";
        }
        return false;
    } else {
        $pdo->commit();
        if ($debug) {
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
