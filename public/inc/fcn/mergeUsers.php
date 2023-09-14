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

// Function to merge two users
function mergeUsers($oldUserId, $oldUsername, $newUserId, $newUsername, $dryRun, $debug): array
{
    global $errorArray;
    $errorArray = [];

    // Function to update the specified table with the new user ID
    function updateTable($table, $userIdColumn, $oldUserId, $newUserId, $dryRun, $debug): void
    {
        $query = "UPDATE $table SET $userIdColumn = '$newUserId' WHERE $userIdColumn = '$oldUserId'";
        processQuery("query_$table", "Updating Table $table", $query, $dryRun, $debug);
    }

    function handleDatabaseQueryFailure($table, $e, $id): void
    {
        global $errorArray;

        $errorReportedTable = $table;
        $errorReportedMessage = $e->getMessage();

        $errorArray[] = [
            'errorReportedTable' => $errorReportedTable,
            'errorReportedMessage' => $errorReportedMessage,
            'errorRowID' => $id
        ];
    }

    function processQuery($id, $heading, $query, $dryRun, $debug): false|array|null
    {
        syslog(LOG_INFO, $heading);
        echo "<div id='$id' class='row mb-1'><div class='col alert alert-secondary'>";
        echo "<h4>$heading ...</h4>";
        try {
            return executeQuery($query, $dryRun, $debug);
        } catch (Exception $e) {
            echo "Error $heading: <code>" . $e->getMessage() . "</code><br><br>";
            handleDatabaseQueryFailure($query, $e, $id);
            syslog(LOG_ERR, "Merge Error in $heading: " . $e->getMessage());
            executeRollbackChanges();
            return null;
        } finally {
            echo "</div></div>";
        }
    }

    function handleUserUpdate($field, $forceValue, $checkboxName, $queryId, $oldUserId, $newUserId, $dryRun, $debug): void
    {
        if (isset($_POST[$checkboxName]) && $_POST[$checkboxName]) {
            $id = $queryId;
            $heading = "Updating $field to <code>$forceValue</code>";

            if ($dryRun) {
                echo "<div id='$id' class='row mb-1'><div class='col alert alert-secondary'>";
                echo "<h4>$heading ...</h4>";

                $checkQuery = "SELECT id FROM users WHERE $field = '$forceValue'";
                $existingUser = executeSELECTQuery($checkQuery);

                if ($existingUser && $existingUser[0]['id'] != $oldUserId) {
                    echo "Error: $field $forceValue is already associated with another user.";
                } else {
                    echo "Dry run: Old user would be deleted and new user's $field would be updated to $forceValue.";
                }

                echo "</div></div>";
            } else {
                $query = "UPDATE users SET $field = '$forceValue' WHERE id = '$newUserId'";
                processQuery($id, $heading, $query, $dryRun, $debug);
            }
        }
    }

    function massUpdateTables($oldUserId, $newUserId, $dryRun, $debug, $playbooksEnabled, $boardsEnabled): void
    {
        // List of tables that contain the user ID
        $primaryTables = [
            'audits' => 'userid',
            'channelmemberhistory' => 'userid',
            'commandwebhooks' => 'userid',
            'drafts' => 'userid',
            'emoji' => 'creatorid',
            'fileinfo' => 'creatorid',
            'incomingwebhooks' => 'userid',
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

        $boardsTables = [
            'focalboard_blocks' => 'created_by',
            'focalboard_blocks_history' => 'created_by',
            'focalboard_board_members' => 'user_id',
            'focalboard_board_members_history' => 'user_id',
            'focalboard_boards_history' => 'created_by',
            'focalboard_subscriptions' => 'subscriber_id',
        ];

        $playbookTables = [
            'ir_category' => 'userid',
            'ir_incident' => 'reporteruserid',
            'ir_playbookautofollow' => 'userid',
            'ir_playbookmember' => 'memberid',
            'ir_run_participants' => 'userid',
            'ir_timelineevent' => 'creatoruserid',
            'ir_viewedchannel' => 'userid',
        ];

        // Update each primaryTable with the new user ID
        foreach ($primaryTables as $table => $userIdColumn) {
            updateTable($table, $userIdColumn, $oldUserId, $newUserId, $dryRun, $debug);
        }

        if ($boardsEnabled) {
            foreach ($boardsTables as $table => $userIdColumn) {
                updateTable($table, $userIdColumn, $oldUserId, $newUserId, $dryRun, $debug);
            }
        }

        if ($playbooksEnabled) {
            foreach ($playbookTables as $table => $userIdColumn) {
                updateTable($table, $userIdColumn, $oldUserId, $newUserId, $dryRun, $debug);
            }
        }
    }

    // Check if Boards tables exist
    $checkBoardsEnabledQuery = "SELECT * FROM focalboard_system_settings LIMIT 1";
    $checkBoardsEnabled = executeSELECTQuery($checkBoardsEnabledQuery);

    if (!empty($checkBoardsEnabled)) {
        $boardsEnabled = true;
        if ($debug) {
            syslog(LOG_INFO, "Boards enabled");
        }
    } else {
        $boardsEnabled = false;
        if ($debug) {
            syslog(LOG_NOTICE, "Boards disabled");
        }
    }

    // Check if Playbooks tables exist
    $checkPlaybooksEnabledQuery = "SELECT * FROM ir_system LIMIT 1";
    $checkPlaybooksEnabled = executeSELECTQuery($checkPlaybooksEnabledQuery);

    if (!empty($checkPlaybooksEnabled)) {
        $playbooksEnabled = true;
        if ($debug) {
            syslog(LOG_INFO, "Playbooks enabled");
        }
    } else {
        $playbooksEnabled = false;
        if ($debug) {
            syslog(LOG_NOTICE, "Playbooks disabled");
        }
    }

    massUpdateTables($oldUserId, $newUserId, $dryRun, $debug, $playbooksEnabled, $boardsEnabled);

    // Update channelmembers table with the new user ID where the newuserID is not already listed as a member
    $query = "UPDATE channelmembers SET userid = '$newUserId' WHERE userid = '$oldUserId' AND channelid NOT IN (SELECT channelid FROM channelmembers WHERE userid = '$newUserId') AND channelid IN (SELECT id FROM channels WHERE type = 'O' or type = 'G' or type = 'P')";
    processQuery('query_channelmembers', "Updating Channel Members", $query, $dryRun, $debug);

    // Update the productnoticeviewstate table with the new user ID
    $query = "DELETE from productnoticeviewstate WHERE userid = '$oldUserId'";
    processQuery('query_productnoticeviewstate', "Removing Product Notice View State", $query, $dryRun, $debug);

    // Update users personal DM channel
    $olddmchannelname = $oldUserId . '__' . $oldUserId;
    $query = "SELECT id FROM channels WHERE name = '$olddmchannelname' AND type = 'D'";
    $results = executeSELECTQuery($query);
    $olddmchannelid = isset($results[0]['id']) ? $results[0]['id'] : null;

    $newdmchannelname = $newUserId . '__' . $newUserId;
    $query = "SELECT id FROM channels WHERE name = '$newdmchannelname' AND type = 'D'";
    $results = executeSELECTQuery($query);
    $newdmchannelid = isset($results[0]['id']) ? $results[0]['id'] : null;

    if (empty($olddmchannelid) || empty($newdmchannelid)) {
        echo '<div id="query_update_dm_posts" class="row mb-1"><div class="col alert alert-secondary">';
        echo '<h4>Updating Users Personal DM\'s ...</h4>';
        if (empty($olddmchannelid)) {
            echo "<code>Could not find old DM channel ID</code><br><br>";
        } elseif (empty($newdmchannelid)) {
            echo "<code>Could not find new DM channel ID</code><br><br>";
        } else {
            echo "<code>Could not find old or new DM channel ID</code><br><br>";
        }
        echo "<p>No DM Channel. Skipping Update ...</p></div></div>";
    } else {
        $query = "UPDATE posts SET channelid = '$newdmchannelid' WHERE channelid = '$olddmchannelid'";
        processQuery('query_update_dm_posts', "Updating Users Personal DM Posts", $query, $dryRun, $debug);

        // Remove users DM channel
        $query = "DELETE FROM channels WHERE name = '$olddmchannelname' AND type = 'D'";
        processQuery('query_remove_old_dm', "Removing Users Personal DM Channel", $query, $dryRun, $debug);

    }

    // Fetch DM channels involving the old user
    $query = "SELECT id, name FROM channels WHERE type = 'D' AND (name LIKE '$oldUserId\__%' OR name LIKE '%__$oldUserId')";
    $results = executeSELECTQuery($query);

    $iteration = 1;

    foreach ($results as $result) {
        $id = $result['id'];
        $oldName = $result['name'];

        // Split the old name to get both user IDs
        list($user1, $user2) = explode('__', $oldName);

        // Construct the new name
        if ($user1 == $oldUserId) {
            $newName = $newUserId . '__' . $user2;
        } else if ($user2 == $oldUserId) {
            $newName = $user1 . '__' . $newUserId;
        } else {
            break;
        }

        // Update the channel name
        $query = "UPDATE channels SET name = '$newName' WHERE id = '$id' AND NOT EXISTS (SELECT 1 FROM channels WHERE name = '$newName')";
        processQuery('query_update_channel_' . $iteration, "Updating DM Channel $id", $query, $dryRun, $debug);

        $iteration++;
    }

    // Remove DM between old and new user
    $sharedmchannelname1 = $oldUserId . '__' . $newUserId;
    $sharedmchannelname2 = $newUserId . '__' . $oldUserId;
    $query = "DELETE FROM channels WHERE name = '$sharedmchannelname1' OR name = '$sharedmchannelname2' AND type = 'D'";
    processQuery('query_old_new_dm', "Removing DM Between Merged Users", $query, $dryRun, $debug);

    // Update channel creator
    $query = "UPDATE channels SET creatorid = '$newUserId' WHERE creatorid = '$oldUserId'";
    processQuery('query_channel_creator', "Updating Channel Creator", $query, $dryRun, $debug);

    if ($boardsEnabled) {
        // Update focalboard_blocks modified_by
        $query = "UPDATE focalboard_blocks SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
        processQuery('query_board_blocks', "Updating Board Blocks", $query, $dryRun, $debug);

        // Update focalboard_blocks modified_by
        $query = "UPDATE focalboard_blocks_history SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
        processQuery('query_board_blocks_history', "Updating Board Blocks History", $query, $dryRun, $debug);

        // Update boards history
        $query = "UPDATE focalboard_boards_history SET modified_by = '$newUserId' WHERE modified_by = '$oldUserId'";
        processQuery('query_board_history', "Updating Board History", $query, $dryRun, $debug);

        // Delete the old users focalboard preferences
        $query = "DELETE FROM focalboard_preferences WHERE userid = '$oldUserId'";
        processQuery('query_board_preferences', "Removing Board Preferences", $query, $dryRun, $debug);

        // Delete board sessions
        $query = "DELETE FROM focalboard_sessions WHERE user_id = '$oldUserId' OR user_id = '$newUserId'";
        processQuery('query_board_sessions', "Removing Board Sessions", $query, $dryRun, $debug);

        // Delete the old users board account
        $query = "DELETE FROM focalboard_users WHERE id = '$oldUserId'";
        processQuery('query_board_account', "Removing Board Account", $query, $dryRun, $debug);
    }

    // Replace the olduserID in groupmembers with the newuserID where the newuserID is not already listed in the same groupid
    $query = "UPDATE groupmembers SET userid = '$newUserId' WHERE userid = '$oldUserId' AND groupid NOT IN (SELECT groupid FROM groupmembers WHERE userid = '$newUserId')";
    processQuery('query_groupmembers', "Updating Group Members", $query, $dryRun, $debug);

    if ($playbooksEnabled) {
        // Update ir_incident with new commander
        $query = "UPDATE ir_incident SET commanderuserid = '$newUserId' WHERE commanderuserid = '$oldUserId'";
        processQuery('query_ir_incident_commander', "Updating Playbook Incident Commander", $query, $dryRun, $debug);

        // Update ir_incident checklistsjson with the new user ID
        $query = "UPDATE ir_incident SET checklistsjson = (checklistsjson::text)::jsonb - '$oldUserId' || '\"$newUserId\"' WHERE checklistsjson::text LIKE '%$oldUserId%'";
        processQuery('query_ir_incident_checklist', "Updating Playbook Incident Checklists", $query, $dryRun, $debug);

        // Update ir_timelineevent subjectuserid
        $query = "UPDATE ir_timelineevent SET subjectuserid = '$newUserId' WHERE subjectuserid = '$oldUserId'";
        processQuery('query_ir_timelineevent', "Updating Playbook Timeline Events", $query, $dryRun, $debug);
    }

    // Delete the old users oauth data
    $query = "DELETE FROM oauthaccessdata WHERE userid = '$oldUserId'";
    processQuery('query_oauthaccessdata', "Removing Oauth Access Data", $query, $dryRun, $debug);
    $query = "DELETE FROM oauthauthdata WHERE userid = '$oldUserId'";
    processQuery('query_oauthauthdata', "Removing Oauth Auth Data", $query, $dryRun, $debug);

    // Update the system posts props json with the proper username of the new user

    if (empty($oldUsername) || empty($newUsername)) {
        echo '<div id="query_posts_props_username" class="row mb-1"><div class="col alert alert-secondary">';
        echo '<h4>Updating System Posts Props JSON ...</h4>';
        if (empty($oldUsername)) {
            echo "<code>Could not find old username</code><br><br>";
        } elseif (empty($newUsername)) {
            echo "<code>Could not find new username</code><br><br>";
        } else {
            echo "<code>Could not find old or new username</code><br><br>";
        }
        echo "</div></div>";
    } else {
        $query = "UPDATE posts SET props = props - '$oldUsername' || '{\"$newUsername\": true}' WHERE props::text LIKE '%$oldUsername%'";
        processQuery('query_posts_props_username', "Updating System Posts Props JSON", $query, $dryRun, $debug);
    }

    // Update the post props json
    $query = "UPDATE posts SET props = jsonb_set(props, '{userId}', '\"$newUserId\"') WHERE props->>'userId' = '$oldUserId'";
    processQuery('query_posts_props_userid', "Updating Post Props JSON", $query, $dryRun, $debug);

    // Delete the old users preferences
    $query = "DELETE FROM preferences WHERE userid = '$oldUserId'";
    processQuery('query_preferences', "Removing Preferences", $query, $dryRun, $debug);

    // Handle special case for reactions table
    if ($dryRun) {
        // If it's a dry run, just display a message about what would be done
        $conflictCheckQuery = "SELECT postid, emojiname FROM reactions WHERE userid = '$oldUserId' AND (postid, emojiname) IN (SELECT postid, emojiname FROM reactions WHERE userid = '$newUserId')";
        $conflicts = executeSELECTQuery($conflictCheckQuery);

        if (!empty($conflicts)) {
            echo "<div id='conflict_reactions' class='row mb-1'><div class='col alert alert-secondary'>";
            echo "<h4>Resolving Conflicts in reactions ...</h4>";
            echo "Dry run: Conflicting reactions from the old user would be deleted.";
            foreach ($conflicts as $conflict) {
                echo "<br>Post ID: {$conflict['postid']}, Emoji: {$conflict['emojiname']}";
            }
            echo "</div></div>";
        } else {
            updateTable('reactions', 'userid', $oldUserId, $newUserId, $dryRun, $debug);
        }
    } else {
        // If it's not a dry run, execute the DELETE and UPDATE queries
        $query = "DELETE FROM reactions WHERE userid = '$oldUserId' AND (postid, emojiname) IN (SELECT postid, emojiname FROM reactions WHERE userid = '$newUserId')";
        processQuery("conflict_reactions", "Resolving Conflicts in reactions", $query, $dryRun, $debug);
        updateTable('reactions', 'userid', $oldUserId, $newUserId, $dryRun, $debug);
    }

    // Delete the old and new users sessions
    $query = "DELETE FROM sessions WHERE userid = '$oldUserId' OR userid = '$newUserId'";
    processQuery('query_sessions', "Removing Sessions", $query, $dryRun, $debug);

    // Remove sidebar categories
    $query = "DELETE FROM sidebarcategories WHERE userid = '$oldUserId'";
    processQuery('query_sidebarcategories', "Removing Sidebar Categories", $query, $dryRun, $debug);

    // Remove status
    $query = "DELETE FROM status WHERE userid = '$oldUserId'";
    processQuery('query_status', "Removing Old Status", $query, $dryRun, $debug);

    // Remove team member
    $query = "DELETE FROM teammembers WHERE userid = '$oldUserId'";
    processQuery('query_remove_teammember', "Removing Old Team Member", $query, $dryRun, $debug);

    // Delete the old users account
    $query = "DELETE FROM users WHERE id = '$oldUserId'";
    processQuery("query_delete_old_account", "Removing Old Account", $query, $dryRun, $debug);

    // Reset authdata if requested
    if (isset($_POST['force_authdata_checkbox']) && $_POST['force_authdata_checkbox']) {
        $query = "UPDATE users SET authdata = NULL WHERE id = '$newUserId'";
        processQuery('query_update_authdata', "Resetting authdata", $query, $dryRun, $debug);
    }

    // Reset email if requested
    if (isset($_POST['force_email_checkbox']) && $_POST['force_email_checkbox']) {
        handleUserUpdate('email', $_POST['force_email'] ?? '', 'force_email_checkbox', 'query_update_email', $oldUserId, $newUserId, $dryRun, $debug);
    }

    // Reset username if requested
    if (isset($_POST['force_username_checkbox']) && $_POST['force_username_checkbox']) {
        handleUserUpdate('username', $_POST['force_username'] ?? '', 'force_username_checkbox', 'query_update_username', $oldUserId, $newUserId, $dryRun, $debug);
    }

    // Ensure the user account is enabled and has no deleted date set
    $query = "UPDATE users SET deleteat = 0 WHERE id = '$newUserId'";
    processQuery("query_enable_account", "Enabling Account", $query, $dryRun, $debug);

    return $errorArray;

}
