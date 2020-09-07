<?php

/**
 * @param $amount
 * @return string
 */
function toMoney($amount)
{
    return '€ ' . number_format($amount, 2, ',', '');
}

/**
 * @return \App\Models\Organisation
 */
function organisation() {
    return \App\Models\Organisation::getRepresentedOrganisation();
}
