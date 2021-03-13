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

        [$inputWithoutGs1Element, $gs1Element] = self::_extractGs1Element($input);

        [$inputWithoutRegistrationGroupElement, $registrationGroupElement] = self::_extractRegistrationGroupElement(
            $inputWithoutGs1Element,
            $gs1Element
        );

        [$registrationAgencyName, $registrantElement, $publicationElement] = self::_extractRegistrationAndPublicationElement(
            $inputWithoutRegistrationGroupElement,
            $gs1Element,
            $registrationGroupElement
        );

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

    private static function _extractGs1Element(string $input): array
    {
        $inputWithoutCheckDigit = self::_stripCheckDigit($input);

        if (!is_numeric($inputWithoutCheckDigit)) {
            throw new IsbnParsingException(static::ERROR_INVALID_CHARACTERS);
        }

        if (strlen($inputWithoutCheckDigit) === 9) {
            return [$inputWithoutCheckDigit, 978];
        }

        $first3 = self::_getStringStart($inputWithoutCheckDigit, 3);
        if (self::_isAnInvalidGs1Element($first3)) {
            throw new IsbnParsingException(static::ERROR_INVALID_PRODUCT_CODE);
        }

        $inputWithoutGs1Element = self::_getStringEnd($inputWithoutCheckDigit, 3);
        return [$inputWithoutGs1Element, $first3];
    }

    private static function _stripCheckDigit(string $input): string
    {
        $inputWithoutUnwantedCharacters = self::_stripUnwantedCharacters($input);
        $length = strlen($inputWithoutUnwantedCharacters);

        if ($length === 12 || $length === 9) {
            return $inputWithoutUnwantedCharacters;
        }

        if ($length === 13 || $length === 10) {
            $inputWithoutCheckDigit = substr_replace($inputWithoutUnwantedCharacters, "", -1);
            return $inputWithoutCheckDigit;
        }

        throw new IsbnParsingException(static::ERROR_INVALID_LENGTH);
    }

    private static function _stripUnwantedCharacters(string $input): string
    {
        $replacements = array('-', '_', ' ');
        $input = str_replace($replacements, '', $input);

        return $input;
    }

    private static function _isAnInvalidGs1Element(string $value): bool
    {
        return $value !== "978" && $value !== "979";
    }

    private static function _extractRegistrationGroupElement(
        string $inputWithoutGs1Element,
        string $gs1Element
    ): array
    {
        $length = self::_getRegistrationGroupLengthForGs1Element(
            $inputWithoutGs1Element,
            $gs1Element
        );

        $registrationGroupElement = self::_getStringStart($inputWithoutGs1Element, $length);
        $inputWithoutRegistrationGroupElement = self::_getStringEnd(
            $inputWithoutGs1Element,
            $length
        );

        return [$inputWithoutRegistrationGroupElement, $registrationGroupElement];
    }

    private static function _getRegistrationGroupLengthForGs1Element(
        string $inputWithoutGs1Element,
        string $gs1Element
    ): int {
        foreach (self::_getRulesForGs1element($gs1Element) as $rule) {
            $range = explode('-', $rule['Range']);
            if (
                self::_valueIsInRange(
                    self::_getStringStart($inputWithoutGs1Element, 7),
                    $range
                ) &&
                self::_lengthIsValid($rule['Length'])
            ) {
                return $rule['Length'];
            }
        }

        throw new IsbnParsingException(static::ERROR_INVALID_COUNTRY_CODE);
    }

    private static function _getRulesForGs1element(string $gs1Element): array
    {
        foreach (self::_getPrefixes() as $prefix) {
            if ($prefix['Prefix'] === $gs1Element) {
                return $prefix['Rules']['Rule'];
            }
        }
    }

    private static function _extractRegistrationAndPublicationElement(
        string $inputWithoutRegistrationGroupElement,
        string $gs1Element,
        string $registrationGroupElement
    ): array
    {
        $group = self::_getGroupForPrefix($gs1Element . '-' . $registrationGroupElement);
        $length = self::_getLengthForGroup($group, $inputWithoutRegistrationGroupElement);

        $registrationAgencyName = $group['Agency'];
        $registrantElement = self::_getStringStart($inputWithoutRegistrationGroupElement, $length);
        $publicationElement = self::_getStringEnd($inputWithoutRegistrationGroupElement, $length);

        return [$registrationAgencyName, $registrantElement, $publicationElement];
    }

    private static function _getGroupForPrefix(string $prefix): array
    {
        foreach (self::_getGroups() as $group) {
            if ($group['Prefix'] === $prefix) {
                return $group;
            }
        }
    }

    private static function _getLengthForGroup(array $group, $input): int
    {
        $first7Chars = self::_getStringStart($input, 7);
        $inputLength = strlen($first7Chars);

        foreach ($group['Rules']['Rule'] as $rule) {
            if (self::_truncatedRangeContainsValue($rule["Range"], $inputLength, $first7Chars)) {
                return (int) $rule['Length'];
            }
        }
    }

    private static function _truncatedRangeContainsValue($range, $limit, $value)
    {
        [$min, $max] = explode('-', $range);
        $truncatedMin = self::_getStringStart($min, $limit);
        $truncatedMax = self::_getStringStart($max, $limit);

        return $value > $truncatedMin && $value < $truncatedMax;
    }

    private static function _valueIsInRange(int $value, array $range): bool
    {
        return $value >= $range[0] && $value <= $range[1];
    }

    private static function _lengthIsValid(string $length): bool
    {
        return $length !== "0";
    }

    private static function _getStringStart(string $string, int $length): string
    {
        return substr($string, 0, $length);
    }

    private static function _getStringEnd(string $string,
        int $length
    ): string {
        return substr($string, $length);
    }

    private static function _getPrefixes(): array
    {
        include('ranges-array.php');
        return $prefixes;
    }

    private static function _getGroups(): array
    {
        include('ranges-array.php');
        return $groups;
    }
}
