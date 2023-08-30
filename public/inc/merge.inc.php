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

/**
 * @var string $DB_HOST
 * @var string $DB_PORT
 * @var string $DB_USER
 */

// Get the selected users to merge
$oldUserId = $_POST['old_user_id'];
$newUserId = $_POST['new_user_id'];

// Set the dry run flag
$dryRun = $_POST['dry_run_checkbox'] ?? false;

if ($dryRun) {
    $debug = true;
} else {
    // Set the debug flag
    $debug = $_POST['debug_checkbox'] ?? false;
}
// Get the usernames
$query = "SELECT username FROM users WHERE id = '$newUserId'";
try {
    $result = executeSELECTQuery($query);
    $newUsername = $result[0]['username'];
    if ($newUsername == "") {
        $newUsername = "Unknown";
    }
} catch (Exception $e) {
    // logs the error to the php error logs
    error_log("Error getting new username " . $e->getMessage());
    $newUsername = "Unknown";
}
$query = "SELECT username FROM users WHERE id = '$oldUserId'";
try {
    $result = executeSELECTQuery($query);
    @$oldUsername = $result[0]['username'];
    if ($oldUsername == "") {
        $oldUsername = "Unknown";
    }
} catch (Exception $e) {
    // logs the error to the php error logs
    error_log("Error getting old username " . $e->getMessage());
    $oldUsername = "Unknown";
}

