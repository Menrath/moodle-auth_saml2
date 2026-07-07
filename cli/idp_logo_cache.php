<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Auth SAML2 - CLI script to manage cached IdP logos.
 *
 * This script can purge cached logos, refresh cache for active IdPs,
 * or both, using CLI options.
 *
 * @package   auth_saml2
 * @copyright 2026 University of Graz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/auth/saml2/classes/local/idp_logo_cache.php');

use auth_saml2\local\idp_logo_cache;

global $DB;

// Get CLI options.
[$options, $unrecognized] = cli_get_params(
    [
        'help'      => false,
        'purge'     => false,   // Delete all cached logos.
        'refresh'   => false,   // Refresh cache for all active IdPs.
        'delete'    => true,    // Delete existing cache before refresh.
    ],
    [
        'h'         => 'help',
        'p'         => 'purge',
        'r'         => 'refresh',
        'd'         => 'delete',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// CLI help.
if (!empty($options['help'])) {
    $help = <<<EOF
Auth SAML2: Manage IdP logo cache

This CLI script allows you to purge and/or refresh cached logos for IdPs
configured in auth_saml2.

By default it will:
  - Refresh cached logos for all active IdPs
  - Delete existing cached logos before re-caching them

Options:
  -h, --help       Show this help.
  -p, --purge      Delete all cached logos for all IdPs.
  -r, --refresh    Refresh logo cache for all active IdPs.
  -d, --delete     When used with --refresh, delete existing logo cache
                   before re-caching (default: enabled).

Example usages:
  # Purge all cached logos
  sudo -u www-data php cache_idp_logos.php --purge

  # Refresh cache for all active IdPs (delete old cache first)
  sudo -u www-data php cache_idp_logos.php --refresh

  # Refresh cache for all active IdPs without deleting old cache
  sudo -u www-data php cache_idp_logos.php --refresh --delete=0

  # Purge and then refresh
  sudo -u www-data php cache_idp_logos.php --purge --refresh

EOF;

    cli_writeln($help);
    exit(0);
}

// If neither purge nor refresh is specified, set a sensible default.
// Here: perform refresh like your original script.
if (empty($options['purge']) && empty($options['refresh'])) {
    $options['refresh'] = true;
}

// Print heading.
cli_heading('Auth SAML2: Manage IdP logo cache');

// Purge all cached logos.
if (!empty($options['purge'])) {
    cli_writeln('Purging all cached IdP logos...');

    $idps = $DB->get_records('auth_saml2_idps', null, '', 'id, defaultname');
    foreach ($idps as $idp) {
        idp_logo_cache::delete_cached_logo((int)$idp->id);
        cli_writeln('Purged logo cache for IdP: ' . $idp->defaultname . ' (ID: ' . $idp->id . ')');
    }

    cli_writeln('Purge completed.');
}

// Refresh cached logos for all active IdPs.
if (!empty($options['refresh'])) {
    cli_writeln('Refreshing logo cache for active IdPs...');

    $activeidps = $DB->get_records('auth_saml2_idps', ['activeidp' => 1], '', 'id, defaultname, logo');

    foreach ($activeidps as $idp) {
        // Optionally delete existing cache before re-caching.
        if (!empty($options['delete'])) {
            idp_logo_cache::delete_cached_logo((int)$idp->id);
            cli_writeln('Deleted existing logo cache for IdP: ' . $idp->defaultname . ' (ID: ' . $idp->id . ')');
        }

        $file = idp_logo_cache::cache_logo($idp->logo, (int)$idp->id);

        if ($file !== false) {
            cli_writeln('Successfully cached logo for ' . $idp->defaultname . ' (ID: ' . $idp->id . ')');
        } else {
            cli_writeln('Failed to cache logo for ' . $idp->defaultname . ' (ID: ' . $idp->id . ')');
        }
    }

    cli_writeln('Refresh completed.');
}

exit(0);
