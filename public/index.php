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

require_once 'inc/loader.php';
require 'inc/header.php';

/**
 * @var string $APP_VERSION
 */

?>
    <div class="row">
        <div class="col text-center">
            <h1>Mattermost User Account Merge Tool (<small><code>v<?= $APP_VERSION; ?></code></small>)</h1>
        </div>
    </div>
<?php
require 'inc/fcn/executeQuery.php';
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'inc/fcn/mergeUsers.php';

    // Merge the users and display the results
    require 'inc/merge.inc.php';

} // Display the form
else {
    syslog(LOG_INFO, "[INFO][APP]: INTERFACE LOADED");
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
    <?php
    if ($_SESSION['safe_mode']) {
        ?>
        <div class="row">
            <div class="col-6 offset-3 text-center">
                <div class="row">
                    <div class="col alert alert-warning">
                        <h4 class="mt-3"><i class="bi bi-cone-striped"></i> SAFE MODE: <code>ENABLED</code></h4>
                        <p>Changes will not be committed to the database!</p>
                    </div>
                </div>
            </div>
        </div>
    <?php }
    require 'inc/form.inc.php';
}

require 'inc/footer.php';
