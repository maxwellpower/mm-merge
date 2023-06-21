<?php

if (getenv('DUMP_POST') !== null && getenv('DUMP_POST')) {
    ini_set('display_errors', 1);
    ?>
    <div class="row mb-3">
        <div class="col-8 offset-2 alert alert-warning">
            <h2 class="text-center"><i class="bi bi-mailbox2"></i> Post Data</h2>
            <div class='row alert bg-dark border border-dark border-3 rounded mb-3 mt-3'>
                <div class='col'>
                    <pre class='mt-2 text-light overflow-auto'>
<?php print_r(json_encode($_POST, JSON_PRETTY_PRINT)); ?>
                    </pre>
                </div>
            </div>
        </div>
    </div>
    <?php
} else {
    ini_set('display_errors', 0);
}
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
    // log the error to the php error log
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
    // log the error to the php error log
    error_log("Error getting old username " . $e->getMessage());
    $oldUsername = "Unknown";
}
?>
<div class="row">
    <div class="col-8 offset-2">
        <div class="row">
            <div class="col text-center">
                <h2>Merging <code><?= $oldUsername; ?></code> into <code><?= $newUsername; ?></code></h2>
                <p><strong><i class="bi bi-database-fill"></i> Connected to</strong><span
                            class="text-success"> <?= $PG_user . '@' . $PG_host . ':' . $PG_port; ?></span></p>
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
                echo "</div></div><div class='row'><div class='col'>";
                if (!$mergeUsers) {
                    echo "<div class='alert alert-success text-center'><strong><i class='bi bi-check-circle-fill'></i> User merge completed successfully!</strong></div>";
                } else {
                    echo "<div class='alert alert-danger text-center'><strong><i class='bi bi-exclamation-circle-fill'></i> User merge completed with errors!</strong></div>";
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
        if ($dryRun) {
            // Generate a new hidden form and submit button that will actually complete the merge
            ?>
            <form id="users" method="post">
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
        <?php } else {
            ?>
            <div class="text-center mt-3">
                <button title="new" id="new" type="button" class="btn btn-lg btn-success"
                        onclick="window.location.href='/'"><strong><i class="bi bi-plus-circle-fill"></i> New
                        Merge</strong></button>
            </div>
            <?php
        } ?>
    </div>
</div>
