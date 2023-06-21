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
 * @var string $PG_host
 * @var string $PG_port
 * @var string $PG_user
 */

// Fetch existing user accounts available to merge
$query = "SELECT id, firstname, lastname, username, email FROM users WHERE email NOT LIKE '%@localhost' AND username
    NOT LIKE 'admin' ORDER BY lastname";
$users = executeSELECTQuery($query);
?>
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
                                        Account to Remain</label></strong> <span
                                    class="badge bg-secondary">Required</span>
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
                                <div class="row mb-2">
                                    <div class="col">
                                        <h2 class="heading">Advanced User Options</h2>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <div class="form-check form-switch form-check-inline">
                                            <strong><label class="form-label" for="force_authdata_checkbox">Force
                                                    <code>authdata</code> Reset</label></strong>
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="force_authdata_checkbox" name="force_authdata_checkbox"
                                                   checked="checked" value="true">
                                            <p><small class="form-text">Reset the SAML <code>authdata</code> of
                                                    the merged user.</small></p>
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
                                            <p><small class="form-text">Update the merged user to the specified
                                                    username</small></p>
                                        </div>
                                        <div>
                                            <label class="form-label" for="force_username" hidden="hidden">Force
                                                Username</label>
                                            <input class="form-control" type="text" id="force_username"
                                                   name="force_username" placeholder="Email Address to Force"
                                                   style="display: none;">
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
                                            <p><small class="form-text">Update the merged user to the specified email
                                                    address</small></p>
                                        </div>
                                        <div>
                                            <label class="form-label" for="force_email" hidden="hidden">Force Email
                                                Address</label>
                                            <input class="form-control" type="text" id="force_email" name="force_email"
                                                   placeholder="Email Address to Force" style="display: none;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row alert alert-light mt-3">
                            <div class="col">
                                <div class="row mb-2">
                                    <div class="col">
                                        <h2>Run Options</h2>
                                    </div>
                                </div>
                                <div class="row alert alert-danger">
                                    <div class="col">
                                        <div class="form-check form-switch form-check-inline mt-3">
                                            <strong><label class="form-label" for="dry_run_checkbox">Dry Run</strong>
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="dry_run_checkbox" name="dry_run_checkbox"
                                                   checked="checked" value="true">
                                            <p><small class="form-text">Perform a dry run and display the changes that
                                                    will be made to the database.</small></p>
                                        </div>
                                        <p><strong>DANGER</strong>: Unselecting will commit database changes!</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <div class="form-check form-switch form-check-inline mt-2">
                                            <strong><label class="form-label" for="debug_checkbox">Debug Output</strong>
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="debug_checkbox" name="debug_checkbox"
                                                   checked="checked" value="true">
                                            <p><small class="form-text">Output JSON from the database changes.</small>
                                            </p>
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
