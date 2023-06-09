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
require 'inc/header.php';
require_once 'inc/db.php';

/**
 * @var string $PG_host
 * @var string $PG_port
 * @var string $PG_dbname
 * @var string $PG_user
 * @var string $PG_password
 */
?>
    <div class="row">
        <div class="col text-center">
            <h1>Mattermost User Account Merge Tool</h1>
        </div>
    </div>
<?php
// Function to execute a SQL query
function executeQuery($query): false|array
{
    global $pdo;
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to update the specified table with the new user ID
function updateTable($table, $userIdColumn, $oldUserId, $newUserId): void
{

    $query = "UPDATE $table SET $userIdColumn = '$newUserId' WHERE $userIdColumn = '$oldUserId'";
    executeQuery($query);
}

// Function to merge two users
function mergeUsers($oldUserId, $newUserId): void
{
    $errorReported = false;

    // List of tables that contain the user ID
    $tables = [
        'audits' => 'userid',
        'channelmemberhistory' => 'userid',
        'commandwebhooks' => 'userid',
        'drafts' => 'userid',
        'emoji' => 'creatorid',
        'fileinfo' => 'creatorid',
        'focalboard_blocks' => 'created_by',
        'focalboard_blocks_history' => 'created_by',
        'focalboard_board_members' => 'user_id',
        'focalboard_board_members_history' => 'user_id',
        'focalboard_boards_history' => 'created_by',
        'focalboard_subscriptions' => 'subscriber_id',
        'incomingwebhooks' => 'userid',
        'ir_category' => 'userid',
        'ir_incident' => 'reporteruserid',
        'ir_playbookautofollow' => 'userid',
        'ir_playbookmember' => 'memberid',
        'ir_run_participants' => 'userid',
        'ir_timelineevent' => 'creatoruserid',
        'ir_viewedchannel' => 'userid',
        'notifyadmin' => 'userid',
        'outgoingwebhooks' => 'creatorid',
        'postacknowledgements' => 'userid',
        'postreminders' => 'userid',
        'posts' => 'userid',
        'recentsearches' => 'userid',
        'sharedchannels' => 'creatorid',
        'sharedchannelusers' => 'userid',
        'termsofservice' => 'userid',
        'threadmemberships' => 'userid',
        'uploadsessions' => 'userid',
        'useraccesstokens' => 'userid'
    ];

    // Update each table with the new user ID
    foreach ($tables as $table => $userIdColumn) {
        echo "Updating $table...<br>";
        try {
            updateTable($table, $userIdColumn, $oldUserId, $newUserId);
        } catch (Exception $e) {
            echo "Error updating $table: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
    }

    // Update channelmembers table with the new user ID where the newuserID is not already listed as a member
    echo "Updating channelmembers...<br>";
    $query = "UPDATE channelmembers SET userid = '$newUserId' WHERE userid = '$oldUserId' AND channelid NOT IN (SELECT channelid FROM channelmembers WHERE userid = '$newUserId') AND channelid IN (SELECT id FROM channels WHERE type = 'O' or type = 'G')";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating channelmembers: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update the productnoticeviewstate table with the new user ID
    echo "Updating productnoticeviewstate ...<br>";
    $query = "DELETE from productnoticeviewstate WHERE userid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating productnoticeviewstate: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update DM Posts
    echo "Updating DM Posts ...<br>";
    $olddmchannelname = $oldUserId . '__' . $oldUserId;
    $query = "SELECT id FROM channels WHERE name = '$olddmchannelname' AND type = 'D'";
    try {
        $results = executeQuery($query);
        @$olddmchannelid = $results[0]['id'];
    } catch (Exception $e) {
        echo "Could not select old channel name: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    $newdmchannelname = $newUserId . '__' . $newUserId;
    $query = "SELECT id FROM channels WHERE name = '$newdmchannelname' AND type = 'D'";
    try {
        $results = executeQuery($query);
        $newdmchannelid = $results[0]['id'];
    } catch (Exception $e) {
        echo "Could not select new channel name: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    if (empty($olddmchannelid) || empty($newdmchannelid)) {
        if (empty($olddmchannelid)) {
            echo "<code>Could not find old DM channel ID</code><br><br>";
        } elseif (empty($newdmchannelid)) {
            echo "<code>Could not find new DM channel ID</code><br><br>";
        } else {
            echo "<code>Could not find old or new DM channel ID</code><br><br>";
        }
        $errorReported = true;
    } else {
        $query = "UPDATE posts SET channelid = '$newdmchannelid' WHERE channelid = '$olddmchannelid'";
        try {
            executeQuery($query);
        } catch (Exception $e) {
            echo "Error updating DM posts: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
    }

    // Remove users DM channel
    echo "Removing users DM channel ...<br>";
    $query = "DELETE FROM channels WHERE name = '$olddmchannelname' AND type = 'D'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error deleting DM channel: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update DM Channels
    echo "Updating DM Channels ...<br>";
    $query = "UPDATE channels SET name = REPLACE(name, '$oldUserId', '$newUserId') WHERE type = 'D' AND name != '$newdmchannelname'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating DM channels: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Remove DM between old and new user
    $sharedmchannelname1 = $oldUserId . '__' . $newUserId;
    $sharedmchannelname2 = $newUserId . '__' . $oldUserId;
    echo "Removing DM between old and new user ...<br>";
    $query = "DELETE FROM channels WHERE name = '$sharedmchannelname1' OR name = '$sharedmchannelname2' AND type = 'D'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error deleting DM channel: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update channel creator
    echo "Updating channel creator ...<br>";
    $query = "UPDATE channels SET creatorid = '$newUserId' WHERE creatorid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating channel creator: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update focalboard_blocks modified_by
    echo "Updating focalboard_blocks ...<br>";
    $query = "UPDATE focalboard_blocks SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating focalboard_blocks: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update focalboard_blocks modified_by
    echo "Updating focalboard_blocks_history ...<br>";
    $query = "UPDATE focalboard_blocks_history SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating focalboard_blocks: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update boards history
    echo "Updating boards history ...<br>";
    $query = "UPDATE focalboard_boards_history SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating focalboard_boards_history: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Delete the old users focalboard preferences
    echo "Deleting focalboard preferences ...<br>";
    $query = "DELETE FROM focalboard_preferences WHERE userid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating focalboard_preferences: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Delete board sessions
    echo "Deleting board sessions ...<br>";
    $query = "DELETE FROM focalboard_sessions WHERE user_id = '$oldUserId' OR user_id = '$newUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating focalboard_sessions: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Delete the old users board account
    echo "Deleting focalboard account ...<br>";
    $query = "DELETE FROM focalboard_users WHERE id = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating focalboard_users: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Replace the olduserID in groupmembers with the newuserID where the newuserID is not already listed in the same groupid
    echo "Replacing groupmembers ...<br>";
    $query = "UPDATE groupmembers SET userid = '$newUserId' WHERE userid = '$oldUserId' AND groupid NOT IN (SELECT groupid FROM groupmembers WHERE userid = '$newUserId')";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating groupmembers: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update ir_incident with new commander
    echo "Updating ir_incident commander ...<br>";
    $query = "UPDATE ir_incident SET commanderuserid = '$newUserId' WHERE commanderuserid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating ir_incident commander: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update ir_incident checklistsjson with the new user ID
    echo "Updating ir_incident checklistsjson ...<br>";
    $query = "UPDATE ir_incident SET checklistsjson = (checklistsjson::text)::jsonb - '$oldUserId' || '\"$newUserId\"' WHERE checklistsjson::text LIKE '%$oldUserId%'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating ir_incident checklistsjson: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update ir_timelineevent subjectuserid
    echo "Updating ir_timelineevent subjectuserid ...<br>";
    $query = "UPDATE ir_timelineevent SET subjectuserid = '$newUserId' WHERE subjectuserid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating ir_timelineevent: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Delete the old users oauth data
    echo "Deleting oauth data ...<br>";
    $query = "DELETE FROM oauthaccessdata WHERE userid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating oauthaccessdata: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    $query = "DELETE FROM oauthauthdata WHERE userid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating oauthauthdata: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Update the system posts props json with the proper username of the new user
    echo "Updating system posts props json ...<br>";

    if (empty($oldUsername) || empty($newUsername)) {
        if (empty($oldUsername)) {
            echo "<code>Could not find old username</code><br><br>";
        } elseif (empty($newUsername)) {
            echo "<code>Could not find new username</code><br><br>";
        } else {
            echo "<code>Could not find old or new username</code><br><br>";
        }
        $errorReported = true;
    } else {
        $query = "UPDATE posts SET props = (props::text)::jsonb - '$oldUsername' || '$newUsername' WHERE props::text LIKE '%$oldUsername%'";
        try {
            executeQuery($query);
        } catch (Exception $e) {
            echo "Error updating system posts props: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
    }
    // Update the post props json
    echo "Updating post props json ...<br>";
    $query = "UPDATE posts SET props = jsonb_set(props, '{userId}', '\"$newUserId\"') WHERE props->>'userId' = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating posts props: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Delete the old users preferences
    echo "Deleting preferences ...<br>";
    $query = "DELETE FROM preferences WHERE userid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating preferences: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Delete the old and new users sessions
    echo "Removing sessions ...<br>";
    $query = "DELETE FROM sessions WHERE userid = '$oldUserId' OR userid = '$newUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating sessions: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Remove sidebar categories
    echo "Removing old sidebar categories ...<br>";
    $query = "DELETE FROM sidebarcategories WHERE userid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating sidebarcategories: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Remove status
    echo "Removing old status ...<br>";
    $query = "DELETE FROM status WHERE userid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating status: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Remove team member
    echo "Removing old team member ...<br>";
    $query = "DELETE FROM teammembers WHERE userid = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating teammembers: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Delete the old users account
    echo "Deleting old account ...<br>";
    $query = "DELETE FROM users WHERE id = '$oldUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error updating users: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    // Reset authdata if requested
    if ($_POST['force_authdata_checkbox']) {
        echo "Resetting authdata ...<br>";
        $query = "UPDATE users SET authdata = NULL WHERE id = '$newUserId'";
        try {
            executeQuery($query);
        } catch (Exception $e) {
            echo "Error updating authdata: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
    }

    // Update the email address if requested
    if (isset($_POST['force_email_checkbox']) && $_POST['force_email_checkbox']) {
        $force_email = $_POST['force_email'];
        echo "Updating email address to <code>$force_email</code> ...<br>";
        $query = "UPDATE users SET email = '$force_email' WHERE id = '$newUserId'";
        try {
            executeQuery($query);
        } catch (Exception $e) {
            echo "Error updating email: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
    }

    // Update the username if requested
    if (isset($_POST['force_email_checkbox']) && $_POST['force_username_checkbox']) {
        $force_username = $_POST['force_username'];
        echo "Updating username to <code>$force_username</code> ...<br>";
        $query = "UPDATE users SET username = '$force_username' WHERE id = '$newUserId'";
        try {
            executeQuery($query);
        } catch (Exception $e) {
            echo "Error updating username: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
    }

    // Ensure the user account is enabled and has no deleted date set
    echo "Enabling account ...<br>";
    $query = "UPDATE users SET deleteat = 0 WHERE id = '$newUserId'";
    try {
        executeQuery($query);
    } catch (Exception $e) {
        echo "Error deleting user: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    if (!$errorReported) {
        echo "<div class='alert alert-success text-center'><strong><i class='bi bi-check-circle-fill'></i> User merge completed successfully!</strong></div>";
    } else {
        echo "<div class='alert alert-danger text-center'><strong><i class='bi bi-exclamation-circle-fill'></i> User merge completed with errors!</strong></div>";
    }
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (getenv('DUMP_POST') !== null && getenv('DUMP_POST')) {
        ini_set('display_errors', 1);
        ?>
        <div class="row mb-3">
            <div class="col-8 offset-2">
                <h2 class="text-center">Post Data</h2>
                <code>
                    <?= json_encode($_POST, JSON_PRETTY_PRINT); ?>
                </code>
            </div>
        </div>
        <?php
    } else {
        ini_set('display_errors', 0);
    }
    // Get the selected users to merge
    $oldUserId = $_POST['old_user_id'];
    $newUserId = $_POST['new_user_id'];

    // Get the usernames
    $query = "SELECT username FROM users WHERE id = '$newUserId'";
    try {
        $result = executeQuery($query);
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
        $result = executeQuery($query);
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
                    // Perform the merge
                    echo "<span class='text-success bg-light'>Starting merge ...</span><br>";
                    mergeUsers($oldUserId, $newUserId);
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
} else {
    // Fetch existing user accounts available to merge
    $query = "SELECT id, firstname, lastname, username, email FROM users WHERE email NOT LIKE '%@localhost' AND username
    NOT LIKE 'admin' ORDER BY lastname";
    $users = executeQuery($query);
    ?>
    <div class="row">
        <div class="col text-center">
            <div class="alert alert-danger">
                <p><strong>DANGER!</strong> This tool is designed to merge two Mattermost users into one. This is an
                    extremely destructive operation and cannot be undone. The old user account will be deleted and all
                    posts, threads, and other system data will be transferred to the other user account.</p>
                <h4><strong><i class="bi bi-exclamation-octagon-fill"></i> Please ensure you have a backup of your
                        database before proceeding!</strong></h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-6 offset-3">
            <div class="row">
                <div class="col text-center">
                    <h2>Accounts to Merge</h2>
                    <p><strong><i class="bi bi-database-fill"></i> Connected to</strong><span
                                class="text-success"> <?= $PG_user . '@' . $PG_host . ':' . $PG_port; ?></span></p>
                </div>
            </div>
            <div class=row">
                <div class="col">
                    <form id="users" method="post">
                        <div class="row alert alert-light">
                            <div class="col">
                                <strong><i class="bi bi-person-x"></i> <label class="form-label" for="old_user_id">User
                                        Account to Purge</label></strong> <span
                                        class="badge bg-secondary">Required</span>
                                <select class="form-select" name="old_user_id" id="old_user_id">
                                    <option value="" selected disabled>Choose a user ...</option>
                                    <?php foreach ($users as $user) { ?>
                                        <option value="<?= $user['id']; ?>"><?= $user['lastname'] . ', ' . $user['firstname'] . ' (' . $user['username'] . ') - ' . $user['email']; ?></option>
                                    <?php } ?>
                                </select>
                                <div id="old_user_idHelp" class="form-text alert alert-danger text-center"><strong><i
                                                class="bi bi-exclamation-triangle-fill"></i> THIS USER WILL BE DELETED!</strong>
                                </div>
                            </div>
                        </div>
                        <div class="row alert alert-light mt-3">
                            <div class="col">
                                <strong><i class="bi bi-person-lock"></i> <label class="form-label" for="new_user_id">User
                                        Account to
                                        Remain</label></strong> <span class="badge bg-secondary">Required</span>
                                <select class="form-select" name="new_user_id" id="new_user_id">
                                    <option value="" selected disabled>Choose a user ...</option>
                                    <?php foreach ($users as $user) { ?>
                                        <option value="<?= $user['id']; ?>" data-email="<?= $user['email']; ?>"
                                                data-username="<?= $user['username']; ?>">
                                            <?= $user['lastname'] . ', ' . $user['firstname'] . ' (' . $user['username'] . ') - ' . $user['email']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="row alert alert-light mt-3">
                            <div class="col">
                                <div class="row">
                                    <div class="col">
                                        <div class="form-check form-switch form-check-inline">
                                            <strong><label class="form-label" for="force_authdata_checkbox">Force
                                                    <code>authdata</code> Reset</label></strong>
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="force_authdata_checkbox" name="force_authdata_checkbox"
                                                   checked="checked" value="true">
                                            <small class="form-text">(This will reset the SAML <code>authdata</code> of
                                                the merged user)</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <div class="form-check form-switch form-check-inline">
                                            <strong><label class="form-label" for="force_username_checkbox">Force
                                                    Username</label></strong>
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="force_username_checkbox" name="force_username_checkbox"
                                                   value="true">
                                        </div>
                                        <div>
                                            <label class="form-label" for="force_username" hidden="hidden">Force
                                                Username</label>
                                            <input class="form-control" type="text" id="force_username"
                                                   name="force_username" placeholder="Email Address to Force"
                                                   style="display: none;">
                                            <small class="form-text">This will update the merged user to the username
                                                specified</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col">
                                        <div class="form-check form-switch form-check-inline">
                                            <strong><label class="form-label" for="force_email_checkbox">Force Email
                                                    Address</label></strong>
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="force_email_checkbox" name="force_email_checkbox" value="true">
                                        </div>
                                        <div>
                                            <label class="form-label" for="force_email" hidden="hidden">Force Email
                                                Address</label>
                                            <input class="form-control" type="text" id="force_email" name="force_email"
                                                   placeholder="Email Address to Force" style="display: none;">
                                            <small class="form-text">This will update the merged user to the email
                                                address specified</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button title="submit" id="submit" type="submit" class="btn btn-lg btn-danger">
                                <strong><i class="bi bi-sign-merge-left-fill"></i> MERGE USER ACCOUNTS</strong></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    if (getenv('DUMP_POST') !== null && getenv('DUMP_POST')) {
        ?>
        <div class="row">
            <div class="col-4 offset-4 text-center mt-3 alert alert-warning">
                <code>POST DEBUG ENABLED!</code>
            </div>
        </div>
        <?php
    }
}
require 'inc/footer.php';
