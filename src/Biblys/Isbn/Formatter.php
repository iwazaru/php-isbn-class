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

class Formatter
{
    public static function formatAsIsbn10(string $input): string
    {
        $isbn = Parser::parse($input);
        $registrationGroupElement = $isbn->getRegistrationGroupElement();
        $registrantElement = $isbn->getRegistrantElement();
        $publicationCode = $isbn->getPublicationElement();
        $checksum = self::_calculateChecksumForIsbn10Format($registrationGroupElement, $registrantElement, $publicationCode);

        return "$registrationGroupElement-$registrantElement-$publicationCode-$checksum";
    }

    public static function formatAsIsbn13(string $input): string
    {
        $isbn = Parser::parse($input);
        $gs1Element = $isbn->getGs1Element();
        $registrationGroupElement = $isbn->getRegistrationGroupElement();
        $registrantElement = $isbn->getRegistrantElement();
        $publicationCode = $isbn->getPublicationElement();
        $checksum = self::_calculateChecksumForIsbn13Format($gs1Element, $registrationGroupElement, $registrantElement, $publicationCode);

        return "$gs1Element-$registrationGroupElement-$registrantElement-$publicationCode-$checksum";
    }

    public static function formatAsEan13(string $input): string
    {
        $isbn = Parser::parse($input);
        $gs1Element = $isbn->getGs1Element();
        $registrationGroupElement = $isbn->getRegistrationGroupElement();
        $registrantElement = $isbn->getRegistrantElement();
        $publicationCode = $isbn->getPublicationElement();
        $checksum = self::_calculateChecksumForIsbn13Format($gs1Element, $registrationGroupElement, $registrantElement, $publicationCode);

        return $gs1Element . $registrationGroupElement . $registrantElement . $publicationCode . $checksum;
    }

    public static function formatAsGtin14(string $input, int $prefix): string
    {
        $isbn = Parser::parse($input);
        $gs1Element = $isbn->getGs1Element();
        $registrationGroupElement = $isbn->getRegistrationGroupElement();
        $registrantElement = $isbn->getRegistrantElement();
        $publicationCode = $isbn->getPublicationElement();

        $gs1ElementWithPrefix = $prefix . $gs1Element;
        $checksum = self::_calculateChecksumForIsbn13Format($gs1ElementWithPrefix, $registrationGroupElement, $registrantElement, $publicationCode);

        return $prefix . $gs1Element . $registrationGroupElement . $registrantElement . $publicationCode . $checksum;
    }

    private static function _calculateChecksumForIsbn10Format(
        string $registrationGroupElement,
        string $registrantElement,
        string $publicationCode
    ): string {
        $code = $registrationGroupElement . $registrantElement . $publicationCode;
        $chars = str_split($code);

        $checksum = (11 - (
            ($chars[0] * 10) +
            ($chars[1] * 9) +
            ($chars[2] * 8) +
            ($chars[3] * 7) +
            ($chars[4] * 6) +
            ($chars[5] * 5) +
            ($chars[6] * 4) +
            ($chars[7] * 3) +
            ($chars[8] * 2)) % 11) % 11;

        if ($checksum == 10) {
            $checksum = 'X';
        }

        return $checksum;
    }

    private static function _calculateChecksumForIsbn13Format(
        string $gs1Element,
        string $registrationGroupElement,
        string $registrantElement,
        string $publicationCode
    ): string {
        $checksum = null;

        $code = $gs1Element . $registrationGroupElement . $registrantElement . $publicationCode;
        $chars = array_reverse(str_split($code));

        foreach ($chars as $index => $char) {
            if ($index & 1) {
                $checksum += $char;
            } else {
                $checksum += $char * 3;
            }
        }

        $checksum = (10 - ($checksum % 10)) % 10;

        return $checksum;
    }
}
