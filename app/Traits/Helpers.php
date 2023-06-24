<?php

namespace App\Traits;


trait Helpers
{
    public static function getMonths()
    {
        return [
            "Jan",
            "Feb",
            "Mar",
            "Apr",
            "May",
            "Jun",
            "Jul",
            "Aug",
            "Sep",
            "Oct",
            "Nov",
            "Dec",
        ];
    }

    public static function getYears() {
        return [
          2021,
          2022,
          2023
        ];
    }

    public static function getPairYears() {
        return [
            '2021-2022',
            '2022-2023',
            '2023-2024'
        ];
    }

}
