<?php

namespace App\Tools;

/**
 * Class StringHelper
 * @package App\Tools
 */
class StringHelper
{
    public static function htmlToText($input)
    {
        $input = str_replace('</p>', "\n\n", $input);
        $input = str_replace('<br>', "\n", $input);
        $input = str_replace('<br />', "\n", $input);
        $input = str_replace('<br/>', "\n", $input);

        $input = str_replace("\t", " ", $input);

        $input = strip_tags($input);
        $input = trim($input);

        // remove double spaces
        $input = preg_replace('/[\t\n\r\0\x0B]/', '', $input);
        $input = preg_replace('/([\s])\1+/', ' ', $input);
        $input = trim($input);

        return $input;
    }
}