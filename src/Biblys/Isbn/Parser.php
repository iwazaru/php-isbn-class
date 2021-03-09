<?php

/*
 * This file is part of the biblys/isbn package.
 *
 * (c) Clément Bourgoin
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace Biblys\Isbn;

use Biblys\Isbn\ParsedIsbn;

class Parser
{
    // FIXME: Create custom exceptions for each case
    const ERROR_EMPTY = 'No code provided',
        ERROR_INVALID_CHARACTERS = 'Invalid characters in the code',
        ERROR_INVALID_LENGTH = 'Code is too short or too long',
        ERROR_INVALID_PRODUCT_CODE = 'Product code should be 978 or 979',
        ERROR_INVALID_COUNTRY_CODE = 'Country code is unknown';

    public static function parse(string $input): ParsedIsbn
    {
        if (empty($input)) {
            throw new IsbnParsingException(static::ERROR_EMPTY);
        }

        $inputWithoutUnwantedCharacters = self::_stripUnwantedCharacters($input);
        $inputWithoutCheckDigit = self::_stripCheckDigit($inputWithoutUnwantedCharacters);

        if (!is_numeric($inputWithoutCheckDigit)) {
            throw new IsbnParsingException(static::ERROR_INVALID_CHARACTERS);
        }

        $result = self::_extractGs1element($inputWithoutCheckDigit);
        $inputWithoutProductCode = $result[0];
        $gs1Element = $result[1];

        $result = self::_extractRegistrationGroupElement(
            $inputWithoutProductCode,
            $gs1Element
        );
        $inputWithoutCountryCode = $result[0];
        $registrationGroupElement = $result[1];

        $result = self::_extractRegistrationAndPublicationElement(
            $inputWithoutCountryCode,
            $gs1Element,
            $registrationGroupElement
        );
        $registrationAgencyName = $result[0];
        $registrantElement = $result[1];
        $publicationElement = $result[2];

        return new ParsedIsbn(
            [
                "gs1Element" => $gs1Element,
                "registrationGroupElement" => $registrationGroupElement,
                "registrantElement" => $registrantElement,
                "publicationElement" => $publicationElement,
                "registrationAgencyName" => $registrationAgencyName,
            ]
        );
    }

    private static function _stripUnwantedCharacters(string $input): string
    {
        $replacements = array('-', '_', ' ');
        $input = str_replace($replacements, '', $input);

        return $input;
    }

    private static function _stripCheckDigit(string $input): string
    {
        $length = strlen($input);

        if ($length == 12 || $length == 9) {
            return $input;
        }

        if ($length == 13 || $length == 10) {
            $input = substr_replace($input, "", -1);
            return $input;
        }

        throw new IsbnParsingException(static::ERROR_INVALID_LENGTH);
    }

    private static function _extractGs1element(string $input): array
    {
        if (strlen($input) == 9) {
            return [$input, 978];
        }

        $first3 = substr($input, 0, 3);
        if ($first3 == 978 || $first3 == 979) {
            $input = substr($input, 3);
            return [$input, $first3];
        }

        throw new IsbnParsingException(static::ERROR_INVALID_PRODUCT_CODE);
    }

    private static function _extractRegistrationGroupElement(
        string $input,
        string $gs1Element
    ): array
    {
        include('ranges-array.php');
        $prefixes = $prefixes;

        // Get the seven first digits
        $first7 = substr($input, 0, 7);

        // Select the right set of rules according to the product code
        foreach ($prefixes as $p) {
            if ($p['Prefix'] == $gs1Element) {
                $rules = $p['Rules']['Rule'];
                break;
            }
        }

        // Select the right rule
        foreach ($rules as $r) {
            $ra = explode('-', $r['Range']);
            if ($first7 >= $ra[0] && $first7 <= $ra[1]) {
                $length = $r['Length'];
                break;
            }
        }

        // Country code is invalid
        if (!isset($length) || $length === "0") {
            throw new IsbnParsingException(static::ERROR_INVALID_COUNTRY_CODE);
        };

        $registrationGroupElement = substr($input, 0, $length);
        $input = substr($input, $length);

        return [$input, $registrationGroupElement];
    }

    /**
     * Remove and save Publisher Code and Publication Code
     */
    private static function _extractRegistrationAndPublicationElement(
        string $input,
        string $gs1Element,
        string $registrationGroupElement
    ): array
    {
        // Get the seven first digits or less
        $first7 = substr($input, 0, 7);
        $inputLength = strlen($first7);

        // Select the right set of rules according to the agency
        $ranges = new Ranges();
        $groups = $ranges->getGroups();
        foreach ($groups as $g) {
            if ($g['Prefix'] <> $gs1Element . '-' . $registrationGroupElement) {
                continue;
            }

            $rules = $g['Rules']['Rule'];
            $agency = $g['Agency'];

            // Select the right rule
            foreach ($rules as $rule) {

                // Get min and max value in range
                // and trim values to match code length
                $range = explode('-', $rule['Range']);
                $min = substr($range[0], 0, $inputLength);
                $max = substr($range[1], 0, $inputLength);

                // If first 7 digits is smaller than min
                // or greater than max, continue to next rule
                if ($first7 < $min || $first7 > $max) {
                    continue;
                }

                $length = $rule['Length'];

                $registrantElement = substr($input, 0, $length);
                $publicationElement = substr($input, $length);

                return [$agency, $registrantElement, $publicationElement];
            }
            break;
        }
    }
}
