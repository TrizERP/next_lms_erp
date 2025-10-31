<?php

namespace App\Traits;


trait Helpers
{
    public static function getMonths()
    {
        return [
            "Apr",
            "May",
            "Jun",
            "Jul",
            "Aug",
            "Sep",
            "Oct",
            "Nov",
            "Dec",
            "Jan",
            "Feb",
            "Mar",
        ];
    }

    public static function getYears() {
        return [
          2021,
          2022,
          2023,
          2024,
          2025
        ];
    }

    public static function getPairYears() {
        return [
            '2021'=>'2021-2022',
            '2022'=>'2022-2023',
            '2023'=>'2023-2024',
            '2024'=>'2024-2025',
            '2025'=>'2025-2026'
        ];
    }

    public static function getPF($totalAllowance) {
        // BASIC + GRADPAY + DA for MMIS
        $PF = 0;
        if($totalAllowance > 0){
            $PF =round(($totalAllowance * 12)/100);
        }
        if($PF>1800){
            $PF = 1800;
        }
        return $PF;
    }

    public static function getPT($totalAllowance,$gender="") {
        // Sum of all allowance plus
        $PT = 0;
        if($totalAllowance > 0){
            // PT calculation for male 
            if($gender=="M"){
                // if salary between 7501 - 10,000 Rs. 175/-
                if($totalAllowance > 7500 && $totalAllowance <= 10000){
                    $PT = 175;
                }
                // if salary Above 10,000 Rs. 200/-
                if($totalAllowance > 10000){
                    $PT = 200;
                }
            }
            // PT calculation for females salary Above 25000/- Rs. 200/-
            else if($gender=="F" && $totalAllowance > 25000){
                $PT = 200;
            }
        }
        return $PT;
    }
    public static function getMarkGrade($obt,$total)
    {
        $mark = round((30*$obt/$total),0);
        
        if (!is_numeric($mark) || $mark === null || $mark === '') {
            $mark = 0; // default value if mark is invalid
        }
        
        //$mark = round(($mark / 2), 0);

        if ($mark >= 25 && $mark <= 30) {
            return 'A';
        } elseif ($mark >= 21 && $mark <= 24) {
            return 'B';
        } elseif ($mark >= 15 && $mark <= 20) {
            return 'C';
        } elseif ($mark >= 10 && $mark <= 14) {
            return 'D';
        } else {
            return 'E';
        }
    }
}
