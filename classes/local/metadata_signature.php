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

namespace auth_saml2\local;

use Exception;
use moodle_exception;;
use SimpleSAML\XML\Validator;

/**
 * Class verify_metadata_signature
 *
 * @package    auth_saml2
 * @copyright  2026 University of Graz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metadata_signature {
    /**
     * Verify ACOnet/eduID.at metadata and notify admins on failure.
     *
     * @param string $idpurl The metadata URL being processed.
     * @param string $rawxml The downloaded metadata XML.
     * @return bool True if metadata is valid (or not ACOnet), false if invalid ACOnet metadata.
     */
    public static function validate(string $idpurl, string $rawxml): bool {
        // Only enforce signature validation for ACOnet / eduID.at metadata URLs.
        if (!str_starts_with(trim($idpurl), 'https://eduid.at/md/')) {
            return true;
        }

        return self::validate_aconet_metadata($idpurl, $rawxml);
    }

    /**
     * Verify ACOnet/eduID.at metadata and notify admins on failure.
     *
     * @param string $idpurl The metadata URL being processed.
     * @param string $rawxml The downloaded metadata XML.
     * @return bool True if metadata is valid (or not ACOnet), false if invalid ACOnet metadata.
     */
    private static function validate_aconet_metadata(string $idpurl, string $rawxml): bool {
        $publickey = file_get_contents(__DIR__ . '/../../certs/aconet-metadata-signing.crt');

        try {
            self::verify_metadata_signature($rawxml, $publickey);
            return true;
        } catch (\Exception $e) {
            $reason = $e->getMessage();
        }

        // Email admins immediately.
        self::notify_admins_on_verification_failure($idpurl, $reason);
        return false;
    }

    /**
     * Verify Metadata signature.
     *
     * @param string $xml Raw metadata XML.
     * @param string $publickey The key used to verify the XML.
     * @return void
     * @throws Exception If the the XML is not signed by this key.
     */
    private static function verify_metadata_signature($xml, $publickey) {
        $xmldoc = new \DOMDocument();
        $xmldoc->loadXML($xml);

        require_once(__DIR__. '/../../vendor/simplesamlphp/simplesamlphp/src/SimpleSAML/XML/Validator.php');
        require_once(__DIR__. '/../../vendor/robrichards/xmlseclibs/src/XMLSecEnc.php');
        require_once(__DIR__. '/../../vendor/robrichards/xmlseclibs/src/XMLSecurityDSig.php');
        require_once(__DIR__. '/../../vendor/robrichards/xmlseclibs/src/Utils/XPath.php');
        require_once(__DIR__. '/../../vendor/robrichards/xmlseclibs/src/XMLSecurityKey.php');

        new Validator($xmldoc, 'ID', $publickey);
    }

    /**
     * Notify Moodle admins that metadata verification has failed.
     *
     * @param string $idpurl The metadata URL that failed.
     * @param string $reason Description of the failure.
     */
    private static function notify_admins_on_verification_failure($idpurl, $reason) {
        global $CFG;

        $admins = \get_admins();

        $subject = \get_string('metadata_verification_failed_subject', 'auth_saml2');
        $message = \get_string(
            'metadata_verification_failed_body',
            'auth_saml2',
            (object) [
                'url'     => $idpurl,
                'reason'  => $reason,
                'wwwroot' => $CFG->wwwroot,
            ]
        );

        foreach ($admins as $admin) {
            \email_to_user($admin, $admin, $subject, $message);
        }
    }
}
