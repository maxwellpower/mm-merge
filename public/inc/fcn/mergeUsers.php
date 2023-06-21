<?php

// Function to merge two users
function mergeUsers($oldUserId, $oldUsername, $newUserId, $newUsername, $dryRun, $debug): bool
{
    // Function to update the specified table with the new user ID
    function updateTable($table, $userIdColumn, $oldUserId, $newUserId, $dryRun, $debug): void
    {
        $query = "UPDATE $table SET $userIdColumn = '$newUserId' WHERE $userIdColumn = '$oldUserId'";
        executeQuery($query, $dryRun, $debug);
    }

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
        echo '<div class="row mb-1"><div class="col alert alert-secondary">';
        echo "<h4>Updating $table...</h4>";
        try {
            updateTable($table, $userIdColumn, $oldUserId, $newUserId, $dryRun, $debug);
        } catch (Exception $e) {
            echo "Error updating $table: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
        echo '</div></div>';
    }

    // Update channelmembers table with the new user ID where the newuserID is not already listed as a member
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating channelmembers...</h4>";
    $query = "UPDATE channelmembers SET userid = '$newUserId' WHERE userid = '$oldUserId' AND channelid NOT IN (SELECT channelid FROM channelmembers WHERE userid = '$newUserId') AND channelid IN (SELECT id FROM channels WHERE type = 'O' or type = 'G')";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating channelmembers: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update the productnoticeviewstate table with the new user ID
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating productnoticeviewstate ...</h4>";
    $query = "DELETE from productnoticeviewstate WHERE userid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating productnoticeviewstate: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update DM Posts
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating DM Posts ...</h4>";
    $olddmchannelname = $oldUserId . '__' . $oldUserId;
    $query = "SELECT id FROM channels WHERE name = '$olddmchannelname' AND type = 'D'";
    try {
        $results = executeSELECTQuery($query);
        @$olddmchannelid = $results[0]['id'];
    } catch (Exception $e) {
        echo "Could not select old channel name: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }

    $newdmchannelname = $newUserId . '__' . $newUserId;
    $query = "SELECT id FROM channels WHERE name = '$newdmchannelname' AND type = 'D'";
    try {
        $results = executeSELECTQuery($query);
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
            executeQuery($query, $dryRun, $debug);
        } catch (Exception $e) {
            echo "Error updating DM posts: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
    }
    echo "</div></div>";

    // Remove users DM channel
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Removing users DM channel ...</h4>";
    $query = "DELETE FROM channels WHERE name = '$olddmchannelname' AND type = 'D'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error deleting DM channel: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update DM Channels
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating DM Channels ...</h4>";
    $query = "UPDATE channels SET name = REPLACE(name, '$oldUserId', '$newUserId') WHERE type = 'D' AND name != '$newdmchannelname' AND NOT EXISTS (SELECT 1 FROM channels WHERE name = '$newdmchannelname')";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating DM channels: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Remove DM between old and new user
    $sharedmchannelname1 = $oldUserId . '__' . $newUserId;
    $sharedmchannelname2 = $newUserId . '__' . $oldUserId;
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Removing DM between old and new user ...</h4>";
    $query = "DELETE FROM channels WHERE name = '$sharedmchannelname1' OR name = '$sharedmchannelname2' AND type = 'D'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error deleting DM channel: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update channel creator
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating channel creator ...</h4>";
    $query = "UPDATE channels SET creatorid = '$newUserId' WHERE creatorid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating channel creator: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update focalboard_blocks modified_by
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating focalboard_blocks ...</h4>";
    $query = "UPDATE focalboard_blocks SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating focalboard_blocks: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update focalboard_blocks modified_by
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating focalboard_blocks_history ...</h4>";
    $query = "UPDATE focalboard_blocks_history SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating focalboard_blocks: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update boards history
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating boards history ...</h4>";
    $query = "UPDATE focalboard_boards_history SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating focalboard_boards_history: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Delete the old users focalboard preferences
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Deleting focalboard preferences ...</h4>";
    $query = "DELETE FROM focalboard_preferences WHERE userid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating focalboard_preferences: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Delete board sessions
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Deleting board sessions ...</h4>";
    $query = "DELETE FROM focalboard_sessions WHERE user_id = '$oldUserId' OR user_id = '$newUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating focalboard_sessions: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Delete the old users board account
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Deleting focalboard account ...</h4>";
    $query = "DELETE FROM focalboard_users WHERE id = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating focalboard_users: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Replace the olduserID in groupmembers with the newuserID where the newuserID is not already listed in the same groupid
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Replacing groupmembers ...</h4>";
    $query = "UPDATE groupmembers SET userid = '$newUserId' WHERE userid = '$oldUserId' AND groupid NOT IN (SELECT groupid FROM groupmembers WHERE userid = '$newUserId')";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating groupmembers: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update ir_incident with new commander
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating ir_incident commander ...</h4>";
    $query = "UPDATE ir_incident SET commanderuserid = '$newUserId' WHERE commanderuserid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating ir_incident commander: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update ir_incident checklistsjson with the new user ID
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating ir_incident checklistsjson ...</h4>";
    $query = "UPDATE ir_incident SET checklistsjson = (checklistsjson::text)::jsonb - '$oldUserId' || '\"$newUserId\"' WHERE checklistsjson::text LIKE '%$oldUserId%'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating ir_incident checklistsjson: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update ir_timelineevent subjectuserid
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating ir_timelineevent subjectuserid ...</h4>";
    $query = "UPDATE ir_timelineevent SET subjectuserid = '$newUserId' WHERE subjectuserid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating ir_timelineevent: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Delete the old users oauth data
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Deleting oauth data ...</h4>";
    $query = "DELETE FROM oauthaccessdata WHERE userid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating oauthaccessdata: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    $query = "DELETE FROM oauthauthdata WHERE userid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating oauthauthdata: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Update the system posts props json with the proper username of the new user
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating system posts props json ...</h4>";

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
        $query = "UPDATE posts SET props = props - '$oldUsername' || '{\"$newUsername\": true}' WHERE props::text LIKE '%$oldUsername%'";
        try {
            executeQuery($query, $dryRun, $debug);
        } catch (Exception $e) {
            echo "Error updating system posts props: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
    }
    echo "</div></div>";

    // Update the post props json
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Updating post props json ...</h4>";
    $query = "UPDATE posts SET props = jsonb_set(props, '{userId}', '\"$newUserId\"') WHERE props->>'userId' = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating posts props: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Delete the old users preferences
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Deleting preferences ...</h4>";
    $query = "DELETE FROM preferences WHERE userid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating preferences: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Delete the old and new users sessions
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Removing sessions ...</h4>";
    $query = "DELETE FROM sessions WHERE userid = '$oldUserId' OR userid = '$newUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating sessions: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Remove sidebar categories
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Removing old sidebar categories ...</h4>";
    $query = "DELETE FROM sidebarcategories WHERE userid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating sidebarcategories: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Remove status
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Removing old status ...</h4>";
    $query = "DELETE FROM status WHERE userid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating status: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Remove team member
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Removing old team member ...</h4>";
    $query = "DELETE FROM teammembers WHERE userid = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating teammembers: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Reset authdata if requested
    if ($_POST['force_authdata_checkbox']) {
        echo '<div class="row mb-1"><div class="col alert alert-secondary">';
        echo "<h4>Resetting authdata ...</h4>";
        $query = "UPDATE users SET authdata = NULL WHERE id = '$newUserId'";
        try {
            executeQuery($query, $dryRun, $debug);
        } catch (Exception $e) {
            echo "Error updating authdata: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
        echo "</div></div>";
    }

    // Update the email address if requested
    if (isset($_POST['force_email_checkbox']) && $_POST['force_email_checkbox']) {
        $force_email = $_POST['force_email'];
        echo '<div class="row mb-1"><div class="col alert alert-secondary">';
        echo "<h4>Updating email address to <code>$force_email</code> ...</h4>";
        $query = "UPDATE users SET email = '$force_email' WHERE id = '$newUserId'";
        try {
            executeQuery($query, $dryRun, $debug);
        } catch (Exception $e) {
            echo "Error updating email: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
        echo "</div></div>";
    }

    // Update the username if requested
    if (isset($_POST['force_email_checkbox']) && $_POST['force_username_checkbox']) {
        $force_username = $_POST['force_username'];
        echo '<div class="row mb-1"><div class="col alert alert-secondary">';
        echo "<h4>Updating username to <code>$force_username</code> ...</h4>";
        $query = "UPDATE users SET username = '$force_username' WHERE id = '$newUserId'";
        try {
            executeQuery($query, $dryRun, $debug);
        } catch (Exception $e) {
            echo "Error updating username: <code>" . $e->getMessage() . "</code><br><br>";
            $errorReported = true;
        }
        echo "</div></div>";
    }

    // Ensure the user account is enabled and has no deleted date set
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Enabling account ...</h4>";
    $query = "UPDATE users SET deleteat = 0 WHERE id = '$newUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error deleting user: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    // Delete the old users account
    echo '<div class="row mb-1"><div class="col alert alert-secondary">';
    echo "<h4>Deleting old account ...</h4>";
    $query = "DELETE FROM users WHERE id = '$oldUserId'";
    try {
        executeQuery($query, $dryRun, $debug);
    } catch (Exception $e) {
        echo "Error updating users: <code>" . $e->getMessage() . "</code><br><br>";
        $errorReported = true;
    }
    echo "</div></div>";

    return $errorReported;
}
