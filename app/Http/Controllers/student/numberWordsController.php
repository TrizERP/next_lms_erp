<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;

class numberWordsController extends Controller
{

    public static $negative = "negative"; /* You may prefer "minus" */
    public static $and = "and";
    public static $comma = ",";
    public static $numeral = [ /* zero to nine */
                               0 => "zero", 1 => "one", 2 => "two", 3 => "three",
                               4 => "four", 5 => "five", 6 => "six", 7 => "seven",
                               8 => "eight", 9 => "nine",
    ];
    public static $dec = [ /* Numbers ten to nineteen */
                           0 => "ten", 1 => "eleven", 2 => "twelve",
                           3 => "thirteen", 4 => "fourteen", 5 => "fifteen",
                           6 => "sixteen", 7 => "seventeen", 8 => "eighteen",
                           9 => "nineteen",
    ];
    public static $tens = [ /* Going by tens .. */
                            2 => "twenty", 3 => "thirty", 4 => "fourty",
                            5 => "fifty", 6 => "sixty", 7 => "seventy",
                            8 => "eighty", 9 => "ninety",
    ];
    /* See http://en.wikipedia.org/wiki/Names_of_large_numbers */
    public static $triplets = [  /* Higher orders of magnitude */
                                 0 => "hundred", "thousand", "million",
                                 "billion", "trillion", "quadrillion",
                                 "quintillion", "sextillion", "septillion",
                                 "octillion", "nonillion", "decillion",
                                 "undecillion", "duodecillion", "tredecillion",
                                 "quattuordecillion", "quindecillion", "sexdecillion",
                                 "septendecillion", "octodecillion", "novemdecillion",
                                 "vigintillion", "unvigintillion", "duovigintillion",
                                 "tresvigintillion", "quattuorvigintillion", "quinquavigintillion",
                                 "sesvigintillion", "septemvigintillion", "octovigintillion",
                                 "novemvigintillion", "trigintillion", "untrigintillion",
                                 "duotrigintillion", "trestrigintillion", "quattuortrigintillion",
                                 "quinquatrigintillion", "sestrigintillion", "septentrigintillion",
                                 "octotrigintillion", "novemtrigintillion", "quadragintillion",
    ];

    /* See http://en.wikipedia.org/wiki/Ordinal_number_(linguistics) */

    public static $suffices_ordinal = [
        /* We switch to ordinal mode if we find one of these */
        "1st", "2nd", "3rd", "th",
    ];
    public static $ordinal_subst = [
        /* Substitutions for ordinal numbers.
            We add the -th from _simple and $triplets at runtime */
        "one"     => "first",
        "two"     => "second",
        "three"   => "third",
        "five"    => "fifth",
        "eight"   => "eighth",
        "nine"    => "ninth",
        "twelve"  => "twelfth",
        "twenty"  => "twentieth",
        "thirty"  => "thirtieth",
        "forty"   => "fortieth",
        "fifty"   => "fiftieth",
        "sixty"   => "sixtieth",
        "seventy" => "seventieth",
        "eighty"  => "eightieth",
        "ninety"  => "ninetieth",
    ];

    public static $ordinal_subst_expanded = false;
    public static $ordinal_subst_simple = [
        /* These just get -th on the end */
        "zero", "four", "six", "seven", "ten", "eleven", "thirteen",
        "fourteen", "fifteen", "sixteen", "seventeen", "eighteen",
        "nineteen",
    ];

    function toWords($num, $ordinal = false)
    {
        /* Converts a number (as string) to words correctly.
            150.3 => one hundred and fifty point three */
        $original = $num;
        $num = trim($num);
        $ret = "";

        /* Check for negative prefix */
        if (str_starts_with($num, '-')) {
            $ret .= numberWordsController::$negative." ";
            $num = substr($num, 1);
        }

        /* Check for ordinality */
        if (! $ordinal) {
            $ordinal = numberWordsController::isOrdinal($num);
        }

        $num_part = explode("/", $num);
        if (count($num_part) == 2) {
            /* Checking for fractions */
            $num_part_first = explode(" ", $num_part[0]);
            if (count($num_part) == 2) {
                /* 1 5/8 Mixed fractions */
                return false;
            } else {
                /* 67/35 Proper or improper fraction */
                return false;
            }
        } else {
            $num_part = explode(":", $num);
            if (count($num_part) == 3) {
                /* Checking for times (or ratios) */
                /* 2:45:28 = two forty five and twenty eight seconds */
                return false;
            } elseif (count($num_part) == 2) {
                /* 2:45 = two forty five */
                /* 18:35 = six thirty five */
                return false;
            } else {
                $num_part = explode(".", $num);
                if (count($num_part) == 2) {
                    /* We run this if we find decimals */
                    $ret .= self::doInteger($num_part[0]);
                    $ret .= " point ";
                    $ret .= self::doDigits($num_part[1]);
                } else {
                    /* No decimals, fix integer up */
                    $ret .= self::doInteger($num);
                }
            }
        }

        /* Final filters */
        $ret = trim($ret);
        if ($ordinal) {
            $ret = self::ordinalise($ret);
        }

        return $ret;
    }

    function isOrdinal($num)
    {
        /* Check if a number ends with an ordinal suffic, eg 1st, 5th */
        foreach (numberWordsController::$suffices_ordinal as $s) {
            if (substr($num, -strlen($s), strlen($s)) == $s) {
                /* A suffix has been matched */
                return true;
            }
        }

        /* No suffix, not an ordinal number */

        return false;
    }

