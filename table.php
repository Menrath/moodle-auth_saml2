<?php
/**
 * Simple file test.php to drop into root of Moodle installation.
 * This is the skeleton code to print a downloadable, paged, sorted table of
 * data from a sql query.
 */
require_once(__DIR__ . '/../../config.php');
require_admin();

require "$CFG->libdir/tablelib.php";
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/test.php');

$download = optional_param('download', '', PARAM_ALPHA);

$table = new table_sql('uniqueid');
$table->is_downloading($download, 'test', 'testing123');

if (!$table->is_downloading()) {
    // Only print headers if not asked to download data
    // Print the page header
    $PAGE->set_title('Testing');
    $PAGE->set_heading('Testing table class');
    $PAGE->navbar->add('Testing table class', new moodle_url('/auth/saml2/table.php'));
    echo $OUTPUT->header();
}

// Work out the sql for the table.
$table->set_sql('*', "{auth_saml2_idps}", '1=1');

$table->define_baseurl("$CFG->wwwroot/auth/saml2/table.php");

$table->out(40, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}