syslog(LOG_INFO, "Merging user $oldUsername ($oldUserId) into $newUsername ($newUserId)");
?>
<div class="row">
    <div class="col-8 offset-2">
        <div class="row">
            <div class="col text-center">
                <h2>Merging <code><?= $oldUsername; ?></code> into <code><?= $newUsername; ?></code></h2>
                <p><strong><i class="bi bi-database-fill"></i> Connected to</strong><span
                            class="text-success"> <?= $DB_USER . '@' . $DB_HOST . ':' . $DB_PORT; ?></span></p>
            </div>
        </div>
        <div class="row alert alert-light">
            <div class="col">
                <?php
                // If dryrun is enabled, display a warning
                if ($dryRun) {
                    echo "<p class='alert alert-success text-center'><strong><i class='bi bi-exclamation-circle-fill'></i> Dry Run: <code>ENABLED</code></strong></p>";
                }

                // If debug is enabled, display a warning
                if ($debug) {
                    echo "<p class='alert alert-info text-center'><strong><i class='bi bi-exclamation-circle-fill'></i> Debug: <code>ENABLED</code></strong></p>";
                }

                // Perform the merge
                echo "<div class='row'><div class='col'><h3 class='text-success'>Starting merge ...</h3></div></div>";
                $mergeUsers = mergeUsers($oldUserId, $oldUsername, $newUserId, $newUsername, $dryRun, $debug);
                echo "<div class='row'><div class='col'>";

                // Check if error array is empty
                if (empty($mergeUsers)) {
                    syslog(LOG_INFO, "User merge completed successfully!");
                    echo "<div class='alert alert-success text-center'><strong><i class='bi bi-check-circle-fill'></i> User merge completed successfully!</strong></div>";
                } else {
                    $debugMergeOutputCount = count($mergeUsers);
                    $debugMergeOutput = json_encode($mergeUsers, JSON_PRETTY_PRINT);
                    syslog(LOG_ERR, "Found $debugMergeOutputCount errors during merge. Exporting log to logs/merge.json");
                    $mergeLog = fopen($_SERVER['DOCUMENT_ROOT'] . "/logs/merge.json", "w") or die("Unable to open file!");
                    $mergeLogWrite = fwrite($mergeLog, $debugMergeOutput);
                    $mergeLog = fclose($mergeLog);
                    if ($mergeLogWrite) {
                        syslog(LOG_INFO, "Successfully exported errors to logs/merge.json.");
                    } else {
                        syslog(LOG_INFO, "Failed to export users list.");
                    }
                    // Output all failures
                    echo "<div class='alert alert-danger text-center'><strong><i class='bi bi-exclamation-circle-fill'></i> User merge completed with errors!</strong></div>";
                    echo "<div class='row'><div class='col'><h3 class='text-danger'>Error Summary</h3><div class='row alert alert-secondary'><div class='col'>";
                    foreach ($mergeUsers as $error) {
                        $id = $error['errorRowID'];
                        $table = $error['errorReportedTable'];
                        $message = $error['errorReportedMessage'];

                        echo "<p><strong>Query</strong>: <a href='#$id'>" . $table . "</a></><br><strong>Message</strong>: <code>" . $message . "</code></p>";
                    }
                    echo "</div></div>";
                    echo "<div class='row'><div class='col'><h3 class='text-danger'>Error Log</h3><p>Errors output to <a href='logs/merge.json'><code>logs/merge.json</code></a></p></div></div>";
                }
                echo "</div></div>";
                ?>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col">
        <?php
        if ($dryRun && empty($mergeUsers)) {
            // Generate a new hidden form and submit button that will actually complete the merge
            ?>
            <form id="complete_user_merge" method="post">
                <input type="text" name="old_user_id" id="old_user_id" hidden="hidden"
                       value="<?= $_POST['old_user_id']; ?>">
                <input type="text" name="new_user_id" id="new_user_id" hidden="hidden"
                       value="<?= $_POST['new_user_id']; ?>">
                <input type="checkbox" id="force_authdata_checkbox" name="force_authdata_checkbox" value="true"
                       hidden="hidden" <?= (isset($_POST['force_authdata_checkbox']) && $_POST['force_authdata_checkbox']) ? "checked=checked" : false; ?>>
                <input type="checkbox" id="force_username_checkbox" name="force_username_checkbox" value="true"
                       hidden="hidden" <?= (isset($_POST['force_username_checkbox']) && $_POST['force_username_checkbox']) ? "checked=checked" : false; ?>>
                <input type="text" id="force_username" name="force_username" hidden="hidden"
                    <?= (isset($_POST['force_username'])) ? 'value="' . $_POST['force_username'] . '"' : false; ?>>
                <input type="checkbox" id="force_email_checkbox" name="force_email_checkbox" value="true"
                       hidden="hidden" <?= (isset($_POST['force_email_checkbox']) && $_POST['force_email_checkbox']) ? "checked=checked" : false; ?>>
                <input type="text" id="force_email" name="force_email"
                       hidden="hidden" <?= (isset($_POST['force_email'])) ? 'value="' . $_POST['force_email'] . '"' : false; ?>>
                <input type="checkbox" id="debug_checkbox" name="debug_checkbox" value="true"
                       hidden="hidden" <?= ((isset($_POST['debug_checkbox']) && $_POST['debug_checkbox']) || (isset($_POST['dry_run_checkbox']) && $_POST['dry_run_checkbox'])) ? "checked=checked" : false; ?>>
                <div class="text-center mt-3">
                    <button title="submit" id="submit" type="submit" class="btn btn-lg btn-danger">
                        <strong><i class="bi bi-sign-merge-left-fill"></i> COMPLETE ACCOUNT MERGE</strong></button>
                </div>
            </form>
        <?php } elseif (empty($mergeUsers)) {
            ?>
            <div class="text-center mt-3">
                <button title="new" id="new" type="button" class="btn btn-lg btn-success"
                        onclick="window.location.href='/'"><strong><i class="bi bi-plus-circle-fill"></i> New
                        Merge</strong></button>
            </div>
            <?php
        } elseif ($dryRun) {
            ?>
            <div class="text-center mt-3">
                <button title="refresh" id="refresh" type="button" class="btn btn-lg btn-primary"
                        onclick="window.location.reload();"><strong><i class="bi bi-arrow-clockwise"></i> Try
                        Again</strong></button>
            </div>
            <?php
        } ?>
    </div>
</div>