    function number_clean($num)
    {
        /* Strip a number to only numeric parts 778 88GDAY5 = 778885*/
        $ret = "";
        for ($i = 0; $i < strlen($num); $i++) {
            $c = substr($num, $i, 1);
            if (is_numeric($c)) {
                $ret .= $c;
            }
        }

        return $ret;
    }

    function ordinalise($str)
    {
        /* Replace last word in a number to make it ordinal:
            ('one' becomes 'first', 'ten' becomes 'tenth', etc). */

        if (! self::$ordinal_subst_expanded) {
            /* Adds some things to the list */
            foreach (self::$ordinal_subst_simple as $entry) {
                self::$ordinal_subst[$entry] = $entry."th";
            }
            foreach (self::$triplets as $entry) {
                self::$ordinal_subst[$entry] = $entry."th";
            }
            self::$ordinal_subst_expanded = true;
        }

        /* Match against list of substitutions */
        foreach (self::$ordinal_subst as $m => $replace) {
            if (substr($str, -strlen($m), strlen($m)) == $m) {
                $str = substr($str, 0, strlen($str) - strlen($m));
                $str .= $replace;

                return $str;
            }
        }

        /* If we reach here, then the number cannot be 'ordinalised',
            probably something missing from the replacement table */

        return $str;
    }

    function doInteger($num)
    {
        /* Returns a whole number (any length) as words */
        $num = self::number_clean($num); /* Clean odd characters */
        $ret = "";

        /* Pad the number with zeroes at the start, to split into triplets */
        $offs = 3 - (strlen($num) % 3);
        if ($offs != 3) {
            $num = self::zeroes($offs).$num;
        }

        /* Read each triplet */
        for ($i = 0; $i < strlen($num); $i += 3) {
            /* $triplet_id = 0 for the final triplet, 1 for the second-last, etc */
            $triplet_id = (int) (((strlen($num) - $i) / 3) - 1);
            /* Pull out 3 digits */
            $subnum = substr($num, $i, 3);
            /* Store for processing */
            $triplet[$triplet_id] = $subnum;
        }

        /* Keeping track of converted parts */
        $parts = [];
        $last = false;

        /* Loop through each triplet and convert to words */
        foreach ($triplet as $id => $part) {
            $sret = "";
            if ($part != "000") {
                /* Only for non-zero triplets */
                $final = ($id == 0); /* Tell doTriplet if this is the final one */
                $sret .= self::doTriplet($part, $last, $final);
                if ($id != 0) {
                    /* Add 'thousands, millions' or whatever */
                    $sret .= self::$triplets[$id];
                    $last = $id;
                }
                /* Store back in array */
                $parts[] = $sret;
            }
        }

        if (count($parts) == 0) {
            /* If all triplets equal 0 */
            $ret = "zero";
        } else {
            /* Join parts by commas. Five thousand, three hundred and five */
            $ret .= join(self::$comma." ", $parts);
            /* Take away a comma if it is before an "and",
                Corrects eg: Seven thousand, and five (from 007, 005) */
            $ret = str_replace(", and ", " and ", $ret);
        }

        return $ret;
    }

    function doDigits($num)
    {
        /* Output digits, 5067 = five zero six seven.
            Used for numbers after the decimal place. */
        $num = self::number_clean($num);
        $ret = "";
        for ($i = 0; $i < strlen($num); $i++) {
            $digit = substr($num, $i, 1);
            $ret .= self::$numeral[$digit]." ";
        }

        return trim($ret);
    }

    function doTriplet($num, $last, $final)
    {
        /* Converts 3 digits of a number to words. Needs to know what
            order the last non-zero triplet was, and whether this
            is the final triplet, in order to add "and" correctly */
        $ret = "";
        $c = substr($num, 0, 1); /* Think Roman numerals, C X I */
        $x = substr($num, 1, 1);
        $i = substr($num, 2, 1);

        /* Do hundreds */
        if ($c != "0") {
            $ret .= self::$numeral[$c]." ".self::$triplets[0]." ";
        }
        /* Do tens */
        switch ($x) {
            case "0":
                if ($i != 0) {
                    /* Second digit is 0: 'one hundred and seven' */
                    if ($c != "0" || ($last && $final)) {
                        $ret .= self::$and." ";
                    }
                    $ret .= self::$numeral[$i]." ";
                } else {
                    /* Second and third are nil: 'one hundred' */
                }
                break;
            case "1":
                /* Number is in tens: twelve, sixteen */
                if ($c != "0" || ($last && $final)) {
                    $ret .= self::$and." ";
                }
                $ret .= self::$dec[$i]." ";
                break;
            default:
                /* Number is above nineteen: eighty-two */
                if ($c != "0" || ($last && $final)) {
                    $ret .= self::$and." ";
                }
                $ret .= self::$tens[$x];
                if ($i != "0") {
                    /* Add that hyphenated final digit if needed*/
                    $ret .= "-".self::$numeral[$i];
                }
                $ret .= " ";
        }

        return self::unspace($ret);
    }

    function zeroes($count)
    {
        /* Output the number of zeroes we need.
            Used to make 5 into 005 for doTriplet() */
        $res = "";
        for ($i = 0; $i < $count; $i++) {
            $res .= "0";
        }

        return $res;
    }

    function unspace($str)
    {
        /* remove double spaces (cleans up removal of "and") */
        return str_replace("  ", " ", $str);
    }

}
